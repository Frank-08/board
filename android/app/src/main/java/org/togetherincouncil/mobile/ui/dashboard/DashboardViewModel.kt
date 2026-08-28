package org.togetherincouncil.mobile.ui.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import org.togetherincouncil.mobile.data.remote.dto.DashboardDto
import org.togetherincouncil.mobile.data.remote.dto.MeetingTypeDto
import org.togetherincouncil.mobile.data.repository.DashboardRepository
import org.togetherincouncil.mobile.data.repository.MeetingTypeRepository

data class DashboardUiState(
    val isLoading: Boolean = true,
    val dashboard: DashboardDto? = null,
    val meetingTypes: List<MeetingTypeDto> = emptyList(),
    val selectedMeetingTypeId: Int? = null,
    val errorMessage: String? = null
)

class DashboardViewModel(
    private val dashboardRepository: DashboardRepository,
    private val meetingTypeRepository: MeetingTypeRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(DashboardUiState())
    val uiState: StateFlow<DashboardUiState> = _uiState

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

    fun refresh() {
        val meetingTypeId = _uiState.value.selectedMeetingTypeId
        _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
        viewModelScope.launch {
            dashboardRepository.getDashboard(meetingTypeId).fold(
                onSuccess = { dto ->
                    _uiState.value = _uiState.value.copy(isLoading = false, dashboard = dto, errorMessage = null)
                },
                onFailure = { error ->
                    // Keep last-known-good data on screen (read-through cache), just flag it stale.
                    _uiState.value = _uiState.value.copy(isLoading = false, errorMessage = error.message)
                }
            )
        }
    }
}
