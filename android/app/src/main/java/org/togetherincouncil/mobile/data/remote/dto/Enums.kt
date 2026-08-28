package org.togetherincouncil.mobile.data.remote.dto

import com.squareup.moshi.Json

/**
 * These enums mirror the MySQL ENUM columns in database/schema.sql exactly
 * (see config/auth.php's PERMISSIONS / ROLE_HIERARCHY for the Role enum,
 * and the individual PHP files under api/ for the rest). Each has an UNKNOWN
 * fallback registered in RetrofitFactory's Moshi.Builder via
 * EnumJsonAdapter.withUnknownFallback(), so a server-side enum value this
 * app doesn't yet know about degrades to UNKNOWN instead of crashing the
 * whole screen's JSON parse.
 */

enum class Role {
    @Json(name = "Viewer") VIEWER,
    @Json(name = "Member") MEMBER,
    @Json(name = "Clerk") CLERK,
    @Json(name = "Admin") ADMIN,
    UNKNOWN;

    val level: Int
        get() = when (this) {
            VIEWER -> 1
            MEMBER -> 2
            CLERK -> 3
            ADMIN -> 4
            UNKNOWN -> 0
        }
}

enum class MeetingStatus {
    @Json(name = "Scheduled") SCHEDULED,
    @Json(name = "In Progress") IN_PROGRESS,
    @Json(name = "Completed") COMPLETED,
    @Json(name = "Cancelled") CANCELLED,
    @Json(name = "Postponed") POSTPONED,
    UNKNOWN
}

enum class AttendanceStatus {
    @Json(name = "Present") PRESENT,
    @Json(name = "Absent") ABSENT,
    @Json(name = "Apology") APOLOGY,
    @Json(name = "Excused") EXCUSED,
    @Json(name = "Late") LATE,
    UNKNOWN
}

enum class MembershipRole {
    @Json(name = "Chair") CHAIR,
    @Json(name = "Deputy Chair") DEPUTY_CHAIR,
    @Json(name = "Secretary") SECRETARY,
    @Json(name = "Treasurer") TREASURER,
    @Json(name = "Member") MEMBER,
    @Json(name = "Ex-officio") EX_OFFICIO,
    UNKNOWN
}

enum class MembershipStatus {
    @Json(name = "Active") ACTIVE,
    @Json(name = "Inactive") INACTIVE,
    @Json(name = "Resigned") RESIGNED,
    @Json(name = "Terminated") TERMINATED,
    UNKNOWN
}

enum class AgendaItemType {
    @Json(name = "Discussion") DISCUSSION,
    @Json(name = "Action Item") ACTION_ITEM,
    @Json(name = "Vote") VOTE,
    @Json(name = "Information") INFORMATION,
    @Json(name = "Presentation") PRESENTATION,
    UNKNOWN
}

enum class DecisionMethod {
    @Json(name = "Consensus") CONSENSUS,
    @Json(name = "Formal Majority") FORMAL_MAJORITY,
    @Json(name = "Referral") REFERRAL,
    @Json(name = "None") NONE,
    UNKNOWN
}

enum class ReportType {
    @Json(name = "Written") WRITTEN,
    @Json(name = "Verbal") VERBAL,
    UNKNOWN
}

enum class MinutesStatus {
    @Json(name = "Draft") DRAFT,
    @Json(name = "Review") REVIEW,
    @Json(name = "Approved") APPROVED,
    @Json(name = "Published") PUBLISHED,
    UNKNOWN
}

enum class VoteType {
    @Json(name = "Voices") VOICES,
    @Json(name = "Show of Hands") SHOW_OF_HANDS,
    @Json(name = "Cards") CARDS,
    @Json(name = "Written Ballot") WRITTEN_BALLOT,
    @Json(name = "Formal Procedures") FORMAL_PROCEDURES,
    UNKNOWN
}

enum class ResolutionStatus {
    @Json(name = "Proposed") PROPOSED,
    @Json(name = "Consensus") CONSENSUS,
    @Json(name = "Agreement") AGREEMENT,
    @Json(name = "Failed") FAILED,
    @Json(name = "Withdrawn") WITHDRAWN,
    @Json(name = "Lapsed") LAPSED,
    UNKNOWN;

    /** Formal Majority validation on the server treats these as "final". */
    val isFinal: Boolean
        get() = this == CONSENSUS || this == AGREEMENT || this == FAILED
}

enum class ProposalType {
    @Json(name = "UseOfProcedures") USE_OF_PROCEDURES,
    @Json(name = "OrderOfDay") ORDER_OF_DAY,
    @Json(name = "Adjournment") ADJOURNMENT,
    @Json(name = "PrivateSitting") PRIVATE_SITTING,
    @Json(name = "Referral") REFERRAL,
    @Json(name = "DecisionNow") DECISION_NOW,
    @Json(name = "WithdrawMotion") WITHDRAW_MOTION,
    @Json(name = "PreviousQuestion") PREVIOUS_QUESTION,
    @Json(name = "Closure") CLOSURE,
    @Json(name = "Reconsideration") RECONSIDERATION,
    @Json(name = "PointOfOrder") POINT_OF_ORDER,
    UNKNOWN;

    /** Human-readable label per the UCA Manual for Meetings terms used in meetings.php. */
    val label: String
        get() = when (this) {
            USE_OF_PROCEDURES -> "Use of Procedures"
            ORDER_OF_DAY -> "Order of the Day"
            ADJOURNMENT -> "Adjournment"
            PRIVATE_SITTING -> "Private Sitting"
            REFERRAL -> "Referral"
            DECISION_NOW -> "Determining Need for Decision Now"
            WITHDRAW_MOTION -> "Withdraw Motion"
            PREVIOUS_QUESTION -> "Previous Question"
            CLOSURE -> "Closure"
            RECONSIDERATION -> "Reconsideration"
            POINT_OF_ORDER -> "Point of Order"
            UNKNOWN -> "Unknown"
        }
}

enum class ProposalOutcome {
    @Json(name = "Carried") CARRIED,
    @Json(name = "Lost") LOST,
    @Json(name = "Lapsed") LAPSED,
    @Json(name = "RuledOn") RULED_ON,
    @Json(name = "Pending") PENDING,
    UNKNOWN
}

enum class AgendaPosition {
    @Json(name = "Before") BEFORE,
    @Json(name = "During") DURING,
    @Json(name = "After") AFTER,
    UNKNOWN
}

enum class DocumentType {
    @Json(name = "Agenda") AGENDA,
    @Json(name = "Minutes") MINUTES,
    @Json(name = "Resolution") RESOLUTION,
    @Json(name = "Report") REPORT,
    @Json(name = "Policy") POLICY,
    @Json(name = "Other") OTHER,
    UNKNOWN
}
