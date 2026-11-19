package uz.educrm.app.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.PhoneAndroid
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import uz.educrm.app.R
import uz.educrm.app.data.History

/** Loading / error / content wrapper. */
sealed interface Load<out T> {
    data object Loading : Load<Nothing>
    data class Failed(val message: String) : Load<Nothing>
    data class Ready<T>(val value: T) : Load<T>
}

@Composable
fun <T> LoadBox(state: Load<T>, onRetry: () -> Unit, content: @Composable (T) -> Unit) {
    when (state) {
        is Load.Loading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
        is Load.Failed -> Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
            Text(state.message, color = MaterialTheme.colorScheme.error)
            Button(onClick = onRetry, modifier = Modifier.padding(top = 12.dp)) { Text(stringResource(R.string.retry)) }
        }
        is Load.Ready -> content(state.value)
    }
}

/**
 * Navy page header (same color as the web sidebar). Draws behind the status bar.
 */
@Composable
fun ScreenHeader(
    title: String,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
    bottom: @Composable ColumnScope.() -> Unit = {},
) {
    Column(
        Modifier.fillMaxWidth()
            .background(eduCrm.bar)
            .statusBarsPadding()
            .padding(start = if (onBack == null) 20.dp else 4.dp, end = 8.dp, top = 8.dp, bottom = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (onBack != null) {
                IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null, tint = Color.White) }
            }
            Column(Modifier.weight(1f).padding(vertical = 6.dp)) {
                Text(title, color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
                subtitle?.let { Text(it, color = eduCrm.barMuted, style = MaterialTheme.typography.bodyMedium, maxLines = 1) }
            }
            actions()
        }
        bottom()
    }
}

/** White (dark: navy) card with a thin border, like the web cards. */
@Composable
fun EduCrmCard(modifier: Modifier = Modifier, onClick: (() -> Unit)? = null, content: @Composable ColumnScope.() -> Unit) {
    val colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    val border = BorderStroke(1.dp, eduCrm.border)
    val shape = MaterialTheme.shapes.large
    if (onClick != null) {
        Card(onClick = onClick, modifier = modifier.fillMaxWidth(), shape = shape, colors = colors, border = border, content = content)
    } else {
        Card(modifier = modifier.fillMaxWidth(), shape = shape, colors = colors, border = border, content = content)
    }
}

/** Small rounded label, e.g. lesson status or "trial". */
@Composable
fun Pill(text: String, color: Color) {
    Text(
        text,
        color = color,
        fontSize = 12.sp,
        fontWeight = FontWeight.Bold,
        modifier = Modifier.clip(CircleShape).background(color.copy(alpha = 0.12f)).padding(horizontal = 10.dp, vertical = 3.dp),
    )
}

/** Initials in a colored circle. */
@Composable
fun Initials(name: String, size: Int = 44, background: Color = MaterialTheme.colorScheme.primaryContainer, color: Color = MaterialTheme.colorScheme.onPrimaryContainer) {
    val letters = name.split(' ').filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }
    Box(Modifier.size(size.dp).clip(CircleShape).background(background), contentAlignment = Alignment.Center) {
        Text(letters, color = color, fontWeight = FontWeight.ExtraBold, fontSize = (size / 2.8).sp)
    }
}

/** Light / dark / system segmented switch. */
@Composable
fun ThemeSwitcher(mode: ThemeMode, onChange: (ThemeMode) -> Unit, modifier: Modifier = Modifier) {
    val options: List<Triple<ThemeMode, ImageVector, Int>> = listOf(
        Triple(ThemeMode.Light, Icons.Filled.LightMode, R.string.theme_light),
        Triple(ThemeMode.Dark, Icons.Filled.DarkMode, R.string.theme_dark),
        Triple(ThemeMode.System, Icons.Filled.PhoneAndroid, R.string.theme_system),
    )
    Row(
        modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.surfaceVariant).border(1.dp, eduCrm.border, RoundedCornerShape(12.dp)).padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        options.forEach { (value, icon, label) ->
            val selected = value == mode
            Row(
                Modifier.weight(1f)
                    .clip(RoundedCornerShape(9.dp))
                    .background(if (selected) MaterialTheme.colorScheme.surface else Color.Transparent)
                    .clickable { onChange(value) }
                    .padding(vertical = 10.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                val tint = if (selected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
                Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
                Text(stringResource(label), color = tint, fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium, fontSize = 13.sp, modifier = Modifier.padding(start = 6.dp))
            }
        }
    }
}

@Composable
fun MoneyText(value: Long, signed: Boolean = false, bold: Boolean = true) {
    val color = when {
        signed && value < 0 -> eduCrm.danger
        signed && value > 0 -> eduCrm.success
        else -> MaterialTheme.colorScheme.onSurface
    }
    Text(money(value), color = color, fontWeight = if (bold) FontWeight.Bold else FontWeight.Normal)
}

@Composable
fun SectionTitle(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.ExtraBold, modifier = Modifier.padding(top = 4.dp))
}

@Composable
fun HistoryList(history: History, signed: Boolean) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        SectionTitle(stringResource(R.string.history))
        EduCrmCard {
            history.rows.forEachIndexed { index, row ->
                if (index > 0) HorizontalDivider(Modifier.padding(horizontal = 14.dp), color = eduCrm.border)
                Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(txLabel(row.type), fontWeight = FontWeight.SemiBold)
                        Text("${date(row.date)} · ${describe(row.type, row.metaObject)}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        when {
                            row.income > 0 -> Text("+" + money(row.income), color = eduCrm.success, fontWeight = FontWeight.Bold)
                            row.expense > 0 -> Text("−" + money(row.expense), color = eduCrm.danger, fontWeight = FontWeight.Bold)
                            else -> Text("—")
                        }
                        Text(money(row.balance), style = MaterialTheme.typography.bodySmall, color = if (signed && row.balance < 0) eduCrm.danger else MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            if (history.rows.isEmpty()) Text("—", modifier = Modifier.padding(14.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

/** Screen content padding that also clears the floating tab bar. */
@Composable
fun contentPadding(): androidx.compose.foundation.layout.PaddingValues =
    androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 16.dp + LocalBottomBarInset.current)
