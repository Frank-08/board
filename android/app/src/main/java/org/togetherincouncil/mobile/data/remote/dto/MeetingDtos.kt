package org.togetherincouncil.mobile.data.remote.dto

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

/**
 * Attendee shape as embedded in GET meetings.php?id= (a narrower join than
 * the dedicated GET attendees.php?meeting_id= list — that one additionally
 * joins phone/title/role/membership_status, modeled separately below as
 * AttendeeDto). Don't conflate the two.
 */
@JsonClass(generateAdapter = true)
data class MeetingAttendeeSummaryDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "attendance_status") val attendanceStatus: AttendanceStatus,
    @Json(name = "arrival_time") val arrivalTime: String?,
    @Json(name = "notes") val notes: String?,
    @Json(name = "first_name") val firstName: String? = null,
    @Json(name = "last_name") val lastName: String? = null,
    @Json(name = "email") val email: String? = null
)

@JsonClass(generateAdapter = true)
data class MeetingDetailDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_type_id") val meetingTypeId: Int,
    @Json(name = "title") val title: String,
    @Json(name = "scheduled_date") val scheduledDate: String,
    @Json(name = "end_time") val endTime: String?,
    @Json(name = "location") val location: String?,
    @Json(name = "virtual_link") val virtualLink: String?,
    @Json(name = "quorum_required") val quorumRequired: Int,
    @Json(name = "quorum_met") val quorumMet: Boolean,
    @Json(name = "status") val status: MeetingStatus,
    @Json(name = "notes") val notes: String?,
    @Json(name = "attendees") val attendees: List<MeetingAttendeeSummaryDto> = emptyList(),
    @Json(name = "agenda_items") val agendaItems: List<AgendaItemDto> = emptyList()
)

/** Full attendee row from GET attendees.php?meeting_id= (richer join than MeetingAttendeeSummaryDto). */
@JsonClass(generateAdapter = true)
data class AttendeeDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "attendance_status") val attendanceStatus: AttendanceStatus,
    @Json(name = "arrival_time") val arrivalTime: String?,
    @Json(name = "notes") val notes: String?,
    @Json(name = "first_name") val firstName: String?,
    @Json(name = "last_name") val lastName: String?,
    @Json(name = "email") val email: String? = null,
    @Json(name = "phone") val phone: String? = null,
    @Json(name = "title") val title: String? = null,
    @Json(name = "role") val role: MembershipRole? = null,
    @Json(name = "membership_status") val membershipStatus: MembershipStatus? = null
) {
    val fullName: String get() = listOfNotNull(firstName, lastName).joinToString(" ")
}

/**
 * Matches includes/agenda_helpers.php's attachPresentersToAgendaItems() exactly — it returns
 * {id, first_name, last_name, title} per presenter (id is the board member's id, not a join-row
 * id; no agenda_item_id or position on the individual object, those are only used server-side to
 * group/order before attaching).
 */
@JsonClass(generateAdapter = true)
data class PresenterDto(
    @Json(name = "id") val memberId: Int,
    @Json(name = "first_name") val firstName: String? = null,
    @Json(name = "last_name") val lastName: String? = null,
    @Json(name = "title") val title: String? = null
)

@JsonClass(generateAdapter = true)
data class DepartureDto(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "agenda_item_id") val agendaItemId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "reason") val reason: String?,
    @Json(name = "returned") val returned: Boolean = false,
    @Json(name = "first_name") val firstName: String? = null,
    @Json(name = "last_name") val lastName: String? = null
)

@JsonClass(generateAdapter = true)
data class AgendaItemDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "title") val title: String,
    @Json(name = "description") val description: String?,
    @Json(name = "item_type") val itemType: AgendaItemType,
    @Json(name = "decision_method") val decisionMethod: DecisionMethod,
    @Json(name = "report_type") val reportType: ReportType? = null,
    @Json(name = "duration_minutes") val durationMinutes: Int?,
    @Json(name = "position") val position: Int,
    @Json(name = "sub_position") val subPosition: Int,
    @Json(name = "item_number") val itemNumber: String?,
    @Json(name = "parent_id") val parentId: Int?,
    @Json(name = "is_starred") val isStarred: Boolean = false,
    @Json(name = "outcome") val outcome: String?,
    @Json(name = "resolutions") val resolutions: List<ResolutionDto> = emptyList(),
    @Json(name = "presenters") val presenters: List<PresenterDto> = emptyList(),
    @Json(name = "departures") val departures: List<DepartureDto> = emptyList()
)

@JsonClass(generateAdapter = true)
data class MinutesCommentDto(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "minutes_id") val minutesId: Int? = null,
    @Json(name = "agenda_item_id") val agendaItemId: Int,
    @Json(name = "comment") val comment: String
)

@JsonClass(generateAdapter = true)
data class MinutesDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "prepared_by") val preparedBy: Int?,
    @Json(name = "approved_by") val approvedBy: Int?,
    @Json(name = "content") val content: String,
    @Json(name = "action_items") val actionItems: String?,
    @Json(name = "next_meeting_date") val nextMeetingDate: String?,
    @Json(name = "status") val status: MinutesStatus,
    @Json(name = "approved_at") val approvedAt: String?,
    @Json(name = "agenda_comments") val agendaComments: List<MinutesCommentDto> = emptyList()
)

@JsonClass(generateAdapter = true)
/**
 * meetingId is nullable: includes/agenda_helpers.php's attachResolutionsToAgendaItems() (used by
 * GET agenda.php) selects specific columns and doesn't include r.meeting_id, unlike GET
 * resolutions.php's own SELECT r.* which does — the same DTO covers both response shapes.
 */
data class ResolutionDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int? = null,
    @Json(name = "agenda_item_id") val agendaItemId: Int?,
    @Json(name = "resolution_number") val resolutionNumber: String?,
    @Json(name = "title") val title: String?,
    @Json(name = "description") val description: String,
    @Json(name = "decision_method") val decisionMethod: DecisionMethod,
    @Json(name = "motion_moved_by") val motionMovedBy: Int?,
    @Json(name = "motion_seconded_by") val motionSecondedBy: Int?,
    @Json(name = "votes_for") val votesFor: Int?,
    @Json(name = "votes_against") val votesAgainst: Int?,
    @Json(name = "votes_abstain") val votesAbstain: Int?,
    @Json(name = "casting_vote_used") val castingVoteUsed: Boolean = false,
    @Json(name = "referral_body") val referralBody: String?,
    @Json(name = "referral_scope") val referralScope: String?,
    @Json(name = "clerk_notes") val clerkNotes: String?,
    @Json(name = "vote_type") val voteType: VoteType?,
    @Json(name = "status") val status: ResolutionStatus,
    @Json(name = "effective_date") val effectiveDate: String?,
    @Json(name = "position") val position: Int = 0,
    @Json(name = "_warning") val warning: String? = null
)

@JsonClass(generateAdapter = true)
data class ProceduralProposalDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "agenda_item_id") val agendaItemId: Int?,
    @Json(name = "agenda_position") val agendaPosition: AgendaPosition,
    @Json(name = "resolution_id") val resolutionId: Int?,
    @Json(name = "proposal_type") val proposalType: ProposalType,
    @Json(name = "proposed_by") val proposedBy: Int?,
    @Json(name = "seconded_by") val secondedBy: Int?,
    @Json(name = "outcome") val outcome: ProposalOutcome,
    @Json(name = "requires_leave") val requiresLeave: Boolean = false,
    @Json(name = "notes") val notes: String?
)
