package uz.educrm.app.ui

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonPrimitive
import java.util.Locale

fun money(value: Long?): String {
    if (value == null) return "—"
    val sign = if (value < 0) "−" else ""
    return sign + String.format(Locale.US, "%,d", kotlin.math.abs(value)).replace(',', ' ')
}

/** "2026-11-17..." → "17.11.2026" */
fun date(value: String?): String {
    if (value.isNullOrBlank() || value.length < 10) return "—"
    return "${value.substring(8, 10)}.${value.substring(5, 7)}.${value.substring(0, 4)}"
}

/** Picks the uz / ru / en text for the current app language. */
private fun Triple<String, String, String>.localized(): String = when (Locale.getDefault().language) {
    "ru" -> second
    "en" -> third
    else -> first
}

private val txLabels = mapOf(
    "student_payment" to Triple("To'lov", "Оплата", "Payment"),
    "payment_reversal" to Triple("To'lov bekor qilindi", "Отмена оплаты", "Payment cancelled"),
    "lesson_charge" to Triple("Dars", "Урок", "Lesson"),
    "charge_reversal" to Triple("Dars qayta hisoblandi", "Урок пересчитан", "Lesson recalculated"),
    "teacher_earning" to Triple("Qarz yopildi", "Долг погашен", "Debt covered"),
    "lesson_share" to Triple("Dars ulushi", "Доля за урок", "Lesson share"),
    "earning_reversal" to Triple("Ulush qaytarildi", "Доля возвращена", "Share returned"),
    "teacher_payout" to Triple("Oylik oldi", "Выплата", "Salary paid"),
    "balance_transfer" to Triple("Balans ko'chirildi", "Перенос баланса", "Balance moved"),
    "refund" to Triple("Pul qaytarildi", "Возврат", "Refund"),
    "debt_writeoff" to Triple("Qarz hisobdan chiqarildi", "Долг списан", "Debt written off"),
    "opening_balance" to Triple("Boshlang'ich qoldiq", "Начальный остаток", "Opening balance"),
    "free_lesson" to Triple("Dars (bepul)", "Урок (бесплатно)", "Lesson (free)"),
    "excused_lesson" to Triple("Dars (sababli)", "Урок (уважительная)", "Lesson (excused)"),
)

fun txLabel(type: String): String = txLabels[type]?.localized() ?: type

fun JsonObject?.text(key: String): String? = this?.get(key)?.jsonPrimitive?.content?.takeIf { it != "null" }

/** Short description of a history row. */
fun describe(type: String, meta: JsonObject?): String = when (type) {
    "student_payment", "payment_reversal" -> listOfNotNull(meta.text("cashbox"), meta.text("receipt_no")?.let { "№ $it" }).joinToString(" · ")
    "lesson_charge", "charge_reversal", "free_lesson", "excused_lesson" -> date(meta.text("lesson_date"))
    "teacher_earning", "lesson_share", "earning_reversal" -> listOfNotNull(meta.text("student"), meta.text("group"), date(meta.text("lesson_date"))).joinToString(" · ")
    "teacher_payout" -> meta.text("cashbox").orEmpty()
    "balance_transfer" -> "${meta.text("from_group")} → ${meta.text("to_group")}"
    else -> meta.text("group").orEmpty()
}

/** The 9 local digits of an Uzbek number from any input ("+998 90 123 45 67", "998901234567", "901234567"). */
fun phoneLocalDigits(value: String?): String {
    val raw = value.orEmpty().trim()
    if (raw.startsWith("+998")) return raw.substring(4).filter(Char::isDigit).take(9)
    val digits = raw.filter(Char::isDigit)
    return (if (digits.length >= 12 && digits.startsWith("998")) digits.substring(3) else digits).take(9)
}

/** "+998901234567" → "+998 90 123 45 67" (partial numbers are formatted as far as they go). */
fun formatPhone(value: String?): String {
    val local = phoneLocalDigits(value)
    if (local.isEmpty()) return ""
    val parts = listOf(0 to 2, 2 to 5, 5 to 7, 7 to 9)
        .filter { (from, _) -> from < local.length }
        .map { (from, to) -> local.substring(from, minOf(to, local.length)) }
    return "+998 " + parts.joinToString(" ")
}

private val roleLabels = mapOf(
    "superadmin" to Triple("Superadmin", "Суперадмин", "Superadmin"),
    "admin" to Triple("Administrator", "Администратор", "Administrator"),
    "manager" to Triple("Menejer", "Менеджер", "Manager"),
    "teacher" to Triple("O'qituvchi", "Преподаватель", "Teacher"),
    "parent" to Triple("Ota-ona", "Родитель", "Parent"),
    "student" to Triple("O'quvchi", "Ученик", "Student"),
)

fun roleLabel(role: String): String = roleLabels[role]?.localized() ?: role
