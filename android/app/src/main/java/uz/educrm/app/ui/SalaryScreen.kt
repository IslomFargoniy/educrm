package uz.educrm.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import uz.educrm.app.R
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.SalaryResponse
import uz.educrm.app.data.readableMessage
import java.util.Calendar
import java.util.Locale

/** "2026-11" shifted by [by] months. */
private fun shiftMonth(month: String, by: Int): String {
    val calendar = Calendar.getInstance().apply {
        set(Calendar.YEAR, month.substring(0, 4).toInt())
        set(Calendar.MONTH, month.substring(5, 7).toInt() - 1)
        set(Calendar.DAY_OF_MONTH, 1)
        add(Calendar.MONTH, by)
    }
    return String.format(Locale.US, "%04d-%02d", calendar.get(Calendar.YEAR), calendar.get(Calendar.MONTH) + 1)
}

private fun currentMonth(): String = java.text.SimpleDateFormat("yyyy-MM", Locale.US).format(java.util.Date())

/**
 * Staff salary for a month: fixed amount + KPI for every new student taken in who made the first payment.
 */
@Composable
fun SalaryScreen(api: EduCrmApi) {
    var month by remember { mutableStateOf(currentMonth()) }
    var state by remember { mutableStateOf<Load<SalaryResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(month, reload) {
        state = Load.Loading
        state = runCatching { api.mySalary(month) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(title = stringResource(R.string.my_salary), bottom = {
            Row(Modifier.padding(top = 10.dp, end = 12.dp).fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                IconButton({ month = shiftMonth(month, -1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, null, tint = Color.White) }
                Text(month.substring(5, 7) + "." + month.substring(0, 4), color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, modifier = Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                IconButton({ month = shiftMonth(month, 1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Color.White) }
            }
        })
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Card(shape = MaterialTheme.shapes.large, colors = CardDefaults.cardColors(containerColor = eduCrm.hero), modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(20.dp)) {
                        Text(stringResource(R.string.total_salary), color = Color.White.copy(alpha = 0.8f), fontWeight = FontWeight.SemiBold)
                        Text(money(data.total) + " " + stringResource(R.string.sum), color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.ExtraBold)
                        Text(money(data.fixed) + " + " + money(data.kpi) + " KPI", color = Color.White.copy(alpha = 0.75f), style = MaterialTheme.typography.bodyMedium)
                    }
                }
                EduCrmCard {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        SalaryRow(stringResource(R.string.fixed_salary), money(data.fixed))
                        SalaryRow(stringResource(R.string.students_taken_in), data.registered.toString())
                        SalaryRow(stringResource(R.string.first_payments), data.first_payments.toString())
                        SalaryRow("KPI", "${data.first_payments} × ${money(data.kpi_rate)} = ${money(data.kpi)}", eduCrm.success)
                        HorizontalDivider(color = eduCrm.border)
                        SalaryRow(stringResource(R.string.total_salary), money(data.total), bold = true)
                        SalaryRow(stringResource(R.string.paid_out), money(data.paid))
                        SalaryRow(stringResource(R.string.left_to_pay), money(data.left), if (data.left > 0) eduCrm.danger else MaterialTheme.colorScheme.onSurface, bold = true)
                    }
                }
                SectionTitle(stringResource(R.string.kpi_students))
                EduCrmCard {
                    Column(Modifier.padding(16.dp)) {
                        if (data.students.isEmpty()) Text("—", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        data.students.forEachIndexed { index, s ->
                            if (index > 0) HorizontalDivider(Modifier.padding(vertical = 8.dp), color = eduCrm.border)
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                RowNumber(index + 1)
                                Text(s.full_name, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                Text(date(s.first_paid_at), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SalaryRow(label: String, value: String, color: Color = MaterialTheme.colorScheme.onSurface, bold: Boolean = false) {
    Row(Modifier.fillMaxWidth()) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
        Text(value, color = color, fontWeight = if (bold) FontWeight.ExtraBold else FontWeight.SemiBold)
    }
}
