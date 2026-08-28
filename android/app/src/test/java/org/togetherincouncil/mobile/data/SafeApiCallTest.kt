package org.togetherincouncil.mobile.data

import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.togetherincouncil.mobile.data.error.ApiException
import org.togetherincouncil.mobile.data.error.SafeApiCall
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import retrofit2.http.GET

private interface FakeApi {
    @GET("thing.php")
    suspend fun getThing(): Map<String, String>
}

class SafeApiCallTest {

    private lateinit var server: MockWebServer
    private lateinit var api: FakeApi
    private lateinit var safeApiCall: SafeApiCall

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
        val moshi = Moshi.Builder().add(KotlinJsonAdapterFactory()).build()
        val retrofit = Retrofit.Builder()
            .baseUrl(server.url("/"))
            .addConverterFactory(MoshiConverterFactory.create(moshi))
            .build()
        api = retrofit.create(FakeApi::class.java)
        safeApiCall = SafeApiCall(moshi)
    }

    @After
    fun tearDown() {
        server.shutdown()
    }

    @Test
    fun `401 maps to AuthRequired`() = runTest {
        server.enqueue(MockResponse().setResponseCode(401).setBody("""{"error":"Authentication required","code":"AUTH_REQUIRED"}"""))
        val result = safeApiCall.execute { api.getThing() }
        assertTrue(result.exceptionOrNull() is ApiException.AuthRequired)
    }

    @Test
    fun `403 maps to Forbidden with server message`() = runTest {
        server.enqueue(MockResponse().setResponseCode(403).setBody("""{"error":"Insufficient permissions for this action","code":"FORBIDDEN"}"""))
        val result = safeApiCall.execute { api.getThing() }
        val error = result.exceptionOrNull() as ApiException.Forbidden
        assertEquals("Insufficient permissions for this action", error.serverMessage)
    }

    @Test
    fun `409 maps to Conflict`() = runTest {
        server.enqueue(MockResponse().setResponseCode(409).setBody("""{"error":"Minutes for this meeting are approved and locked."}"""))
        val result = safeApiCall.execute { api.getThing() }
        assertTrue(result.exceptionOrNull() is ApiException.Conflict)
    }

    @Test
    fun `400 maps to Validation`() = runTest {
        server.enqueue(MockResponse().setResponseCode(400).setBody("""{"error":"Formal Majority resolutions require a mover and seconder."}"""))
        val result = safeApiCall.execute { api.getThing() }
        assertTrue(result.exceptionOrNull() is ApiException.Validation)
    }

    @Test
    fun `404 maps to NotFound`() = runTest {
        server.enqueue(MockResponse().setResponseCode(404).setBody("""{"error":"Not found"}"""))
        val result = safeApiCall.execute { api.getThing() }
        assertTrue(result.exceptionOrNull() is ApiException.NotFound)
    }

    @Test
    fun `success passes through`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"a":"b"}"""))
        val result = safeApiCall.execute { api.getThing() }
        assertEquals(mapOf("a" to "b"), result.getOrNull())
    }
}
