package uz.mensalc.app.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val Navy = Color(0xFF0B0F7E)
val Orange = Color(0xFFF7941D)
val Success = Color(0xFF12805C)
val Danger = Color(0xFFD92D20)

private val Light = lightColorScheme(
    primary = Navy,
    onPrimary = Color.White,
    secondary = Orange,
    onSecondary = Color(0xFF1F1300),
    secondaryContainer = Color(0xFFFFF1E0),
    primaryContainer = Color(0xFFEEF0FB),
    onPrimaryContainer = Navy,
    background = Color.White,
    surface = Color.White,
    surfaceVariant = Color(0xFFF4F5FA),
    error = Danger,
)

private val Dark = darkColorScheme(
    primary = Color(0xFF7C83FF),
    onPrimary = Color(0xFF070A2A),
    secondary = Orange,
    onSecondary = Color(0xFF1F1300),
    primaryContainer = Color(0xFF1A1F4F),
    onPrimaryContainer = Color(0xFFECEEFF),
    background = Color(0xFF090C2C),
    surface = Color(0xFF0F1338),
    surfaceVariant = Color(0xFF151A45),
    error = Color(0xFFF97066),
)

@Composable
fun MensaTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = if (isSystemInDarkTheme()) Dark else Light, content = content)
}
