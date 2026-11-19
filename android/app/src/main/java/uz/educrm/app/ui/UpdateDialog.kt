package uz.educrm.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import uz.educrm.app.R
import uz.educrm.app.data.AppUpdater
import uz.educrm.app.data.AppVersion

/**
 * Checks for a newer APK once per launch and offers to download it in the browser.
 */
@Composable
fun UpdatePrompt() {
    val context = LocalContext.current
    val updater = remember { AppUpdater(context.applicationContext) }
    var version by remember { mutableStateOf<AppVersion?>(null) }
    var dismissed by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val failed = stringResource(R.string.update_failed)

    LaunchedEffect(Unit) { version = updater.newerVersion() }

    val available = version ?: return
    if (dismissed) return

    AlertDialog(
        onDismissRequest = { dismissed = true },
        title = { Text(stringResource(R.string.update_title, available.versionName), fontWeight = FontWeight.ExtraBold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(available.notes?.takeIf { it.isNotBlank() } ?: stringResource(R.string.update_text))
                Text(stringResource(R.string.update_how), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    runCatching { updater.openDownload(available) }
                        .onSuccess { dismissed = true }
                        .onFailure { error = failed }
                },
            ) { Text(stringResource(R.string.update_now)) }
        },
        dismissButton = { TextButton({ dismissed = true }) { Text(stringResource(R.string.update_later)) } },
    )
}
