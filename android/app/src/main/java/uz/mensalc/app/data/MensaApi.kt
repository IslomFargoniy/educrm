package uz.mensalc.app.data

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import uz.mensalc.app.BuildConfig
import java.util.Locale
import java.util.concurrent.TimeUnit

interface MensaApi {
    @POST("login") suspend fun login(@Body body: LoginRequest): LoginResponse
    @GET("me") suspend fun me(): MeResponse
    @POST("logout") suspend fun logout(): OkResponse
    @GET("lessons") suspend fun lessons(@Query("date") date: String): LessonsResponse
    @GET("lessons/{id}") suspend fun lesson(@Path("id") id: Int): LessonResponse
    @POST("lessons/{id}/attendance") suspend fun saveAttendance(@Path("id") id: Int, @Body body: AttendanceRequest): OkResponse
    @GET("students") suspend fun students(@Query("q") query: String): StudentsResponse
    @GET("students/{id}") suspend fun student(@Path("id") id: Int): StudentResponse
    @GET("cashboxes") suspend fun cashboxes(): CashboxesResponse
    @POST("enrollments/{id}/payments") suspend fun pay(@Path("id") enrollmentId: Int, @Body body: PaymentRequest): PaymentResponse
    @GET("me/balance") suspend fun myBalance(): BalanceResponse

    companion object {
        val json = Json {
            ignoreUnknownKeys = true
            coerceInputValues = true
            explicitNulls = false
        }

        fun create(tokenProvider: () -> String?): MensaApi {
            val client = OkHttpClient.Builder()
                .connectTimeout(15, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .addInterceptor { chain ->
                    val request = chain.request().newBuilder()
                        .header("Accept", "application/json")
                        .header("Accept-Language", Locale.getDefault().language)
                    tokenProvider()?.let { request.header("Authorization", "Bearer $it") }
                    chain.proceed(request.build())
                }
                .build()

            return Retrofit.Builder()
                .baseUrl(BuildConfig.API_URL)
                .client(client)
                .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
                .build()
                .create(MensaApi::class.java)
        }
    }
}

/**
 * Human readable error: first validation message from Laravel, or a generic network text.
 */
fun Throwable.readableMessage(fallback: String): String {
    if (this is HttpException) {
        val body = response()?.errorBody()?.string().orEmpty()
        runCatching {
            val obj = MensaApi.json.parseToJsonElement(body).jsonObject
            val errors = obj["errors"]?.jsonObject
            val first = errors?.values?.firstOrNull()?.let { (it as? kotlinx.serialization.json.JsonArray)?.firstOrNull()?.jsonPrimitive?.content }
            return first ?: obj["message"]?.jsonPrimitive?.content ?: fallback
        }
    }

    return fallback
}
