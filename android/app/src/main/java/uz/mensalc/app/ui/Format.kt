package uz.mensalc.app.ui

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

private val isRussian get() = Locale.getDefault().language == "ru"

private val txLabels = mapOf(
    "student_payment" to ("To'lov" to "Оплата"),
    "payment_reversal" to ("To'lov bekor qilindi" to "Отмена оплаты"),
    "lesson_charge" to ("Dars" to "Урок"),
    "charge_reversal" to ("Dars qayta hisoblandi" to "Урок пересчитан"),
    "teacher_earning" to ("Qarz yopildi" to "Долг погашен"),
    "lesson_share" to ("Dars ulushi" to "Доля за урок"),
    "earning_reversal" to ("Ulush qaytarildi" to "Доля возвращена"),
    "teacher_payout" to ("Oylik oldi" to "Выплата"),
    "balance_transfer" to ("Balans ko'chirildi" to "Перенос баланса"),
    "refund" to ("Pul qaytarildi" to "Возврат"),
    "debt_writeoff" to ("Qarz hisobdan chiqarildi" to "Долг списан"),
    "opening_balance" to ("Boshlang'ich qoldiq" to "Начальный остаток"),
    "free_lesson" to ("Dars (bepul)" to "Урок (бесплатно)"),
    "excused_lesson" to ("Dars (sababli)" to "Урок (уважительная)"),
)

fun txLabel(type: String): String = txLabels[type]?.let { if (isRussian) it.second else it.first } ?: type

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
    "superadmin" to ("Superadmin" to "Суперадмин"),
    "admin" to ("Administrator" to "Администратор"),
    "manager" to ("Menejer" to "Менеджер"),
    "teacher" to ("O'qituvchi" to "Преподаватель"),
    "parent" to ("Ota-ona" to "Родитель"),
    "student" to ("O'quvchi" to "Ученик"),
)

fun roleLabel(role: String): String = roleLabels[role]?.let { if (isRussian) it.second else it.first } ?: role
