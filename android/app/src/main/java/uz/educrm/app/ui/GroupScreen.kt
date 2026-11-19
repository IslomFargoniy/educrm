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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.PhotoCamera
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
import kotlinx.coroutines.launch
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
                if (tab == 0) StudentsTab(api, data.students, data.lessons) { reload++ } else AttendanceTab(data.students, data.lessons)
            }
        }
    }
}

@Composable
private fun StudentsTab(api: EduCrmApi, students: List<GroupStudent>, lessons: List<GroupLesson>, onChanged: () -> Unit) {
    val uri = LocalUriHandler.current
    val context = androidx.compose.ui.platform.LocalContext.current
    val scope = androidx.compose.runtime.rememberCoroutineScope()
    var target by remember { mutableStateOf<GroupStudent?>(null) }
    var cameraUri by remember { mutableStateOf<android.net.Uri?>(null) }
    var uploading by remember { mutableStateOf<Int?>(null) }
    var message by remember { mutableStateOf<String?>(null) }
    val savedText = stringResource(R.string.photo_sent)
    val failedText = stringResource(R.string.photo_failed)

    fun upload(picked: android.net.Uri?) {
        val student = target ?: return
        if (picked == null) return
        uploading = student.enrollment_id
        scope.launch {
            val part = facePart(context, picked)
            message = if (part != null && runCatching { api.uploadFace(student.student_id, part) }.isSuccess) savedText else failedText
            uploading = null
            target = null
            onChanged()
        }
    }

    val takePicture = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.TakePicture()) { ok -> upload(if (ok) cameraUri else null) }
    val pickImage = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.GetContent()) { picked -> upload(picked) }

    target?.let { student ->
        if (uploading == null) {
            androidx.compose.material3.AlertDialog(
                onDismissRequest = { target = null },
                title = { Text(student.full_name) },
                text = { Text(stringResource(R.string.photo_hint)) },
                confirmButton = {
                    androidx.compose.material3.TextButton({
                        val newUri = newCameraUri(context)
                        cameraUri = newUri
                        takePicture.launch(newUri)
                    }) { Text(stringResource(R.string.camera)) }
                },
                dismissButton = {
                    androidx.compose.material3.TextButton({ pickImage.launch("image/*") }) { Text(stringResource(R.string.gallery)) }
                },
            )
        }
    }

    message?.let {
        Text(it, color = if (it == savedText) eduCrm.success else eduCrm.danger, fontWeight = FontWeight.SemiBold)
    }
    val debt = students.mapNotNull { it.balance }.filter { it < 0 }.sum()
    EduCrmCard {
        Column(Modifier.padding(16.dp)) {
            Text(stringResource(R.string.students_count, students.size), fontWeight = FontWeight.Bold)
            if (debt < 0) Text(stringResource(R.string.group_debt, money(debt)), color = eduCrm.danger, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
            students.forEachIndexed { index, s ->
                HorizontalDivider(Modifier.padding(vertical = 8.dp), color = eduCrm.border)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RowNumber(index + 1)
                    Box(
                        Modifier.padding(end = 10.dp).size(46.dp).clip(CircleShape).clickable(enabled = s.can_photo && uploading == null) { target = s },
                        contentAlignment = Alignment.Center,
                    ) {
                        if (uploading == s.enrollment_id) {
                            androidx.compose.material3.CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp)
                        } else {
                            RemotePhoto(s.photo, 46) { Initials(s.full_name, size = 46) }
                            if (s.can_photo) {
                                Box(Modifier.align(Alignment.BottomEnd).size(18.dp).clip(CircleShape).background(MaterialTheme.colorScheme.primary), contentAlignment = Alignment.Center) {
                                    Icon(Icons.Filled.PhotoCamera, contentDescription = stringResource(R.string.camera), tint = Color.White, modifier = Modifier.size(12.dp))
                                }
                            }
                        }
                    }
                    Column(Modifier.weight(1f)) {
                        Text(s.full_name, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                        if (s.photo == null && s.can_photo) Text(stringResource(R.string.no_face_photo), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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

/**
 * Attendance journal like the web: one row per student, one column per lesson (oldest on the left).
 */
@Composable
private fun AttendanceTab(students: List<GroupStudent>, lessons: List<GroupLesson>) {
    if (lessons.isEmpty()) {
        EmptyState(stringResource(R.string.no_lessons))
        return
    }
    val columns = lessons.reversed()
    val scroll = rememberScrollState(Int.MAX_VALUE)
    val nameWidth = 128.dp
    val cell = 34.dp

    EduCrmCard {
        Column(Modifier.padding(vertical = 8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(stringResource(R.string.students), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.width(nameWidth).padding(start = 12.dp))
                Row(Modifier.horizontalScroll(scroll).padding(end = 8.dp)) {
                    columns.forEach { lesson ->
                        Column(Modifier.width(cell), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(lesson.date.substring(8, 10), fontWeight = FontWeight.Bold, fontSize = 12.sp, color = if (lesson.status == "cancelled") eduCrm.danger else MaterialTheme.colorScheme.onSurface)
                            Text(lesson.date.substring(5, 7), fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
            students.forEachIndexed { index, s ->
                HorizontalDivider(Modifier.padding(vertical = 4.dp), color = eduCrm.border)
                val key = s.enrollment_id.toString()
                val present = columns.count { it.marks[key] == "present" }
                val marked = columns.count { it.marks[key] != null }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.width(nameWidth).padding(start = 12.dp, end = 6.dp)) {
                        Text("${index + 1}. ${s.full_name}", style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis)
                        Text("$present/$marked", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Row(Modifier.horizontalScroll(scroll).padding(end = 8.dp)) {
                        columns.forEach { lesson ->
                            val status = lesson.marks[key]
                            Box(Modifier.width(cell).padding(3.dp), contentAlignment = Alignment.Center) {
                                Box(
                                    Modifier.size(26.dp).clip(RoundedCornerShape(7.dp))
                                        .background(if (status == null) Color.Transparent else markColor(status).copy(alpha = 0.18f))
                                        .border(1.dp, if (status == null) eduCrm.border else markColor(status), RoundedCornerShape(7.dp)),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Text(
                                        when (status) {
                                            "present" -> "✓"
                                            "absent" -> "✗"
                                            "excused" -> stringResource(R.string.excused).take(1)
                                            else -> if (lesson.status == "cancelled") "—" else ""
                                        },
                                        color = if (status == null) MaterialTheme.colorScheme.onSurfaceVariant else markColor(status),
                                        fontWeight = FontWeight.ExtraBold,
                                        fontSize = 13.sp,
                                    )
                                }
                            }
                        }
                    }
                }
            }
            Row(Modifier.padding(start = 12.dp, top = 10.dp), horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                Legend("✓", stringResource(R.string.present), eduCrm.success)
                Legend("✗", stringResource(R.string.absent), eduCrm.danger)
                Legend(stringResource(R.string.excused).take(1), stringResource(R.string.excused), eduCrm.info)
            }
        }
    }
}

@Composable
private fun Legend(mark: String, label: String, color: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(mark, color = color, fontWeight = FontWeight.ExtraBold, fontSize = 13.sp)
        Text(" $label", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun markColor(status: String): Color = when (status) {
    "present" -> eduCrm.success
    "absent" -> eduCrm.danger
    else -> eduCrm.info
}
