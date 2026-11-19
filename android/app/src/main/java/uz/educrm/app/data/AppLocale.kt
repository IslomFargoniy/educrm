package uz.educrm.app.data

import android.content.Context
import android.content.res.Configuration
import java.util.Locale

/**
 * App language (uz / ru / en), independent of the phone language.
 * Kept in plain preferences so it can be read synchronously before the activity is created.
 */
object AppLocale {
    val supported = listOf("uz", "ru", "en")
    private const val PREFS = "app_locale"
    private const val KEY = "language"

    /** The chosen language, or null when the user has not chosen one yet. */
    fun saved(context: Context): String? =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null)?.takeIf { it in supported }

    fun current(context: Context): String = saved(context) ?: "uz"

    fun save(context: Context, language: String) {
        if (language !in supported) return
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, language).apply()
    }

    /** Context whose resources (and the default Locale used for the API) follow the app language. */
    fun wrap(base: Context): Context {
        val locale = Locale.forLanguageTag(current(base))
        Locale.setDefault(locale)
        val config = Configuration(base.resources.configuration)
        config.setLocale(locale)
        return base.createConfigurationContext(config)
    }
}
