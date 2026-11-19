package uz.educrm.app.data

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore by preferencesDataStore(name = "session")
private val TOKEN = stringPreferencesKey("token")
private val THEME = stringPreferencesKey("theme")

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
        context.dataStore.edit { it.remove(TOKEN) }
    }

    /** "light", "dark" or "system". */
    val theme: Flow<String> = context.dataStore.data.map { it[THEME] ?: "system" }

    suspend fun saveTheme(value: String) {
        context.dataStore.edit { it[THEME] = value }
    }
}
