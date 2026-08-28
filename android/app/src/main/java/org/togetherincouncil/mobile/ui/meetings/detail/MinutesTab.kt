package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.*

@Composable
fun MinutesTab(
    state: MeetingDetailUiState,
    canManage: Boolean,
    canManageProposals: Boolean,
    onSaveMinutes: (content: String, actionItems: String?, nextMeetingDate: String?, status: MinutesStatus, preparedBy: Int?) -> Unit,
    onApprove: (approvedBy: Int) -> Unit,
    onSaveComment: (agendaItemId: Int, comment: String) -> Unit,
    onSaveProposal: (ProceduralProposalWriteRequest) -> Unit,
    onDeleteProposal: (Int) -> Unit,
    onSaveDeparture: (DepartureWriteRequest) -> Unit
) {
    if (!state.minutesLoaded) {
        return
    }
    val meetingId = state.meeting?.id ?: return
    val isLocked = state.isLocked

    var showEditMinutes by remember { mutableStateOf(false) }
    var showApproveDialog by remember { mutableStateOf(false) }

    val flatAgendaItems = remember(state.agendaItems) {
        val topLevel = state.agendaItems.filter { it.parentId == null }.sortedBy { it.position }
        val childrenByParent = state.agendaItems.filter { it.parentId != null }.groupBy { it.parentId }
        topLevel.flatMap { parent -> listOf(parent) + childrenByParent[parent.id].orEmpty().sortedBy { it.subPosition } }
    }

    val generalProposals = state.proposals.filter { it.agendaItemId == null }

    LazyColumn(contentPadding = PaddingValues(16.dp)) {
        item {
            MinutesSummaryCard(
                minutes = state.minutes,
                canManage = canManage,
                onEdit = { showEditMinutes = true },
                onApproveClick = { showApproveDialog = true }
            )
            Spacer(Modifier.height(16.dp))
        }

        if (generalProposals.isNotEmpty() || canManageProposals) {
            item {
                Text("General procedural proposals", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
            }
            items(generalProposals, key = { "gp-${it.id}" }) { proposal ->
                ProceduralProposalRow(proposal, state.members, canManageProposals, onDelete = { onDeleteProposal(proposal.id) })
            }
            if (canManageProposals) {
                item {
                    AddProposalButton(meetingId, null, state.members, state.resolutions, onSaveProposal)
                    Spacer(Modifier.height(16.dp))
                }
            }
        }

        items(flatAgendaItems, key = { it.id }) { agendaItem ->
            AgendaItemMinutesCard(
                agendaItem = agendaItem,
                comment = state.minutes?.agendaComments?.find { it.agendaItemId == agendaItem.id }?.comment.orEmpty(),
                departures = state.departures.filter { it.agendaItemId == agendaItem.id },
                proposals = state.proposals.filter { it.agendaItemId == agendaItem.id },
                members = state.members,
                resolutions = state.resolutions,
                meetingId = meetingId,
                editable = canManage && !isLocked,
                canManageProposals = canManageProposals,
                onSaveComment = { onSaveComment(agendaItem.id, it) },
                onSaveProposal = onSaveProposal,
                onDeleteProposal = onDeleteProposal,
                onSaveDeparture = onSaveDeparture
            )
            Spacer(Modifier.height(12.dp))
        }
    }

    if (showEditMinutes) {
        MinutesEditSheet(
            existing = state.minutes,
            onDismiss = { showEditMinutes = false },
            onSave = { content, actionItems, nextDate, status, preparedBy ->
                onSaveMinutes(content, actionItems, nextDate, status, preparedBy)
                showEditMinutes = false
            }
        )
    }

    if (showApproveDialog) {
        ApproveMinutesDialog(
            members = state.members,
            onDismiss = { showApproveDialog = false },
            onConfirm = { approvedBy -> onApprove(approvedBy); showApproveDialog = false }
        )
    }
}

@Composable
private fun MinutesSummaryCard(minutes: MinutesDto?, canManage: Boolean, onEdit: () -> Unit, onApproveClick: () -> Unit) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            if (minutes == null) {
                Text("Minutes haven't been started for this meeting yet.")
                if (canManage) {
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = onEdit) { Text("Create minutes") }
                }
            } else {
                Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
                    Text("Status: ${minutes.status.name}", fontWeight = FontWeight.Medium)
                    if (canManage && minutes.status != MinutesStatus.APPROVED) {
                        TextButton(onClick = onEdit) { Text("Edit") }
                    }
                }
                Spacer(Modifier.height(8.dp))
                Text(minutes.content, maxLines = 4)
                if (canManage && (minutes.status == MinutesStatus.DRAFT || minutes.status == MinutesStatus.REVIEW)) {
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = onApproveClick) { Text("Approve") }
                }
            }
        }
    }
}

