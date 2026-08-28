package org.togetherincouncil.mobile.permissions

import org.togetherincouncil.mobile.data.remote.dto.Role

/**
 * Local mirror of config/auth.php's ROLE_HIERARCHY + PERMISSIONS, used only
 * as a fallback for servers that haven't deployed api/whoami.php yet (see
 * OnboardingViewModel — onboarding still works without it via a manual role
 * picker, just with weaker guarantees). Whenever whoami.php answers
 * successfully, SessionManager uses ITS server-provided permissions map
 * instead of this table, since the server is always authoritative and this
 * copy can drift if config/auth.php changes without a matching edit here.
 *
 * Every mutating repository call handles a 403 gracefully regardless of
 * what either table says — this object only drives UI-level gating
 * (hide/disable a button), never the final word on whether an action
 * succeeds.
 */
object PermissionEvaluator {

    private val rolesByPermission: Map<Permission, Set<Role>> = mapOf(
        Permission.VIEW_DASHBOARD to allRoles(),
        Permission.VIEW_MEETINGS to allRoles(),
        Permission.VIEW_MEMBERS to allRoles(),
        Permission.VIEW_DOCUMENTS to allRoles(),
        Permission.VIEW_RESOLUTIONS to allRoles(),
        Permission.CREATE_MEETING to clerkAndAdmin(),
        Permission.EDIT_MEETING to clerkAndAdmin(),
        Permission.MANAGE_AGENDA to clerkAndAdmin(),
        Permission.MANAGE_ATTENDEES to clerkAndAdmin(),
        Permission.MANAGE_MINUTES to clerkAndAdmin(),
        Permission.UPLOAD_DOCUMENTS to clerkAndAdmin(),
        Permission.CREATE_RESOLUTION to clerkAndAdmin(),
        Permission.EDIT_RESOLUTION to clerkAndAdmin(),
        Permission.MANAGE_PROCEDURAL_PROPOSALS to clerkAndAdmin(),
        Permission.EDIT_OWN_ATTENDANCE to setOf(Role.MEMBER, Role.CLERK, Role.ADMIN),
        Permission.MANAGE_MEMBERS to setOf(Role.ADMIN),
        Permission.MANAGE_MEETING_TYPES to setOf(Role.ADMIN),
        Permission.MANAGE_USERS to setOf(Role.ADMIN),
        Permission.DELETE_MEETING to setOf(Role.ADMIN),
        Permission.DELETE_MEMBER to setOf(Role.ADMIN),
        Permission.DELETE_RESOLUTION to setOf(Role.ADMIN),
        Permission.DELETE_DOCUMENT to setOf(Role.ADMIN)
    )

    fun canByRole(role: Role, permission: Permission): Boolean =
        role in (rolesByPermission[permission] ?: emptySet())

    private fun allRoles() = setOf(Role.VIEWER, Role.MEMBER, Role.CLERK, Role.ADMIN)
    private fun clerkAndAdmin() = setOf(Role.CLERK, Role.ADMIN)
}
