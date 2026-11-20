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
import uz.educrm.app.data.IncomeGroup
import uz.educrm.app.data.BalanceSummary
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.border
import uz.educrm.app.data.MyStudentsResponse
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.material3.Checkbox
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.background
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
    var state by remember { mutableStateOf<Load<BalanceSummary>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    var section by remember { mutableStateOf<String?>(null) }
    var byStudent by rememberSaveable { mutableStateOf(true) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalanceSummary() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    // The list under the cards is loaded page by page, only when a card is opened.
    val historyKind = when (section) {
        "all" -> "all"
        "expense" -> "payout"
        "income" -> if (byStudent) null else "income"
        else -> null
    }
    val history = rememberPaged(historyKind to reload) { page ->
        if (historyKind == null) PageResult(emptyList(), null, 0) else api.myHistory(historyKind, page).let { PageResult(it.rows, it.next_page, it.total) }
    }
    val incomeByStudent = section == "income" && byStudent
    val students = rememberPaged(incomeByStudent to reload) { page ->
        if (!incomeByStudent) PageResult(emptyList(), null, 0) else api.myIncomeStudents(page).let { PageResult(it.rows, it.next_page, it.total) }
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.my_balance))
        LoadBox(state, { reload++ }) { data ->
            LazyColumn(contentPadding = contentPadding(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                item(key = "hero") {
                    Card(
                        onClick = { section = if (section == "all") null else "all" },
                        shape = MaterialTheme.shapes.large,
                        colors = CardDefaults.cardColors(containerColor = eduCrm.hero),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Row(Modifier.padding(20.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(stringResource(R.string.can_take), color = Color.White.copy(alpha = 0.8f), fontWeight = FontWeight.SemiBold)
                                Text(money(data.balance) + " " + stringResource(R.string.sum), color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.ExtraBold)
                                Text(stringResource(R.string.tap_for_history), color = Color.White.copy(alpha = 0.7f), style = MaterialTheme.typography.bodySmall)
                            }
                            Icon(if (section == "all") Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null, tint = Color.White)
                        }
                    }
                }
                item(key = "cards") {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        SummaryCard(stringResource(R.string.month_start), data.opening, MaterialTheme.colorScheme.onSurface, section == "opening", Modifier.weight(1f)) { section = if (section == "opening") null else "opening" }
                        SummaryCard(stringResource(R.string.income_short), data.income, eduCrm.success, section == "income", Modifier.weight(1f)) { section = if (section == "income") null else "income" }
                        SummaryCard(stringResource(R.string.paid_out), data.paid_out, eduCrm.danger, section == "expense", Modifier.weight(1f)) { section = if (section == "expense") null else "expense" }
                    }
                }
                when (section) {
                    "opening" -> item(key = "opening") {
                        EduCrmCard {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text(stringResource(R.string.month_start), fontWeight = FontWeight.Bold)
                                Text(stringResource(R.string.month_start_hint), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                HorizontalDivider(color = eduCrm.border)
                                FormulaRow(stringResource(R.string.month_start), money(data.opening))
                                FormulaRow("+ " + stringResource(R.string.income_short), money(data.income), eduCrm.success)
                                if (data.earlier_debts != 0L) FormulaRow("+ " + stringResource(R.string.earlier_debts_paid), money(data.earlier_debts), eduCrm.success)
                                FormulaRow("− " + stringResource(R.string.paid_out), money(data.paid_out), eduCrm.danger)
                                HorizontalDivider(color = eduCrm.border)
                                FormulaRow("= " + stringResource(R.string.can_take), money(data.closing), bold = true)
                            }
                        }
                    }
                    null -> Unit
                    else -> {
                        item(key = "title-$section") {
                            SectionTitle(
                                when (section) {
                                    "income" -> stringResource(R.string.income_this_month)
                                    "expense" -> stringResource(R.string.paid_out_this_month)
                                    else -> stringResource(R.string.history)
                                },
                            )
                        }
                        if (section == "income") {
                            item(key = "income-tabs") { IncomeTabs(byStudent) { byStudent = it } }
                        }
                        if (incomeByStudent) {
                            itemsIndexed(students.items, key = { index, it -> "s$index-${it.student}-${it.group}" }) { index, group ->
                                EduCrmCard { Column(Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) { IncomeStudentRow(index + 1, group) } }
                            }
                            pagingFooter(students)
                        } else {
                            itemsIndexed(history.items, key = { index, it -> "h$index-${it.id}" }) { index, row ->
                                EduCrmCard { HistoryRowItem(index + 1, row, signed = false) }
                            }
                            pagingFooter(history)
                        }
                    }
                }
            }
        }
    }
}

