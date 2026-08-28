package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.*
import org.togetherincouncil.mobile.ui.common.EnumDropdown

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AgendaItemEditSheet(
    existing: AgendaItemDto?,
    meetingId: Int,
    members: List<BoardMemberDto>,
    topLevelItems: List<AgendaItemDto>,
    onDismiss: () -> Unit,
    onSave: (AgendaItemWriteRequest) -> Unit
) {
    var title by remember { mutableStateOf(existing?.title.orEmpty()) }
    var description by remember { mutableStateOf(existing?.description.orEmpty()) }
    var itemType by remember { mutableStateOf(existing?.itemType ?: AgendaItemType.DISCUSSION) }
    var decisionMethod by remember { mutableStateOf(existing?.decisionMethod ?: DecisionMethod.NONE) }
    var durationMinutes by remember { mutableStateOf(existing?.durationMinutes?.toString().orEmpty()) }
    var isStarred by remember { mutableStateOf(existing?.isStarred ?: false) }
    var parentId by remember { mutableStateOf(existing?.parentId) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text(if (existing == null) "New agenda item" else "Edit agenda item", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))

            OutlinedTextField(value = title, onValueChange = { title = it }, label = { Text("Title") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = description, onValueChange = { description = it }, label = { Text("Description") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))

            EnumDropdown(
                label = "Item type",
                options = listOf(AgendaItemType.DISCUSSION, AgendaItemType.ACTION_ITEM, AgendaItemType.VOTE, AgendaItemType.INFORMATION, AgendaItemType.PRESENTATION),
                selected = itemType,
                labelFor = { it.name.replace('_', ' ') },
                onSelect = { itemType = it }
            )
            Spacer(Modifier.height(8.dp))
            EnumDropdown(
                label = "Decision method",
                options = listOf(DecisionMethod.NONE, DecisionMethod.CONSENSUS, DecisionMethod.FORMAL_MAJORITY, DecisionMethod.REFERRAL),
                selected = decisionMethod,
                labelFor = { it.name.replace('_', ' ') },
                onSelect = { decisionMethod = it }
            )
            Spacer(Modifier.height(8.dp))

            OutlinedTextField(
                value = durationMinutes,
                onValueChange = { durationMinutes = it.filter(Char::isDigit) },
                label = { Text("Duration (minutes)") },
                modifier = Modifier.fillMaxWidth()
            )
            Spacer(Modifier.height(8.dp))

            if (existing == null || existing.parentId != null) {
                EnumDropdown(
                    label = "Parent item (optional)",
                    options = listOf<AgendaItemDto?>(null) + topLevelItems.filter { it.id != existing?.id },
                    selected = topLevelItems.find { it.id == parentId },
                    labelFor = { it?.title ?: "None — top level" },
                    onSelect = { parentId = it?.id }
                )
                Spacer(Modifier.height(8.dp))
            }

            Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                Checkbox(checked = isStarred, onCheckedChange = { isStarred = it })
                Text("Starred")
            }

            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                TextButton(onClick = onDismiss) { Text("Cancel") }
                Spacer(Modifier.width(8.dp))
                Button(
                    enabled = title.isNotBlank(),
                    onClick = {
                        onSave(
                            AgendaItemWriteRequest(
                                id = existing?.id,
                                meetingId = meetingId,
                                title = title,
                                description = description.ifBlank { null },
                                itemType = itemType,
                                decisionMethod = decisionMethod,
                                durationMinutes = durationMinutes.toIntOrNull(),
                                isStarred = isStarred,
                                parentId = parentId
                            )
                        )
                    }
                ) { Text("Save") }
            }
        }
    }
}
