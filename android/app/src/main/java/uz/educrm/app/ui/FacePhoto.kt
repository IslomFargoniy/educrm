package uz.educrm.app.ui

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.ByteArrayOutputStream
import java.io.File

private val imageClient by lazy { OkHttpClient() }

/** Round photo from a (signed) URL; shows [fallback] while loading or when there is none. */
@Composable
fun RemotePhoto(url: String?, size: Int, fallback: @Composable () -> Unit) {
    val bitmap by produceState<Bitmap?>(null, url) {
        value = url?.let { link ->
            withContext(Dispatchers.IO) {
                runCatching {
                    imageClient.newCall(Request.Builder().url(link).build()).execute().use { response ->
                        if (response.isSuccessful) response.body?.bytes()?.let { BitmapFactory.decodeByteArray(it, 0, it.size) } else null
                    }
                }.getOrNull()
            }
        }
    }
    val image = bitmap
    if (image == null) {
        fallback()
    } else {
        Image(image.asImageBitmap(), contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(size.dp).clip(CircleShape))
    }
}

/** A file in the app cache the camera writes the photo into, shared through the FileProvider. */
fun newCameraUri(context: Context): Uri {
    val dir = File(context.cacheDir, "faces").apply { mkdirs() }
    val file = File(dir, "face-${System.currentTimeMillis()}.jpg")
    return FileProvider.getUriForFile(context, "${context.packageName}.updates", file)
}

/** Read the picked or taken photo, shrink it to at most 1280 px and wrap it for the upload. */
suspend fun facePart(context: Context, uri: Uri): MultipartBody.Part? = withContext(Dispatchers.IO) {
    runCatching {
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
        var sample = 1
        while (maxOf(bounds.outWidth, bounds.outHeight) / (sample * 2) >= 1280) sample *= 2
        val bitmap = context.contentResolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = sample })
        } ?: return@runCatching null
        val rotated = rotateByExif(context, uri, bitmap)
        val bytes = ByteArrayOutputStream().also { rotated.compress(Bitmap.CompressFormat.JPEG, 90, it) }.toByteArray()
        MultipartBody.Part.createFormData("photo", "face.jpg", bytes.toRequestBody("image/jpeg".toMediaType()))
    }.getOrNull()
}

private fun rotateByExif(context: Context, uri: Uri, bitmap: Bitmap): Bitmap {
    val degrees = runCatching {
        context.contentResolver.openInputStream(uri)?.use {
            when (android.media.ExifInterface(it).getAttributeInt(android.media.ExifInterface.TAG_ORIENTATION, 1)) {
                android.media.ExifInterface.ORIENTATION_ROTATE_90 -> 90f
                android.media.ExifInterface.ORIENTATION_ROTATE_180 -> 180f
                android.media.ExifInterface.ORIENTATION_ROTATE_270 -> 270f
                else -> 0f
            }
        } ?: 0f
    }.getOrDefault(0f)
    if (degrees == 0f) return bitmap
    val matrix = android.graphics.Matrix().apply { postRotate(degrees) }
    return Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
}
