package org.togetherincouncil.mobile.data.remote

import com.squareup.moshi.Moshi
import com.squareup.moshi.adapters.EnumJsonAdapter
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import org.togetherincouncil.mobile.BuildConfig
import org.togetherincouncil.mobile.data.auth.ApiKeyStore
import org.togetherincouncil.mobile.data.remote.dto.*
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Builds the shared OkHttp/Retrofit instance every *Api interface is
 * created from. Each enum gets an UNKNOWN fallback registered so a
 * server-side enum value this build doesn't recognize yet (e.g. a new
 * ProposalType added to the DB later) degrades that one field to UNKNOWN
 * instead of throwing and blanking the whole screen's JSON parse.
 *
 * Every enum adapter also gets .nullSafe() explicitly: several enum DTO
 * fields are nullable (report_type, vote_type, ...) and the plain Moshi
 * EnumJsonAdapter throws on a JSON null rather than returning null —
 * confirmed by an on-device crash ("Expected a string but was NULL at
 * path $.agenda_items[0].report_type"). Wrapping here guarantees null
 * handling regardless of whether generated-adapter null-wrapping applies
 * to a manually-registered adapter the same way it does a reflective one.
 */
object RetrofitFactory {

    fun buildMoshi(): Moshi = Moshi.Builder()
        // Covers both non-null Boolean (maps to boolean.class) and nullable Boolean? (maps to
        // java.lang.Boolean.class) DTO fields — see LenientBooleanAdapter for why this is needed.
        .add(Boolean::class.javaPrimitiveType!!, LenientBooleanAdapter)
        .add(Boolean::class.javaObjectType, LenientBooleanAdapter)
        .add(Role::class.java, EnumJsonAdapter.create(Role::class.java).withUnknownFallback(Role.UNKNOWN).nullSafe())
        .add(MeetingStatus::class.java, EnumJsonAdapter.create(MeetingStatus::class.java).withUnknownFallback(MeetingStatus.UNKNOWN).nullSafe())
        .add(AttendanceStatus::class.java, EnumJsonAdapter.create(AttendanceStatus::class.java).withUnknownFallback(AttendanceStatus.UNKNOWN).nullSafe())
        .add(MembershipRole::class.java, EnumJsonAdapter.create(MembershipRole::class.java).withUnknownFallback(MembershipRole.UNKNOWN).nullSafe())
        .add(MembershipStatus::class.java, EnumJsonAdapter.create(MembershipStatus::class.java).withUnknownFallback(MembershipStatus.UNKNOWN).nullSafe())
        .add(AgendaItemType::class.java, EnumJsonAdapter.create(AgendaItemType::class.java).withUnknownFallback(AgendaItemType.UNKNOWN).nullSafe())
        .add(DecisionMethod::class.java, EnumJsonAdapter.create(DecisionMethod::class.java).withUnknownFallback(DecisionMethod.UNKNOWN).nullSafe())
        .add(ReportType::class.java, EnumJsonAdapter.create(ReportType::class.java).withUnknownFallback(ReportType.UNKNOWN).nullSafe())
        .add(MinutesStatus::class.java, EnumJsonAdapter.create(MinutesStatus::class.java).withUnknownFallback(MinutesStatus.UNKNOWN).nullSafe())
        .add(VoteType::class.java, EnumJsonAdapter.create(VoteType::class.java).withUnknownFallback(VoteType.UNKNOWN).nullSafe())
        .add(ResolutionStatus::class.java, EnumJsonAdapter.create(ResolutionStatus::class.java).withUnknownFallback(ResolutionStatus.UNKNOWN).nullSafe())
        .add(ProposalType::class.java, EnumJsonAdapter.create(ProposalType::class.java).withUnknownFallback(ProposalType.UNKNOWN).nullSafe())
        .add(ProposalOutcome::class.java, EnumJsonAdapter.create(ProposalOutcome::class.java).withUnknownFallback(ProposalOutcome.UNKNOWN).nullSafe())
        .add(AgendaPosition::class.java, EnumJsonAdapter.create(AgendaPosition::class.java).withUnknownFallback(AgendaPosition.UNKNOWN).nullSafe())
        .add(DocumentType::class.java, EnumJsonAdapter.create(DocumentType::class.java).withUnknownFallback(DocumentType.UNKNOWN).nullSafe())
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
