package uz.educrm.app.data

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject

@Serializable
data class LoginRequest(val phone: String, val password: String, val device_name: String = "android")

@Serializable
data class Branch(val id: Int, val name: String)

@Serializable
data class UserProfile(
    val id: Int,
    val name: String,
    val phone: String? = null,
    val locale: String = "uz",
    val balance: Long = 0,
    val roles: List<String> = emptyList(),
    val permissions: List<String> = emptyList(),
    val branches: List<Branch> = emptyList(),
) {
    fun can(permission: String) = permissions.contains(permission)
}

@Serializable
data class LoginResponse(val token: String, val user: UserProfile)

@Serializable
data class MeResponse(val user: UserProfile)

@Serializable
data class LessonItem(
    val id: Int,
    val group_id: Int = 0,
    val group: String,
    val date: String,
    val start_time: String,
    val end_time: String,
    val teacher: String? = null,
    val room: String? = null,
    val status: String,
    val is_substitute: Boolean = false,
    val marked: Int = 0,
    val group_days: List<Int> = emptyList(),
    val group_start_time: String? = null,
    val group_end_time: String? = null,
    val students: Int = 0,
    val frozen: Int = 0,
)

@Serializable
data class LessonsResponse(val date: String, val lessons: List<LessonItem>, val summary: LessonsSummary = LessonsSummary())

@Serializable
data class LessonsSummary(val students: Int = 0, val frozen: Int = 0)

@Serializable
data class LessonInfo(val id: Int, val group: String, val date: String, val status: String)

@Serializable
data class RosterStudent(
    val enrollment_id: Int,
    val full_name: String,
    val status: String? = null,
    val arrived_at: String? = null,
    val is_trial: Boolean = false,
    val is_debtor: Boolean? = null,
)

@Serializable
data class LessonResponse(val lesson: LessonInfo, val can_mark: Boolean, val students: List<RosterStudent>, val is_future: Boolean = false)

@Serializable
data class AttendanceRequest(val statuses: Map<String, String>)

@Serializable
data class OkResponse(val ok: Boolean = true)

@Serializable
data class StudentGroup(val enrollment_id: Int, val group: String, val branch_id: Int, val balance: Long)

@Serializable
data class StudentItem(val id: Int, val full_name: String, val phone: String? = null, val groups: List<StudentGroup> = emptyList())

@Serializable
data class StudentsResponse(val students: List<StudentItem>)

@Serializable
data class HistoryRow(
    val id: JsonElement,
    val date: String,
    val type: String,
    val meta: JsonElement? = null,
    val income: Long = 0,
    val expense: Long = 0,
    val balance: Long = 0,
    val note: String? = null,
    val by: String? = null,
    val flagged: Boolean = false,
    val reversed: Boolean = false,
    val children: List<HistoryChild> = emptyList(),
) {
    val metaObject: JsonObject? get() = meta as? JsonObject
}

/** One student inside a grouped "lesson share" row. */
@Serializable
data class HistoryChild(val student: String = "", val lesson_amount: Long = 0, val covered: Long = 0, val percent: Double = 0.0, val share: Long = 0)

@Serializable
data class History(val opening: Long = 0, val income: Long = 0, val expense: Long = 0, val closing: Long = 0, val rows: List<HistoryRow> = emptyList())

@Serializable
data class EnrollmentDetail(
    val id: Int,
    val group: String,
    val branch_id: Int,
    val status: String,
    val balance: Long,
    val available: Long = 0,
    val history: History = History(),
)

@Serializable
data class StudentShort(val id: Int, val full_name: String)

@Serializable
data class StudentResponse(val student: StudentShort, val enrollments: List<EnrollmentDetail>)

@Serializable
data class Cashbox(val id: Int, val name: String, val branch_id: Int, val method: String, val balance: Long? = null)

@Serializable
data class CashboxesResponse(val cashboxes: List<Cashbox>)

@Serializable
data class PaymentRequest(val cashbox_id: Int, val amount: Long, val idempotency_key: String)

@Serializable
data class PaymentInfo(val id: Int, val receipt_no: String, val amount: Long)

@Serializable
data class PaymentResponse(val payment: PaymentInfo, val balance: Long)

@Serializable
data class PendingItem(val id: Int, val student: String, val group: String, val lesson_date: String, val amount: Long, val is_substitute: Boolean = false)

@Serializable
data class Pending(val total: Long = 0, val items: List<PendingItem> = emptyList())

@Serializable
data class BalanceResponse(val balance: Long, val history: History, val pending: Pending)

@Serializable
data class OptionItem(val id: Int, val name: String, val branch_id: Int? = null)

@Serializable
data class FormOptions(val groups: List<OptionItem> = emptyList(), val courses: List<OptionItem> = emptyList(), val sources: List<String> = emptyList())

@Serializable
data class NewStudentRequest(val first_name: String, val last_name: String, val phone: String? = null, val group_id: Int? = null)

@Serializable
data class NewLeadRequest(val first_name: String, val last_name: String? = null, val phone: String, val source: String, val course_id: Int? = null)

@Serializable
data class CreatedPerson(val id: Int, val full_name: String)

@Serializable
data class NewStudentResponse(val student: CreatedPerson)

@Serializable
data class NewLeadResponse(val lead: CreatedPerson)

@Serializable
data class LocaleRequest(val locale: String)

@Serializable
data class MyStudent(val enrollment_id: Int, val student: String, val phone: String? = null, val group: String, val status: String, val balance: Long)

@Serializable
data class MyStudentsResponse(val debt: Long = 0, val debtors: Int = 0, val students: List<MyStudent> = emptyList())

@Serializable
data class GroupInfo(val id: Int, val name: String, val teacher: String? = null, val days: List<Int> = emptyList(), val start_time: String? = null, val end_time: String? = null)

@Serializable
data class GroupStudent(val enrollment_id: Int, val full_name: String, val phone: String? = null, val balance: Long? = null, val status: String = "active", val frozen: Boolean = false)

@Serializable
data class GroupLesson(val id: Int, val date: String, val start_time: String, val status: String, val marks: Map<String, String> = emptyMap())

@Serializable
data class GroupResponse(val group: GroupInfo, val students: List<GroupStudent> = emptyList(), val lessons: List<GroupLesson> = emptyList())
