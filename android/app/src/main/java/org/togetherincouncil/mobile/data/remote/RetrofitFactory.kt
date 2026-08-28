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
 */
object RetrofitFactory {

    fun buildMoshi(): Moshi = Moshi.Builder()
        .add(Role::class.java, EnumJsonAdapter.create(Role::class.java).withUnknownFallback(Role.UNKNOWN))
        .add(MeetingStatus::class.java, EnumJsonAdapter.create(MeetingStatus::class.java).withUnknownFallback(MeetingStatus.UNKNOWN))
        .add(AttendanceStatus::class.java, EnumJsonAdapter.create(AttendanceStatus::class.java).withUnknownFallback(AttendanceStatus.UNKNOWN))
        .add(MembershipRole::class.java, EnumJsonAdapter.create(MembershipRole::class.java).withUnknownFallback(MembershipRole.UNKNOWN))
        .add(MembershipStatus::class.java, EnumJsonAdapter.create(MembershipStatus::class.java).withUnknownFallback(MembershipStatus.UNKNOWN))
        .add(AgendaItemType::class.java, EnumJsonAdapter.create(AgendaItemType::class.java).withUnknownFallback(AgendaItemType.UNKNOWN))
        .add(DecisionMethod::class.java, EnumJsonAdapter.create(DecisionMethod::class.java).withUnknownFallback(DecisionMethod.UNKNOWN))
        .add(ReportType::class.java, EnumJsonAdapter.create(ReportType::class.java).withUnknownFallback(ReportType.UNKNOWN))
        .add(MinutesStatus::class.java, EnumJsonAdapter.create(MinutesStatus::class.java).withUnknownFallback(MinutesStatus.UNKNOWN))
        .add(VoteType::class.java, EnumJsonAdapter.create(VoteType::class.java).withUnknownFallback(VoteType.UNKNOWN))
        .add(ResolutionStatus::class.java, EnumJsonAdapter.create(ResolutionStatus::class.java).withUnknownFallback(ResolutionStatus.UNKNOWN))
        .add(ProposalType::class.java, EnumJsonAdapter.create(ProposalType::class.java).withUnknownFallback(ProposalType.UNKNOWN))
        .add(ProposalOutcome::class.java, EnumJsonAdapter.create(ProposalOutcome::class.java).withUnknownFallback(ProposalOutcome.UNKNOWN))
        .add(AgendaPosition::class.java, EnumJsonAdapter.create(AgendaPosition::class.java).withUnknownFallback(AgendaPosition.UNKNOWN))
        .add(DocumentType::class.java, EnumJsonAdapter.create(DocumentType::class.java).withUnknownFallback(DocumentType.UNKNOWN))
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
