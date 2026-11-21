package uz.educrm.app

import android.graphics.Color
import android.os.Bundle
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.fragment.app.FragmentActivity
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import uz.educrm.app.data.AppLocale
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.LocaleRequest
import uz.educrm.app.data.Session
import uz.educrm.app.data.UserProfile
import uz.educrm.app.ui.BiometricLockScreen
import uz.educrm.app.ui.LoginScreen
import uz.educrm.app.ui.MainScreen
import uz.educrm.app.ui.EduCrmTheme
import uz.educrm.app.ui.SplashScreen
import uz.educrm.app.ui.ThemeMode
import uz.educrm.app.ui.UpdatePrompt
import uz.educrm.app.ui.isDark

/** FragmentActivity (still a ComponentActivity) because the fingerprint prompt needs one. */
class MainActivity : FragmentActivity() {
    private lateinit var session: Session
    private lateinit var api: EduCrmApi

    override fun attachBaseContext(newBase: android.content.Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    /** Switch the app language: remember it, tell the server (for notifications) and redraw. */
    private fun changeLanguage(language: String, scope: kotlinx.coroutines.CoroutineScope) {
        if (language == AppLocale.current(this)) return
        AppLocale.save(this, language)
        scope.launch {
            runCatching { api.updateLocale(LocaleRequest(language)) }
            recreate()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.dark(Color.TRANSPARENT),
            navigationBarStyle = SystemBarStyle.dark(Color.TRANSPARENT),
        )
        session = Session(applicationContext)
        api = EduCrmApi.create { session.token }

        setContent {
            val themeName by session.theme.collectAsState(initial = "system")
            val mode = when (themeName) {
                "light" -> ThemeMode.Light
                "dark" -> ThemeMode.Dark
                else -> ThemeMode.System
            }
            val scope = rememberCoroutineScope()
            val dark = isDark(mode)

            // Headers are navy in both themes (light status icons); the glass tab bar follows the theme.
            DisposableEffect(dark) {
                enableEdgeToEdge(
                    statusBarStyle = SystemBarStyle.dark(Color.TRANSPARENT),
                    navigationBarStyle = if (dark) SystemBarStyle.dark(Color.TRANSPARENT) else SystemBarStyle.light(Color.TRANSPARENT, Color.TRANSPARENT),
                )
                onDispose {}
            }

            EduCrmTheme(mode) {
                var user by remember { mutableStateOf<UserProfile?>(null) }
                var checking by remember { mutableStateOf(true) }
                val biometricEnabled by session.biometricEnabled.collectAsState(initial = null)
                // "" = no PIN; null = not read yet (the lock must not take "not read" for "none").
                val pinHash by remember { session.pinHash.map { it ?: "" } }.collectAsState(initial = null)
                // Locks once per app start; saveable so rotating or changing the language does not lock again.
                var unlocked by rememberSaveable { mutableStateOf(false) }
                val logout: () -> Unit = {
                    scope.launch {
                        runCatching { api.logout() }
                        session.clear()
                        user = null
                    }
                }

                LaunchedEffect(Unit) {
                    if (session.load() != null) {
                        user = runCatching { api.me().user }.getOrNull()
                        if (user == null) session.clear()
                    }
                    // First launch after sign-in: use the language set on the account.
                    user?.locale?.let { if (AppLocale.saved(this@MainActivity) == null && it in AppLocale.supported && it != AppLocale.current(this@MainActivity)) changeLanguage(it, scope) }
                    checking = false
                }

                when {
                    checking || biometricEnabled == null || pinHash == null -> SplashScreen()
                    user != null && biometricEnabled == true && !unlocked -> BiometricLockScreen(
                        pinHash = pinHash?.ifEmpty { null },
                        onUnlocked = { unlocked = true },
                        // Sign out: the login screen opens and the fingerprint setup is cleared with the session.
                        onUsePassword = logout,
                    )
                    user == null -> LoginScreen(api) { token, profile ->
                        scope.launch {
                            session.save(token)
                            user = profile
                            if (AppLocale.saved(this@MainActivity) == null && profile.locale in AppLocale.supported && profile.locale != AppLocale.current(this@MainActivity)) {
                                changeLanguage(profile.locale, scope)
                            }
                        }
                    }
                    else -> {
                        // Already inside the app: turning fingerprint login on in Profile must not lock it right away.
                        LaunchedEffect(Unit) { unlocked = true }
                        MainScreen(
                            api = api,
                            user = user!!,
                            themeMode = mode,
                            onThemeChange = { scope.launch { session.saveTheme(it.name.lowercase()) } },
                            language = AppLocale.current(this@MainActivity),
                            onLanguageChange = { changeLanguage(it, scope) },
                            biometricEnabled = biometricEnabled == true,
                            onBiometricChange = { pin ->
                                scope.launch { if (pin != null) session.enableBiometric(pin) else session.disableBiometric() }
                            },
                            onLogout = logout,
                        )
                    }
                }

                if (!checking) UpdatePrompt()
            }
        }
    }
}