private fun signedMoney(value: Long): String = if (value < 0) money(value) else "+" + money(value)

@Composable
private fun IncomeTabs(byStudent: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.surfaceVariant).border(1.dp, eduCrm.border, RoundedCornerShape(12.dp)).padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        listOf(true to R.string.by_student, false to R.string.history).forEach { (value, label) ->
            val selected = byStudent == value
            Text(
                stringResource(label),
                textAlign = TextAlign.Center,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                fontSize = 13.sp,
                color = if (selected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.weight(1f).clip(RoundedCornerShape(9.dp))
                    .background(if (selected) MaterialTheme.colorScheme.surface else Color.Transparent)
                    .clickable { onChange(value) }
                    .padding(vertical = 10.dp),
            )
        }
    }
}

/** One student's income this month; a tap shows each lesson (a share taken back is shown with a minus). */
@Composable
private fun IncomeStudentRow(number: Int, group: IncomeGroup) {
    var expanded by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxWidth().clickable { expanded = !expanded }) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            RowNumber(number)
            Column(Modifier.weight(1f)) {
                Text(group.student, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                Text("${group.group} · ${stringResource(R.string.lessons_count, group.items.size)}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Text(signedMoney(group.total), fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyMedium, color = if (group.total < 0) eduCrm.danger else eduCrm.success)
            Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(start = 4.dp).size(20.dp))
        }
        if (expanded) {
            group.items.forEach {
                Row(Modifier.fillMaxWidth().padding(start = 26.dp, end = 24.dp, top = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(stringResource(R.string.lesson_on, date(it.lesson_date ?: it.date)), style = MaterialTheme.typography.bodySmall)
                        Text("${txLabel(it.type)} · ${date(it.date)}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Text(signedMoney(it.amount), style = MaterialTheme.typography.bodySmall, color = if (it.amount < 0) eduCrm.danger else eduCrm.success, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

/** Month start / income / paid out card; tapping it shows the details below. */
@Composable
private fun SummaryCard(label: String, value: Long, color: Color, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    Card(
        onClick = onClick,
        modifier = modifier,
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = androidx.compose.foundation.BorderStroke(if (selected) 2.dp else 1.dp, if (selected) MaterialTheme.colorScheme.primary else eduCrm.border),
    ) {
        Column(Modifier.padding(horizontal = 12.dp, vertical = 12.dp)) {
            Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
            Text(money(value), color = color, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp, maxLines = 1)
        }
    }
}

@Composable
private fun FormulaRow(label: String, value: String, color: Color = MaterialTheme.colorScheme.onSurface, bold: Boolean = false) {
    Row(Modifier.fillMaxWidth()) {
        Text(label, modifier = Modifier.weight(1f), fontWeight = if (bold) FontWeight.Bold else FontWeight.Normal)
        Text(value + " " + stringResource(R.string.sum), color = color, fontWeight = if (bold) FontWeight.ExtraBold else FontWeight.SemiBold)
    }
}

/**
 * Lessons taught whose students have not paid yet (the teacher's share waits for them), by student.
 */
@Composable
fun DebtsScreen(api: EduCrmApi) {
    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.debts))
        ShareWaitingTab(api)
    }
}

/** The teacher's share waiting for the students to pay, by student, page by page. */
@Composable
private fun ShareWaitingTab(api: EduCrmApi) {
    var sum by remember { mutableStateOf<Long?>(null) }
    val pending = rememberPaged(Unit) { page ->
        api.myPending(page).also { sum = it.sum }.let { PageResult(it.rows, it.next_page, it.total) }
    }

    LazyColumn(contentPadding = contentPadding(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item(key = "total") {
            EduCrmCard {
                Column(Modifier.padding(16.dp)) {
                    Text(stringResource(R.string.waiting_payment), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    val total = sum ?: 0L
                    Text(money(-total) + " " + stringResource(R.string.sum), fontWeight = FontWeight.ExtraBold, fontSize = 22.sp, color = if (total > 0) eduCrm.danger else MaterialTheme.colorScheme.onSurface)
                    Text(stringResource(R.string.share_waiting_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        itemsIndexed(pending.items, key = { index, it -> "p$index-${it.student}-${it.group}" }) { index, group ->
            EduCrmCard { Column(Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) { PendingStudentRow(index + 1, group.student, group.group, group.items) } }
        }
        pagingFooter(pending)
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
