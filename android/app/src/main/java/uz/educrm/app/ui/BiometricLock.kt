package uz.educrm.app.ui

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Backspace
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SwitchColors
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import uz.educrm.app.R
import uz.educrm.app.data.Biometric
import uz.educrm.app.data.PinCode
import uz.educrm.app.data.findFragmentActivity

private const val MaxPinTries = 5
private val KeySize = 74.dp
private val LockError = Color(0xFFFDA06B)

/**
 * Shown on app start when fingerprint login is on. The fingerprint / face prompt opens by itself;
 * the PIN chosen with it is the other way in (5 wrong tries sign out). Without a working sensor only
 * the PIN opens it; without a PIN either, the user signs in with the password.
 */
@Composable
fun BiometricLockScreen(pinHash: String?, onUnlocked: () -> Unit, onUsePassword: () -> Unit) {
    val context = LocalContext.current
    val sensorReady = remember { Biometric.unavailableReason(context) == null && context.findFragmentActivity() != null }
    var error by remember { mutableStateOf<String?>(null) }
    var pin by remember { mutableStateOf("") }
    var wrongTries by remember { mutableIntStateOf(0) }
    var errorKey by remember { mutableIntStateOf(0) }
    val scope = rememberCoroutineScope()

    val title = stringResource(R.string.app_name)
    val subtitle = stringResource(R.string.biometric_unlock_subtitle)
    val negative = stringResource(if (pinHash != null) R.string.pin_code else R.string.cancel)

    fun prompt() {
        val activity = context.findFragmentActivity() ?: return
        error = null
        Biometric.authenticate(
            activity = activity,
            title = title,
            subtitle = subtitle,
            negativeText = negative,
            onSuccess = onUnlocked,
            onFailure = { message -> if (message != null) error = message },
        )
    }

    LaunchedEffect(Unit) {
        when {
            sensorReady -> prompt()
            pinHash == null -> onUsePassword()
        }
    }

    val wrongPinText = stringResource(R.string.pin_wrong, MaxPinTries - wrongTries - 1)

    Column(
        Modifier.fillMaxSize().background(eduCrm.bar).systemBarsPadding().padding(vertical = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.weight(1f))
        PinHeader(
            title = stringResource(R.string.lock_title),
            message = error ?: stringResource(if (pinHash != null) R.string.lock_enter_pin else R.string.lock_confirm_biometric),
            isError = error != null,
        )
        Spacer(Modifier.height(28.dp))
        if (pinHash != null) {
            PinDots(pin.length, errorKey)
            Spacer(Modifier.weight(1f))
            PinPad(
                value = pin,
                onValueChange = { pin = it; error = null },
                onComplete = { value ->
                    scope.launch {
                        delay(120)
                        if (PinCode.matches(value, pinHash)) {
                            onUnlocked()
                        } else {
                            pin = ""
                            errorKey++
                            if (wrongTries + 1 >= MaxPinTries) {
                                onUsePassword()
                            } else {
                                error = wrongPinText
                                wrongTries++
                            }
                        }
                    }
                },
                onBiometric = if (sensorReady) ({ prompt() }) else null,
            )
        } else {
            Spacer(Modifier.weight(1f))
            Button(
                onClick = { prompt() },
                shape = MaterialTheme.shapes.medium,
                colors = ButtonDefaults.buttonColors(containerColor = Orange, contentColor = OnOrange),
                modifier = Modifier.padding(horizontal = 32.dp).fillMaxWidth().height(50.dp),
            ) {
                Icon(Icons.Outlined.Fingerprint, null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text(stringResource(R.string.lock_unlock), fontWeight = FontWeight.Bold)
            }
        }
        Spacer(Modifier.height(12.dp))
        TextButton(onClick = onUsePassword) {
            Text(stringResource(R.string.lock_use_password), color = Color.White, fontWeight = FontWeight.SemiBold)
        }
    }
}

/**
 * Full-screen PIN choice: 4 digits, then the same again. Required whenever fingerprint login is turned on.
 */
@Composable
fun PinSetupDialog(onDismiss: () -> Unit, onSet: (String) -> Unit) {
    var first by remember { mutableStateOf<String?>(null) }
    var value by remember { mutableStateOf("") }
    var mismatch by remember { mutableStateOf(false) }
    var errorKey by remember { mutableIntStateOf(0) }
    val scope = rememberCoroutineScope()

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, dismissOnClickOutside = false)) {
        Column(
            Modifier.fillMaxSize().background(eduCrm.bar).systemBarsPadding().padding(vertical = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Row(Modifier.fillMaxWidth().padding(horizontal = 8.dp)) {
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel), color = Color.White) }
            }
            Spacer(Modifier.weight(1f))
            PinHeader(
                title = stringResource(if (first == null) R.string.pin_create else R.string.pin_repeat),
                message = stringResource(
                    when {
                        mismatch -> R.string.pin_mismatch
                        first == null -> R.string.pin_create_hint
                        else -> R.string.pin_repeat_hint
                    },
                ),
                isError = mismatch,
            )
            Spacer(Modifier.height(28.dp))
            PinDots(value.length, errorKey)
            Spacer(Modifier.weight(1f))
            PinPad(value, { value = it; mismatch = false }, onComplete = { pin ->
                scope.launch {
                    delay(120)
                    val chosen = first
                    when {
                        chosen == null -> { first = pin; value = "" }
                        chosen == pin -> onSet(pin)
                        else -> { first = null; value = ""; mismatch = true; errorKey++ }
                    }
                }
            })
            Spacer(Modifier.height(16.dp))
        }
    }
}