@Composable
private fun AgendaItemMinutesCard(
    agendaItem: AgendaItemDto,
    comment: String,
    departures: List<DepartureDto>,
    proposals: List<ProceduralProposalDto>,
    members: List<BoardMemberDto>,
    resolutions: List<ResolutionDto>,
    meetingId: Int,
    editable: Boolean,
    canManageProposals: Boolean,
    onSaveComment: (String) -> Unit,
    onSaveProposal: (ProceduralProposalWriteRequest) -> Unit,
    onDeleteProposal: (Int) -> Unit,
    onSaveDeparture: (DepartureWriteRequest) -> Unit
) {
    var commentText by remember(agendaItem.id, comment) { mutableStateOf(comment) }
    var lastSaved by remember(agendaItem.id, comment) { mutableStateOf(comment) }
    var showDepartureSheet by remember { mutableStateOf(false) }

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            Text("${agendaItem.itemNumber ?: ""}  ${agendaItem.title}", fontWeight = FontWeight.SemiBold)
            if (agendaItem.resolutions.isNotEmpty()) {
                Spacer(Modifier.height(4.dp))
                agendaItem.resolutions.forEach { r ->
                    Text("• ${r.resolutionNumber ?: ""} ${r.description} (${r.status.name})", style = MaterialTheme.typography.bodyMedium)
                }
            }
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = commentText,
                onValueChange = { commentText = it },
                label = { Text("Discussion / comments") },
                enabled = editable,
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                modifier = Modifier
                    .fillMaxWidth()
                    .onFocusChanged { focusState ->
                        if (!focusState.isFocused && commentText != lastSaved) {
                            lastSaved = commentText
                            onSaveComment(commentText)
                        }
                    }
            )

            if (departures.isNotEmpty() || editable) {
                Spacer(Modifier.height(8.dp))
                Text("Members who left the room", style = MaterialTheme.typography.labelLarge)
                departures.forEach { d ->
                    val name = members.find { it.id == d.memberId }?.fullName ?: "Member #${d.memberId}"
                    Text("• $name${d.reason?.let { " — $it" } ?: ""}${if (d.returned) " (returned)" else ""}")
                }
                if (editable) {
                    TextButton(onClick = { showDepartureSheet = true }) { Text("Record departure") }
                }
            }

            if (proposals.isNotEmpty() || canManageProposals) {
                Spacer(Modifier.height(8.dp))
                Text("Procedural proposals", style = MaterialTheme.typography.labelLarge)
                proposals.forEach { p ->
                    ProceduralProposalRow(p, members, canManageProposals, onDelete = { onDeleteProposal(p.id) })
                }
                if (canManageProposals) {
                    AddProposalButton(meetingId, agendaItem.id, members, resolutions, onSaveProposal)
                }
            }
        }
    }

    if (showDepartureSheet) {
        DepartureEditSheet(
            agendaItemId = agendaItem.id,
            members = members,
            onDismiss = { showDepartureSheet = false },
            onSave = { onSaveDeparture(it); showDepartureSheet = false }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MinutesEditSheet(
    existing: MinutesDto?,
    onDismiss: () -> Unit,
    onSave: (content: String, actionItems: String?, nextMeetingDate: String?, status: MinutesStatus, preparedBy: Int?) -> Unit
) {
    var content by remember { mutableStateOf(existing?.content.orEmpty()) }
    var actionItems by remember { mutableStateOf(existing?.actionItems.orEmpty()) }
    var status by remember { mutableStateOf(existing?.status ?: MinutesStatus.DRAFT) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text(if (existing == null) "Create minutes" else "Edit minutes", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(value = content, onValueChange = { content = it }, label = { Text("Content") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = actionItems, onValueChange = { actionItems = it }, label = { Text("Action items") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            org.togetherincouncil.mobile.ui.common.EnumDropdown(
                label = "Status",
                options = listOf(MinutesStatus.DRAFT, MinutesStatus.REVIEW, MinutesStatus.PUBLISHED),
                selected = status,
                labelFor = { it.name },
                onSelect = { status = it }
            )
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                TextButton(onClick = onDismiss) { Text("Cancel") }
                Spacer(Modifier.width(8.dp))
                Button(
                    enabled = content.isNotBlank(),
                    onClick = { onSave(content, actionItems.ifBlank { null }, null, status, null) }
                ) { Text("Save") }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ApproveMinutesDialog(members: List<BoardMemberDto>, onDismiss: () -> Unit, onConfirm: (Int) -> Unit) {
    var approver by remember { mutableStateOf(members.firstOrNull()) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Approve minutes") },
        text = {
            Column {
                Text("Once approved, this meeting's agenda, minutes, and resolutions become read-only.")
                Spacer(Modifier.height(12.dp))
                if (members.isNotEmpty()) {
                    org.togetherincouncil.mobile.ui.common.EnumDropdown(
                        label = "Approved by",
                        options = members,
                        selected = approver ?: members.first(),
                        labelFor = { it.fullName },
                        onSelect = { approver = it }
                    )
                }
            }
        },
        confirmButton = {
            TextButton(onClick = { approver?.let { onConfirm(it.id) } }, enabled = approver != null) { Text("Approve") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } }
    )
}
