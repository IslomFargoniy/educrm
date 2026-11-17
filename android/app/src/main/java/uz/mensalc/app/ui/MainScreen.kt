package uz.mensalc.app.ui

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.EventNote
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import uz.mensalc.app.R
import uz.mensalc.app.data.MensaApi
import uz.mensalc.app.data.UserProfile

private data class Tab(val route: String, val label: Int, val icon: ImageVector)

@Composable
fun MainScreen(api: MensaApi, user: UserProfile, onLogout: () -> Unit) {
    val nav = rememberNavController()
    val isTeacher = user.roles.contains("teacher")
    val tabs = listOfNotNull(
        Tab("lessons", R.string.lessons, Icons.Filled.EventNote),
        if (user.can("students.view")) Tab("students", R.string.students, Icons.Filled.People) else null,
        if (isTeacher) Tab("balance", R.string.my_balance, Icons.Filled.AccountBalanceWallet) else null,
        Tab("profile", R.string.profile, Icons.Filled.Person),
    )
    val current by nav.currentBackStackEntryAsState()

    Scaffold(
        bottomBar = {
            NavigationBar {
                tabs.forEach { tab ->
                    NavigationBarItem(
                        selected = current?.destination?.route?.startsWith(tab.route) == true,
                        onClick = { nav.navigate(tab.route) { popUpTo("lessons"); launchSingleTop = true } },
                        icon = { Icon(tab.icon, contentDescription = null) },
                        label = { Text(stringResource(tab.label)) },
                        colors = NavigationBarItemDefaults.colors(indicatorColor = Orange.copy(alpha = 0.25f)),
                    )
                }
            }
        },
    ) { padding ->
        NavHost(nav, startDestination = "lessons", modifier = Modifier.padding(padding)) {
            composable("lessons") { LessonsScreen(api) { nav.navigate("lesson/$it") } }
            composable("lesson/{id}", arguments = listOf(navArgument("id") { type = NavType.IntType })) {
                LessonScreen(api, it.arguments!!.getInt("id"))
            }
            composable("students") { StudentsScreen(api) { id -> nav.navigate("student/$id") } }
            composable("student/{id}", arguments = listOf(navArgument("id") { type = NavType.IntType })) {
                StudentScreen(api, user, it.arguments!!.getInt("id"))
            }
            composable("balance") { BalanceScreen(api) }
            composable("profile") { ProfileScreen(user, onLogout) }
        }
    }
}
