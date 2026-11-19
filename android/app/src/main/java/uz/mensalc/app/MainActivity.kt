package uz.mensalc.app

import android.graphics.Color
import android.os.Bundle
import androidx.activity.ComponentActivity
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
import androidx.compose.runtime.setValue
import kotlinx.coroutines.launch
import uz.mensalc.app.data.MensaApi
import uz.mensalc.app.data.Session
import uz.mensalc.app.data.UserProfile
import uz.mensalc.app.ui.LoginScreen
import uz.mensalc.app.ui.MainScreen
import uz.mensalc.app.ui.MensaTheme
import uz.mensalc.app.ui.SplashScreen
import uz.mensalc.app.ui.ThemeMode
import uz.mensalc.app.ui.UpdatePrompt
import uz.mensalc.app.ui.isDark

class MainActivity : ComponentActivity() {
    private lateinit var session: Session
    private lateinit var api: MensaApi

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.dark(Color.TRANSPARENT),
            navigationBarStyle = SystemBarStyle.dark(Color.TRANSPARENT),
        )
        session = Session(applicationContext)
        api = MensaApi.create { session.token }

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

            MensaTheme(mode) {
                var user by remember { mutableStateOf<UserProfile?>(null) }
                var checking by remember { mutableStateOf(true) }

                LaunchedEffect(Unit) {
                    if (session.load() != null) {
                        user = runCatching { api.me().user }.getOrNull()
                        if (user == null) session.clear()
                    }
                    checking = false
                }

                when {
                    checking -> SplashScreen()
                    user == null -> LoginScreen(api) { token, profile ->
                        scope.launch {
                            session.save(token)
                            user = profile
                        }
                    }
                    else -> MainScreen(
                        api = api,
                        user = user!!,
                        themeMode = mode,
                        onThemeChange = { scope.launch { session.saveTheme(it.name.lowercase()) } },
                        onLogout = {
                            scope.launch {
                                runCatching { api.logout() }
                                session.clear()
                                user = null
                            }
                        },
                    )
                }

                if (!checking) UpdatePrompt()
            }
        }
    }
}
