package uz.educrm.app.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/** Brand colors shared with the web CRM (resources/css/app.css). */
val Navy = Color(0xFF0B0F7E)
val Orange = Color(0xFFF7941D)
val OnOrange = Color(0xFF1F1300)

/** Light / dark / system, same as the switch in the web header. */
enum class ThemeMode { Light, Dark, System }

/** Colors that Material 3 has no slot for. */
data class EduCrmColors(
    val success: Color,
    val danger: Color,
    val info: Color,
    val border: Color,
    val bar: Color,
    val barForeground: Color,
    val barMuted: Color,
    val barAccent: Color,
    val hero: Color,
)

private val LightExtra = EduCrmColors(
    success = Color(0xFF0B7A4B),
    danger = Color(0xFFC2410C),
    info = Color(0xFF0369A1),
    border = Color(0xFFE3E5F0),
    bar = Navy,
    barForeground = Color(0xFFD2D4FA),
    barMuted = Color(0xFF9DA2EC),
    barAccent = Color(0xFF1C2196),
    hero = Navy,
)

private val DarkExtra = EduCrmColors(
    success = Color(0xFF4ADE9A),
    danger = Color(0xFFFDA06B),
    info = Color(0xFF7DD3FC),
    border = Color(0xFF272D63),
    bar = Color(0xFF06081F),
    barForeground = Color(0xFFD2D4FA),
    barMuted = Color(0xFF7F85C4),
    barAccent = Color(0xFF141A45),
    hero = Color(0xFF2B32C4),
)

private val Light = lightColorScheme(
    primary = Navy,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFEEEFFC),
    onPrimaryContainer = Color(0xFF2A2FA6),
    secondary = Orange,
    onSecondary = OnOrange,
    secondaryContainer = Color(0xFFFFF1E0),
    onSecondaryContainer = OnOrange,
    background = Color(0xFFF4F5FA),
    onBackground = Color(0xFF0E1236),
    surface = Color.White,
    onSurface = Color(0xFF0E1236),
    surfaceVariant = Color(0xFFEEF0F7),
    onSurfaceVariant = Color(0xFF5A5F7E),
    surfaceContainerHighest = Color(0xFFEEF0F7),
    outline = Color(0xFFCDD0E2),
    outlineVariant = Color(0xFFE3E5F0),
    error = Color(0xFFC2410C),
)

private val Dark = darkColorScheme(
    primary = Color(0xFF7C83FF),
    onPrimary = Color(0xFF070A2A),
    primaryContainer = Color(0xFF1C2252),
    onPrimaryContainer = Color(0xFFECEEFF),
    secondary = Orange,
    onSecondary = OnOrange,
    secondaryContainer = Color(0xFF3A2A12),
    onSecondaryContainer = Color(0xFFFFE2BF),
    background = Color(0xFF0C0F2E),
    onBackground = Color(0xFFECEEFF),
    surface = Color(0xFF151A42),
    onSurface = Color(0xFFECEEFF),
    surfaceVariant = Color(0xFF1C2252),
    onSurfaceVariant = Color(0xFFA9AED6),
    surfaceContainerHighest = Color(0xFF1C2252),
    surfaceContainerHigh = Color(0xFF1A1F4C),
    outline = Color(0xFF3A4180),
    outlineVariant = Color(0xFF272D63),
    error = Color(0xFFFDA06B),
)

private val LocalEduCrmColors = staticCompositionLocalOf { LightExtra }

/** Extra EduCrm colors for the current theme. */
val eduCrm: EduCrmColors
    @Composable @ReadOnlyComposable get() = LocalEduCrmColors.current

private val EduCrmShapes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(10.dp),
    medium = RoundedCornerShape(14.dp),
    large = RoundedCornerShape(18.dp),
    extraLarge = RoundedCornerShape(24.dp),
)

@Composable
fun isDark(mode: ThemeMode): Boolean = when (mode) {
    ThemeMode.Light -> false
    ThemeMode.Dark -> true
    ThemeMode.System -> isSystemInDarkTheme()
}

@Composable
fun EduCrmTheme(mode: ThemeMode = ThemeMode.System, content: @Composable () -> Unit) {
    val dark = isDark(mode)
    androidx.compose.runtime.CompositionLocalProvider(LocalEduCrmColors provides if (dark) DarkExtra else LightExtra) {
        val scheme = if (dark) Dark else Light
        MaterialTheme(colorScheme = scheme, shapes = EduCrmShapes) {
            androidx.compose.runtime.CompositionLocalProvider(androidx.compose.material3.LocalContentColor provides scheme.onBackground, content = content)
        }
    }
}
