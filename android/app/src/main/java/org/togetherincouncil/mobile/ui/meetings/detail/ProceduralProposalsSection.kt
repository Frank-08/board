package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.*
import org.togetherincouncil.mobile.ui.common.EnumDropdown

private val PROPOSAL_TYPES = listOf(
    ProposalType.USE_OF_PROCEDURES, ProposalType.ORDER_OF_DAY, ProposalType.ADJOURNMENT,
    ProposalType.PRIVATE_SITTING, ProposalType.REFERRAL, ProposalType.DECISION_NOW,
    ProposalType.WITHDRAW_MOTION, ProposalType.PREVIOUS_QUESTION, ProposalType.CLOSURE,
    ProposalType.RECONSIDERATION, ProposalType.POINT_OF_ORDER
)
private val OUTCOMES = listOf(
    ProposalOutcome.PENDING, ProposalOutcome.CARRIED, ProposalOutcome.LOST,
    ProposalOutcome.LAPSED, ProposalOutcome.RULED_ON
)
private val POSITIONS = listOf(AgendaPosition.BEFORE, AgendaPosition.DURING, AgendaPosition.AFTER)

@Composable
fun ProceduralProposalRow(
    proposal: ProceduralProposalDto,
    members: List<BoardMemberDto>,
    canManage: Boolean,
    onDelete: () -> Unit
) {
    val proposedByName = members.find { it.id == proposal.proposedBy }?.fullName
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(Modifier.weight(1f)) {
            Text("${proposal.proposalType.label} (${proposal.agendaPosition.name.lowercase()}) — ${proposal.outcome.name}")
            if (proposedByName != null) Text("Proposed by $proposedByName", style = MaterialTheme.typography.bodyMedium)
            if (!proposal.notes.isNullOrBlank()) Text(proposal.notes, style = MaterialTheme.typography.bodyMedium)
        }
        if (canManage) {
            TextButton(onClick = onDelete) { Text("Remove") }
        }
    }
}

@Composable
fun AddProposalButton(
    meetingId: Int,
    agendaItemId: Int?,
    members: List<BoardMemberDto>,
    resolutions: List<ResolutionDto>,
    onSave: (ProceduralProposalWriteRequest) -> Unit
) {
    var showSheet by remember { mutableStateOf(false) }
    TextButton(onClick = { showSheet = true }) { Text("Add procedural proposal") }

    if (showSheet) {
        ProposalEditSheet(
            meetingId = meetingId,
            agendaItemId = agendaItemId,
            members = members,
            resolutions = resolutions,
            onDismiss = { showSheet = false },
            onSave = { onSave(it); showSheet = false }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProposalEditSheet(
    meetingId: Int,
    agendaItemId: Int?,
    members: List<BoardMemberDto>,
    resolutions: List<ResolutionDto>,
    onDismiss: () -> Unit,
    onSave: (ProceduralProposalWriteRequest) -> Unit
) {
    var proposalType by remember { mutableStateOf(ProposalType.POINT_OF_ORDER) }
    var position by remember { mutableStateOf(AgendaPosition.DURING) }
    var proposedBy by remember { mutableStateOf<BoardMemberDto?>(null) }
    var secondedBy by remember { mutableStateOf<BoardMemberDto?>(null) }
    var linkedResolution by remember { mutableStateOf<ResolutionDto?>(null) }
    var outcome by remember { mutableStateOf(ProposalOutcome.PENDING) }
    var notes by remember { mutableStateOf("") }
    var requiresLeave by remember { mutableStateOf(false) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text("Add procedural proposal", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))
            EnumDropdown("Type", PROPOSAL_TYPES, proposalType, { it.label }, { proposalType = it })
            Spacer(Modifier.height(8.dp))
            if (agendaItemId != null) {
                EnumDropdown("Anchor", POSITIONS, position, { it.name }, { position = it })
                Spacer(Modifier.height(8.dp))
            }
            EnumDropdown("Proposed by", listOf<BoardMemberDto?>(null) + members, proposedBy, { it?.fullName ?: "—" }, { proposedBy = it })
            Spacer(Modifier.height(8.dp))
            EnumDropdown("Seconded by", listOf<BoardMemberDto?>(null) + members, secondedBy, { it?.fullName ?: "—" }, { secondedBy = it })
            Spacer(Modifier.height(8.dp))
            EnumDropdown("Outcome", OUTCOMES, outcome, { it.name }, { outcome = it })
            Spacer(Modifier.height(8.dp))
            if (resolutions.isNotEmpty()) {
                EnumDropdown(
                    "Linked resolution (optional)",
                    listOf<ResolutionDto?>(null) + resolutions,
                    linkedResolution,
                    { it?.let { r -> r.resolutionNumber ?: r.description.take(40) } ?: "None" },
                    { linkedResolution = it }
                )
                Spacer(Modifier.height(8.dp))
            }
            OutlinedTextField(value = notes, onValueChange = { notes = it }, label = { Text("Notes") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = requiresLeave, onCheckedChange = { requiresLeave = it })
                Text("Requires leave")
            }
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                TextButton(onClick = onDismiss) { Text("Cancel") }
                Spacer(Modifier.width(8.dp))
                Button(onClick = {
                    onSave(
                        ProceduralProposalWriteRequest(
                            meetingId = meetingId,
                            proposalType = proposalType,
                            agendaItemId = agendaItemId,
                            agendaPosition = position,
                            proposedBy = proposedBy?.id,
                            secondedBy = secondedBy?.id,
                            resolutionId = linkedResolution?.id,
                            outcome = outcome,
                            requiresLeave = requiresLeave,
                            notes = notes.ifBlank { null }
                        )
                    )
                }) { Text("Save") }
            }
        }
    }
}
