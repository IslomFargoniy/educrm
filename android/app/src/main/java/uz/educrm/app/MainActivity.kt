package uz.educrm.app

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
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.Session
import uz.educrm.app.data.UserProfile
import uz.educrm.app.ui.LoginScreen
import uz.educrm.app.ui.MainScreen
import uz.educrm.app.ui.EduCrmTheme
import uz.educrm.app.ui.SplashScreen
import uz.educrm.app.ui.ThemeMode
import uz.educrm.app.ui.UpdatePrompt
import uz.educrm.app.ui.isDark

class MainActivity : ComponentActivity() {
    private lateinit var session: Session
    private lateinit var api: EduCrmApi

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
