package uz.educrm.app.data

import java.security.MessageDigest

/** 4-digit app PIN that opens the app when the fingerprint / face is not read. Only a hash is stored. */
object PinCode {
    const val LENGTH = 4

    fun hash(pin: String): String =
        MessageDigest.getInstance("SHA-256").digest("educrm-pin:$pin".toByteArray())
            .joinToString("") { "%02x".format(it) }

    fun matches(pin: String, storedHash: String?): Boolean = storedHash != null && hash(pin) == storedHash
}