/** Logo, title and a line of help (highlighted when it is an error) above the dots. */
@Composable
private fun PinHeader(title: String, message: String, isError: Boolean) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        LogoBadge(64)
        Spacer(Modifier.height(18.dp))
        Text(title, color = Color.White, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.ExtraBold, textAlign = TextAlign.Center)
        Spacer(Modifier.height(6.dp))
        Text(
            message,
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = if (isError) LockError else eduCrm.barForeground,
            modifier = Modifier.padding(horizontal = 24.dp),
        )
    }
}

/** 4 dots filling up as digits are typed; shakes when [errorKey] changes. */
@Composable
private fun PinDots(length: Int, errorKey: Int) {
    val shake = remember { Animatable(0f) }
    LaunchedEffect(errorKey) {
        if (errorKey == 0) {
            return@LaunchedEffect
        }
        for (x in listOf(18f, -16f, 12f, -8f, 4f, 0f)) {
            shake.animateTo(x, tween(45))
        }
    }
    Row(horizontalArrangement = Arrangement.spacedBy(20.dp), modifier = Modifier.offset { IntOffset(shake.value.dp.roundToPx(), 0) }) {
        repeat(PinCode.LENGTH) { index ->
            val filled = index < length
            Box(
                Modifier.size(18.dp).clip(CircleShape)
                    .background(if (filled) Orange else Color.Transparent)
                    .border(2.dp, if (filled) Orange else eduCrm.barMuted, CircleShape),
            )
        }
    }
}

/** Number pad: 1–9, then fingerprint (or empty), 0 and delete. Calls [onComplete] once 4 digits are typed. */
@Composable
private fun PinPad(value: String, onValueChange: (String) -> Unit, onComplete: (String) -> Unit, onBiometric: (() -> Unit)? = null) {
    val haptic = LocalHapticFeedback.current
    fun press(digit: Char) {
        if (value.length >= PinCode.LENGTH) {
            return
        }
        haptic.performHapticFeedback(HapticFeedbackType.TextHandleMove)
        val next = value + digit
        onValueChange(next)
        if (next.length == PinCode.LENGTH) {
            onComplete(next)
        }
    }
    val rows = listOf(listOf('1', '2', '3'), listOf('4', '5', '6'), listOf('7', '8', '9'))
    Column(verticalArrangement = Arrangement.spacedBy(14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        rows.forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(26.dp)) {
                row.forEach { digit -> DigitKey(digit.toString()) { press(digit) } }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(26.dp)) {
            if (onBiometric != null) {
                IconKey(Icons.Outlined.Fingerprint, stringResource(R.string.biometric_short), onBiometric)
            } else {
                Spacer(Modifier.size(KeySize))
            }
            DigitKey("0") { press('0') }
            IconKey(Icons.AutoMirrored.Outlined.Backspace, stringResource(R.string.pin_delete)) {
                if (value.isNotEmpty()) {
                    onValueChange(value.dropLast(1))
                }
            }
        }
    }
}

@Composable
private fun DigitKey(label: String, onClick: () -> Unit) {
    Box(Modifier.size(KeySize).clip(CircleShape).background(eduCrm.barAccent).clickable(onClick = onClick), contentAlignment = Alignment.Center) {
        Text(label, color = Color.White, fontSize = 28.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun IconKey(icon: ImageVector, description: String, onClick: () -> Unit) {
    Box(Modifier.size(KeySize).clip(CircleShape).clickable(onClick = onClick), contentAlignment = Alignment.Center) {
        Icon(icon, description, tint = Color.White, modifier = Modifier.size(28.dp))
    }
}

/** Switch whose "off" state is visible on a white card too (the Material default nearly disappears there). */
@Composable
fun eduCrmSwitchColors(): SwitchColors = SwitchDefaults.colors(
    checkedThumbColor = MaterialTheme.colorScheme.onPrimary,
    checkedTrackColor = MaterialTheme.colorScheme.primary,
    checkedBorderColor = MaterialTheme.colorScheme.primary,
    uncheckedThumbColor = MaterialTheme.colorScheme.onSurfaceVariant,
    uncheckedTrackColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f),
    uncheckedBorderColor = MaterialTheme.colorScheme.onSurfaceVariant,
)
