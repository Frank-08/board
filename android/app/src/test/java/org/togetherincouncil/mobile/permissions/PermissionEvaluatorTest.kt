package org.togetherincouncil.mobile.permissions

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import org.togetherincouncil.mobile.data.remote.dto.Role

/**
 * Spot-checks PermissionEvaluator's local table against config/auth.php's PERMISSIONS array
 * (transcribed there by hand) to catch drift if one side is edited without the other. This
 * table is only the fallback used when api/whoami.php isn't available — real gating always
 * prefers the server-provided permissions map when present (see SessionManager.can()).
 */
class PermissionEvaluatorTest {

    @Test
    fun `viewer can view but not manage`() {
        assertTrue(PermissionEvaluator.canByRole(Role.VIEWER, Permission.VIEW_MEETINGS))
        assertFalse(PermissionEvaluator.canByRole(Role.VIEWER, Permission.MANAGE_AGENDA))
        assertFalse(PermissionEvaluator.canByRole(Role.VIEWER, Permission.EDIT_OWN_ATTENDANCE))
    }

    @Test
    fun `member can edit own attendance but not manage agenda`() {
        assertTrue(PermissionEvaluator.canByRole(Role.MEMBER, Permission.EDIT_OWN_ATTENDANCE))
        assertFalse(PermissionEvaluator.canByRole(Role.MEMBER, Permission.MANAGE_AGENDA))
    }

    @Test
    fun `clerk can manage meeting workflow but not admin actions`() {
        assertTrue(PermissionEvaluator.canByRole(Role.CLERK, Permission.MANAGE_AGENDA))
        assertTrue(PermissionEvaluator.canByRole(Role.CLERK, Permission.CREATE_RESOLUTION))
        assertTrue(PermissionEvaluator.canByRole(Role.CLERK, Permission.MANAGE_PROCEDURAL_PROPOSALS))
        assertFalse(PermissionEvaluator.canByRole(Role.CLERK, Permission.MANAGE_MEMBERS))
        assertFalse(PermissionEvaluator.canByRole(Role.CLERK, Permission.DELETE_MEETING))
    }

    @Test
    fun `admin can do everything`() {
        Permission.entries.forEach { permission ->
            assertTrue("Admin should have $permission", PermissionEvaluator.canByRole(Role.ADMIN, permission))
        }
    }
}
