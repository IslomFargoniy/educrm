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
    var state by remember { mutableStateOf<Load<BalanceResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    var section by remember { mutableStateOf<String?>(null) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalance() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.my_balance))
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                val history = data.history
                // Income for this month's lessons; debts of earlier months' lessons paid now are shown apart.
                val thisMonth = java.text.SimpleDateFormat("yyyy-MM", java.util.Locale.US).format(java.util.Date())
                val isThisMonth = { row: uz.educrm.app.data.HistoryRow -> (row.metaObject.text("lesson_date") ?: row.date).startsWith(thisMonth) }
                // Paid out = salary given only; a share taken back (excused lesson, cancelled payment) lowers the income.
                val isPayout = { row: uz.educrm.app.data.HistoryRow -> row.type == "teacher_payout" }
                val incomeRows = history.rows.filter { !isPayout(it) && (it.income > 0 || it.expense > 0) && isThisMonth(it) }
                val incomeThisMonth = incomeRows.sumOf { it.income - it.expense }
                val expenseRows = history.rows.filter { isPayout(it) && it.expense > 0 }
                val paidOut = expenseRows.sumOf { it.expense }
                val incomeEarlier = history.income - (history.expense - paidOut) - incomeThisMonth
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
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    SummaryCard(stringResource(R.string.month_start), history.opening, MaterialTheme.colorScheme.onSurface, section == "opening", Modifier.weight(1f)) { section = if (section == "opening") null else "opening" }
                    SummaryCard(stringResource(R.string.income_short), incomeThisMonth, eduCrm.success, section == "income", Modifier.weight(1f)) { section = if (section == "income") null else "income" }
                    SummaryCard(stringResource(R.string.paid_out), paidOut, eduCrm.danger, section == "expense", Modifier.weight(1f)) { section = if (section == "expense") null else "expense" }
                }
                when (section) {
                    "all" -> HistoryList(history, signed = false)
                    "income" -> IncomeDetails(history.copy(rows = incomeRows))
                    "expense" -> HistoryList(history.copy(rows = expenseRows), signed = false, title = stringResource(R.string.paid_out_this_month))
                    "opening" -> EduCrmCard {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text(stringResource(R.string.month_start), fontWeight = FontWeight.Bold)
                            Text(stringResource(R.string.month_start_hint), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            HorizontalDivider(color = eduCrm.border)
                            FormulaRow(stringResource(R.string.month_start), money(history.opening))
                            FormulaRow("+ " + stringResource(R.string.income_short), money(incomeThisMonth), eduCrm.success)
                            if (incomeEarlier != 0L) FormulaRow("+ " + stringResource(R.string.earlier_debts_paid), money(incomeEarlier), eduCrm.success)
                            FormulaRow("− " + stringResource(R.string.paid_out), money(paidOut), eduCrm.danger)
                            HorizontalDivider(color = eduCrm.border)
                            FormulaRow("= " + stringResource(R.string.can_take), money(history.closing), bold = true)
                        }
                    }
                }
            }
        }
    }
}

private fun signedMoney(value: Long): String = if (value < 0) money(value) else "+" + money(value)

/** One payment into the teacher's balance for one student's lesson. */
private data class IncomeItem(val student: String, val group: String, val lessonDate: String?, val date: String, val amount: Long, val type: String)

/**
 * This month's income: by student (tap a student for each lesson), or as the plain history.
 */
@Composable
private fun IncomeDetails(history: uz.educrm.app.data.History) {
    var byStudent by rememberSaveable { mutableStateOf(true) }
    val items = history.rows.flatMap { row ->
        val group = row.metaObject.text("group").orEmpty()
        val lessonDate = row.metaObject.text("lesson_date")
        if (row.children.isNotEmpty()) {
            row.children.map { IncomeItem(it.student, group, lessonDate, row.date, it.share, row.type) }
        } else {
            listOf(IncomeItem(row.metaObject.text("student").orEmpty(), group, lessonDate, row.date, row.income - row.expense, row.type))
        }
    }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        SectionTitle(stringResource(R.string.income_this_month))
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
                        .clickable { byStudent = value }
                        .padding(vertical = 10.dp),
                )
            }
        }
        if (!byStudent) {
            HistoryList(history, signed = false, title = null, showTitle = false)
        } else {
            EduCrmCard {
                Column(Modifier.padding(16.dp)) {
                    val groups = items.groupBy { it.student to it.group }.entries.sortedByDescending { e -> e.value.sumOf { it.amount } }
                    if (groups.isEmpty()) Text("—", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    groups.forEachIndexed { index, (key, list) ->
                        if (index > 0) HorizontalDivider(Modifier.padding(vertical = 8.dp), color = eduCrm.border)
                        IncomeStudentRow(index + 1, key.first, key.second, list)
                    }
                }
            }
        }
    }
}

@Composable
private fun IncomeStudentRow(number: Int, student: String, group: String, items: List<IncomeItem>) {
    var expanded by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxWidth().clickable { expanded = !expanded }) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            RowNumber(number)
            Column(Modifier.weight(1f)) {
                Text(student, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                Text("$group · ${stringResource(R.string.lessons_count, items.size)}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            val total = items.sumOf { it.amount }
            Text(signedMoney(total), fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyMedium, color = if (total < 0) eduCrm.danger else eduCrm.success)
            Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(start = 4.dp).size(20.dp))
        }
        if (expanded) {
            items.sortedBy { it.lessonDate ?: it.date }.forEach {
                Row(Modifier.fillMaxWidth().padding(start = 26.dp, end = 24.dp, top = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(stringResource(R.string.lesson_on, date(it.lessonDate ?: it.date)), style = MaterialTheme.typography.bodySmall)
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

/** The teacher's share waiting for the students to pay, by student. */
@Composable
private fun ShareWaitingTab(api: EduCrmApi) {
    var state by remember { mutableStateOf<Load<BalanceResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalance() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    LoadBox(state, { reload++ }) { data ->
        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            EduCrmCard {
                Column(Modifier.padding(16.dp)) {
                    Text(stringResource(R.string.waiting_payment), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(money(-data.pending.total) + " " + stringResource(R.string.sum), fontWeight = FontWeight.ExtraBold, fontSize = 22.sp, color = if (data.pending.total > 0) eduCrm.danger else MaterialTheme.colorScheme.onSurface)
                    Text(stringResource(R.string.share_waiting_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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
