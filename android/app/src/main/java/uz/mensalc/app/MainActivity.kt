package uz.mensalc.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.LaunchedEffect
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

class MainActivity : ComponentActivity() {
    private lateinit var session: Session
    private lateinit var api: MensaApi

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        session = Session(applicationContext)
        api = MensaApi.create { session.token }

        setContent {
            MensaTheme {
                var user by remember { mutableStateOf<UserProfile?>(null) }
                var checking by remember { mutableStateOf(true) }
                val scope = rememberCoroutineScope()

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
                    else -> MainScreen(api, user!!) {
                        scope.launch {
                            runCatching { api.logout() }
                            session.clear()
                            user = null
                        }
                    }
                }
            }
        }
    }
}
