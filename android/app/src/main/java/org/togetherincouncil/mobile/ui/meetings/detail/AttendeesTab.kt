package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.AttendanceStatus
import org.togetherincouncil.mobile.data.remote.dto.AttendeeDto
import org.togetherincouncil.mobile.ui.common.EnumDropdown

private val STATUSES = listOf(
    AttendanceStatus.PRESENT, AttendanceStatus.LATE, AttendanceStatus.APOLOGY,
    AttendanceStatus.EXCUSED, AttendanceStatus.ABSENT
)

@Composable
fun AttendeesTab(
    state: MeetingDetailUiState,
    canManageAll: Boolean,
    canEditOwn: Boolean,
    currentBoardMemberId: Int?,
    onStatusChange: (AttendeeDto, AttendanceStatus) -> Unit,
    onAddAttendee: (memberId: Int, status: AttendanceStatus) -> Unit
) {
    var showAddSheet by remember { mutableStateOf(false) }

    Box(Modifier.fillMaxSize()) {
        if (state.attendees.isEmpty()) {
            Text("No attendees recorded yet.", modifier = Modifier.align(Alignment.Center).padding(24.dp))
        } else {
            LazyColumn(contentPadding = PaddingValues(bottom = 80.dp)) {
                items(state.attendees, key = { it.id }) { attendee ->
                    val editable = canManageAll || (canEditOwn && attendee.memberId == currentBoardMemberId)
                    AttendeeRow(attendee, editable, onStatusChange = { onStatusChange(attendee, it) })
                }
            }
        }

        if (canManageAll) {
            FloatingActionButton(
                onClick = { showAddSheet = true },
                modifier = Modifier.align(Alignment.BottomEnd).padding(16.dp)
            ) {
                Icon(Icons.Filled.Add, contentDescription = "Add attendee")
            }
        }
    }

    if (showAddSheet) {
        val alreadyAttending = state.attendees.map { it.memberId }.toSet()
        val candidates = state.members.filter { it.id !in alreadyAttending }
        AddAttendeeSheet(
            candidates = candidates,
            onDismiss = { showAddSheet = false },
            onAdd = { memberId, status -> onAddAttendee(memberId, status); showAddSheet = false }
        )
    }
}

@Composable
private fun AttendeeRow(attendee: AttendeeDto, editable: Boolean, onStatusChange: (AttendanceStatus) -> Unit) {
    ListItem(
        headlineContent = { Text(attendee.fullName.ifBlank { "Member #${attendee.memberId}" }, fontWeight = FontWeight.Medium) },
        supportingContent = { Text(attendee.role?.name?.replace('_', ' ') ?: "") },
        trailingContent = {
            FlowRowChips(
                selected = attendee.attendanceStatus,
                editable = editable,
                onSelect = onStatusChange
            )
        }
    )
    HorizontalDivider()
}

@Composable
private fun FlowRowChips(selected: AttendanceStatus, editable: Boolean, onSelect: (AttendanceStatus) -> Unit) {
    Row {
        STATUSES.forEach { status ->
            val isSelected = status == selected
            FilterChip(
                selected = isSelected,
                enabled = editable,
                onClick = { onSelect(status) },
                label = { Text(status.name.take(3)) },
                modifier = Modifier.padding(end = 2.dp)
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AddAttendeeSheet(
    candidates: List<org.togetherincouncil.mobile.data.remote.dto.BoardMemberDto>,
    onDismiss: () -> Unit,
    onAdd: (memberId: Int, status: AttendanceStatus) -> Unit
) {
    var selectedMember by remember { mutableStateOf(candidates.firstOrNull()) }
    var status by remember { mutableStateOf(AttendanceStatus.PRESENT) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text("Add attendee", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))
            if (candidates.isEmpty()) {
                Text("Every board member is already on the attendee list.")
            } else {
                EnumDropdown(
                    label = "Member",
                    options = candidates,
                    selected = selectedMember ?: candidates.first(),
                    labelFor = { it.fullName },
                    onSelect = { selectedMember = it }
                )
                Spacer(Modifier.height(8.dp))
                EnumDropdown(
                    label = "Status",
                    options = STATUSES,
                    selected = status,
                    labelFor = { it.name },
                    onSelect = { status = it }
                )
                Spacer(Modifier.height(16.dp))
                Button(
                    onClick = { selectedMember?.let { onAdd(it.id, status) } },
                    enabled = selectedMember != null,
                    modifier = Modifier.fillMaxWidth()
                ) { Text("Add") }
            }
        }
    }
}
