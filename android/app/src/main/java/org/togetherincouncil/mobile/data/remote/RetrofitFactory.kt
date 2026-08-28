package org.togetherincouncil.mobile.data.remote

import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import org.togetherincouncil.mobile.BuildConfig
import org.togetherincouncil.mobile.data.auth.ApiKeyStore
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Builds the shared OkHttp/Retrofit instance every *Api interface is
 * created from. LenientEnumAdapterFactory handles every enum DTO field
 * uniformly — null tokens (nullable fields like report_type/vote_type) and
 * unrecognized values (fall back to each enum's UNKNOWN constant) — see
 * that file for why a plain per-type EnumJsonAdapter registration wasn't
 * reliable here.
 */
object RetrofitFactory {

    fun buildMoshi(): Moshi = Moshi.Builder()
        // Covers both non-null Boolean (maps to boolean.class) and nullable Boolean? (maps to
        // java.lang.Boolean.class) DTO fields — see LenientBooleanAdapter for why this is needed.
        .add(Boolean::class.javaPrimitiveType!!, LenientBooleanAdapter)
        .add(Boolean::class.javaObjectType, LenientBooleanAdapter)
        .add(LenientEnumAdapterFactory)
        .add(KotlinJsonAdapterFactory())
        .build()

    fun buildOkHttpClient(apiKeyStore: ApiKeyStore): OkHttpClient {
        val logging = HttpLoggingInterceptor().apply {
            // Never BODY-log in release: the X-API-Key header/values must not reach logcat.
            level = if (BuildConfig.DEBUG) HttpLoggingInterceptor.Level.BODY else HttpLoggingInterceptor.Level.NONE
        }
        return OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(apiKeyStore))
            .addInterceptor(logging)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .build()
    }

    fun buildRetrofit(client: OkHttpClient, moshi: Moshi): Retrofit = Retrofit.Builder()
        .baseUrl(ApiConfig.BASE_URL)
        .client(client)
        .addConverterFactory(MoshiConverterFactory.create(moshi))
        .build()
}
