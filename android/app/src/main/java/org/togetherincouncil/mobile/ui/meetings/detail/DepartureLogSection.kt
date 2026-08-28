package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.dto.BoardMemberDto
import org.togetherincouncil.mobile.data.remote.dto.DepartureWriteRequest
import org.togetherincouncil.mobile.ui.common.EnumDropdown

/** "Member left the room" conflict-of-interest record, recorded per agenda item (see
 * api/agenda_item_departures.php). Kept as its own file per the module layout even though it's
 * currently only invoked from MinutesTab's per-item card. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DepartureEditSheet(
    agendaItemId: Int,
    members: List<BoardMemberDto>,
    onDismiss: () -> Unit,
    onSave: (DepartureWriteRequest) -> Unit
) {
    var member by remember { mutableStateOf(members.firstOrNull()) }
    var reason by remember { mutableStateOf("") }
    var returned by remember { mutableStateOf(false) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.padding(16.dp).padding(bottom = 32.dp)) {
            Text("Record departure", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(16.dp))
            if (members.isEmpty()) {
                Text("No board members available.")
            } else {
                EnumDropdown("Member", members, member ?: members.first(), { it.fullName }, { member = it })
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = reason, onValueChange = { reason = it }, label = { Text("Reason") }, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = returned, onCheckedChange = { returned = it })
                    Text("Returned")
                }
                Spacer(Modifier.height(16.dp))
                Button(
                    onClick = {
                        member?.let {
                            onSave(DepartureWriteRequest(agendaItemId = agendaItemId, memberId = it.id, reason = reason.ifBlank { null }, returned = returned))
                        }
                    },
                    enabled = member != null,
                    modifier = Modifier.fillMaxWidth()
                ) { Text("Save") }
            }
        }
    }
}
