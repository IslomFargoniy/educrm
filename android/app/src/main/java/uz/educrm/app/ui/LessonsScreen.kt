package uz.educrm.app.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.DoneAll
import androidx.compose.material.icons.filled.EventBusy
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import uz.educrm.app.R
import uz.educrm.app.data.AttendanceRequest
import uz.educrm.app.data.LessonItem
import uz.educrm.app.data.LessonResponse
import uz.educrm.app.data.LessonsResponse
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.UserProfile
import uz.educrm.app.data.readableMessage
import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale

@Composable
fun LessonsScreen(api: EduCrmApi, user: UserProfile, onOpen: (Int) -> Unit) {
    var day by remember { mutableStateOf(LocalDate.now()) }
    var state by remember { mutableStateOf<Load<LessonsResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)
    val isToday = day == LocalDate.now()

    LaunchedEffect(day, reload) {
        state = Load.Loading
        state = runCatching { api.lessons(day.toString()) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(
            title = stringResource(R.string.hello, user.name.substringBefore(' ')),
            subtitle = stringResource(R.string.lessons),
            bottom = {
                Row(
                    Modifier.padding(top = 12.dp, end = 12.dp).fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(eduCrm.barAccent),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    IconButton({ day = day.minusDays(1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, null, tint = Color.White) }
                    Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(date(day.toString()), color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp)
                        Text(
                            if (isToday) stringResource(R.string.today) else day.dayOfWeek.getDisplayName(TextStyle.FULL, Locale.getDefault()).replaceFirstChar { it.uppercase() },
                            color = if (isToday) Orange else eduCrm.barMuted,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.clickable(enabled = !isToday) { day = LocalDate.now() },
                        )
                    }
                    IconButton({ day = day.plusDays(1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Color.White) }
                }
                (state as? Load.Ready)?.value?.summary?.let { summary ->
                    Row(Modifier.padding(top = 8.dp, end = 12.dp).fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        HeaderStat(stringResource(R.string.total_students), summary.students.toString(), Color.White, Modifier.weight(1f))
                        HeaderStat(stringResource(R.string.frozen_students), summary.frozen.toString(), if (summary.frozen > 0) Color(0xFF7DD3FC) else Color.White, Modifier.weight(1f))
                    }
                }
            },
        )
        LoadBox(state, { reload++ }) { data ->
            if (data.lessons.isEmpty()) {
                EmptyState(stringResource(R.string.no_lessons))
            } else {
                LazyColumn(contentPadding = contentPadding(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    itemsIndexed(data.lessons, key = { _, it -> it.id }) { index, lesson -> LessonCard(index + 1, lesson) { onOpen(lesson.id) } }
                }
            }
        }
    }
}

/** Small figure under the date in the navy header. */
@Composable
private fun HeaderStat(label: String, value: String, color: Color, modifier: Modifier) {
    Row(
        modifier.clip(RoundedCornerShape(12.dp)).background(eduCrm.barAccent).padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, color = eduCrm.barMuted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f), maxLines = 1)
        Text(value, color = color, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold)
    }
}

@Composable
private fun LessonCard(number: Int, lesson: LessonItem, onClick: () -> Unit) {
    val (statusText, statusColor) = lessonStatus(lesson.status)
    EduCrmCard(onClick = onClick) {
        Row(Modifier.height(androidx.compose.foundation.layout.IntrinsicSize.Min).padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            RowNumber(number)
            Column(Modifier.width(56.dp)) {
                Text(lesson.start_time, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp)
                Text(lesson.end_time, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp)
            }
            Box(Modifier.padding(horizontal = 12.dp).width(3.dp).fillMaxHeight().clip(CircleShape).background(statusColor))
            Column(Modifier.weight(1f)) {
                Text(lesson.group, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.ExtraBold, maxLines = 1)
                val details = listOfNotNull(lesson.teacher, lesson.room).joinToString(" · ")
                if (details.isNotEmpty()) Text(details, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall, maxLines = 1)
                if (lesson.students > 0) {
                    val counts = stringResource(R.string.students_count, lesson.students) + if (lesson.frozen > 0) " · " + stringResource(R.string.frozen_count, lesson.frozen) else ""
                    Text(counts, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall, maxLines = 1)
                }
                val groupTime = listOfNotNull(lesson.group_start_time, lesson.group_end_time).joinToString("–")
                val schedule = listOf(weekdays(lesson.group_days), groupTime).filter { it.isNotEmpty() }.joinToString(" · ")
                if (schedule.isNotEmpty()) Text(schedule, color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold, maxLines = 1)
                if (lesson.is_substitute) Text(stringResource(R.string.substitute), color = Orange, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
            }
            Pill(statusText, statusColor)
        }
    }
}

@Composable
private fun lessonStatus(status: String): Pair<String, Color> = when (status) {
    "held" -> stringResource(R.string.status_held) to eduCrm.success
    "cancelled" -> stringResource(R.string.status_cancelled) to eduCrm.danger
    else -> stringResource(R.string.status_scheduled) to MaterialTheme.colorScheme.primary
}

@Composable
fun EmptyState(text: String) {
    Column(Modifier.fillMaxSize().padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.clip(CircleShape).background(MaterialTheme.colorScheme.surfaceVariant).padding(18.dp)) {
            Icon(Icons.Filled.EventBusy, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Text(text, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(top = 12.dp))
    }
}

@Composable
fun LessonScreen(api: EduCrmApi, lessonId: Int, onBack: () -> Unit) {
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
            // Students the face terminal saw come in are pre-marked present; saving confirms it.
            data.students.forEach { s ->
                val prefill = if (data.can_mark && data.lesson.status != "cancelled" && s.arrived_at != null) "present" else null
                (s.status ?: prefill)?.let { statuses[s.enrollment_id] = it }
            }
            Load.Ready(data)
        }, { Load.Failed(it.readableMessage(networkError)) })
    }

    val ready = (state as? Load.Ready)?.value

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(
            title = ready?.lesson?.group ?: stringResource(R.string.lessons),
            subtitle = ready?.let { date(it.lesson.date) + " · " + stringResource(R.string.students_count, it.students.size) },
            onBack = onBack,
            actions = {
                if (ready?.can_mark == true) {
                    TextButton({ ready.students.forEach { if (statuses[it.enrollment_id] == null) statuses[it.enrollment_id] = "present" } }) {
                        Icon(Icons.Filled.DoneAll, null, tint = Orange, modifier = Modifier.padding(end = 6.dp))
                        Text(stringResource(R.string.all_present), color = Orange, fontWeight = FontWeight.Bold)
                    }
                }
            },
        )
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().padding(bottom = LocalBottomBarInset.current - 12.dp)) {
                val arrived = data.students.count { it.arrived_at != null }
                if (data.can_mark && arrived > 0) {
                    Text(
                        stringResource(R.string.face_arrived_summary, arrived, data.students.size),
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp),
                    )
                }
                if (!data.can_mark) {
                    Text(
                        stringResource(R.string.cannot_mark),
                        color = MaterialTheme.colorScheme.onSecondaryContainer,
                        modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp).fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.secondaryContainer).padding(12.dp),
                    )
                }
                LazyColumn(Modifier.weight(1f), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    itemsIndexed(data.students, key = { _, it -> it.enrollment_id }) { index, student ->
                        EduCrmCard {
                            Column(Modifier.padding(14.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    RowNumber(index + 1)
                                    Initials(student.full_name, size = 38)
                                    Column(Modifier.weight(1f).padding(horizontal = 10.dp)) {
                                        Text(student.full_name, fontWeight = FontWeight.Bold)
                                        student.arrived_at?.let { Text("● " + stringResource(R.string.came_at, it), color = eduCrm.success, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold) }
                                    }
                                    if (student.is_trial) Pill(stringResource(R.string.trial), Orange)
                                    if (student.is_debtor == true) Box(Modifier.padding(start = 4.dp)) { Pill(stringResource(R.string.debtor), eduCrm.danger) }
                                }
                                AttendanceChoice(
                                    selected = statuses[student.enrollment_id],
                                    enabled = data.can_mark,
                                    onSelect = { statuses[student.enrollment_id] = it },
                                )
                            }
                        }
                    }
                }
                message?.let { Text(it, modifier = Modifier.padding(horizontal = 16.dp), color = if (it == savedText) eduCrm.success else eduCrm.danger, fontWeight = FontWeight.SemiBold) }
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
                        shape = MaterialTheme.shapes.medium,
                        modifier = Modifier.fillMaxWidth().padding(16.dp).height(52.dp),
                    ) { Text(stringResource(R.string.save_attendance), fontWeight = FontWeight.Bold) }
                }
            }
        }
    }
}

/** Present / absent / excused segmented choice. */
@Composable
private fun AttendanceChoice(selected: String?, enabled: Boolean, onSelect: (String) -> Unit) {
    val options = listOf(
        Triple("present", R.string.present, eduCrm.success),
        Triple("absent", R.string.absent, eduCrm.danger),
        Triple("excused", R.string.excused, eduCrm.info),
    )
    Row(Modifier.padding(top = 12.dp).fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        options.forEach { (value, label, color) ->
            val isSelected = selected == value
            Box(
                Modifier.weight(1f)
                    .clip(RoundedCornerShape(10.dp))
                    .background(if (isSelected) color else Color.Transparent)
                    .border(1.dp, if (isSelected) color else eduCrm.border, RoundedCornerShape(10.dp))
                    .clickable(enabled = enabled) { onSelect(value) }
                    .padding(vertical = 10.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    stringResource(label),
                    color = if (isSelected) MaterialTheme.colorScheme.surface else MaterialTheme.colorScheme.onSurface.copy(alpha = if (enabled) 1f else 0.5f),
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp,
                )
            }
        }
    }
}
