package org.togetherincouncil.mobile.data.repository

import org.togetherincouncil.mobile.data.error.SafeApiCall
import org.togetherincouncil.mobile.data.remote.api.*
import org.togetherincouncil.mobile.data.remote.dto.*

class WhoAmIRepository(private val api: WhoAmIApi, private val safe: SafeApiCall) {
    suspend fun whoAmI(): Result<WhoAmIDto> = safe.execute { api.whoAmI() }
}

class DashboardRepository(private val api: DashboardApi, private val safe: SafeApiCall) {
    suspend fun getDashboard(meetingTypeId: Int?): Result<DashboardDto> =
        safe.execute { api.getDashboard(meetingTypeId) }
}

class MeetingTypeRepository(private val api: MeetingTypesApi, private val safe: SafeApiCall) {
    suspend fun list(): Result<List<MeetingTypeDto>> = safe.execute { api.list() }
}

class MemberRepository(private val api: MembersApi, private val safe: SafeApiCall) {
    suspend fun list(meetingTypeId: Int? = null): Result<List<BoardMemberDto>> =
        safe.execute { api.list(meetingTypeId) }

    suspend fun listMemberships(meetingTypeId: Int): Result<List<MeetingTypeMemberDto>> =
        safe.execute { api.listMemberships(meetingTypeId) }
}

class MeetingRepository(private val api: MeetingsApi, private val safe: SafeApiCall) {
    suspend fun listByType(meetingTypeId: Int?, status: String?, limit: Int? = null): Result<List<MeetingSummaryDto>> =
        safe.execute { api.listByType(meetingTypeId, status, limit) }

    suspend fun getById(id: Int): Result<MeetingDetailDto> = safe.execute { api.getById(id) }
}

class AgendaRepository(private val api: AgendaApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<AgendaItemDto>> =
        safe.execute { api.listByMeeting(meetingId) }

    suspend fun create(body: AgendaItemWriteRequest): Result<AgendaItemDto> = safe.execute { api.create(body) }
    suspend fun update(body: AgendaItemWriteRequest): Result<AgendaItemDto> = safe.execute { api.update(body) }
    suspend fun delete(id: Int): Result<SuccessDto> = safe.execute { api.delete(IdRequest(id)) }

    /** Single bulk call — never synthesize N individual PUTs for a reorder, see api/agenda.php. */
    suspend fun reorder(meetingId: Int, order: List<Int>): Result<SuccessDto> =
        safe.execute { api.reorder(ReorderRequest(meetingId = meetingId, order = order)) }
}

class AttendeeRepository(private val api: AttendeesApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<AttendeeDto>> =
        safe.execute { api.listByMeeting(meetingId) }

    suspend fun create(body: AttendeeWriteRequest): Result<AttendeeDto> = safe.execute { api.create(body) }
    suspend fun update(body: AttendeeWriteRequest): Result<AttendeeDto> = safe.execute { api.update(body) }
    suspend fun delete(id: Int): Result<SuccessDto> = safe.execute { api.delete(IdRequest(id)) }
}

class MinutesRepository(
    private val minutesApi: MinutesApi,
    private val commentsApi: MinutesCommentsApi,
    private val safe: SafeApiCall
) {
    suspend fun getByMeeting(meetingId: Int): Result<MinutesDto?> = safe.execute { minutesApi.getByMeeting(meetingId) }
    suspend fun create(body: MinutesWriteRequest): Result<MinutesDto> = safe.execute { minutesApi.create(body) }
    suspend fun update(body: MinutesWriteRequest): Result<MinutesDto> = safe.execute { minutesApi.update(body) }

    /** meeting_id is sent (not minutes_id) so the server auto-creates a draft minutes row if none exists yet. */
    suspend fun upsertComment(meetingId: Int, agendaItemId: Int, comment: String): Result<MinutesCommentDto> =
        safe.execute { commentsApi.upsert(MinutesCommentWriteRequest(meetingId, agendaItemId, comment)) }

    suspend fun deleteComment(id: Int): Result<SuccessDto> = safe.execute { commentsApi.delete(IdRequest(id)) }
}

class ResolutionRepository(private val api: ResolutionsApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<ResolutionDto>> =
        safe.execute { api.listByMeeting(meetingId) }

    suspend fun create(body: ResolutionWriteRequest): Result<ResolutionDto> = safe.execute { api.create(body) }
    suspend fun update(body: ResolutionWriteRequest): Result<ResolutionDto> = safe.execute { api.update(body) }
    suspend fun delete(id: Int): Result<SuccessDto> = safe.execute { api.delete(IdRequest(id)) }

    suspend fun reorder(agendaItemId: Int, order: List<Int>): Result<SuccessDto> =
        safe.execute { api.reorder(ReorderRequest(agendaItemId = agendaItemId, order = order)) }
}

class ProceduralProposalRepository(private val api: ProceduralProposalsApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<ProceduralProposalDto>> =
        safe.execute { api.listByMeeting(meetingId) }

    suspend fun create(body: ProceduralProposalWriteRequest): Result<ProceduralProposalDto> = safe.execute { api.create(body) }
    suspend fun update(body: ProceduralProposalWriteRequest): Result<ProceduralProposalDto> = safe.execute { api.update(body) }
    suspend fun delete(id: Int): Result<SuccessDto> = safe.execute { api.delete(IdRequest(id)) }
}

class DepartureRepository(private val api: DeparturesApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<DepartureDto>> = safe.execute { api.listByMeeting(meetingId) }
    suspend fun create(body: DepartureWriteRequest): Result<DepartureDto> = safe.execute { api.create(body) }
    suspend fun update(body: DepartureWriteRequest): Result<DepartureDto> = safe.execute { api.update(body) }
    suspend fun delete(id: Int): Result<SuccessDto> = safe.execute { api.delete(IdRequest(id)) }
}

class DocumentRepository(private val api: DocumentsApi, private val safe: SafeApiCall) {
    suspend fun listByMeeting(meetingId: Int): Result<List<DocumentDto>> = safe.execute { api.listByMeeting(meetingId) }
    suspend fun listByAgendaItem(agendaItemId: Int): Result<List<DocumentDto>> = safe.execute { api.listByAgendaItem(agendaItemId) }
}
