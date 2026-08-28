package org.togetherincouncil.mobile.data.remote

import okhttp3.Interceptor
import okhttp3.Response
import org.togetherincouncil.mobile.data.auth.ApiKeyStore

/** Adds the X-API-Key header to every outgoing request, mirroring the pattern the
 * existing VBA Word-macro client uses (word macro/modBoardAPI.bas) against this
 * same backend — see config/auth.php's currentApiKeyUser().
 *
 * Also sends X-Minutes-Script: the production deployment sits behind Cloudflare,
 * which blocks non-browser HTTP clients (curl, OkHttp, WinHTTP) by default —
 * confirmed by hand: a plain request to api/whoami.php returns Cloudflare's own
 * "Sorry, you have been blocked" page, while the same request carrying this header
 * reaches the PHP backend correctly. This header value is already allowlisted in
 * Cloudflare's WAF/Bot Fight Mode config for scripted access to api/*.php; it isn't
 * a secret (X-API-Key remains the actual auth boundary) — it just identifies this
 * traffic as a known non-browser client so Cloudflare doesn't challenge/block it. */
class AuthInterceptor(private val apiKeyStore: ApiKeyStore) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val key = apiKeyStore.getKey()
        val builder = request.newBuilder().addHeader("X-Minutes-Script", "board-minutes-sync/1.0")
        if (key != null) {
            builder.addHeader("X-API-Key", key)
        }
        return chain.proceed(builder.build())
    }
}
