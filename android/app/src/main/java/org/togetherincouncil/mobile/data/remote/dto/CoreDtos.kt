package org.togetherincouncil.mobile.data.remote.dto

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

@JsonClass(generateAdapter = true)
data class ApiErrorDto(
    @Json(name = "error") val error: String? = null,
    @Json(name = "code") val code: String? = null
)

@JsonClass(generateAdapter = true)
data class SuccessDto(
    @Json(name = "success") val success: Boolean = true
)

@JsonClass(generateAdapter = true)
data class WhoAmIUserDto(
    @Json(name = "id") val id: Int,
    @Json(name = "username") val username: String,
    @Json(name = "email") val email: String?,
    @Json(name = "role") val role: Role,
    @Json(name = "board_member_id") val boardMemberId: Int?
)

@JsonClass(generateAdapter = true)
data class WhoAmIDto(
    @Json(name = "user") val user: WhoAmIUserDto,
    @Json(name = "permissions") val permissions: Map<String, Boolean>
)

@JsonClass(generateAdapter = true)
data class MeetingTypeDto(
    @Json(name = "id") val id: Int,
    @Json(name = "name") val name: String,
    @Json(name = "description") val description: String?,
    @Json(name = "shortcode") val shortcode: String?
)

@JsonClass(generateAdapter = true)
data class BoardMemberDto(
    @Json(name = "id") val id: Int,
    @Json(name = "first_name") val firstName: String,
    @Json(name = "last_name") val lastName: String,
    @Json(name = "email") val email: String?,
    @Json(name = "phone") val phone: String?,
    @Json(name = "title") val title: String?,
    @Json(name = "bio") val bio: String?,
    @Json(name = "photo") val photo: String?
) {
    val fullName: String get() = "$firstName $lastName".trim()
}

@JsonClass(generateAdapter = true)
data class MeetingTypeMemberDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_type_id") val meetingTypeId: Int,
    @Json(name = "member_id") val memberId: Int,
    @Json(name = "role") val role: MembershipRole,
    @Json(name = "status") val status: MembershipStatus,
    @Json(name = "first_name") val firstName: String? = null,
    @Json(name = "last_name") val lastName: String? = null
)

@JsonClass(generateAdapter = true)
data class DashboardDto(
    @Json(name = "active_members") val activeMembers: Int,
    @Json(name = "upcoming_meetings") val upcomingMeetings: Int,
    @Json(name = "recent_meetings") val recentMeetings: Int,
    @Json(name = "pending_resolutions") val pendingResolutions: Int,
    @Json(name = "draft_minutes") val draftMinutes: Int,
    @Json(name = "recent_meetings_list") val recentMeetingsList: List<MeetingSummaryDto> = emptyList(),
    @Json(name = "upcoming_meetings_list") val upcomingMeetingsList: List<MeetingSummaryDto> = emptyList()
)

@JsonClass(generateAdapter = true)
data class MeetingSummaryDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_type_id") val meetingTypeId: Int,
    @Json(name = "meeting_type_name") val meetingTypeName: String? = null,
    @Json(name = "title") val title: String,
    @Json(name = "scheduled_date") val scheduledDate: String,
    @Json(name = "location") val location: String?,
    @Json(name = "status") val status: MeetingStatus
)

@JsonClass(generateAdapter = true)
data class DocumentDto(
    @Json(name = "id") val id: Int,
    @Json(name = "meeting_type_id") val meetingTypeId: Int?,
    @Json(name = "meeting_id") val meetingId: Int?,
    @Json(name = "agenda_item_id") val agendaItemId: Int?,
    @Json(name = "document_type") val documentType: DocumentType,
    @Json(name = "title") val title: String,
    @Json(name = "description") val description: String?,
    @Json(name = "sharepoint_url") val sharepointUrl: String,
    @Json(name = "uploaded_by") val uploadedBy: Int?
)
