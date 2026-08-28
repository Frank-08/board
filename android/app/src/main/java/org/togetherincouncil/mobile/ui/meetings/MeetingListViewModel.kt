package org.togetherincouncil.mobile.ui.meetings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import org.togetherincouncil.mobile.data.remote.dto.MeetingSummaryDto
import org.togetherincouncil.mobile.data.remote.dto.MeetingStatus
import org.togetherincouncil.mobile.data.remote.dto.MeetingTypeDto
import org.togetherincouncil.mobile.data.repository.MeetingRepository
import org.togetherincouncil.mobile.data.repository.MeetingTypeRepository

data class MeetingListUiState(
    val isLoading: Boolean = true,
    val meetings: List<MeetingSummaryDto> = emptyList(),
    val meetingTypes: List<MeetingTypeDto> = emptyList(),
    val selectedMeetingTypeId: Int? = null,
    val selectedStatus: MeetingStatus? = null,
    val errorMessage: String? = null
)

class MeetingListViewModel(
    private val meetingRepository: MeetingRepository,
    private val meetingTypeRepository: MeetingTypeRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(MeetingListUiState())
    val uiState: StateFlow<MeetingListUiState> = _uiState

    init {
        viewModelScope.launch {
            meetingTypeRepository.list().onSuccess { types ->
                _uiState.value = _uiState.value.copy(meetingTypes = types)
            }
        }
        refresh()
    }

    fun selectMeetingType(id: Int?) {
        _uiState.value = _uiState.value.copy(selectedMeetingTypeId = id)
        refresh()
    }

    fun selectStatus(status: MeetingStatus?) {
        _uiState.value = _uiState.value.copy(selectedStatus = status)
        refresh()
    }

    fun refresh() {
        val state = _uiState.value
        _uiState.value = state.copy(isLoading = true, errorMessage = null)
        viewModelScope.launch {
            val statusParam = state.selectedStatus?.let { statusToApiValue(it) }
            meetingRepository.listByType(state.selectedMeetingTypeId, statusParam).fold(
                onSuccess = { meetings ->
                    _uiState.value = _uiState.value.copy(isLoading = false, meetings = meetings, errorMessage = null)
                },
                onFailure = { error ->
                    _uiState.value = _uiState.value.copy(isLoading = false, errorMessage = error.message)
                }
            )
        }
    }

    private fun statusToApiValue(status: MeetingStatus): String = when (status) {
        MeetingStatus.SCHEDULED -> "Scheduled"
        MeetingStatus.IN_PROGRESS -> "In Progress"
        MeetingStatus.COMPLETED -> "Completed"
        MeetingStatus.CANCELLED -> "Cancelled"
        MeetingStatus.POSTPONED -> "Postponed"
        MeetingStatus.UNKNOWN -> ""
    }
}
