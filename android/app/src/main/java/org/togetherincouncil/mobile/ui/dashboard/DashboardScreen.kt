package org.togetherincouncil.mobile.ui.dashboard

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import org.togetherincouncil.mobile.data.remote.dto.MeetingSummaryDto
import org.togetherincouncil.mobile.data.remote.dto.MeetingTypeDto
import org.togetherincouncil.mobile.ui.common.ErrorBanner

@Composable
fun DashboardScreen(viewModel: DashboardViewModel, onMeetingClick: (Int) -> Unit) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    Scaffold(topBar = { TopAppBar(title = { Text("Dashboard") }) }) { padding ->
        Column(modifier = Modifier.padding(padding).fillMaxSize()) {
            if (uiState.meetingTypes.isNotEmpty()) {
                MeetingTypeFilterRow(
                    meetingTypes = uiState.meetingTypes,
                    selectedId = uiState.selectedMeetingTypeId,
                    onSelect = viewModel::selectMeetingType
                )
            }

            if (uiState.errorMessage != null) {
                ErrorBanner(
                    message = uiState.errorMessage ?: "Couldn't refresh",
                    onRetry = viewModel::refresh,
                    modifier = Modifier.padding(12.dp)
                )
            }

            if (uiState.isLoading && uiState.dashboard == null) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            } else {
                val dashboard = uiState.dashboard
                if (dashboard != null) {
                    LazyColumn(contentPadding = PaddingValues(16.dp)) {
                        item {
                            LazyVerticalGrid(
                                columns = GridCells.Fixed(2),
                                modifier = Modifier.height(180.dp),
                                horizontalArrangement = Arrangement.spacedBy(12.dp),
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                items(
                                    listOf(
                                        "Active members" to dashboard.activeMembers,
                                        "Upcoming meetings" to dashboard.upcomingMeetings,
                                        "Pending resolutions" to dashboard.pendingResolutions,
                                        "Draft minutes" to dashboard.draftMinutes
                                    )
                                ) { (label, value) -> StatTile(label, value) }
                            }
                        }
                        item {
                            Spacer(Modifier.height(24.dp))
                            Text("Upcoming meetings", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                        }
                        items(dashboard.upcomingMeetingsList) { meeting ->
                            MeetingRow(meeting, onClick = { onMeetingClick(meeting.id) })
                        }
                        item {
                            Spacer(Modifier.height(24.dp))
                            Text("Recent meetings", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                        }
                        items(dashboard.recentMeetingsList) { meeting ->
                            MeetingRow(meeting, onClick = { onMeetingClick(meeting.id) })
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StatTile(label: String, value: Int) {
    Card(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier.fillMaxSize().padding(16.dp),
            verticalArrangement = Arrangement.Center
        ) {
            Text(value.toString(), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(label, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

@Composable
private fun MeetingRow(meeting: MeetingSummaryDto, onClick: () -> Unit) {
    ListItem(
        headlineContent = { Text(meeting.title) },
        supportingContent = { Text("${meeting.meetingTypeName ?: ""} · ${meeting.scheduledDate}") },
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)
    )
    HorizontalDivider()
}

@Composable
private fun MeetingTypeFilterRow(
    meetingTypes: List<MeetingTypeDto>,
    selectedId: Int?,
    onSelect: (Int?) -> Unit
) {
    LazyRow(
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        item {
            FilterChip(selected = selectedId == null, onClick = { onSelect(null) }, label = { Text("All") })
        }
        items(meetingTypes) { type ->
            FilterChip(selected = selectedId == type.id, onClick = { onSelect(type.id) }, label = { Text(type.name) })
        }
    }
}
