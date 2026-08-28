package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.launch
import org.togetherincouncil.mobile.data.remote.dto.MeetingDetailDto
import org.togetherincouncil.mobile.ui.common.ErrorBanner
import org.togetherincouncil.mobile.ui.common.LoadingIndicator
import org.togetherincouncil.mobile.ui.common.LockedBanner

private val TAB_TITLES = listOf("Agenda", "Attendees", "Minutes", "Resolutions")

@Composable
fun MeetingDetailScreen(
    viewModel: MeetingDetailViewModel,
    onBack: () -> Unit,
    canManageAgenda: Boolean,
    canManageAttendees: Boolean,
    canEditOwnAttendance: Boolean,
    canManageMinutes: Boolean,
    canManageResolutions: Boolean,
    canManageProceduralProposals: Boolean,
    currentBoardMemberId: Int?,
    isAdmin: Boolean
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val pagerState = rememberPagerState(pageCount = { TAB_TITLES.size })
    val scope = rememberCoroutineScope()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(uiState.snackbarMessage) {
        uiState.snackbarMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeSnackbar()
        }
    }

    Scaffold(
        topBar = {
            Column {
                TopAppBar(
                    title = { Text(uiState.meeting?.title ?: "Meeting") },
                    navigationIcon = {
                        IconButton(onClick = onBack) {
                            Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                        }
                    }
                )
                uiState.meeting?.let { MeetingHeader(it) }
                TabRow(selectedTabIndex = pagerState.currentPage) {
                    TAB_TITLES.forEachIndexed { index, title ->
                        Tab(
                            selected = pagerState.currentPage == index,
                            onClick = { scope.launch { pagerState.animateScrollToPage(index) } },
                            text = { Text(title) }
                        )
                    }
                }
            }
        },
        snackbarHost = { SnackbarHost(snackbarHostState) }
    ) { padding ->
        Column(modifier = Modifier.padding(padding).fillMaxSize()) {
            if (uiState.errorMessage != null) {
                ErrorBanner(
                    message = uiState.errorMessage ?: "Couldn't refresh",
                    onRetry = viewModel::refreshAll,
                    modifier = Modifier.padding(12.dp)
                )
            }
            if (uiState.isLocked) {
                LockedBanner(
                    "Minutes approved — this meeting's record is locked.",
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
                )
            }

            if (uiState.isLoading && uiState.meeting == null) {
                LoadingIndicator(Modifier.fillMaxSize())
            } else {
                HorizontalPager(state = pagerState, modifier = Modifier.fillMaxSize()) { page ->
                    when (page) {
                        0 -> AgendaTab(
                            state = uiState,
                            canManage = canManageAgenda && !uiState.isLocked,
                            onSave = viewModel::saveAgendaItem,
                            onDelete = viewModel::deleteAgendaItem,
                            onReorder = viewModel::reorderAgenda
                        )
                        1 -> AttendeesTab(
                            state = uiState,
                            canManageAll = canManageAttendees,
                            canEditOwn = canEditOwnAttendance,
                            currentBoardMemberId = currentBoardMemberId,
                            onStatusChange = viewModel::setAttendanceStatus,
                            onAddAttendee = viewModel::addAttendee
                        )
                        2 -> MinutesTab(
                            state = uiState,
                            canManage = canManageMinutes,
                            canManageProposals = canManageProceduralProposals && !uiState.isLocked,
                            onSaveMinutes = viewModel::saveMinutes,
                            onApprove = viewModel::approveMinutes,
                            onSaveComment = viewModel::saveAgendaComment,
                            onSaveProposal = viewModel::saveProposal,
                            onDeleteProposal = viewModel::deleteProposal,
                            onSaveDeparture = viewModel::saveDeparture
                        )
                        3 -> ResolutionsTab(
                            state = uiState,
                            canManage = canManageResolutions && !uiState.isLocked,
                            isAdmin = isAdmin,
                            onSave = viewModel::saveResolution,
                            onDelete = viewModel::deleteResolution,
                            onReorder = viewModel::reorderResolutions
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun MeetingHeader(meeting: MeetingDetailDto) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(Modifier.weight(1f)) {
            Text(meeting.scheduledDate, style = MaterialTheme.typography.bodyMedium)
            Text(meeting.location ?: "No location set", style = MaterialTheme.typography.bodyMedium)
        }
        AssistChip(
            onClick = {},
            enabled = false,
            label = {
                Text(
                    if (meeting.quorumMet) "Quorum met (${meeting.quorumRequired} required)"
                    else "No quorum (${meeting.quorumRequired} required)",
                    fontWeight = FontWeight.Medium
                )
            }
        )
    }
}
