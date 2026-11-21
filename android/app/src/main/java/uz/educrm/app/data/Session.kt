package uz.educrm.app.data

import android.content.Context
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore by preferencesDataStore(name = "session")
private val TOKEN = stringPreferencesKey("token")
private val THEME = stringPreferencesKey("theme")
private val BIOMETRIC = booleanPreferencesKey("biometric_enabled")
private val PIN_HASH = stringPreferencesKey("pin_hash")

/**
 * Keeps the API token between app launches.
 */
class Session(private val context: Context) {
    @Volatile var token: String? = null
        private set

    suspend fun load(): String? {
        token = context.dataStore.data.first()[TOKEN]
        return token
    }

    suspend fun save(value: String) {
        token = value
        context.dataStore.edit { it[TOKEN] = value }
    }

    suspend fun clear() {
        token = null
        context.dataStore.edit {
            it.remove(TOKEN)
            // The lock belongs to this account: the next person must not meet someone else's PIN.
            it.remove(BIOMETRIC)
            it.remove(PIN_HASH)
        }
    }

    /** Fingerprint / face login is on. */
    val biometricEnabled: Flow<Boolean> = context.dataStore.data.map { it[BIOMETRIC] ?: false }

    /** Hash of the 4-digit PIN that comes with fingerprint login (see [PinCode]), or null. */
    val pinHash: Flow<String?> = context.dataStore.data.map { it[PIN_HASH] }

    /** Turns fingerprint login on together with its PIN (only the hash is stored). */
    suspend fun enableBiometric(pin: String) {
        context.dataStore.edit {
            it[PIN_HASH] = PinCode.hash(pin)
            it[BIOMETRIC] = true
        }
    }

    suspend fun disableBiometric() {
        context.dataStore.edit {
            it.remove(BIOMETRIC)
            it.remove(PIN_HASH)
        }
    }

    /** "light", "dark" or "system". */
    val theme: Flow<String> = context.dataStore.data.map { it[THEME] ?: "system" }

    suspend fun saveTheme(value: String) {
        context.dataStore.edit { it[THEME] = value }
    }
}
