package org.togetherincouncil.mobile.navigation

object Destinations {
    const val ONBOARDING = "onboarding"
    const val DASHBOARD = "dashboard"
    const val MEETING_LIST = "meetings"
    const val MEETING_DETAIL = "meetings/{meetingId}"
    const val MORE = "more"

    fun meetingDetail(meetingId: Int) = "meetings/$meetingId"

    const val ARG_MEETING_ID = "meetingId"
}
