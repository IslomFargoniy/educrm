package uz.educrm.app.data

import android.content.Context
import android.content.Intent
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import okhttp3.CacheControl
import okhttp3.OkHttpClient
import okhttp3.Request
import uz.educrm.app.BuildConfig
import java.util.concurrent.TimeUnit

/** Contents of /downloads/version.json on the CRM server. */
@Serializable
data class AppVersion(
    val versionCode: Int,
    val versionName: String,
    val apk: String = "educrm.apk",
    val notes: String? = null,
)

/**
 * Update check for the sideloaded APK. The app never downloads or installs packages itself
 * (Play Protect treats that as harmful): it only tells about a new version and opens the
 * download link in the browser, and the user installs the file from there.
 */
class AppUpdater(private val context: Context) {
    private val base = BuildConfig.API_URL.substringBefore("api/v1/") + "downloads/"
    private val json = Json { ignoreUnknownKeys = true }
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    /** The published version when it is newer than this build, otherwise null (also on any error). */
    suspend fun newerVersion(): AppVersion? = withContext(Dispatchers.IO) {
        runCatching {
            val request = Request.Builder().url(base + "version.json").cacheControl(CacheControl.FORCE_NETWORK).build()
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@use null
                json.decodeFromString<AppVersion>(response.body!!.string())
            }
        }.getOrNull()?.takeIf { it.versionCode > BuildConfig.VERSION_CODE && APK_NAME.matches(it.apk) }
    }

    /** Opens the APK link in the browser. */
    fun openDownload(version: AppVersion) {
        context.startActivity(
            Intent(Intent.ACTION_VIEW, Uri.parse(base + version.apk)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        )
    }

    private companion object {
        /** Only a plain file name next to version.json is accepted. */
        val APK_NAME = Regex("^[A-Za-z0-9._-]+\\.apk$")
    }
}
