package org.togetherincouncil.mobile.data

import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import org.togetherincouncil.mobile.data.remote.LenientBooleanAdapter
import org.togetherincouncil.mobile.data.remote.LenientEnumAdapterFactory
import org.togetherincouncil.mobile.data.remote.dto.*

/**
 * Parses real captured JSON shapes (trimmed but field-accurate copies of what the api/
 * PHP endpoints actually return) to catch @Json(name=...) typos early, without needing a running backend.
 */
class DtoParsingTest {

    private val moshi: Moshi = Moshi.Builder()
        .add(Boolean::class.javaPrimitiveType!!, LenientBooleanAdapter)
        .add(Boolean::class.javaObjectType, LenientBooleanAdapter)
        .add(LenientEnumAdapterFactory)
        .add(KotlinJsonAdapterFactory())
        .build()

    @Test
    fun `whoami response parses user and permissions map`() {
        val json = """
            {
              "user": {"id":12,"username":"jsecretary","email":"j@example.org","role":"Clerk","board_member_id":7},
              "permissions": {"view_dashboard": true, "manage_members": false}
            }
        """.trimIndent()
        val dto = moshi.adapter(WhoAmIDto::class.java).fromJson(json)!!
        assertEquals("jsecretary", dto.user.username)
        assertEquals(Role.CLERK, dto.user.role)
        assertEquals(true, dto.permissions["view_dashboard"])
        assertEquals(false, dto.permissions["manage_members"])
    }

    @Test
    fun `meeting detail parses nested attendees and agenda items`() {
        val json = """
            {
              "id": 5, "meeting_type_id": 1, "title": "August Meeting",
              "scheduled_date": "2026-08-28 18:00:00", "end_time": null,
              "location": "Hall", "virtual_link": null,
              "quorum_required": 4, "quorum_met": true, "status": "Scheduled", "notes": null,
              "attendees": [{"id":1,"meeting_id":5,"member_id":2,"attendance_status":"Present","arrival_time":null,"notes":null,"first_name":"Ann","last_name":"Lee"}],
              "agenda_items": [{"id":10,"meeting_id":5,"title":"Opening","description":null,"item_type":"Discussion","decision_method":"None","duration_minutes":5,"position":0,"sub_position":0,"item_number":"26.8.1","parent_id":null,"is_starred":false,"outcome":null,"resolutions":[],"presenters":[],"departures":[]}]
            }
        """.trimIndent()
        val dto = moshi.adapter(MeetingDetailDto::class.java).fromJson(json)!!
        assertEquals(MeetingStatus.SCHEDULED, dto.status)
        assertTrue(dto.quorumMet)
        assertEquals(1, dto.attendees.size)
        assertEquals(AttendanceStatus.PRESENT, dto.attendees[0].attendanceStatus)
        assertEquals(1, dto.agendaItems.size)
        assertEquals("26.8.1", dto.agendaItems[0].itemNumber)
    }

    @Test
    fun `resolution with warning field parses without error`() {
        val json = """
            {
              "id": 1, "meeting_id": 5, "agenda_item_id": null, "resolution_number": "26.8.1a",
              "title": null, "description": "Approve the budget", "decision_method": "Formal Majority",
              "motion_moved_by": 2, "motion_seconded_by": 3, "votes_for": 5, "votes_against": 1,
              "votes_abstain": 0, "casting_vote_used": false, "referral_body": null, "referral_scope": null,
              "clerk_notes": "proxy vote noted", "vote_type": "Voices", "status": "Agreement",
              "effective_date": null, "position": 0,
              "_warning": "Reminder: proxy and absentee voting are not permitted."
            }
        """.trimIndent()
        val dto = moshi.adapter(ResolutionDto::class.java).fromJson(json)!!
        assertEquals(ResolutionStatus.AGREEMENT, dto.status)
        assertEquals("Reminder: proxy and absentee voting are not permitted.", dto.warning)
    }

    @Test
    fun `boolean DB columns parse from a JSON number, not just true false`() {
        // Real regression: PHP's PDO/json_encode() serializes MySQL BOOLEAN (TINYINT(1))
        // columns as a JSON number, not a boolean literal — this crashed the app on-device
        // with "Expected a boolean but was NUMBER at path $.quorum_met" before
        // LenientBooleanAdapter was registered.
        val json = """
            {
              "id": 5, "meeting_type_id": 1, "title": "August Meeting",
              "scheduled_date": "2026-08-28 18:00:00", "end_time": null,
              "location": "Hall", "virtual_link": null,
              "quorum_required": 4, "quorum_met": 1, "status": "Scheduled", "notes": null,
              "attendees": [], "agenda_items": []
            }
        """.trimIndent()
        val dto = moshi.adapter(MeetingDetailDto::class.java).fromJson(json)!!
        assertTrue(dto.quorumMet)
    }

    @Test
    fun `nullable enum field parses a JSON null instead of throwing`() {
        // Real regression: agenda_items[].report_type is nullable, and the server sends a
        // literal JSON null for items with no report type — this crashed the app on-device
        // with "Expected a string but was NULL at path $.agenda_items[0].report_type" before
        // .nullSafe() was added to the enum adapter registrations.
        val json = """
            {"id":10,"meeting_id":5,"title":"Opening","description":null,"item_type":"Discussion",
             "decision_method":"None","report_type":null,"duration_minutes":5,"position":0,
             "sub_position":0,"item_number":"26.8.1","parent_id":null,"is_starred":false,"outcome":null,
             "resolutions":[],"presenters":[],"departures":[]}
        """.trimIndent()
        val dto = moshi.adapter(AgendaItemDto::class.java).fromJson(json)!!
        assertNull(dto.reportType)
    }

    @Test
    fun `unknown enum value falls back instead of throwing`() {
        val json = """{"error": null, "code": null}"""
        val errorDto = moshi.adapter(ApiErrorDto::class.java).fromJson(json)!!
        assertNull(errorDto.error)

        val statusJson = """"SomeFutureStatus""""
        val status = moshi.adapter(MeetingStatus::class.java).fromJson(statusJson)
        assertEquals(MeetingStatus.UNKNOWN, status)
    }
}
