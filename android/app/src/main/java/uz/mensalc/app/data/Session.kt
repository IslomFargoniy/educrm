package uz.mensalc.app.data

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first

private val Context.dataStore by preferencesDataStore(name = "session")
private val TOKEN = stringPreferencesKey("token")

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
}
