package uz.mensalc.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import uz.mensalc.app.R
import uz.mensalc.app.data.History

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

@Composable
fun MoneyText(value: Long, signed: Boolean = false, bold: Boolean = true) {
    val color = when {
        signed && value < 0 -> Danger
        signed && value > 0 -> Success
        else -> MaterialTheme.colorScheme.onSurface
    }
    Text(money(value), color = color, fontWeight = if (bold) FontWeight.Bold else FontWeight.Normal)
}

@Composable
fun HistoryList(history: History, signed: Boolean) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(stringResource(R.string.history), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        history.rows.forEach { row ->
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
                Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(txLabel(row.type), fontWeight = FontWeight.SemiBold)
                        Text("${date(row.date)} · ${describe(row.type, row.metaObject)}", style = MaterialTheme.typography.bodySmall)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        when {
                            row.income > 0 -> Text("+" + money(row.income), color = Success, fontWeight = FontWeight.Bold)
                            row.expense > 0 -> Text("−" + money(row.expense), color = Danger, fontWeight = FontWeight.Bold)
                            else -> Text("—")
                        }
                        Text(money(row.balance), style = MaterialTheme.typography.bodySmall, color = if (signed && row.balance < 0) Danger else MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
        HorizontalDivider()
    }
}
