package uz.educrm.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material.icons.filled.SystemUpdate
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.foundation.clickable
import kotlinx.coroutines.launch
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.ui.Alignment
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import uz.educrm.app.R
import uz.educrm.app.data.BalanceResponse
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.PendingItem
import uz.educrm.app.data.UserProfile
import uz.educrm.app.data.readableMessage

@Composable
fun BalanceScreen(api: EduCrmApi) {
    var state by remember { mutableStateOf<Load<BalanceResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalance() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.my_balance))
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Card(shape = MaterialTheme.shapes.large, colors = CardDefaults.cardColors(containerColor = eduCrm.hero), modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(20.dp)) {
                        Text(stringResource(R.string.can_take), color = Color.White.copy(alpha = 0.8f), fontWeight = FontWeight.SemiBold)
                        Text(money(data.balance) + " " + stringResource(R.string.sum), color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.ExtraBold)
                    }
                }
                HistoryList(data.history, signed = false)
            }
        }
    }
}

/**
 * Lessons taught whose students have not paid yet (the teacher's share waits for them), by student.
 */
@Composable
fun DebtsScreen(api: EduCrmApi) {
    var state by remember { mutableStateOf<Load<BalanceResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalance() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.debts))
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                EduCrmCard {
                    Column(Modifier.padding(16.dp)) {
                        Text(stringResource(R.string.waiting_payment), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(money(-data.pending.total) + " " + stringResource(R.string.sum), fontWeight = FontWeight.ExtraBold, fontSize = 22.sp, color = if (data.pending.total > 0) eduCrm.danger else MaterialTheme.colorScheme.onSurface)
                        if (data.pending.items.isEmpty()) {
                            Text("—", modifier = Modifier.padding(top = 8.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        data.pending.items.groupBy { it.student to it.group }.entries.forEachIndexed { index, (key, lessons) ->
                            HorizontalDivider(Modifier.padding(vertical = 8.dp), color = eduCrm.border)
                            PendingStudentRow(index + 1, key.first, key.second, lessons)
                        }
                    }
                }
            }
        }
    }
}

/**
 * One student in the waiting-for-payment list: lessons are summed, a tap shows each lesson.
 */
@Composable
private fun PendingStudentRow(number: Int, student: String, group: String, lessons: List<PendingItem>) {
    var expanded by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxWidth().clickable { expanded = !expanded }) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            RowNumber(number)
            Column(Modifier.weight(1f)) {
                Text(student, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                Text("$group · ${stringResource(R.string.lessons_count, lessons.size)}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Text(money(-lessons.sumOf { it.amount }), fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyMedium, color = eduCrm.danger)
            Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(start = 4.dp).size(20.dp))
        }
        if (expanded) {
            lessons.sortedBy { it.lesson_date }.forEach {
                Row(Modifier.fillMaxWidth().padding(start = 26.dp, end = 24.dp, top = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(date(it.lesson_date), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
                    Text(money(-it.amount), style = MaterialTheme.typography.bodySmall, color = eduCrm.danger)
                }
            }
        }
    }
}

@Composable
fun ProfileScreen(user: UserProfile, themeMode: ThemeMode, onThemeChange: (ThemeMode) -> Unit, language: String, onLanguageChange: (String) -> Unit, onLogout: () -> Unit) {
    val uri = LocalUriHandler.current

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.profile))
        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            EduCrmCard {
                Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Initials(user.name, size = 56, background = Orange, color = OnOrange)
                    Column(Modifier.padding(start = 14.dp)) {
                        Text(user.name, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.ExtraBold)
                        Text(formatPhone(user.phone), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                HorizontalDivider(color = eduCrm.border)
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    InfoRow(stringResource(R.string.role), user.roles.joinToString { roleLabel(it) })
                    if (user.branches.isNotEmpty()) InfoRow(stringResource(R.string.branches), user.branches.joinToString { it.name })
                }
            }
            SectionTitle(stringResource(R.string.theme))
            ThemeSwitcher(themeMode, onThemeChange)
            SectionTitle(stringResource(R.string.language))
            LanguageSwitcher(language, onLanguageChange)
            Spacer(Modifier.height(4.dp))
            OutlinedButton({ uri.openUri("https://crm.mensalc.uz") }, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth().height(50.dp)) {
                Icon(Icons.AutoMirrored.Filled.OpenInNew, null, modifier = Modifier.size(18.dp))
                Text(stringResource(R.string.open_web), modifier = Modifier.padding(start = 8.dp), fontWeight = FontWeight.SemiBold)
            }
            CheckUpdateButton()
            Text(
                stringResource(R.string.app_version, uz.educrm.app.BuildConfig.VERSION_NAME),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.fillMaxWidth(),
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            )
            Button(
                onLogout,
                shape = MaterialTheme.shapes.medium,
                colors = ButtonDefaults.buttonColors(containerColor = eduCrm.danger.copy(alpha = 0.12f), contentColor = eduCrm.danger),
                modifier = Modifier.fillMaxWidth().height(50.dp),
            ) {
                Icon(Icons.AutoMirrored.Filled.Logout, null, modifier = Modifier.size(18.dp))
                Text(stringResource(R.string.logout), modifier = Modifier.padding(start = 8.dp), fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth()) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
        Text(value, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * Manual update check: opens the new APK link in the browser, or says the app is up to date.
 */
@Composable
private fun CheckUpdateButton() {
    val context = androidx.compose.ui.platform.LocalContext.current
    val updater = remember { uz.educrm.app.data.AppUpdater(context.applicationContext) }
    val scope = androidx.compose.runtime.rememberCoroutineScope()
    var checking by remember { mutableStateOf(false) }
    var note by remember { mutableStateOf<String?>(null) }
    val upToDate = stringResource(R.string.update_latest)

    var found by remember { mutableStateOf<uz.educrm.app.data.AppVersion?>(null) }

    OutlinedButton(
        {
            checking = true
            note = null
            scope.launch {
                found = updater.newerVersion()
                if (found == null) note = upToDate
                checking = false
            }
        },
        enabled = !checking,
        shape = MaterialTheme.shapes.medium,
        modifier = Modifier.fillMaxWidth().height(50.dp),
    ) {
        Icon(Icons.Filled.SystemUpdate, null, modifier = Modifier.size(18.dp))
        Text(stringResource(R.string.update_check), modifier = Modifier.padding(start = 8.dp), fontWeight = FontWeight.SemiBold)
    }
    found?.let { UpdateDialog(it) { found = null } }
    note?.let {
        Text(
            it,
            style = MaterialTheme.typography.bodySmall,
            color = eduCrm.success,
            modifier = Modifier.fillMaxWidth(),
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        )
    }
}
