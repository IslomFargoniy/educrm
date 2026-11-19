package uz.educrm.app.ui

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.FilterChip
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
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import uz.educrm.app.R
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.FormOptions
import uz.educrm.app.data.NewLeadRequest
import uz.educrm.app.data.NewStudentRequest
import uz.educrm.app.data.readableMessage

enum class NewPersonKind { Student, Lead }

/**
 * Add a student or a lead: first name and last name are entered separately,
 * the system shows them together as one full name.
 */
@Composable
fun AddPersonDialog(
    api: EduCrmApi,
    kinds: List<NewPersonKind>,
    onDismiss: () -> Unit,
    onDone: (kind: NewPersonKind, id: Int, fullName: String) -> Unit,
) {
    var kind by remember { mutableStateOf(kinds.first()) }
    var firstName by remember { mutableStateOf("") }
    var lastName by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var groupId by remember { mutableStateOf<Int?>(null) }
    var courseId by remember { mutableStateOf<Int?>(null) }
    var source by remember { mutableStateOf("instagram") }
    var options by remember { mutableStateOf(FormOptions()) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(Unit) {
        options = runCatching { api.formOptions() }.getOrDefault(FormOptions())
    }

    val isStudent = kind == NewPersonKind.Student
    val valid = firstName.isNotBlank() && (!isStudent || lastName.isNotBlank()) && (isStudent || phone.length == 9) && (phone.isEmpty() || phone.length == 9)

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(if (isStudent) R.string.new_student else R.string.new_lead)) },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                if (kinds.size > 1) {
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        kinds.forEach { k ->
                            FilterChip(
                                selected = k == kind,
                                onClick = { kind = k; error = null },
                                label = { Text(stringResource(if (k == NewPersonKind.Student) R.string.student else R.string.lead)) },
                            )
                        }
                    }
                }
                NameField(firstName, { firstName = it }, stringResource(R.string.first_name))
                NameField(lastName, { lastName = it }, stringResource(if (isStudent) R.string.last_name else R.string.last_name_optional))
                PhoneField(phone, { phone = it }, label = stringResource(R.string.phone), modifier = Modifier.fillMaxWidth())

                if (isStudent) {
                    ChoiceRow(
                        title = stringResource(R.string.group_optional),
                        items = options.groups.map { it.id to it.name },
                        selected = groupId,
                        onSelect = { groupId = if (groupId == it) null else it },
                    )
                } else {
                    ChoiceRow(
                        title = stringResource(R.string.source),
                        items = options.sources.mapIndexed { i, s -> i to sourceLabel(s) },
                        selected = options.sources.indexOf(source).takeIf { it >= 0 },
                        onSelect = { source = options.sources[it] },
                    )
                    ChoiceRow(
                        title = stringResource(R.string.course),
                        items = options.courses.map { it.id to it.name },
                        selected = courseId,
                        onSelect = { courseId = if (courseId == it) null else it },
                    )
                }
                error?.let { Text(it, color = eduCrm.danger) }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    busy = true
                    error = null
                    scope.launch {
                        val fullPhone = phone.takeIf { it.isNotEmpty() }?.let { "+998$it" }
                        runCatching {
                            if (isStudent) {
                                api.createStudent(NewStudentRequest(firstName.trim(), lastName.trim(), fullPhone, groupId)).student
                            } else {
                                api.createLead(NewLeadRequest(firstName.trim(), lastName.trim().ifEmpty { null }, fullPhone.orEmpty(), source, courseId)).lead
                            }
                        }
                            .onSuccess { onDone(kind, it.id, it.full_name) }
                            .onFailure { error = it.readableMessage(networkError) }
                        busy = false
                    }
                },
                enabled = !busy && valid,
            ) { Text(stringResource(R.string.add)) }
        },
        dismissButton = { TextButton(onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}

@Composable
private fun NameField(value: String, onChange: (String) -> Unit, label: String) {
    OutlinedTextField(
        value,
        onChange,
        label = { Text(label) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun ChoiceRow(title: String, items: List<Pair<Int, String>>, selected: Int?, onSelect: (Int) -> Unit) {
    if (items.isEmpty()) return
    Text(title, style = MaterialTheme.typography.labelMedium)
    Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        items.forEach { (id, name) -> FilterChip(selected = id == selected, onClick = { onSelect(id) }, label = { Text(name) }) }
    }
}

@Composable
private fun sourceLabel(source: String): String = when (source) {
    "instagram" -> "Instagram"
    "telegram" -> "Telegram"
    "facebook" -> "Facebook"
    "referral" -> stringResource(R.string.source_referral)
    "banner" -> stringResource(R.string.source_banner)
    "walk_in" -> stringResource(R.string.source_walk_in)
    "website" -> stringResource(R.string.source_website)
    else -> stringResource(R.string.source_other)
}
