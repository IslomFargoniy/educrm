package uz.educrm.app.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.requiredSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.Image
import kotlinx.coroutines.launch
import uz.educrm.app.R
import uz.educrm.app.data.LoginRequest
import uz.educrm.app.data.EduCrmApi
import uz.educrm.app.data.UserProfile
import uz.educrm.app.data.readableMessage

@Composable
fun SplashScreen() {
    Box(Modifier.fillMaxSize().background(eduCrm.bar), contentAlignment = Alignment.Center) {
        LogoBadge(96)
    }
}

/** Orange rounded box with the logo, as in the web sidebar. */
@Composable
fun LogoBadge(size: Int) {
    Box(Modifier.size(size.dp).clip(RoundedCornerShape((size / 3.5).dp)).background(Orange), contentAlignment = Alignment.Center) {
        Image(painterResource(R.drawable.ic_launcher_foreground), contentDescription = null, colorFilter = ColorFilter.tint(Navy), modifier = Modifier.requiredSize((size * 1.7).dp))
    }
}

@Composable
fun LoginScreen(api: EduCrmApi, onLoggedIn: (String, UserProfile) -> Unit) {
    var phone by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val networkError = stringResource(R.string.error_network)

    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background).imePadding().verticalScroll(rememberScrollState())) {
        Column(
            Modifier.fillMaxWidth()
                .clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp))
                .background(eduCrm.bar)
                .statusBarsPadding()
                .padding(start = 24.dp, end = 24.dp, top = 36.dp, bottom = 56.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                LogoBadge(52)
                Column(Modifier.padding(start = 12.dp)) {
                    Text("EduCrm", color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold)
                    Text("Learning center", color = eduCrm.barMuted, fontSize = 13.sp)
                }
            }
            Box(Modifier.padding(top = 28.dp, bottom = 10.dp).size(12.dp).rotate(45f).background(Orange))
            Text(stringResource(R.string.login_tagline), color = Color.White, fontSize = 24.sp, lineHeight = 30.sp, fontWeight = FontWeight.ExtraBold)
        }

        EduCrmCard(Modifier.padding(horizontal = 16.dp).offset(y = (-32).dp)) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Column {
                    Text(stringResource(R.string.login_title), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.ExtraBold)
                    Text(stringResource(R.string.login_subtitle), color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                PhoneField(phone, { phone = it }, label = stringResource(R.string.phone), modifier = Modifier.fillMaxWidth())
                OutlinedTextField(password, { password = it }, label = { Text(stringResource(R.string.password)) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password), modifier = Modifier.fillMaxWidth())
                error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
                Button(
                    onClick = {
                        busy = true
                        error = null
                        scope.launch {
                            runCatching { api.login(LoginRequest("+998$phone", password)) }
                                .onSuccess { onLoggedIn(it.token, it.user) }
                                .onFailure { error = it.readableMessage(networkError) }
                            busy = false
                        }
                    },
                    enabled = !busy && phone.length == 9 && password.isNotBlank(),
                    shape = MaterialTheme.shapes.medium,
                    modifier = Modifier.fillMaxWidth().height(52.dp),
                ) {
                    if (busy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary) else Text(stringResource(R.string.login), fontWeight = FontWeight.Bold)
                }
                Text(stringResource(R.string.forgot_password), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
            }
        }
    }
}
