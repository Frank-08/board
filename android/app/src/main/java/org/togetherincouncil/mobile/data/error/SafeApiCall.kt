package org.togetherincouncil.mobile.data.error

import com.squareup.moshi.Moshi
import kotlinx.coroutines.CancellationException
import okhttp3.ResponseBody
import org.togetherincouncil.mobile.data.remote.AuthEventBus
import org.togetherincouncil.mobile.data.remote.dto.ApiErrorDto
import retrofit2.HttpException
import java.io.IOException

/**
 * Every repository method routes its Retrofit call through this, so
 * ViewModels only ever see Result<T> / the sealed ApiException hierarchy,
 * never raw Retrofit/OkHttp exceptions. Centralizes 401 handling
 * (AuthEventBus) in one place so no call site can forget it.
 */
class SafeApiCall(private val moshi: Moshi) {

    suspend fun <T> execute(block: suspend () -> T): Result<T> = try {
        Result.success(block())
    } catch (e: CancellationException) {
        throw e
    } catch (e: HttpException) {
        Result.failure(mapHttpException(e))
    } catch (e: IOException) {
        Result.failure(ApiException.Network(e))
    }

    private suspend fun mapHttpException(e: HttpException): ApiException {
        val message = parseErrorMessage(e.response()?.errorBody())
        return when (e.code()) {
            401 -> {
                AuthEventBus.emitUnauthorized()
                ApiException.AuthRequired
            }
            403 -> ApiException.Forbidden(message)
            404 -> ApiException.NotFound(message)
            409 -> ApiException.Conflict(message)
            400 -> ApiException.Validation(message)
            else -> ApiException.Unknown(e.code(), message)
        }
    }

    private fun parseErrorMessage(body: ResponseBody?): String {
        val raw = body?.string()
        if (raw.isNullOrBlank()) return "Unexpected server error"
        return runCatching { moshi.adapter(ApiErrorDto::class.java).fromJson(raw) }
            .getOrNull()
            ?.error
            ?: "Unexpected server error"
    }
}
