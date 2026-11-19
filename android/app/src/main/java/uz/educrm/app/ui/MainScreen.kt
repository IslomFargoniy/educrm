package uz.educrm.app.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.EventNote
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import uz.educrm.app.R
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.UserProfile

private data class Tab(val route: String, val label: Int, val icon: ImageVector)

@Composable
fun MainScreen(api: EduCrmApi, user: UserProfile, themeMode: ThemeMode, onThemeChange: (ThemeMode) -> Unit, onLogout: () -> Unit) {
    val nav = rememberNavController()
    val isTeacher = user.roles.contains("teacher")
    val tabs = listOfNotNull(
        Tab("lessons", R.string.lessons, Icons.Filled.EventNote),
        if (user.can("students.view")) Tab("students", R.string.students, Icons.Filled.People) else null,
        if (isTeacher) Tab("balance", R.string.my_balance, Icons.Filled.AccountBalanceWallet) else null,
        Tab("profile", R.string.profile, Icons.Filled.Person),
    )
    val current by nav.currentBackStackEntryAsState()
    val backdrop = rememberBackdrop()
    val systemBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()
    val barBottom = systemBottom + 4.dp
    val inset = barBottom + GlassBarHeight + 12.dp

    Box(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        CompositionLocalProvider(LocalBottomBarInset provides inset) {
            NavHost(
                nav,
                startDestination = "lessons",
                modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background).backdropSource(backdrop),
            ) {
                composable("lessons") { LessonsScreen(api, user) { nav.navigate("lesson/$it") } }
                composable("lesson/{id}", arguments = listOf(navArgument("id") { type = NavType.IntType })) {
                    LessonScreen(api, it.arguments!!.getInt("id")) { nav.popBackStack() }
                }
                composable("students") { StudentsScreen(api) { id -> nav.navigate("student/$id") } }
                composable("student/{id}", arguments = listOf(navArgument("id") { type = NavType.IntType })) {
                    StudentScreen(api, user, it.arguments!!.getInt("id")) { nav.popBackStack() }
                }
                composable("balance") { BalanceScreen(api) }
                composable("profile") { ProfileScreen(user, themeMode, onThemeChange, onLogout) }
            }
        }

        GlassTabBar(
            tabs = tabs.map { tab ->
                GlassTab(
                    label = stringResource(tab.label),
                    icon = tab.icon,
                    selected = current?.destination?.route?.startsWith(tab.route.removeSuffix("s")) == true,
                    onClick = { nav.navigate(tab.route) { popUpTo("lessons"); launchSingleTop = true } },
                )
            },
            backdrop = backdrop,
            dark = isDark(themeMode),
            modifier = Modifier.align(Alignment.BottomCenter).padding(start = 16.dp, end = 16.dp, bottom = barBottom),
        )
    }
}
