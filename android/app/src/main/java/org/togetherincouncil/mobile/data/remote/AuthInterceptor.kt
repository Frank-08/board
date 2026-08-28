package org.togetherincouncil.mobile.data.remote

import okhttp3.Interceptor
import okhttp3.Response
import org.togetherincouncil.mobile.data.auth.ApiKeyStore

/** Adds the X-API-Key header to every outgoing request, mirroring the pattern the
 * existing VBA Word-macro client uses (word macro/modBoardAPI.bas) against this
 * same backend — see config/auth.php's currentApiKeyUser(). */
class AuthInterceptor(private val apiKeyStore: ApiKeyStore) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val key = apiKeyStore.getKey()
        val newRequest = if (key != null) {
            request.newBuilder().addHeader("X-API-Key", key).build()
        } else {
            request
        }
        return chain.proceed(newRequest)
    }
}
