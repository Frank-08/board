package org.togetherincouncil.mobile.permissions

/**
 * Mirrors the PERMISSIONS array keys in config/auth.php exactly. Keep this
 * enum's `key` values in sync with that file — api/whoami.php's response
 * map is keyed by these same strings, generated server-side by iterating
 * array_keys(PERMISSIONS), so any new server-side permission automatically
 * shows up under a new key here needs a matching addition on this side.
 */
enum class Permission(val key: String) {
    VIEW_DASHBOARD("view_dashboard"),
    VIEW_MEETINGS("view_meetings"),
    VIEW_MEMBERS("view_members"),
    VIEW_DOCUMENTS("view_documents"),
    VIEW_RESOLUTIONS("view_resolutions"),
    CREATE_MEETING("create_meeting"),
    EDIT_MEETING("edit_meeting"),
    MANAGE_AGENDA("manage_agenda"),
    MANAGE_ATTENDEES("manage_attendees"),
    MANAGE_MINUTES("manage_minutes"),
    UPLOAD_DOCUMENTS("upload_documents"),
    CREATE_RESOLUTION("create_resolution"),
    EDIT_RESOLUTION("edit_resolution"),
    MANAGE_PROCEDURAL_PROPOSALS("manage_procedural_proposals"),
    EDIT_OWN_ATTENDANCE("edit_own_attendance"),
    MANAGE_MEMBERS("manage_members"),
    MANAGE_MEETING_TYPES("manage_meeting_types"),
    MANAGE_USERS("manage_users"),
    DELETE_MEETING("delete_meeting"),
    DELETE_MEMBER("delete_member"),
    DELETE_RESOLUTION("delete_resolution"),
    DELETE_DOCUMENT("delete_document")
}
