package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import org.togetherincouncil.mobile.data.remote.dto.AgendaItemWriteRequest

/**
 * Reordering is done with up/down affordances rather than a drag gesture for this v1 — both
 * end up calling the exact same bulk reorder contract (onReorder receives the full flat id
 * list in its new order); swapping in a drag-handle library later is a pure UI change, the
 * reorder call itself doesn't need to change.
 */
@Composable
fun AgendaTab(
    state: MeetingDetailUiState,
    canManage: Boolean,
    onSave: (AgendaItemWriteRequest) -> Unit,
    onDelete: (Int) -> Unit,
    onReorder: (List<Int>) -> Unit
) {
    var editingItem by remember { mutableStateOf<AgendaItemDto?>(null) }
    var showCreateSheet by remember { mutableStateOf(false) }

    // Top-level items in position order, each followed immediately by its children in
    // sub_position order — mirrors how the server groups children with their parent on reorder.
    val topLevel = state.agendaItems.filter { it.parentId == null }.sortedBy { it.position }
    val childrenByParent = state.agendaItems.filter { it.parentId != null }.groupBy { it.parentId }
    val flatOrder = remember(state.agendaItems) {
        buildList {
            topLevel.forEach { parent ->
                add(parent.id)
                childrenByParent[parent.id]?.sortedBy { it.subPosition }?.forEach { add(it.id) }
            }
        }
    }

    Box(Modifier.fillMaxSize()) {
        if (state.agendaItems.isEmpty()) {
            Text(
                "No agenda items yet.",
                modifier = Modifier.align(Alignment.Center).padding(24.dp)
            )
        } else {
            LazyColumn(contentPadding = PaddingValues(bottom = 80.dp)) {
                topLevel.forEachIndexed { index, parent ->
                    item(key = "p-${parent.id}") {
                        AgendaItemRow(
                            item = parent,
                            indent = 0,
                            canManage = canManage,
                            canMoveUp = index > 0,
                            canMoveDown = index < topLevel.size - 1,
                            onEdit = { editingItem = parent },
                            onDelete = { onDelete(parent.id) },
                            onMoveUp = { onReorder(moveWithinTopLevel(topLevel, childrenByParent, parent.id, -1)) },
                            onMoveDown = { onReorder(moveWithinTopLevel(topLevel, childrenByParent, parent.id, 1)) }
                        )
                    }
                    val children = childrenByParent[parent.id]?.sortedBy { it.subPosition }.orEmpty()
                    itemsIndexed(children) { childIndex, child ->
                        AgendaItemRow(
                            item = child,
                            indent = 1,
                            canManage = canManage,
                            canMoveUp = childIndex > 0,
                            canMoveDown = childIndex < children.size - 1,
                            onEdit = { editingItem = child },
                            onDelete = { onDelete(child.id) },
                            onMoveUp = { onReorder(swapChild(flatOrder, children, childIndex, -1)) },
                            onMoveDown = { onReorder(swapChild(flatOrder, children, childIndex, 1)) }
                        )
                    }
                }
            }
        }

        if (canManage) {
            FloatingActionButton(
                onClick = { showCreateSheet = true },
                modifier = Modifier.align(Alignment.BottomEnd).padding(16.dp)
            ) {
                Icon(Icons.Filled.Add, contentDescription = "Add agenda item")
            }
        }
    }

    if (editingItem != null) {
        AgendaItemEditSheet(
            existing = editingItem,
            meetingId = state.meeting?.id ?: return,
            members = state.members,
            topLevelItems = topLevel,
            onDismiss = { editingItem = null },
            onSave = { onSave(it); editingItem = null }
        )
    }
    if (showCreateSheet) {
        AgendaItemEditSheet(
            existing = null,
            meetingId = state.meeting?.id ?: return,
            members = state.members,
            topLevelItems = topLevel,
            onDismiss = { showCreateSheet = false },
            onSave = { onSave(it); showCreateSheet = false }
        )
    }
}

private fun moveWithinTopLevel(
    topLevel: List<AgendaItemDto>,
    childrenByParent: Map<Int?, List<AgendaItemDto>>,
    itemId: Int,
    delta: Int
): List<Int> {
    val ids = topLevel.map { it.id }.toMutableList()
    val index = ids.indexOf(itemId)
    val target = index + delta
    if (target < 0 || target >= ids.size) return flatten(topLevel, childrenByParent)
    ids.add(target, ids.removeAt(index))
    val reorderedTopLevel = ids.mapNotNull { id -> topLevel.find { it.id == id } }
    return flatten(reorderedTopLevel, childrenByParent)
}

private fun swapChild(currentFlatOrder: List<Int>, children: List<AgendaItemDto>, childIndex: Int, delta: Int): List<Int> {
    val targetIndex = childIndex + delta
    if (targetIndex < 0 || targetIndex >= children.size) return currentFlatOrder
    val a = children[childIndex].id
    val b = children[targetIndex].id
    return currentFlatOrder.map { id -> if (id == a) b else if (id == b) a else id }
}

private fun flatten(topLevel: List<AgendaItemDto>, childrenByParent: Map<Int?, List<AgendaItemDto>>): List<Int> =
    buildList {
        topLevel.forEach { parent ->
            add(parent.id)
            childrenByParent[parent.id]?.sortedBy { it.subPosition }?.forEach { add(it.id) }
        }
    }

@Composable
private fun AgendaItemRow(
    item: AgendaItemDto,
    indent: Int,
    canManage: Boolean,
    canMoveUp: Boolean,
    canMoveDown: Boolean,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
    onMoveUp: () -> Unit,
    onMoveDown: () -> Unit
) {
    ListItem(
        modifier = Modifier.padding(start = (indent * 24).dp),
        headlineContent = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (item.isStarred) {
                    Icon(Icons.Filled.Star, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                }
                Text("${item.itemNumber ?: ""}  ${item.title}", fontWeight = FontWeight.Medium)
            }
        },
        supportingContent = {
            Text("${item.itemType.name.replace('_', ' ')} · ${item.decisionMethod.name.replace('_', ' ')}")
        },
        trailingContent = if (canManage) {
            {
                Row {
                    IconButton(onClick = onMoveUp, enabled = canMoveUp) {
                        Icon(Icons.Filled.KeyboardArrowUp, contentDescription = "Move up")
                    }
                    IconButton(onClick = onMoveDown, enabled = canMoveDown) {
                        Icon(Icons.Filled.KeyboardArrowDown, contentDescription = "Move down")
                    }
                    IconButton(onClick = onEdit) {
                        Icon(Icons.Filled.Edit, contentDescription = "Edit")
                    }
                    IconButton(onClick = onDelete) {
                        Icon(Icons.Filled.Delete, contentDescription = "Delete")
                    }
                }
            }
        } else null
    )
    HorizontalDivider()
}
