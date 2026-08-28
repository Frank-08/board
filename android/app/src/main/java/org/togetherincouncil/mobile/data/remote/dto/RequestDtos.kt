package org.togetherincouncil.mobile.data.remote.dto

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

/** Shared shape for every DELETE endpoint — the id travels in the JSON body, not the URL. */
@JsonClass(generateAdapter = true)
data class IdRequest(@Json(name = "id") val id: Int)

/** Shared bulk-reorder body used by agenda.php ({meeting_id}) and resolutions.php ({agenda_item_id}). */
@JsonClass(generateAdapter = true)
data class ReorderRequest(
    @Json(name = "action") val action: String = "reorder",
    @Json(name = "meeting_id") val meetingId: Int? = null,
    @Json(name = "agenda_item_id") val agendaItemId: Int? = null,
    @Json(name = "order") val order: List<Int>
)

@JsonClass(generateAdapter = true)
data class AgendaItemWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "title") val title: String,
    @Json(name = "description") val description: String?,
    @Json(name = "item_type") val itemType: AgendaItemType,
    @Json(name = "decision_method") val decisionMethod: DecisionMethod,
    @Json(name = "duration_minutes") val durationMinutes: Int?,
    @Json(name = "presenter_ids") val presenterIds: List<Int> = emptyList(),
    @Json(name = "report_type") val reportType: ReportType? = null,
    @Json(name = "is_starred") val isStarred: Boolean = false,
    @Json(name = "parent_id") val parentId: Int? = null
)

@JsonClass(generateAdapter = true)
data class AttendeeWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "attendance_status") val attendanceStatus: AttendanceStatus,
    @Json(name = "arrival_time") val arrivalTime: String? = null,
    @Json(name = "notes") val notes: String? = null
)

@JsonClass(generateAdapter = true)
data class MinutesWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "content") val content: String,
    @Json(name = "action_items") val actionItems: String? = null,
    @Json(name = "next_meeting_date") val nextMeetingDate: String? = null,
    @Json(name = "status") val status: MinutesStatus,
    @Json(name = "prepared_by") val preparedBy: Int? = null,
    @Json(name = "approve") val approve: Boolean? = null,
    @Json(name = "approved_by") val approvedBy: Int? = null
)

@JsonClass(generateAdapter = true)
data class MinutesCommentWriteRequest(
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "agenda_item_id") val agendaItemId: Int,
    @Json(name = "comment") val comment: String
)

@JsonClass(generateAdapter = true)
data class ResolutionWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "agenda_item_id") val agendaItemId: Int? = null,
    @Json(name = "title") val title: String? = null,
    @Json(name = "description") val description: String,
    @Json(name = "resolution_number") val resolutionNumber: String? = null,
    @Json(name = "decision_method") val decisionMethod: DecisionMethod,
    @Json(name = "vote_type") val voteType: VoteType? = null,
    @Json(name = "status") val status: ResolutionStatus,
    @Json(name = "effective_date") val effectiveDate: String? = null,
    @Json(name = "motion_moved_by") val motionMovedBy: Int? = null,
    @Json(name = "motion_seconded_by") val motionSecondedBy: Int? = null,
    @Json(name = "votes_for") val votesFor: Int? = null,
    @Json(name = "votes_against") val votesAgainst: Int? = null,
    @Json(name = "votes_abstain") val votesAbstain: Int? = null,
    @Json(name = "casting_vote_used") val castingVoteUsed: Boolean = false,
    @Json(name = "referral_body") val referralBody: String? = null,
    @Json(name = "referral_scope") val referralScope: String? = null,
    @Json(name = "clerk_notes") val clerkNotes: String? = null,
    @Json(name = "override_quorum") val overrideQuorum: Boolean = false
)

@JsonClass(generateAdapter = true)
data class ProceduralProposalWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "meeting_id") val meetingId: Int,
    @Json(name = "proposal_type") val proposalType: ProposalType,
    @Json(name = "agenda_item_id") val agendaItemId: Int? = null,
    @Json(name = "agenda_position") val agendaPosition: AgendaPosition = AgendaPosition.DURING,
    @Json(name = "resolution_id") val resolutionId: Int? = null,
    @Json(name = "proposed_by") val proposedBy: Int? = null,
    @Json(name = "seconded_by") val secondedBy: Int? = null,
    @Json(name = "outcome") val outcome: ProposalOutcome = ProposalOutcome.PENDING,
    @Json(name = "requires_leave") val requiresLeave: Boolean = false,
    @Json(name = "notes") val notes: String? = null
)

@JsonClass(generateAdapter = true)
data class DepartureWriteRequest(
    @Json(name = "id") val id: Int? = null,
    @Json(name = "agenda_item_id") val agendaItemId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "reason") val reason: String? = null,
    @Json(name = "returned") val returned: Boolean = false
)
