package org.togetherincouncil.mobile.data.remote.api

import org.togetherincouncil.mobile.data.remote.dto.*
import retrofit2.http.*

interface WhoAmIApi {
    @GET("whoami.php")
    suspend fun whoAmI(): WhoAmIDto
}

interface DashboardApi {
    @GET("dashboard.php")
    suspend fun getDashboard(@Query("meeting_type_id") meetingTypeId: Int? = null): DashboardDto
}

interface MeetingTypesApi {
    @GET("meeting_types.php")
    suspend fun list(): List<MeetingTypeDto>
}

interface MembersApi {
    @GET("members.php")
    suspend fun list(@Query("meeting_type_id") meetingTypeId: Int? = null): List<BoardMemberDto>

    @GET("meeting_type_members.php")
    suspend fun listMemberships(@Query("meeting_type_id") meetingTypeId: Int): List<MeetingTypeMemberDto>
}

interface MeetingsApi {
    @GET("meetings.php")
    suspend fun listByType(
        @Query("meeting_type_id") meetingTypeId: Int? = null,
        @Query("status") status: String? = null,
        @Query("limit") limit: Int? = null
    ): List<MeetingSummaryDto>

    @GET("meetings.php")
    suspend fun getById(@Query("id") id: Int): MeetingDetailDto
}

interface AgendaApi {
    @GET("agenda.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<AgendaItemDto>

    @POST("agenda.php")
    suspend fun create(@Body body: AgendaItemWriteRequest): AgendaItemDto

    @PUT("agenda.php")
    suspend fun update(@Body body: AgendaItemWriteRequest): AgendaItemDto

    @HTTP(method = "DELETE", path = "agenda.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto

    @POST("agenda.php")
    suspend fun reorder(@Body body: ReorderRequest): SuccessDto
}

interface AttendeesApi {
    @GET("attendees.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<AttendeeDto>

    @POST("attendees.php")
    suspend fun create(@Body body: AttendeeWriteRequest): AttendeeDto

    @PUT("attendees.php")
    suspend fun update(@Body body: AttendeeWriteRequest): AttendeeDto

    @HTTP(method = "DELETE", path = "attendees.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto
}

interface MinutesApi {
    @GET("minutes.php")
    suspend fun getByMeeting(@Query("meeting_id") meetingId: Int): MinutesDto?

    @POST("minutes.php")
    suspend fun create(@Body body: MinutesWriteRequest): MinutesDto

    @PUT("minutes.php")
    suspend fun update(@Body body: MinutesWriteRequest): MinutesDto
}

interface MinutesCommentsApi {
    @POST("minutes_comments.php")
    suspend fun upsert(@Body body: MinutesCommentWriteRequest): MinutesCommentDto

    @HTTP(method = "DELETE", path = "minutes_comments.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto
}

interface ResolutionsApi {
    @GET("resolutions.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<ResolutionDto>

    @POST("resolutions.php")
    suspend fun create(@Body body: ResolutionWriteRequest): ResolutionDto

    @PUT("resolutions.php")
    suspend fun update(@Body body: ResolutionWriteRequest): ResolutionDto

    @HTTP(method = "DELETE", path = "resolutions.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto

    @POST("resolutions.php")
    suspend fun reorder(@Body body: ReorderRequest): SuccessDto
}

interface ProceduralProposalsApi {
    @GET("procedural_proposals.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<ProceduralProposalDto>

    @POST("procedural_proposals.php")
    suspend fun create(@Body body: ProceduralProposalWriteRequest): ProceduralProposalDto

    @PUT("procedural_proposals.php")
    suspend fun update(@Body body: ProceduralProposalWriteRequest): ProceduralProposalDto

    @HTTP(method = "DELETE", path = "procedural_proposals.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto
}

interface DeparturesApi {
    @GET("agenda_item_departures.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<DepartureDto>

    @POST("agenda_item_departures.php")
    suspend fun create(@Body body: DepartureWriteRequest): DepartureDto

    @PUT("agenda_item_departures.php")
    suspend fun update(@Body body: DepartureWriteRequest): DepartureDto

    @HTTP(method = "DELETE", path = "agenda_item_departures.php", hasBody = true)
    suspend fun delete(@Body body: IdRequest): SuccessDto
}

interface DocumentsApi {
    @GET("documents.php")
    suspend fun listByMeeting(@Query("meeting_id") meetingId: Int): List<DocumentDto>

    @GET("documents.php")
    suspend fun listByAgendaItem(@Query("agenda_item_id") agendaItemId: Int): List<DocumentDto>
}
