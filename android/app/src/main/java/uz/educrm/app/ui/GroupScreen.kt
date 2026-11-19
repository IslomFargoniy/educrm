package uz.educrm.app.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import uz.educrm.app.R
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.GroupLesson
import uz.educrm.app.data.GroupResponse
import uz.educrm.app.data.GroupStudent
import uz.educrm.app.data.readableMessage

/**
 * Group card (opened by a long press on a lesson): students with phone and balance, and recent attendance.
 */
@Composable
fun GroupScreen(api: EduCrmApi, groupId: Int, onBack: () -> Unit) {
    var state by remember { mutableStateOf<Load<GroupResponse>>(Load.Loading) }
    var reload by remember { mutableStateOf(0) }
    var tab by rememberSaveable { mutableStateOf(0) }
    val networkError = stringResource(R.string.error_network)

    LaunchedEffect(reload) {
        state = runCatching { api.group(groupId) }.fold({ Load.Ready(it) }, { Load.Failed(it.readableMessage(networkError)) })
    }

    val ready = (state as? Load.Ready)?.value

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(
            title = ready?.group?.name ?: stringResource(R.string.groups_tab),
            subtitle = ready?.group?.let { g ->
                listOfNotNull(
                    weekdays(g.days).takeIf { it.isNotEmpty() },
                    listOfNotNull(g.start_time, g.end_time).joinToString("–").takeIf { it.isNotEmpty() },
                    g.teacher,
                ).joinToString(" · ")
            },
            onBack = onBack,
            bottom = {
                Row(
                    Modifier.padding(end = 12.dp, top = 8.dp).fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Color.White.copy(alpha = 0.10f)).padding(4.dp),
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                ) {
                    listOf(R.string.students, R.string.attendance_history).forEachIndexed { index, label ->
                        val selected = tab == index
                        Text(
                            stringResource(label),
                            color = if (selected) eduCrm.bar else Color.White,
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.weight(1f).clip(RoundedCornerShape(9.dp))
                                .background(if (selected) Color.White else Color.Transparent)
                                .clickable { tab = index }
                                .padding(vertical = 9.dp),
                        )
                    }
                }
            },
        )
        LoadBox(state, { reload++ }) { data ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(contentPadding()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                if (tab == 0) StudentsTab(data.students, data.lessons) else AttendanceTab(data.students, data.lessons)
            }
        }
    }
}

@Composable
private fun StudentsTab(students: List<GroupStudent>, lessons: List<GroupLesson>) {
    val uri = LocalUriHandler.current
    val debt = students.mapNotNull { it.balance }.filter { it < 0 }.sum()
    EduCrmCard {
        Column(Modifier.padding(16.dp)) {
            Text(stringResource(R.string.students_count, students.size), fontWeight = FontWeight.Bold)
            if (debt < 0) Text(stringResource(R.string.group_debt, money(debt)), color = eduCrm.danger, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
            students.forEachIndexed { index, s ->
                HorizontalDivider(Modifier.padding(vertical = 8.dp), color = eduCrm.border)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RowNumber(index + 1)
                    Column(Modifier.weight(1f)) {
                        Text(s.full_name, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                        val tags = listOfNotNull(
                            if (s.status == "trial") stringResource(R.string.trial) else null,
                            if (s.frozen) stringResource(R.string.frozen) else null,
                            if (s.status == "left" || s.status == "transferred") stringResource(R.string.left_group) else null,
                        )
                        if (tags.isNotEmpty()) Text(tags.joinToString(" · "), style = MaterialTheme.typography.labelSmall, color = Orange)
                        s.phone?.let { phone ->
                            Text(formatPhone(phone), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary, modifier = Modifier.clickable { uri.openUri("tel:$phone") })
                        }
                        MarksStrip(s.enrollment_id, lessons)
                    }
                    s.balance?.let { MoneyText(it, signed = true) }
                }
            }
        }
    }
}

/** The student's last lessons as coloured dots, newest on the right. */
@Composable
private fun MarksStrip(enrollmentId: Int, lessons: List<GroupLesson>) {
    val marks = lessons.take(12).reversed().mapNotNull { it.marks[enrollmentId.toString()] }
    if (marks.isEmpty()) return
    Row(Modifier.padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
        marks.forEach { Box(Modifier.size(9.dp).clip(CircleShape).background(markColor(it))) }
    }
}

@Composable
private fun AttendanceTab(students: List<GroupStudent>, lessons: List<GroupLesson>) {
    if (lessons.isEmpty()) {
        EmptyState(stringResource(R.string.no_lessons))
        return
    }
    val names = students.associate { it.enrollment_id.toString() to it.full_name }
    EduCrmCard {
        Column(Modifier.padding(vertical = 6.dp)) {
            lessons.forEachIndexed { index, lesson ->
                if (index > 0) HorizontalDivider(Modifier.padding(horizontal = 14.dp), color = eduCrm.border)
                LessonMarksRow(index + 1, lesson, names)
            }
        }
    }
}

@Composable
private fun LessonMarksRow(number: Int, lesson: GroupLesson, names: Map<String, String>) {
    var expanded by remember { mutableStateOf(false) }
    val present = lesson.marks.values.count { it == "present" }
    val absent = lesson.marks.values.count { it == "absent" }
    val excused = lesson.marks.values.count { it == "excused" }
    Column(Modifier.fillMaxWidth().clickable(enabled = lesson.marks.isNotEmpty()) { expanded = !expanded }.padding(horizontal = 14.dp, vertical = 10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            RowNumber(number)
            Column(Modifier.weight(1f)) {
                Text("${date(lesson.date)} · ${lesson.start_time}", fontWeight = FontWeight.SemiBold)
                when {
                    lesson.status == "cancelled" -> Text(stringResource(R.string.status_cancelled), color = eduCrm.danger, style = MaterialTheme.typography.bodySmall)
                    lesson.marks.isEmpty() -> Text(stringResource(R.string.not_marked), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                    else -> Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("${stringResource(R.string.present)}: $present", color = eduCrm.success, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                        if (absent > 0) Text("${stringResource(R.string.absent)}: $absent", color = eduCrm.danger, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                        if (excused > 0) Text("${stringResource(R.string.excused)}: $excused", color = eduCrm.info, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
            if (lesson.marks.isNotEmpty()) Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        if (expanded) {
            lesson.marks.entries.sortedBy { names[it.key].orEmpty() }.forEach { (enrollmentId, status) ->
                Row(Modifier.fillMaxWidth().padding(start = 26.dp, top = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(9.dp).clip(CircleShape).background(markColor(status)))
                    Text(names[enrollmentId] ?: "#$enrollmentId", style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f).padding(start = 8.dp))
                    Text(markLabel(status), style = MaterialTheme.typography.bodySmall, color = markColor(status), fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun markColor(status: String): Color = when (status) {
    "present" -> eduCrm.success
    "absent" -> eduCrm.danger
    else -> eduCrm.info
}

@Composable
private fun markLabel(status: String): String = when (status) {
    "present" -> stringResource(R.string.present)
    "absent" -> stringResource(R.string.absent)
    else -> stringResource(R.string.excused)
}
