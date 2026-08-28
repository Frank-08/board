package org.togetherincouncil.mobile.data.remote

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * Fired centrally by SafeApiCall whenever any endpoint returns
 * 401/AUTH_REQUIRED. NavGraph collects this to force navigation back to
 * Onboarding, so no individual screen has to remember to check for it.
 */
object AuthEventBus {
    private val _unauthorized = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val unauthorized: SharedFlow<Unit> = _unauthorized.asSharedFlow()

    suspend fun emitUnauthorized() {
        _unauthorized.emit(Unit)
    }
}
