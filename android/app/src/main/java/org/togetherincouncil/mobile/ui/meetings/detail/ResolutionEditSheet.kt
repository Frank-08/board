package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.*
import org.togetherincouncil.mobile.ui.common.EnumDropdown

private val DECISION_METHODS = listOf(DecisionMethod.CONSENSUS, DecisionMethod.FORMAL_MAJORITY, DecisionMethod.REFERRAL)
private val VOTE_TYPES = listOf(VoteType.VOICES, VoteType.SHOW_OF_HANDS, VoteType.CARDS, VoteType.WRITTEN_BALLOT, VoteType.FORMAL_PROCEDURES)
private val STATUSES = listOf(
    ResolutionStatus.PROPOSED, ResolutionStatus.CONSENSUS, ResolutionStatus.AGREEMENT,
    ResolutionStatus.FAILED, ResolutionStatus.WITHDRAWN, ResolutionStatus.LAPSED
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ResolutionEditSheet(
    existing: ResolutionDto?,
    meetingId: Int,
    defaultAgendaItemId: Int?,
    agendaItems: List<AgendaItemDto>,
    members: List<BoardMemberDto>,
    isAdmin: Boolean,
    quorumMet: Boolean,
    onDismiss: () -> Unit,
    onSave: (ResolutionWriteRequest) -> Unit
) {
    var title by remember { mutableStateOf(existing?.title.orEmpty()) }
    var description by remember { mutableStateOf(existing?.description.orEmpty()) }
    var resolutionNumber by remember { mutableStateOf(existing?.resolutionNumber.orEmpty()) }
    var decisionMethod by remember { mutableStateOf(existing?.decisionMethod ?: DecisionMethod.CONSENSUS) }
    var voteType by remember { mutableStateOf(existing?.voteType) }
    var status by remember { mutableStateOf(existing?.status ?: ResolutionStatus.PROPOSED) }
    var movedBy by remember { mutableStateOf(members.find { it.id == existing?.motionMovedBy }) }
    var secondedBy by remember { mutableStateOf(members.find { it.id == existing?.motionSecondedBy }) }
    var votesFor by remember { mutableStateOf(existing?.votesFor?.toString().orEmpty()) }
    var votesAgainst by remember { mutableStateOf(existing?.votesAgainst?.toString().orEmpty()) }
    var votesAbstain by remember { mutableStateOf(existing?.votesAbstain?.toString().orEmpty()) }
    var clerkNotes by remember { mutableStateOf(existing?.clerkNotes.orEmpty()) }
    var overrideQuorum by remember { mutableStateOf(false) }
    var showQuorumConfirm by remember { mutableStateOf(false) }

    // Mirrors resolution_helpers.php::validateResolutionData()'s Formal-Majority-needs-mover-and-
    // seconder rule as a client-side UX hint only — the server remains authoritative and its
    // actual 400/409 message is always surfaced if this local check is ever out of sync.
    val requiresMoverSeconder = decisionMethod == DecisionMethod.FORMAL_MAJORITY && status.isFinal
    val missingMoverSeconder = requiresMoverSeconder &&
        (movedBy == null || secondedBy == null || (votesFor.toIntOrNull() == null && votesAgainst.toIntOrNull() == null))
    val quorumBlocksThis = !quorumMet && requiresMoverSeconder && !overrideQuorum

    fun buildRequest() = ResolutionWriteRequest(
        id = existing?.id,
        meetingId = meetingId,
        agendaItemId = defaultAgendaItemId ?: existing?.agendaItemId,
        title = title.ifBlank { null },
        description = description,
        resolutionNumber = resolutionNumber.ifBlank { null },
        decisionMethod = decisionMethod,
        voteType = voteType,
        status = status,
        motionMovedBy = movedBy?.id,
        motionSecondedBy = secondedBy?.id,
        votesFor = votesFor.toIntOrNull(),
        votesAgainst = votesAgainst.toIntOrNull(),
        votesAbstain = votesAbstain.toIntOrNull(),
        clerkNotes = clerkNotes.ifBlank { null },
        overrideQuorum = overrideQuorum
    )

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text(if (existing == null) "New resolution" else "Edit resolution", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))

            OutlinedTextField(value = title, onValueChange = { title = it }, label = { Text("Title (optional)") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = description, onValueChange = { description = it }, label = { Text("Description") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = resolutionNumber, onValueChange = { resolutionNumber = it }, label = { Text("Resolution number") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))

            EnumDropdown("Decision method", DECISION_METHODS, decisionMethod, { it.name.replace('_', ' ') }, { decisionMethod = it })
            Spacer(Modifier.height(8.dp))
            EnumDropdown("Status", STATUSES, status, { it.name }, { status = it })
            Spacer(Modifier.height(8.dp))

            if (decisionMethod == DecisionMethod.FORMAL_MAJORITY) {
                EnumDropdown("Vote type", VOTE_TYPES, voteType ?: VoteType.VOICES, { it.name.replace('_', ' ') }, { voteType = it })
                Spacer(Modifier.height(8.dp))
                EnumDropdown("Moved by", listOf<BoardMemberDto?>(null) + members, movedBy, { it?.fullName ?: "—" }, { movedBy = it })
                Spacer(Modifier.height(8.dp))
                EnumDropdown("Seconded by", listOf<BoardMemberDto?>(null) + members, secondedBy, { it?.fullName ?: "—" }, { secondedBy = it })
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(value = votesFor, onValueChange = { votesFor = it.filter(Char::isDigit) }, label = { Text("For") }, modifier = Modifier.weight(1f))
                    OutlinedTextField(value = votesAgainst, onValueChange = { votesAgainst = it.filter(Char::isDigit) }, label = { Text("Against") }, modifier = Modifier.weight(1f))
                    OutlinedTextField(value = votesAbstain, onValueChange = { votesAbstain = it.filter(Char::isDigit) }, label = { Text("Abstain") }, modifier = Modifier.weight(1f))
                }
                Spacer(Modifier.height(8.dp))
                if (missingMoverSeconder) {
                    Text(
                        "Formal Majority resolutions reaching a final status need a mover, seconder, and at least one vote count.",
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium
                    )
                    Spacer(Modifier.height(8.dp))
                }
                if (!quorumMet) {
                    Text(
                        "Quorum has not been met for this meeting.",
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.bodyMedium
                    )
                    if (isAdmin) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Checkbox(checked = overrideQuorum, onCheckedChange = { overrideQuorum = it })
                            Text("Override quorum requirement (Admin)")
                        }
                    } else {
                        Text("Only an admin can override this.", style = MaterialTheme.typography.bodyMedium)
                    }
                    Spacer(Modifier.height(8.dp))
                }
            }

            OutlinedTextField(value = clerkNotes, onValueChange = { clerkNotes = it }, label = { Text("Clerk notes") }, modifier = Modifier.fillMaxWidth())

            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                TextButton(onClick = onDismiss) { Text("Cancel") }
                Spacer(Modifier.width(8.dp))
                Button(
                    enabled = description.isNotBlank() && !missingMoverSeconder && !(quorumBlocksThis && !isAdmin),
                    onClick = {
                        if (quorumBlocksThis) showQuorumConfirm = true else onSave(buildRequest())
                    }
                ) { Text("Save") }
            }
        }
    }

    if (showQuorumConfirm) {
        AlertDialog(
            onDismissRequest = { showQuorumConfirm = false },
            title = { Text("Save without quorum?") },
            text = { Text("Quorum has not been met — saving this will require the admin override.") },
            confirmButton = {
                TextButton(onClick = {
                    overrideQuorum = true
                    showQuorumConfirm = false
                    onSave(buildRequest().copy(overrideQuorum = true))
                }) { Text("Save with override") }
            },
            dismissButton = { TextButton(onClick = { showQuorumConfirm = false }) { Text("Cancel") } }
        )
    }
}
