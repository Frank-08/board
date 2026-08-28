package org.togetherincouncil.mobile.ui.meetings.detail

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import org.togetherincouncil.mobile.data.remote.dto.*
import org.togetherincouncil.mobile.data.repository.*

data class MeetingDetailUiState(
    val isLoading: Boolean = true,
    val meeting: MeetingDetailDto? = null,
    val agendaItems: List<AgendaItemDto> = emptyList(),
    val attendees: List<AttendeeDto> = emptyList(),
    val members: List<BoardMemberDto> = emptyList(),
    val minutes: MinutesDto? = null,
    val minutesLoaded: Boolean = false,
    val resolutions: List<ResolutionDto> = emptyList(),
    val proposals: List<ProceduralProposalDto> = emptyList(),
    val departures: List<DepartureDto> = emptyList(),
    val errorMessage: String? = null,
    val snackbarMessage: String? = null
) {
    val isLocked: Boolean get() = minutes?.status == MinutesStatus.APPROVED
}

class MeetingDetailViewModel(
    private val meetingId: Int,
    private val meetingRepository: MeetingRepository,
    private val agendaRepository: AgendaRepository,
    private val attendeeRepository: AttendeeRepository,
    private val minutesRepository: MinutesRepository,
    private val resolutionRepository: ResolutionRepository,
    private val proceduralProposalRepository: ProceduralProposalRepository,
    private val departureRepository: DepartureRepository,
    private val memberRepository: MemberRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(MeetingDetailUiState())
    val uiState: StateFlow<MeetingDetailUiState> = _uiState

    init {
        refreshAll()
    }

    fun refreshAll() {
        _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
        viewModelScope.launch {
            loadMeeting()
            loadAgenda()
            loadAttendees()
            loadMinutes()
            loadResolutions()
            loadProposals()
            loadDepartures()
            loadMembers()
            _uiState.value = _uiState.value.copy(isLoading = false)
        }
    }

    // ---- Meeting shell -----------------------------------------------------------------

    private suspend fun loadMeeting() {
        meetingRepository.getById(meetingId).fold(
            onSuccess = { m -> _uiState.value = _uiState.value.copy(meeting = m) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    private suspend fun loadMembers() {
        memberRepository.list().onSuccess { list -> _uiState.value = _uiState.value.copy(members = list) }
    }

    // ---- Agenda -------------------------------------------------------------------------

    private suspend fun loadAgenda() {
        agendaRepository.listByMeeting(meetingId).fold(
            onSuccess = { items -> _uiState.value = _uiState.value.copy(agendaItems = items) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    fun saveAgendaItem(request: AgendaItemWriteRequest) = viewModelScope.launch {
        val result = if (request.id == null) agendaRepository.create(request) else agendaRepository.update(request)
        result.fold(
            onSuccess = { loadAgenda() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save agenda item") }
        )
    }

    fun deleteAgendaItem(id: Int) = viewModelScope.launch {
        agendaRepository.delete(id).fold(
            onSuccess = { loadAgenda() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't delete agenda item") }
        )
    }

    /**
     * Single bulk call, never N individual PUTs — see api/agenda.php's reorder branch. [newOrder]
     * is the full flat list of item ids (parents and children) in their final visual order; the
     * server re-derives position/sub_position/item_number for everyone in one transaction, so we
     * re-fetch afterwards rather than guessing the new numbering client-side.
     */
    fun reorderAgenda(newOrder: List<Int>) {
        val previous = _uiState.value.agendaItems
        viewModelScope.launch {
            agendaRepository.reorder(meetingId, newOrder).fold(
                onSuccess = { loadAgenda() },
                onFailure = { e ->
                    _uiState.value = _uiState.value.copy(agendaItems = previous)
                    emitSnackbar(e.message ?: "Couldn't save the new order — reverted")
                }
            )
        }
    }

    // ---- Attendees ----------------------------------------------------------------------

    private suspend fun loadAttendees() {
        attendeeRepository.listByMeeting(meetingId).fold(
            onSuccess = { list -> _uiState.value = _uiState.value.copy(attendees = list) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    fun setAttendanceStatus(attendee: AttendeeDto, status: AttendanceStatus) = viewModelScope.launch {
        attendeeRepository.update(
            AttendeeWriteRequest(
                id = attendee.id,
                meetingId = meetingId,
                memberId = attendee.memberId,
                attendanceStatus = status,
                arrivalTime = attendee.arrivalTime,
                notes = attendee.notes
            )
        ).fold(
            onSuccess = {
                loadAttendees()
                // Quorum may have shifted — refresh the meeting header too.
                loadMeeting()
            },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't update attendance") }
        )
    }

    fun addAttendee(memberId: Int, status: AttendanceStatus) = viewModelScope.launch {
        attendeeRepository.create(AttendeeWriteRequest(meetingId = meetingId, memberId = memberId, attendanceStatus = status)).fold(
            onSuccess = { loadAttendees(); loadMeeting() },
            onFailure = { e -> emitSnackbar(if ((e as? org.togetherincouncil.mobile.data.error.ApiException.Conflict) != null) "That member is already on the attendee list." else e.message ?: "Couldn't add attendee") }
        )
    }

    // ---- Minutes + comments ---------------------------------------------------------------

    private suspend fun loadMinutes() {
        minutesRepository.getByMeeting(meetingId).fold(
            onSuccess = { m -> _uiState.value = _uiState.value.copy(minutes = m, minutesLoaded = true) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message, minutesLoaded = true) }
        )
    }

    fun saveMinutes(content: String, actionItems: String?, nextMeetingDate: String?, status: MinutesStatus, preparedBy: Int?) {
        val existing = _uiState.value.minutes
        val request = MinutesWriteRequest(
            id = existing?.id,
            meetingId = meetingId,
            content = content,
            actionItems = actionItems,
            nextMeetingDate = nextMeetingDate,
            status = status,
            preparedBy = preparedBy
        )
        viewModelScope.launch {
            val result = if (existing == null) minutesRepository.create(request) else minutesRepository.update(request)
            result.fold(
                onSuccess = { loadMinutes() },
                onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save minutes") }
            )
        }
    }

    fun approveMinutes(approvedBy: Int) {
        val existing = _uiState.value.minutes ?: return
        viewModelScope.launch {
            minutesRepository.update(
                MinutesWriteRequest(
                    id = existing.id,
                    meetingId = meetingId,
                    content = existing.content,
                    actionItems = existing.actionItems,
                    nextMeetingDate = existing.nextMeetingDate,
                    status = MinutesStatus.APPROVED,
                    approve = true,
                    approvedBy = approvedBy
                )
            ).fold(
                onSuccess = { loadMinutes() },
                onFailure = { e -> emitSnackbar(e.message ?: "Couldn't approve minutes") }
            )
        }
    }

    /** meeting_id (not minutes_id) so the server auto-creates a draft minutes row if none exists yet. */
    fun saveAgendaComment(agendaItemId: Int, comment: String) = viewModelScope.launch {
        minutesRepository.upsertComment(meetingId, agendaItemId, comment).fold(
            onSuccess = { loadMinutes() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save comment") }
        )
    }

    // ---- Resolutions ------------------------------------------------------------------------

    private suspend fun loadResolutions() {
        resolutionRepository.listByMeeting(meetingId).fold(
            onSuccess = { list -> _uiState.value = _uiState.value.copy(resolutions = list) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    fun saveResolution(request: ResolutionWriteRequest) = viewModelScope.launch {
        val result = if (request.id == null) resolutionRepository.create(request) else resolutionRepository.update(request)
        result.fold(
            onSuccess = { dto ->
                loadResolutions()
                loadAgenda() // creating a resolution can auto-create a sub-item
                dto.warning?.let { emitSnackbar(it) }
            },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save resolution") }
        )
    }

    fun deleteResolution(id: Int) = viewModelScope.launch {
        resolutionRepository.delete(id).fold(
            onSuccess = { loadResolutions() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't delete resolution") }
        )
    }

    fun reorderResolutions(agendaItemId: Int, newOrder: List<Int>) {
        val previous = _uiState.value.resolutions
        viewModelScope.launch {
            resolutionRepository.reorder(agendaItemId, newOrder).fold(
                onSuccess = { loadResolutions() },
                onFailure = { e ->
                    _uiState.value = _uiState.value.copy(resolutions = previous)
                    emitSnackbar(e.message ?: "Couldn't save the new order — reverted")
                }
            )
        }
    }

    // ---- Procedural proposals -----------------------------------------------------------

    private suspend fun loadProposals() {
        proceduralProposalRepository.listByMeeting(meetingId).fold(
            onSuccess = { list -> _uiState.value = _uiState.value.copy(proposals = list) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    fun saveProposal(request: ProceduralProposalWriteRequest) = viewModelScope.launch {
        val result = if (request.id == null) proceduralProposalRepository.create(request) else proceduralProposalRepository.update(request)
        result.fold(
            onSuccess = { loadProposals() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save procedural proposal") }
        )
    }

    fun deleteProposal(id: Int) = viewModelScope.launch {
        proceduralProposalRepository.delete(id).fold(
            onSuccess = { loadProposals() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't delete procedural proposal") }
        )
    }

    // ---- Departures ------------------------------------------------------------------------

    private suspend fun loadDepartures() {
        departureRepository.listByMeeting(meetingId).fold(
            onSuccess = { list -> _uiState.value = _uiState.value.copy(departures = list) },
            onFailure = { e -> _uiState.value = _uiState.value.copy(errorMessage = e.message) }
        )
    }

    fun saveDeparture(request: DepartureWriteRequest) = viewModelScope.launch {
        val result = if (request.id == null) departureRepository.create(request) else departureRepository.update(request)
        result.fold(
            onSuccess = { loadDepartures() },
            onFailure = { e -> emitSnackbar(e.message ?: "Couldn't save departure record") }
        )
    }

    // ---- Misc --------------------------------------------------------------------------

    fun consumeSnackbar() {
        _uiState.value = _uiState.value.copy(snackbarMessage = null)
    }

    private fun emitSnackbar(message: String) {
        _uiState.value = _uiState.value.copy(snackbarMessage = message)
    }
}
