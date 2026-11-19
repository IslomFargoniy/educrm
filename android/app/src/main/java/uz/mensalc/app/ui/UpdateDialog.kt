package uz.mensalc.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import uz.mensalc.app.R
import uz.mensalc.app.data.AppUpdater
import uz.mensalc.app.data.AppVersion

/**
 * Checks for a newer APK once per launch and offers to download and install it.
 */
@Composable
fun UpdatePrompt() {
    val context = LocalContext.current
    val updater = remember { AppUpdater(context.applicationContext) }
    var version by remember { mutableStateOf<AppVersion?>(null) }
    var dismissed by remember { mutableStateOf(false) }
    var progress by remember { mutableStateOf<Float?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var needsPermission by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val failed = stringResource(R.string.update_failed)

    LaunchedEffect(Unit) { version = updater.newerVersion() }

    val available = version ?: return
    if (dismissed) return

    AlertDialog(
        onDismissRequest = { if (progress == null) dismissed = true },
        title = { Text(stringResource(R.string.update_title, available.versionName), fontWeight = FontWeight.ExtraBold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(available.notes?.takeIf { it.isNotBlank() } ?: stringResource(R.string.update_text))
                if (needsPermission) Text(stringResource(R.string.update_permission), color = MaterialTheme.colorScheme.onSurfaceVariant)
                progress?.let {
                    LinearProgressIndicator(progress = { it }, modifier = Modifier.fillMaxWidth())
                    Text(stringResource(R.string.update_downloading, (it * 100).toInt()), style = MaterialTheme.typography.bodySmall)
                }
                error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            }
        },
        confirmButton = {
            Button(
                enabled = progress == null,
                onClick = {
                    error = null
                    if (!updater.canInstall()) {
                        needsPermission = true
                        updater.openInstallPermissionSettings()
                        return@Button
                    }
                    needsPermission = false
                    progress = 0f
                    scope.launch {
                        runCatching { updater.download(available) { progress = it } }
                            .onSuccess { updater.install(it) }
                            .onFailure { error = failed }
                        progress = null
                    }
                },
            ) { Text(stringResource(R.string.update_now)) }
        },
        dismissButton = {
            if (progress == null) TextButton({ dismissed = true }) { Text(stringResource(R.string.update_later)) }
        },
    )
}
