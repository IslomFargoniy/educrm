package uz.educrm.app.data

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
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query
import uz.educrm.app.BuildConfig
import java.util.Locale
import java.util.concurrent.TimeUnit

interface EduCrmApi {
    @POST("login") suspend fun login(@Body body: LoginRequest): LoginResponse
    @GET("me") suspend fun me(): MeResponse
    @POST("logout") suspend fun logout(): OkResponse
    @GET("lessons") suspend fun lessons(@Query("date") date: String): LessonsResponse
    @GET("lessons/{id}") suspend fun lesson(@Path("id") id: Int): LessonResponse
    @GET("groups/{id}") suspend fun group(@Path("id") id: Int): GroupResponse
    @retrofit2.http.Multipart @POST("students/{id}/face") suspend fun uploadFace(@Path("id") studentId: Int, @retrofit2.http.Part photo: okhttp3.MultipartBody.Part): OkResponse
    @POST("lessons/{id}/attendance") suspend fun saveAttendance(@Path("id") id: Int, @Body body: AttendanceRequest): OkResponse
    @GET("students") suspend fun students(@Query("q") query: String, @Query("page") page: Int = 1): StudentsResponse
    @GET("students/{id}") suspend fun student(@Path("id") id: Int): StudentResponse
    @GET("cashboxes") suspend fun cashboxes(): CashboxesResponse
    @POST("enrollments/{id}/payments") suspend fun pay(@Path("id") enrollmentId: Int, @Body body: PaymentRequest): PaymentResponse
    @GET("me/balance") suspend fun myBalance(): BalanceResponse
    @GET("me/balance/summary") suspend fun myBalanceSummary(): BalanceSummary
    @GET("me/history") suspend fun myHistory(@Query("kind") kind: String, @Query("page") page: Int): HistoryPage
    @GET("me/income-students") suspend fun myIncomeStudents(@Query("page") page: Int): IncomePage
    @GET("me/pending") suspend fun myPending(@Query("page") page: Int): PendingPage
    @GET("me/salary") suspend fun mySalary(@Query("month") month: String): SalaryResponse
    @GET("me/students") suspend fun myStudents(): MyStudentsResponse
    @GET("form-options") suspend fun formOptions(): FormOptions
    @PUT("me/locale") suspend fun updateLocale(@Body body: LocaleRequest): OkResponse
    @POST("students") suspend fun createStudent(@Body body: NewStudentRequest): NewStudentResponse
    @POST("leads") suspend fun createLead(@Body body: NewLeadRequest): NewLeadResponse

    companion object {
        val json = Json {
            ignoreUnknownKeys = true
            coerceInputValues = true
            explicitNulls = false
        }

        fun create(tokenProvider: () -> String?): EduCrmApi {
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
                .create(EduCrmApi::class.java)
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
            val obj = EduCrmApi.json.parseToJsonElement(body).jsonObject
            val errors = obj["errors"]?.jsonObject
            val first = errors?.values?.firstOrNull()?.let { (it as? kotlinx.serialization.json.JsonArray)?.firstOrNull()?.jsonPrimitive?.content }
            return first ?: obj["message"]?.jsonPrimitive?.content ?: fallback
        }
    }

    return fallback
}
