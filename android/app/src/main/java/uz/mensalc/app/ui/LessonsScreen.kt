package uz.mensalc.app.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import uz.mensalc.app.R
import uz.mensalc.app.data.AttendanceRequest
import uz.mensalc.app.data.LessonResponse
import uz.mensalc.app.data.LessonsResponse
import uz.mensalc.app.data.MensaApi
import uz.mensalc.app.data.readableMessage
import java.time.LocalDate

@Composable
fun LessonsScreen(api: MensaApi, onOpen: (Int) -> Unit) {
    var day by remember { mutableStateOf(LocalDate.now()) }
    var state by remember { mutableStateOf<Load<LessonsResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(day, reload) {
        state = Load.Loading
        state = runCatching { api.lessons(day.toString()) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        Row(Modifier.fillMaxWidth().padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton({ day = day.minusDays(1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, null) }
            Text(date(day.toString()), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            if (day != LocalDate.now()) TextButton({ day = LocalDate.now() }) { Text(stringResource(R.string.today)) }
            IconButton({ day = day.plusDays(1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null) }
        }
        LoadBox(state, { reload++ }) { data ->
            if (data.lessons.isEmpty()) {
                Text(stringResource(R.string.no_lessons), modifier = Modifier.padding(24.dp))
            }
            LazyColumn(contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(data.lessons, key = { it.id }) { lesson ->
                    Card(Modifier.fillMaxWidth().clickable { onOpen(lesson.id) }) {
                        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(lesson.group, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.ExtraBold)
                                Text("${lesson.start_time}–${lesson.end_time}" + (lesson.room?.let { " · $it" } ?: ""), color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text((lesson.teacher ?: "") + if (lesson.is_substitute) " (${stringResource(R.string.substitute)})" else "", color = if (lesson.is_substitute) Orange else MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            if (lesson.status == "held") Icon(Icons.Filled.CheckCircle, null, tint = Success)
                            if (lesson.status == "cancelled") Text("✕", color = Danger)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun LessonScreen(api: MensaApi, lessonId: Int) {
    var state by remember { mutableStateOf<Load<LessonResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val statuses = remember { mutableStateMapOf<Int, String>() }
    var message by remember { mutableStateOf<String?>(null) }
    var saving by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val networkError = stringResource(R.string.error_network)
    val savedText = stringResource(R.string.saved)

    LaunchedEffect(reload) {
        state = runCatching { api.lesson(lessonId) }.fold({ data ->
            statuses.clear()
            data.students.forEach { s -> s.status?.let { statuses[s.enrollment_id] = it } }
            Load.Ready(data)
        }, { Load.Failed(it.readableMessage(networkError)) })
    }

    LoadBox(state, { reload++ }) { data ->
        Column(Modifier.fillMaxSize()) {
            Column(Modifier.padding(16.dp)) {
                Text(data.lesson.group, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.ExtraBold)
                Text(date(data.lesson.date), color = MaterialTheme.colorScheme.onSurfaceVariant)
                if (!data.can_mark) Text(stringResource(R.string.cannot_mark), color = Orange, modifier = Modifier.padding(top = 6.dp))
                if (data.can_mark) TextButton({ data.students.forEach { if (statuses[it.enrollment_id] == null) statuses[it.enrollment_id] = "present" } }) {
                    Text(stringResource(R.string.all_present))
                }
            }
            LazyColumn(Modifier.weight(1f), contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(data.students, key = { it.enrollment_id }) { student ->
                    Card(Modifier.fillMaxWidth()) {
                        Column(Modifier.padding(12.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(student.full_name, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                if (student.is_trial) Text(stringResource(R.string.trial), color = Orange)
                                if (student.is_debtor == true) Text(" " + stringResource(R.string.debtor), color = Danger)
                            }
                            Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                listOf("present" to (R.string.present to Success), "absent" to (R.string.absent to Danger), "excused" to (R.string.excused to Color(0xFF0284C7))).forEach { (value, meta) ->
                                    val selected = statuses[student.enrollment_id] == value
                                    if (selected) {
                                        Button({}, enabled = data.can_mark, colors = ButtonDefaults.buttonColors(containerColor = meta.second), modifier = Modifier.weight(1f)) { Text(stringResource(meta.first)) }
                                    } else {
                                        OutlinedButton({ statuses[student.enrollment_id] = value }, enabled = data.can_mark, modifier = Modifier.weight(1f)) { Text(stringResource(meta.first)) }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            message?.let { Text(it, modifier = Modifier.padding(horizontal = 16.dp), color = if (it == savedText) Success else Danger) }
            if (data.can_mark && data.lesson.status != "cancelled") {
                Button(
                    onClick = {
                        saving = true
                        scope.launch {
                            message = runCatching { api.saveAttendance(lessonId, AttendanceRequest(statuses.mapKeys { it.key.toString() })) }
                                .fold({ savedText }, { it.readableMessage(networkError) })
                            saving = false
                        }
                    },
                    enabled = !saving && statuses.isNotEmpty(),
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                ) { Text(stringResource(R.string.save_attendance)) }
            }
            Spacer(Modifier.width(1.dp))
        }
    }
}
