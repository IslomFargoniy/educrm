package uz.mensalc.app.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
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
import uz.mensalc.app.R
import uz.mensalc.app.data.BalanceResponse
import uz.mensalc.app.data.MensaApi
import uz.mensalc.app.data.UserProfile
import uz.mensalc.app.data.readableMessage

@Composable
fun BalanceScreen(api: MensaApi) {
    var state by remember { mutableStateOf<Load<BalanceResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.myBalance() }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    LoadBox(state, { reload++ }) { data ->
        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Card(colors = CardDefaults.cardColors(containerColor = Navy), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(20.dp)) {
                    Text(stringResource(R.string.can_take), color = Color.White.copy(alpha = 0.8f))
                    Text(money(data.balance) + " " + stringResource(R.string.sum), color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.ExtraBold)
                }
            }
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp)) {
                    Text(stringResource(R.string.waiting_payment), style = MaterialTheme.typography.labelMedium)
                    Text(money(data.pending.total), fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    data.pending.items.take(20).forEach {
                        Row(Modifier.fillMaxWidth().padding(top = 4.dp)) {
                            Text("${it.student} · ${it.group} · ${date(it.lesson_date)}", modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodySmall)
                            Text(money(it.amount), style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }
            }
            HistoryList(data.history, signed = false)
        }
    }
}

@Composable
fun ProfileScreen(user: UserProfile, onLogout: () -> Unit) {
    val uri = LocalUriHandler.current

    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(user.name, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.ExtraBold)
        Text(user.phone.orEmpty(), color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text("${stringResource(R.string.role)}: ${user.roles.joinToString()}")
        if (user.branches.isNotEmpty()) Text("${stringResource(R.string.branches)}: ${user.branches.joinToString { it.name }}")
        OutlinedButton({ uri.openUri("https://crm.mensalc.uz") }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.open_web)) }
        Button(onLogout, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.logout)) }
    }
}
