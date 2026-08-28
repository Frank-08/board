package org.togetherincouncil.mobile.di

import android.content.Context
import org.togetherincouncil.mobile.data.auth.ApiKeyStore
import org.togetherincouncil.mobile.data.auth.SessionManager
import org.togetherincouncil.mobile.data.error.SafeApiCall
import org.togetherincouncil.mobile.data.remote.RetrofitFactory
import org.togetherincouncil.mobile.data.remote.api.*
import org.togetherincouncil.mobile.data.repository.*

/**
 * Hand-written service locator instead of Hilt/Dagger — the object graph
 * here is small (one OkHttp client, ~10 Retrofit interfaces, ~10
 * repositories, two singletons) and doesn't justify annotation-processing
 * overhead for a single-module app. Instantiated once in
 * TicApplication.onCreate() and threaded through ViewModel factories.
 */
class AppContainer(context: Context) {

    val apiKeyStore = ApiKeyStore(context.applicationContext)
    val sessionManager = SessionManager()

    private val moshi = RetrofitFactory.buildMoshi()
    private val okHttpClient = RetrofitFactory.buildOkHttpClient(apiKeyStore)
    private val retrofit = RetrofitFactory.buildRetrofit(okHttpClient, moshi)
    private val safeApiCall = SafeApiCall(moshi)

    val whoAmIRepository = WhoAmIRepository(retrofit.create(WhoAmIApi::class.java), safeApiCall)
    val dashboardRepository = DashboardRepository(retrofit.create(DashboardApi::class.java), safeApiCall)
    val meetingTypeRepository = MeetingTypeRepository(retrofit.create(MeetingTypesApi::class.java), safeApiCall)
    val memberRepository = MemberRepository(retrofit.create(MembersApi::class.java), safeApiCall)
    val meetingRepository = MeetingRepository(retrofit.create(MeetingsApi::class.java), safeApiCall)
    val agendaRepository = AgendaRepository(retrofit.create(AgendaApi::class.java), safeApiCall)
    val attendeeRepository = AttendeeRepository(retrofit.create(AttendeesApi::class.java), safeApiCall)
    val minutesRepository = MinutesRepository(
        retrofit.create(MinutesApi::class.java),
        retrofit.create(MinutesCommentsApi::class.java),
        safeApiCall
    )
    val resolutionRepository = ResolutionRepository(retrofit.create(ResolutionsApi::class.java), safeApiCall)
    val proceduralProposalRepository =
        ProceduralProposalRepository(retrofit.create(ProceduralProposalsApi::class.java), safeApiCall)
    val departureRepository = DepartureRepository(retrofit.create(DeparturesApi::class.java), safeApiCall)
    val documentRepository = DocumentRepository(retrofit.create(DocumentsApi::class.java), safeApiCall)
}
