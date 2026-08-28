package org.togetherincouncil.mobile.data.auth

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import org.togetherincouncil.mobile.data.remote.dto.Role
import org.togetherincouncil.mobile.data.remote.dto.WhoAmIUserDto
import org.togetherincouncil.mobile.permissions.Permission
import org.togetherincouncil.mobile.permissions.PermissionEvaluator

sealed interface AuthState {
    data object Unauthenticated : AuthState
    data class Authenticated(
        val user: WhoAmIUserDto,
        /** Server-provided permission map from api/whoami.php, keyed by Permission.key. Null if
         * that endpoint wasn't available and onboarding fell back to a manual role pick. */
        val serverPermissions: Map<String, Boolean>?
    ) : AuthState
}

/**
 * Single source of truth, app-wide, for "who is logged in and what can they
 * do." Populated at the end of a successful onboarding flow, cleared by
 * SafeApiCall whenever a 401/AUTH_REQUIRED comes back from any endpoint.
 */
class SessionManager {

    private val _authState = MutableStateFlow<AuthState>(AuthState.Unauthenticated)
    val authState: StateFlow<AuthState> = _authState

    fun setAuthenticated(user: WhoAmIUserDto, serverPermissions: Map<String, Boolean>?) {
        _authState.update { AuthState.Authenticated(user, serverPermissions) }
    }

    fun clear() {
        _authState.update { AuthState.Unauthenticated }
    }

    fun can(permission: Permission): Boolean {
        val state = _authState.value as? AuthState.Authenticated ?: return false
        state.serverPermissions?.get(permission.key)?.let { return it }
        return PermissionEvaluator.canByRole(state.user.role, permission)
    }

    val currentBoardMemberId: Int?
        get() = (_authState.value as? AuthState.Authenticated)?.user?.boardMemberId

    val currentRole: Role?
        get() = (_authState.value as? AuthState.Authenticated)?.user?.role
}
