package org.togetherincouncil.mobile.ui.onboarding

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import org.togetherincouncil.mobile.data.auth.ApiKeyStore
import org.togetherincouncil.mobile.data.auth.SessionManager
import org.togetherincouncil.mobile.data.error.ApiException
import org.togetherincouncil.mobile.data.remote.dto.Role
import org.togetherincouncil.mobile.data.remote.dto.WhoAmIUserDto
import org.togetherincouncil.mobile.data.repository.DashboardRepository
import org.togetherincouncil.mobile.data.repository.WhoAmIRepository

sealed interface OnboardingUiState {
    data object EnteringKey : OnboardingUiState
    data object Validating : OnboardingUiState
    data class Error(val message: String) : OnboardingUiState
    /** Fallback path when GET api/whoami.php isn't deployed on this server (404) — the key still
     * validated against api/dashboard.php (readable by every role), so ask the user which role
     * they are instead of failing onboarding outright. */
    data class NeedsManualRole(val apiKey: String) : OnboardingUiState
    data object Done : OnboardingUiState
}

class OnboardingViewModel(
    private val whoAmIRepository: WhoAmIRepository,
    private val dashboardRepository: DashboardRepository,
    private val apiKeyStore: ApiKeyStore,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _uiState = MutableStateFlow<OnboardingUiState>(OnboardingUiState.EnteringKey)
    val uiState: StateFlow<OnboardingUiState> = _uiState

    fun submitApiKey(rawKey: String) {
        val trimmed = rawKey.trim()
        if (trimmed.isEmpty()) {
            _uiState.value = OnboardingUiState.Error("Paste your API key first.")
            return
        }
        _uiState.value = OnboardingUiState.Validating
        viewModelScope.launch {
            // Store provisionally so the interceptor picks it up for this validation call;
            // rolled back on any failure below.
            apiKeyStore.saveKey(trimmed)

            whoAmIRepository.whoAmI().fold(
                onSuccess = { dto ->
                    sessionManager.setAuthenticated(dto.user, dto.permissions)
                    _uiState.value = OnboardingUiState.Done
                },
                onFailure = { error ->
                    when (error) {
                        is ApiException.NotFound -> attemptManualRoleFallback(trimmed)
                        is ApiException.AuthRequired -> {
                            apiKeyStore.clear()
                            _uiState.value = OnboardingUiState.Error(
                                "That API key was rejected — check it was copied correctly, or ask an admin to check it hasn't been revoked."
                            )
                        }
                        else -> {
                            apiKeyStore.clear()
                            _uiState.value = OnboardingUiState.Error(error.message ?: "Something went wrong. Try again.")
                        }
                    }
                }
            )
        }
    }

    private suspend fun attemptManualRoleFallback(apiKey: String) {
        dashboardRepository.getDashboard(null).fold(
            onSuccess = { _uiState.value = OnboardingUiState.NeedsManualRole(apiKey) },
            onFailure = {
                apiKeyStore.clear()
                _uiState.value = OnboardingUiState.Error(
                    "That API key was rejected — check it was copied correctly, or ask an admin to check it hasn't been revoked."
                )
            }
        )
    }

    fun confirmManualRole(username: String, role: Role) {
        val boardMemberId: Int? = null
        sessionManager.setAuthenticated(
            WhoAmIUserDto(id = -1, username = username, email = null, role = role, boardMemberId = boardMemberId),
            serverPermissions = null
        )
        _uiState.value = OnboardingUiState.Done
    }

    fun dismissError() {
        _uiState.value = OnboardingUiState.EnteringKey
    }
}
