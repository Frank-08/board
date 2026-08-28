@file:OptIn(ExperimentalMaterial3Api::class)

package org.togetherincouncil.mobile.ui.meetings

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import org.togetherincouncil.mobile.data.remote.dto.MeetingStatus
import org.togetherincouncil.mobile.data.remote.dto.MeetingSummaryDto
import org.togetherincouncil.mobile.ui.common.ErrorBanner

@Composable
fun MeetingListScreen(viewModel: MeetingListViewModel, onMeetingClick: (Int) -> Unit) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    Scaffold(topBar = { TopAppBar(title = { Text("Meetings") }) }) { padding ->
        Column(modifier = Modifier.padding(padding).fillMaxSize()) {
            LazyRow(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                item {
                    FilterChip(
                        selected = uiState.selectedMeetingTypeId == null,
                        onClick = { viewModel.selectMeetingType(null) },
                        label = { Text("All types") }
                    )
                }
                items(uiState.meetingTypes) { type ->
                    FilterChip(
                        selected = uiState.selectedMeetingTypeId == type.id,
                        onClick = { viewModel.selectMeetingType(type.id) },
                        label = { Text(type.name) }
                    )
                }
            }
            LazyRow(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                item {
                    FilterChip(
                        selected = uiState.selectedStatus == null,
                        onClick = { viewModel.selectStatus(null) },
                        label = { Text("Any status") }
                    )
                }
                items(
                    listOf(
                        MeetingStatus.SCHEDULED, MeetingStatus.IN_PROGRESS,
                        MeetingStatus.COMPLETED, MeetingStatus.POSTPONED, MeetingStatus.CANCELLED
                    )
                ) { status ->
                    FilterChip(
                        selected = uiState.selectedStatus == status,
                        onClick = { viewModel.selectStatus(status) },
                        label = { Text(status.name.replace('_', ' ').lowercase().replaceFirstChar { it.uppercase() }) }
                    )
                }
            }

            if (uiState.errorMessage != null) {
                ErrorBanner(
                    message = uiState.errorMessage ?: "Couldn't load meetings",
                    onRetry = viewModel::refresh,
                    modifier = Modifier.padding(12.dp)
                )
            }

            when {
                uiState.isLoading && uiState.meetings.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
                uiState.meetings.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("No meetings match these filters.")
                }
                else -> LazyColumn {
                    items(uiState.meetings) { meeting -> MeetingRow(meeting, onClick = { onMeetingClick(meeting.id) }) }
                }
            }
        }
    }
}

@Composable
private fun MeetingRow(meeting: MeetingSummaryDto, onClick: () -> Unit) {
    ListItem(
        headlineContent = { Text(meeting.title) },
        supportingContent = { Text("${meeting.scheduledDate} · ${meeting.location ?: "No location set"}") },
        trailingContent = { AssistChip(onClick = {}, label = { Text(meeting.status.name.replace('_', ' ')) }, enabled = false) },
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)
    )
    HorizontalDivider()
}
