package org.togetherincouncil.mobile.data.error

sealed class ApiException(message: String) : Exception(message) {
    data object AuthRequired : ApiException("Your session is no longer valid. Please re-enter your API key.")
    data class Forbidden(val serverMessage: String) : ApiException(serverMessage)
    /** 409 — duplicate resource, or a write rejected because minutes for this meeting are Approved. */
    data class Conflict(val serverMessage: String) : ApiException(serverMessage)
    /** 400 — server-side validation failure (e.g. resolution_helpers.php's validateResolutionData()). */
    data class Validation(val serverMessage: String) : ApiException(serverMessage)
    data class NotFound(val serverMessage: String) : ApiException(serverMessage)
    data class Unknown(val httpCode: Int, val serverMessage: String) : ApiException(serverMessage)
    data class Network(override val cause: Throwable) : ApiException("Couldn't reach the server. Check your connection and try again.")
}
