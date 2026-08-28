package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.AgendaItemDto
import org.togetherincouncil.mobile.data.remote.dto.DecisionMethod
import org.togetherincouncil.mobile.data.remote.dto.ResolutionDto
import org.togetherincouncil.mobile.data.remote.dto.ResolutionStatus
import org.togetherincouncil.mobile.data.remote.dto.ResolutionWriteRequest

@Composable
fun ResolutionsTab(
    state: MeetingDetailUiState,
    canManage: Boolean,
    isAdmin: Boolean,
    onSave: (ResolutionWriteRequest) -> Unit,
    onDelete: (Int) -> Unit,
    onReorder: (agendaItemId: Int, order: List<Int>) -> Unit
) {
    var editing by remember { mutableStateOf<Pair<ResolutionDto?, Int?>?>(null) } // (existing, agendaItemId for a new one)

    val grouped: Map<Int?, List<ResolutionDto>> = remember(state.resolutions) {
        state.resolutions.groupBy { it.agendaItemId }.mapValues { (_, list) -> list.sortedBy { it.position } }
    }
    val agendaItemsById = remember(state.agendaItems) { state.agendaItems.associateBy { it.id } }

    if (state.resolutions.isEmpty()) {
        Box(Modifier.fillMaxSize()) {
            Text("No resolutions recorded yet.", modifier = Modifier.align(Alignment.Center).padding(24.dp))
        }
    } else {
        LazyColumn(contentPadding = PaddingValues(16.dp)) {
            grouped.forEach { (agendaItemId, resolutions) ->
                item(key = "header-$agendaItemId") {
                    Text(
                        agendaItemsById[agendaItemId]?.title ?: "Not linked to an agenda item",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(top = 8.dp, bottom = 4.dp)
                    )
                }
                itemsIndexed(resolutions, key = { _, r -> "res-${r.id}" }) { index, resolution ->
                    ResolutionRow(
                        resolution = resolution,
                        members = state.members,
                        canManage = canManage,
                        canMoveUp = agendaItemId != null && index > 0,
                        canMoveDown = agendaItemId != null && index < resolutions.size - 1,
                        onEdit = { editing = resolution to null },
                        onDelete = { onDelete(resolution.id) },
                        onMoveUp = {
                            if (agendaItemId != null) onReorder(agendaItemId, swapPositions(resolutions, index, index - 1))
                        },
                        onMoveDown = {
                            if (agendaItemId != null) onReorder(agendaItemId, swapPositions(resolutions, index, index + 1))
                        }
                    )
                }
                if (canManage && agendaItemId != null) {
                    item(key = "add-$agendaItemId") {
                        TextButton(onClick = { editing = null to agendaItemId }) { Text("Add resolution") }
                    }
                }
            }
        }
    }

    val current = editing
    if (current != null) {
        ResolutionEditSheet(
            existing = current.first,
            meetingId = state.meeting?.id ?: return,
            defaultAgendaItemId = current.second,
            agendaItems = state.agendaItems,
            members = state.members,
            isAdmin = isAdmin,
            quorumMet = state.meeting?.quorumMet ?: true,
            onDismiss = { editing = null },
            onSave = { onSave(it); editing = null }
        )
    }
}

private fun swapPositions(resolutions: List<ResolutionDto>, a: Int, b: Int): List<Int> {
    if (b < 0 || b >= resolutions.size) return resolutions.map { it.id }
    val ids = resolutions.map { it.id }.toMutableList()
    val tmp = ids[a]
    ids[a] = ids[b]
    ids[b] = tmp
    return ids
}

@Composable
private fun ResolutionRow(
    resolution: ResolutionDto,
    members: List<org.togetherincouncil.mobile.data.remote.dto.BoardMemberDto>,
    canManage: Boolean,
    canMoveUp: Boolean,
    canMoveDown: Boolean,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
    onMoveUp: () -> Unit,
    onMoveDown: () -> Unit
) {
    ListItem(
        headlineContent = { Text("${resolution.resolutionNumber ?: ""} ${resolution.title ?: resolution.description.take(60)}") },
        supportingContent = {
            Column {
                Text("${resolution.decisionMethod.name.replace('_', ' ')} · ${resolution.status.name}")
                if (resolution.status == ResolutionStatus.PROPOSED && resolution.decisionMethod == DecisionMethod.FORMAL_MAJORITY) {
                    Text("Votes: ${resolution.votesFor ?: 0} for / ${resolution.votesAgainst ?: 0} against / ${resolution.votesAbstain ?: 0} abstain")
                }
            }
        },
        trailingContent = if (canManage) {
            {
                Row {
                    IconButton(onClick = onMoveUp, enabled = canMoveUp) { Icon(Icons.Filled.KeyboardArrowUp, contentDescription = "Move up") }
                    IconButton(onClick = onMoveDown, enabled = canMoveDown) { Icon(Icons.Filled.KeyboardArrowDown, contentDescription = "Move down") }
                    IconButton(onClick = onEdit) { Icon(Icons.Filled.Edit, contentDescription = "Edit") }
                    IconButton(onClick = onDelete) { Icon(Icons.Filled.Delete, contentDescription = "Delete") }
                }
            }
        } else null
    )
    HorizontalDivider()
}
