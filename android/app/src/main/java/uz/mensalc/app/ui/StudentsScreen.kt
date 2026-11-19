package uz.mensalc.app.ui

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import uz.mensalc.app.R
import uz.mensalc.app.data.Cashbox
import uz.mensalc.app.data.EnrollmentDetail
import uz.mensalc.app.data.MensaApi
import uz.mensalc.app.data.PaymentRequest
import uz.mensalc.app.data.StudentResponse
import uz.mensalc.app.data.StudentsResponse
import uz.mensalc.app.data.UserProfile
import uz.mensalc.app.data.readableMessage
import java.util.UUID

@Composable
fun StudentsScreen(api: MensaApi, onOpen: (Int) -> Unit) {
    var query by remember { mutableStateOf("") }
    var state by remember { mutableStateOf<Load<StudentsResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(query, reload) {
        delay(300)
        state = runCatching { api.students(query) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(
            title = stringResource(R.string.students),
            subtitle = (state as? Load.Ready)?.value?.let { stringResource(R.string.students_count, it.students.size) },
            bottom = {
                TextField(
                    query,
                    { query = it },
                    leadingIcon = { Icon(Icons.Filled.Search, null) },
                    placeholder = { Text(stringResource(R.string.search)) },
                    singleLine = true,
                    shape = RoundedCornerShape(14.dp),
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = MaterialTheme.colorScheme.surface,
                        unfocusedContainerColor = MaterialTheme.colorScheme.surface,
                        focusedIndicatorColor = Color.Transparent,
                        unfocusedIndicatorColor = Color.Transparent,
                    ),
                    modifier = Modifier.fillMaxWidth().padding(top = 12.dp, end = 12.dp),
                )
            },
        )
        LoadBox(state, { reload++ }) { data ->
            if (data.students.isEmpty()) {
                EmptyState("—")
            }
            LazyColumn(contentPadding = contentPadding(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(data.students, key = { it.id }) { student ->
                    MensaCard(onClick = { onOpen(student.id) }) {
                        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                            Initials(student.full_name)
                            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                                Text(student.full_name, fontWeight = FontWeight.Bold, maxLines = 1)
                                Text(student.groups.joinToString { it.group }, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
                            }
                            MoneyText(student.groups.sumOf { it.balance }, signed = true)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StudentScreen(api: MensaApi, user: UserProfile, studentId: Int, onBack: () -> Unit) {
    var state by remember { mutableStateOf<Load<StudentResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    var selected by remember { mutableStateOf<Int?>(null) }
    var paying by remember { mutableStateOf<EnrollmentDetail?>(null) }
    var message by remember { mutableStateOf<String?>(null) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.student(studentId) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    val ready = (state as? Load.Ready)?.value

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(
            title = ready?.student?.full_name ?: stringResource(R.string.students),
            subtitle = ready?.enrollments?.joinToString { it.group },
            onBack = onBack,
        )
        LoadBox(state, { reload++ }) { data ->
            val enrollment = data.enrollments.firstOrNull { it.id == selected } ?: data.enrollments.firstOrNull()
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                if (data.enrollments.size > 1) {
                    Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        data.enrollments.forEach { e ->
                            FilterChip(selected = e.id == enrollment?.id, onClick = { selected = e.id }, label = { Text(e.group) })
                        }
                    }
                }
                message?.let { Text(it, color = mensa.success, fontWeight = FontWeight.SemiBold) }
                enrollment?.let { e ->
                    Card(shape = MaterialTheme.shapes.large, colors = CardDefaults.cardColors(containerColor = mensa.hero), modifier = Modifier.fillMaxWidth()) {
                        Column(Modifier.padding(20.dp)) {
                            Text(e.group, color = Color.White.copy(alpha = 0.8f), fontWeight = FontWeight.SemiBold)
                            Text(stringResource(R.string.balance), color = Color.White.copy(alpha = 0.7f), style = MaterialTheme.typography.labelMedium, modifier = Modifier.padding(top = 10.dp))
                            Text(
                                money(e.balance) + " " + stringResource(R.string.sum),
                                color = if (e.balance < 0) Color(0xFFFFB48A) else Color.White,
                                fontSize = 28.sp,
                                fontWeight = FontWeight.ExtraBold,
                            )
                            if (user.can("payments.create")) {
                                Button(
                                    { paying = e },
                                    shape = MaterialTheme.shapes.medium,
                                    colors = ButtonDefaults.buttonColors(containerColor = Orange, contentColor = OnOrange),
                                    modifier = Modifier.padding(top = 14.dp).fillMaxWidth().height(48.dp),
                                ) {
                                    Text(stringResource(R.string.receive_payment), fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                    HistoryList(e.history, signed = true)
                }
            }

            paying?.let { e ->
                PaymentDialog(api, e, onDismiss = { paying = null }) { text ->
                    paying = null
                    message = text
                    reload++
                }
            }
        }
    }
}

@Composable
private fun PaymentDialog(api: MensaApi, enrollment: EnrollmentDetail, onDismiss: () -> Unit, onDone: (String) -> Unit) {
    var amount by remember { mutableStateOf(if (enrollment.balance < 0) (-enrollment.balance).toString() else "") }
    var cashboxes by remember { mutableStateOf<List<Cashbox>>(emptyList()) }
    var cashboxId by remember { mutableStateOf<Int?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val key = remember { UUID.randomUUID().toString() }
    val scope = rememberCoroutineScope()
    val networkError = stringResource(R.string.error_network)
    val doneTemplate = stringResource(R.string.payment_done, "%s")

    LaunchedEffect(Unit) {
        cashboxes = runCatching { api.cashboxes().cashboxes.filter { it.branch_id == enrollment.branch_id } }.getOrDefault(emptyList())
        cashboxId = cashboxes.firstOrNull()?.id
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.receive_payment)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(enrollment.group, fontWeight = FontWeight.Bold)
                MoneyField(amount, { amount = it }, label = stringResource(R.string.amount), modifier = Modifier.fillMaxWidth())
                Text(stringResource(R.string.cashbox), style = MaterialTheme.typography.labelMedium)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    cashboxes.forEach { c -> FilterChip(selected = c.id == cashboxId, onClick = { cashboxId = c.id }, label = { Text(c.name) }) }
                }
                error?.let { Text(it, color = mensa.danger) }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    busy = true
                    scope.launch {
                        runCatching { api.pay(enrollment.id, PaymentRequest(cashboxId ?: 0, amount.toLongOrNull() ?: 0, key)) }
                            .onSuccess { onDone(doneTemplate.format(it.payment.receipt_no)) }
                            .onFailure { error = it.readableMessage(networkError) }
                        busy = false
                    }
                },
                enabled = !busy && cashboxId != null && (amount.toLongOrNull() ?: 0) > 0,
            ) { Text(stringResource(R.string.receive)) }
        },
        dismissButton = { TextButton(onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}
