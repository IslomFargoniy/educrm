package uz.educrm.app.data

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.content.FileProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import okhttp3.CacheControl
import okhttp3.OkHttpClient
import okhttp3.Request
import uz.educrm.app.BuildConfig
import java.io.File
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
 * Self-update for the sideloaded APK: reads the published version, downloads the
 * new APK from the same server and hands it to the system installer (which only
 * accepts it when it is signed with the same key).
 */
class AppUpdater(private val context: Context) {
    private val base = BuildConfig.API_URL.substringBefore("api/v1/") + "downloads/"
    private val json = Json { ignoreUnknownKeys = true }
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
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

    /** Downloads the APK into the app cache, reporting progress from 0 to 1. */
    suspend fun download(version: AppVersion, onProgress: (Float) -> Unit): File = withContext(Dispatchers.IO) {
        val dir = File(context.cacheDir, "updates").apply { mkdirs() }
        dir.listFiles()?.forEach { it.delete() }
        val target = File(dir, "eduCrm-${version.versionCode}.apk")
        val request = Request.Builder().url(base + version.apk).cacheControl(CacheControl.FORCE_NETWORK).build()

        client.newCall(request).execute().use { response ->
            check(response.isSuccessful) { "HTTP ${response.code}" }
            val body = response.body!!
            val total = body.contentLength().takeIf { it > 0 }
            body.byteStream().use { input ->
                target.outputStream().use { output ->
                    val buffer = ByteArray(64 * 1024)
                    var done = 0L
                    while (true) {
                        val read = input.read(buffer)
                        if (read < 0) break
                        output.write(buffer, 0, read)
                        done += read
                        total?.let { onProgress((done.toFloat() / it).coerceIn(0f, 1f)) }
                    }
                }
            }
        }

        target
    }

    /** Android 8+ asks the user once to allow installing apps from this app. */
    fun canInstall(): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.O || context.packageManager.canRequestPackageInstalls()

    fun openInstallPermissionSettings() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startActivity(
                Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:${context.packageName}"))
                    .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )
        }
    }

    /** Fallback when the in-app download fails: open the APK link in the browser. */
    fun openDownload(version: AppVersion) {
        context.startActivity(
            Intent(Intent.ACTION_VIEW, Uri.parse(base + version.apk)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        )
    }

    fun install(apk: File) {
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.updates", apk)
        context.startActivity(
            Intent(Intent.ACTION_VIEW)
                .setDataAndType(uri, "application/vnd.android.package-archive")
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK),
        )
    }

    private companion object {
        /** Only a plain file name next to version.json is accepted. */
        val APK_NAME = Regex("^[A-Za-z0-9._-]+\\.apk$")
    }
}
