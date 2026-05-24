package com.guidepaw.companion

import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.SharedPreferences
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedCard
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Slider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import kotlin.math.roundToInt
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.Executors

// ── Brand colours ──────────────────────────────────────────────────────────
private val GpPrimary          = Color(0xFF0D6EFD)
private val GpPrimaryContainer = Color(0xFFE8F1FF)
private val GpOnSurface        = Color(0xFF0F172A)
private val GpOnSurfaceVariant = Color(0xFF334155)
private val GpOutline          = Color(0xFFE2E8F0)

private val GpColorScheme = lightColorScheme(
    primary             = GpPrimary,
    onPrimary           = Color.White,
    primaryContainer    = GpPrimaryContainer,
    onPrimaryContainer  = GpPrimary,
    surface             = Color.White,
    onSurface           = GpOnSurface,
    onSurfaceVariant    = GpOnSurfaceVariant,
    outline             = GpOutline,
)

@Composable
private fun GuidePawCompanionTheme(content: @Composable () -> Unit) =
    MaterialTheme(colorScheme = GpColorScheme, content = content)

// ── Navigation ─────────────────────────────────────────────────────────────
private enum class NavSection { OVERVIEW, TRAINING, DOGS, WEARABLES, MORE }

private val NAV_ITEMS = listOf(
    NavSection.OVERVIEW  to "🏠\nHome",
    NavSection.TRAINING  to "⚡\nLog",
    NavSection.DOGS      to "📋\nHistory",
    NavSection.WEARABLES to "🔔\nAlerts",
    NavSection.MORE      to "☰\nMenu",
)

// ── MainActivity ───────────────────────────────────────────────────────────
class MainActivity : AppCompatActivity() {

    // ── Non-UI fields ──────────────────────────────────────────────────────
    private val api    = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()
    private lateinit var prefs: SharedPreferences
    private var pendingDownloadId: Long = -1L
    private var updateReceiverRegistered = false

    // ── Compose-observed state ─────────────────────────────────────────────
    private var currentToken       by mutableStateOf<String?>(null)
    private var currentMe          by mutableStateOf<GuidePawMeResult?>(null)
    private var currentDogs        by mutableStateOf<List<GuidePawDogItem>>(emptyList())
    private var currentLogs        by mutableStateOf<List<GuidePawLogItem>>(emptyList())
    private var currentSuggestions by mutableStateOf<List<String>>(emptyList())
    private var currentActiveDogId by mutableStateOf<Int?>(null)
    private var currentEditingLogId by mutableStateOf<Int?>(null)
    private var currentSection     by mutableStateOf(NavSection.OVERVIEW)
    private var currentUnreadCount by mutableStateOf(0)
    private var currentRelease     by mutableStateOf<GuidePawAppReleaseResult?>(null)

    private var isLoading        by mutableStateOf(false)
    private var statusMessage    by mutableStateOf("")
    private var loginMessage     by mutableStateOf("")
    private var usernameText     by mutableStateOf("")
    private var passwordText     by mutableStateOf("")
    private var twoFactorText    by mutableStateOf("")
    private var recoveryKeyText  by mutableStateOf("")
    private var showTwoFactor    by mutableStateOf(false)
    private var showUpdateCard   by mutableStateOf(false)
    private var updateStatusText by mutableStateOf("")
    private var showMenu         by mutableStateOf(false)

    private var logLocation    by mutableStateOf("")
    private var logCityState   by mutableStateOf("")
    private var logType        by mutableStateOf(locationTypes.first())
    private var focusLevel     by mutableStateOf(3)
    private var logNotes       by mutableStateOf("")
    private var selectedSkills by mutableStateOf<Set<String>>(emptySet())
    private var trainMessage   by mutableStateOf("")
    private var saveLogLabel   by mutableStateOf("Save training log")
    private var wearablesBody  by mutableStateOf(
        "Wearable data will feed the active dog timeline once a dog is selected."
    )

    // ── Download receiver (unchanged) ───────────────────────────────────────
    private val updateDownloadReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            val completedId = intent?.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1L) ?: -1L
            if (completedId != pendingDownloadId || completedId <= 0L) return
            worker.execute {
                val manager = getSystemService(DOWNLOAD_SERVICE) as DownloadManager
                val cursor  = manager.query(DownloadManager.Query().setFilterById(completedId))
                cursor.use {
                    if (!it.moveToFirst()) {
                        runOnUiThread { updateStatusText = "Update downloaded but not found." }
                        return@execute
                    }
                    val statusIdx = it.getColumnIndex(DownloadManager.COLUMN_STATUS)
                    val uriIdx    = it.getColumnIndex(DownloadManager.COLUMN_LOCAL_URI)
                    val status    = if (statusIdx >= 0) it.getInt(statusIdx) else DownloadManager.STATUS_FAILED
                    val uriText   = if (uriIdx >= 0) it.getString(uriIdx) else null
                    if (status == DownloadManager.STATUS_SUCCESSFUL && !uriText.isNullOrBlank()) {
                        val apkUri = Uri.parse(uriText)
                        runOnUiThread {
                            updateStatusText = "Downloaded. Opening installer..."
                            launchInstaller(apkUri)
                        }
                    } else {
                        runOnUiThread { updateStatusText = "Download failed. Try again." }
                    }
                }
            }
        }
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        checkForAppUpdate()

        val storedToken = prefs.getString(KEY_TOKEN, null)
        if (!storedToken.isNullOrBlank()) {
            currentToken = storedToken
            statusMessage = "Restoring saved session..."
            restoreCachedDashboard()
            refreshDashboard(storedToken, null)
        } else {
            showLoggedOut("Sign in to load your dogs, logs, and training dashboard.")
        }

        setContent {
            GuidePawCompanionTheme { MainScreen() }
        }
    }

    override fun onDestroy() {
        unregisterUpdateReceiver()
        super.onDestroy()
        worker.shutdownNow()
    }

    override fun onResume() {
        super.onResume()
        checkForAppUpdate()
    }

    // ── Root composable ─────────────────────────────────────────────────────
    @Composable
    private fun MainScreen() {
        Scaffold(
            containerColor = MaterialTheme.colorScheme.surface,
            bottomBar      = { BottomNav() },
        ) { padding ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
            ) {
                if (showUpdateCard) UpdateBanner()
                if (isLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                if (statusMessage.isNotBlank()) {
                    Text(
                        text     = statusMessage,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
                        style    = MaterialTheme.typography.bodySmall,
                        color    = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (currentToken == null) {
                    LoginSection()
                } else {
                    when (currentSection) {
                        NavSection.OVERVIEW  -> OverviewSection()
                        NavSection.TRAINING  -> TrainingSection()
                        NavSection.DOGS      -> DogsSection()
                        NavSection.WEARABLES -> WearablesSection()
                        NavSection.MORE      -> OverviewSection()
                    }
                }
            }
        }
        if (showMenu) {
            MenuBottomSheet(onDismiss = { showMenu = false })
        }
    }

    // ── Bottom navigation — matches v0.016 MaterialButton row exactly ──────
    @Composable
    private fun BottomNav() {
        Surface(
            modifier        = Modifier.fillMaxWidth(),
            color           = Color.White,
            shadowElevation = 4.dp,
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(8.dp),
            ) {
                NAV_ITEMS.forEach { (section, label) ->
                    val selected = section != NavSection.MORE && currentSection == section
                    val bgColor  = if (selected) GpPrimaryContainer else Color.Transparent
                    val txtColor = if (selected) GpPrimary else Color(0xFF1F2937)
                    Box(modifier = Modifier.weight(1f)) {
                        TextButton(
                            onClick        = {
                                if (section == NavSection.MORE) showMenu = true
                                else currentSection = section
                            },
                            modifier       = Modifier
                                .fillMaxWidth()
                                .heightIn(min = 48.dp),
                            shape          = RoundedCornerShape(14.dp),
                            colors         = ButtonDefaults.textButtonColors(
                                containerColor = bgColor,
                                contentColor   = txtColor,
                            ),
                            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 5.dp),
                        ) {
                            Text(
                                text      = label,
                                fontSize  = 12.sp,
                                textAlign = TextAlign.Center,
                                lineHeight = 16.sp,
                                color     = txtColor,
                            )
                        }
                        if (section == NavSection.WEARABLES && currentUnreadCount > 0) {
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(top = 4.dp, end = 4.dp)
                                    .background(GpPrimary, RoundedCornerShape(50))
                                    .defaultMinSize(minWidth = 18.dp, minHeight = 18.dp)
                                    .padding(horizontal = 5.dp),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    text       = currentUnreadCount.toString(),
                                    color      = Color.White,
                                    fontSize   = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Update banner ───────────────────────────────────────────────────────
    @Composable
    private fun UpdateBanner() {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFFFFFBEB)),
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(updateStatusText, style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(onClick = { startAppUpdate() })     { Text("Update Now") }
                    OutlinedButton(onClick = { hideUpdateNotice() }) { Text("Dismiss") }
                }
            }
        }
    }

    // ── Login section ───────────────────────────────────────────────────────
    @Composable
    private fun LoginSection() {
        var showPassword by remember { mutableStateOf(false) }
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Text(
                "Sign in to GuidePaw",
                style      = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )
            if (loginMessage.isNotBlank()) {
                Text(loginMessage, color = MaterialTheme.colorScheme.error)
            }
            OutlinedTextField(
                value           = usernameText,
                onValueChange   = { usernameText = it },
                label           = { Text("Username") },
                singleLine      = true,
                keyboardOptions = KeyboardOptions(
                    keyboardType    = KeyboardType.Text,
                    autoCorrect     = false,
                    capitalization  = KeyboardCapitalization.None,
                ),
                modifier        = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value                = passwordText,
                onValueChange        = { passwordText = it },
                label                = { Text("Password") },
                singleLine           = true,
                visualTransformation = if (showPassword) VisualTransformation.None
                                       else PasswordVisualTransformation(),
                keyboardOptions      = KeyboardOptions(
                    keyboardType = KeyboardType.Password,
                    autoCorrect  = false,
                ),
                trailingIcon         = {
                    IconButton(onClick = { showPassword = !showPassword }) {
                        Icon(
                            imageVector         = if (showPassword) Icons.Filled.VisibilityOff
                                                  else Icons.Filled.Visibility,
                            contentDescription  = if (showPassword) "Hide password" else "Show password",
                        )
                    }
                },
                modifier             = Modifier.fillMaxWidth(),
            )
            if (showTwoFactor) {
                OutlinedTextField(
                    value           = twoFactorText,
                    onValueChange   = { twoFactorText = it },
                    label           = { Text("Two-factor code") },
                    singleLine      = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier        = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value           = recoveryKeyText,
                    onValueChange   = { recoveryKeyText = it },
                    label           = { Text("Recovery key (optional)") },
                    singleLine      = true,
                    keyboardOptions = KeyboardOptions(autoCorrect = false),
                    modifier        = Modifier.fillMaxWidth(),
                )
            }
            Button(
                onClick  = { attemptLogin() },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Sign In") }
            TextButton(
                onClick = { startActivity(Intent(this@MainActivity, FeedbackActivity::class.java)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Report an issue", fontSize = 13.sp, color = Color(0xFF6B7280)) }
        }
    }

    // ── Overview section ────────────────────────────────────────────────────
    @Composable
    private fun OverviewSection() {
        val me        = currentMe
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            if (me != null) {
                SummaryCard {
                    Text("Signed in as ${me.username}", fontWeight = FontWeight.SemiBold)
                    Text(
                        buildString {
                            append("Active dog: ")
                            append(activeDog?.name ?: "None selected")
                            activeDog?.breed?.let { append(" • $it") }
                            if (currentDogs.isNotEmpty()) append(" • ${currentDogs.size} dogs")
                        },
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    Text(
                        "Account synced. Training, logs, and dog access are up to date.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }
            if (currentSuggestions.isNotEmpty()) {
                SummaryCard {
                    Text("Training Suggestions", fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(4.dp))
                    currentSuggestions.forEach { s ->
                        Text("• $s", modifier = Modifier.padding(vertical = 2.dp))
                    }
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(
                    onClick   = { refreshCurrent() },
                    modifier  = Modifier.weight(1f),
                ) { Text("Refresh") }
                OutlinedButton(
                    onClick  = { signOut("Signed out.") },
                    modifier = Modifier.weight(1f),
                    colors   = ButtonDefaults.outlinedButtonColors(
                        contentColor = MaterialTheme.colorScheme.error,
                    ),
                ) { Text("Sign Out") }
            }
        }
    }

    // ── Training section ────────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
    @Composable
    private fun TrainingSection() {
        var dropdownExpanded by remember { mutableStateOf(false) }
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            if (trainMessage.isNotBlank()) {
                Text(trainMessage, color = GpOnSurfaceVariant)
            }
            OutlinedTextField(
                value         = logLocation,
                onValueChange = { logLocation = it },
                label         = { Text("Location name") },
                singleLine    = true,
                modifier      = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value         = logCityState,
                onValueChange = { logCityState = it },
                label         = { Text("City, State") },
                singleLine    = true,
                modifier      = Modifier.fillMaxWidth(),
            )
            ExposedDropdownMenuBox(
                expanded        = dropdownExpanded,
                onExpandedChange = { dropdownExpanded = it },
            ) {
                OutlinedTextField(
                    value         = logType,
                    onValueChange = {},
                    readOnly      = true,
                    label         = { Text("Location type") },
                    trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(dropdownExpanded) },
                    modifier      = Modifier.menuAnchor().fillMaxWidth(),
                )
                ExposedDropdownMenu(
                    expanded         = dropdownExpanded,
                    onDismissRequest = { dropdownExpanded = false },
                ) {
                    locationTypes.forEach { type ->
                        DropdownMenuItem(
                            text    = { Text(type) },
                            onClick = { logType = type; dropdownExpanded = false },
                        )
                    }
                }
            }
            Text("Focus level: $focusLevel", fontWeight = FontWeight.Medium)
            Slider(
                value         = focusLevel.toFloat(),
                onValueChange = { focusLevel = it.roundToInt() },
                valueRange    = 1f..5f,
                steps         = 3,
                modifier      = Modifier.fillMaxWidth(),
            )
            Text("Skills practiced", fontWeight = FontWeight.Medium)
            FlowRow(
                modifier             = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement   = Arrangement.spacedBy(4.dp),
            ) {
                skillOptions.forEach { skill ->
                    FilterChip(
                        selected = skill in selectedSkills,
                        onClick  = {
                            selectedSkills = if (skill in selectedSkills)
                                selectedSkills - skill else selectedSkills + skill
                        },
                        label    = { Text(skill, fontSize = 12.sp) },
                    )
                }
            }
            OutlinedTextField(
                value         = logNotes,
                onValueChange = { logNotes = it },
                label         = { Text("Handler notes") },
                minLines      = 3,
                modifier      = Modifier.fillMaxWidth(),
            )
            Button(
                onClick  = { submitTrainingLog() },
                enabled  = currentActiveDogId != null,
                modifier = Modifier.fillMaxWidth(),
            ) { Text(saveLogLabel) }
        }
    }

    // ── Dogs section ────────────────────────────────────────────────────────
    @Composable
    private fun DogsSection() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Your Dogs", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            if (currentDogs.isEmpty()) {
                Text("No dogs are accessible on this account.", color = GpOnSurfaceVariant)
            } else {
                Text(
                    "Tap a dog to make it active. The app uses the active dog for logs and daily tracking.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                currentDogs.forEach { DogCard(it) }
            }

            HorizontalDivider(modifier = Modifier.padding(vertical = 4.dp))
            Text("Recent Logs", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            if (currentLogs.isEmpty()) {
                Text("No training logs yet for the active dog.", color = GpOnSurfaceVariant)
            } else {
                currentLogs.take(8).forEach { LogCard(it) }
            }
        }
    }

    @Composable
    private fun DogCard(dog: GuidePawDogItem) {
        val active = dog.id == currentActiveDogId
        OutlinedCard(
            modifier = Modifier.fillMaxWidth(),
            colors   = CardDefaults.outlinedCardColors(
                containerColor = if (active) GpPrimaryContainer else MaterialTheme.colorScheme.surface,
            ),
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text(dog.name, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyLarge)
                Text(
                    listOfNotNull(
                        dog.breed,
                        dog.accessRole?.replaceFirstChar { it.uppercase() },
                        dog.lifecycleStatus?.replace('_', ' '),
                    ).joinToString(" • ").ifBlank { "Dog record" },
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
                Button(
                    onClick  = {
                        if (!active) {
                            setLoading(true, "Switching active dog...")
                            worker.execute {
                                try {
                                    val newId = api.setActiveDog(currentToken ?: return@execute, dog.id)
                                    runOnUiThread {
                                        currentActiveDogId = newId ?: dog.id
                                        refreshDashboard(currentToken ?: "", currentActiveDogId)
                                    }
                                } catch (e: Throwable) {
                                    runOnUiThread { setLoading(false, friendlyMessage(e.message, "Could not switch dog.")) }
                                }
                            }
                        }
                    },
                    enabled  = !active,
                    modifier = Modifier.fillMaxWidth(),
                ) { Text(if (active) "Active dog" else "Use this dog") }
            }
        }
    }

    @Composable
    private fun LogCard(log: GuidePawLogItem) {
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text(
                    log.locationName.ifBlank { "Training session" },
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    listOfNotNull(
                        log.logDate.takeIf { it.isNotBlank() },
                        log.locationCityState,
                        log.locationType,
                        "Focus ${log.focusLevel}",
                    ).joinToString(" • "),
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                if (log.skillsPracticed.isNotEmpty()) {
                    Spacer(Modifier.height(4.dp))
                    Text(log.skillsPracticed.joinToString(" · "), style = MaterialTheme.typography.bodySmall)
                }
                if (log.handlerNotes.isNotBlank()) {
                    Spacer(Modifier.height(4.dp))
                    Text(log.handlerNotes.take(220), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
                Spacer(Modifier.height(8.dp))
                OutlinedButton(
                    onClick  = { beginEditLog(log) },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Edit") }
            }
        }
    }

    // ── Wearables section ───────────────────────────────────────────────────
    @Composable
    private fun WearablesSection() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(
                "Wearables & Notifications",
                style      = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
            )
            SummaryCard { Text(wearablesBody) }
            Button(
                onClick  = { openWebPage("https://guidepaw.app/wearable_integrations.php") },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Open Wearables Setup") }
            OutlinedButton(
                onClick  = { startActivity(Intent(this@MainActivity, NotificationCenterActivity::class.java)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Notification Center") }
            OutlinedButton(
                onClick  = { openWebPage("https://guidepaw.app/notifications.php") },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Notification Settings (Web)") }
        }
    }

    // ── Menu bottom sheet ───────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun MenuBottomSheet(onDismiss: () -> Unit) {
        ModalBottomSheet(
            onDismissRequest = onDismiss,
            sheetState       = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp)
                    .padding(bottom = 32.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Text(
                    "GuidePaw Menu",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                )
                Row(
                    modifier              = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    OutlinedButton(
                        onClick  = { onDismiss(); openWebPage("https://guidepaw.app/settings.php") },
                        modifier = Modifier.weight(1f),
                    ) { Text("⚙️ Settings") }
                    OutlinedButton(
                        onClick  = { onDismiss(); signOut("Signed out.") },
                        modifier = Modifier.weight(1f),
                        colors   = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                    ) { Text("↩️ Sign Out") }
                }
                MenuSheetSection("Dog", listOf(
                    "👤 Handler Profile"   to { openWebPage("https://guidepaw.app/handler_profile.php") },
                    "🐕 Dogs"              to { currentSection = NavSection.DOGS },
                    "🪪 Dog Profile"       to { openWebPage("https://guidepaw.app/dog_profile.php") },
                    "📡 QR Tracking"       to { openWebPage("https://guidepaw.app/qr_tracking.php") },
                    "🤝 Dog Access"        to { openWebPage("https://guidepaw.app/dog_access.php") },
                    "🧾 Dog Audit"         to { openWebPage("https://guidepaw.app/dog_access_audit.php") },
                    "📊 Stats"             to { openWebPage("https://guidepaw.app/stats.php") },
                ), onDismiss)
                MenuSheetSection("Logs", listOf(
                    "⚡ Quick Session"    to { currentSection = NavSection.TRAINING },
                    "📝 Detailed Log"     to { currentSection = NavSection.TRAINING },
                    "📋 History"          to { currentSection = NavSection.DOGS },
                    "🎥 Media Review"     to { openWebPage("https://guidepaw.app/media_review.php") },
                    "🎞️ Video Review"    to { openWebPage("https://guidepaw.app/video_review.php") },
                ), onDismiss)
                MenuSheetSection("Training", listOf(
                    "🎓 Training Program"      to { currentSection = NavSection.TRAINING },
                    "🐾 Candidate Assessment"  to { openWebPage("https://guidepaw.app/candidate_assessment.php") },
                    "🔎 Candidate Comparison"  to { openWebPage("https://guidepaw.app/candidate_comparison.php") },
                    "⚠️ Behavior Risk"         to { openWebPage("https://guidepaw.app/behavior_risk_scoring.php") },
                    "♻️ Regression Engine"     to { openWebPage("https://guidepaw.app/regression_engine.php") },
                    "🧩 Goal Builder"          to { openWebPage("https://guidepaw.app/goal_builder.php") },
                    "🏅 Community Challenges"  to { openWebPage("https://guidepaw.app/community_challenges.php") },
                    "🚚 Trucking Mode"         to { openWebPage("https://guidepaw.app/trucking_mode.php") },
                    "🛡️ Tactical Training"    to { openWebPage("https://guidepaw.app/tactical_training.php") },
                    "🎯 Goal Intake"           to { openWebPage("https://guidepaw.app/training_goal_intake.php") },
                    "🛠️ Habit Repair"         to { openWebPage("https://guidepaw.app/habit_repair.php") },
                    "✅ Session Log"            to { currentSection = NavSection.TRAINING },
                    "📚 Training History"      to { currentSection = NavSection.DOGS },
                    "🧭 Coach Review"          to { openWebPage("https://guidepaw.app/coach_review.php") },
                ), onDismiss)
                MenuSheetSection("Care", listOf(
                    "🩺 Health Docs"      to { openWebPage("https://guidepaw.app/dog_health.php") },
                    "📅 Vet Appointments" to { openWebPage("https://guidepaw.app/appointments.php") },
                    "💊 Medications"      to { openWebPage("https://guidepaw.app/medications.php") },
                    "⌚ Wearable Sync"    to { openWebPage("https://guidepaw.app/wearable_integrations.php") },
                ), onDismiss)
                MenuSheetSection("More", listOf(
                    "🔔 Notification Center"    to { startActivity(Intent(this@MainActivity, NotificationCenterActivity::class.java)) },
                    "🧠 Smart Alerts"           to { openWebPage("https://guidepaw.app/alerts.php") },
                    "❓ Breed Finder"           to { openWebPage("https://guidepaw.app/breed_questionnaire.php") },
                    "🤝 Community"              to { openWebPage("https://guidepaw.app/community.php") },
                    "💙 Support Funding"        to { openWebPage("https://guidepaw.app/support_funding.php") },
                    "🏷️ Plans"                 to { openWebPage("https://guidepaw.app/paywalls.php") },
                    "📇 Contact Us"             to { openWebPage("https://guidepaw.app/contact_us.php") },
                    "🪪 ADA Access Card"        to { openWebPage("https://guidepaw.app/ada_access_card.php") },
                    "⚖️ Detailed ADA Notes"    to { openWebPage("https://guidepaw.app/service_dog_rights.php") },
                    "✈️ Air Travel Rights"     to { openWebPage("https://guidepaw.app/air_travel_rights.php") },
                    "✅ Certification"           to { openWebPage("https://guidepaw.app/certification.php") },
                    "💬 Feedback / Bug Report"  to { startActivity(Intent(this@MainActivity, FeedbackActivity::class.java)) },
                    "📖 Public Guides"          to { openWebPage("https://guidepaw.app/app.php") },
                    "❓ FAQ"                    to { openWebPage("https://guidepaw.app/faq.php") },
                    "🔎 Breed Comparisons"      to { openWebPage("https://guidepaw.app/breed_comparison_hub.php") },
                    "🌿 Breed Family Guide"     to { openWebPage("https://guidepaw.app/breed_family_guide.php") },
                    "⚖️ Legal Info"             to { openWebPage("https://guidepaw.app/service_dog_esa_legal_info.php") },
                ), onDismiss)
            }
        }
    }

    @Composable
    private fun MenuSheetSection(title: String, items: List<Pair<String, () -> Unit>>, onDismiss: () -> Unit) {
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(12.dp)) {
                Text(
                    title,
                    style      = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold,
                    color      = GpOnSurfaceVariant,
                    modifier   = Modifier.padding(bottom = 8.dp),
                )
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    items.chunked(2).forEach { pair ->
                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            pair.forEach { (label, action) ->
                                OutlinedButton(
                                    onClick        = { onDismiss(); action() },
                                    modifier       = Modifier.weight(1f),
                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 8.dp),
                                ) {
                                    Text(label, fontSize = 12.sp, textAlign = TextAlign.Center, maxLines = 2)
                                }
                            }
                            if (pair.size == 1) Spacer(Modifier.weight(1f))
                        }
                    }
                }
            }
        }
    }

    // ── Shared composable ───────────────────────────────────────────────────
    @Composable
    private fun SummaryCard(content: @Composable ColumnScope.() -> Unit) {
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier            = Modifier.padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
                content             = content,
            )
        }
    }

    // ── Business logic (minimal changes from original) ──────────────────────
    private fun attemptLogin() {
        val username    = usernameText.trim()
        val password    = passwordText
        val totpCode    = twoFactorText.trim()
        val recoveryKey = recoveryKeyText.trim()

        if (username.isBlank() || password.isBlank()) {
            loginMessage = "Username and password are required."
            return
        }

        setLoading(true, "Signing in...")
        worker.execute {
            try {
                val result = api.login(username, password, "GuidePaw Companion", totpCode, recoveryKey)
                runOnUiThread {
                    if (result.requiresTwoFactor && result.token.isNullOrBlank()) {
                        showTwoFactorPrompt(friendlyMessage(result.message, "Two-factor authentication is required."))
                        setLoading(false, "Two-factor required.")
                        return@runOnUiThread
                    }
                    if (!result.success || result.token.isNullOrBlank()) {
                        loginMessage = friendlyMessage(result.message, "Sign in failed.")
                        setLoading(false, null)
                        return@runOnUiThread
                    }
                    saveToken(result.token)
                    statusMessage = "Signed in. Loading dashboard..."
                    refreshDashboard(result.token, null)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    val payload = e.payload
                    if (e.statusCode == 401 && payload?.optBoolean("requires_2fa", false) == true) {
                        showTwoFactorPrompt(payload.optString("message") ?: "Two-factor authentication is required.")
                    } else {
                        loginMessage = friendlyMessage(e.message, "Sign in failed.")
                        setLoading(false, null)
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    loginMessage = friendlyMessage(t.message, "Sign in failed.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun refreshCurrent() {
        val token = currentToken ?: prefs.getString(KEY_TOKEN, null)
        if (token.isNullOrBlank()) { showLoggedOut("Sign in to load the dashboard."); return }
        refreshDashboard(token, currentActiveDogId, keepSignedInOnFailure = currentMe != null)
        checkForAppUpdate()
    }

    private fun refreshDashboard(token: String, preferDogId: Int?, keepSignedInOnFailure: Boolean = false) {
        setLoading(true, "Refreshing dashboard...")
        worker.execute {
            try {
                val me    = api.me(token)
                val dogs  = api.dogs(token)
                var dogId = preferDogId ?: me.activeDogId ?: dogs.firstOrNull()?.id
                if (dogId != null && dogId > 0 && dogId != me.activeDogId) {
                    dogId = api.setActiveDog(token, dogId) ?: dogId
                }
                val logsResult   = api.logs(token, dogId)
                val unreadCount  = runCatching { api.notifications(token).visibleUnreadCount }.getOrDefault(0)
                runOnUiThread {
                    currentToken        = token
                    currentMe           = me
                    currentDogs         = dogs
                    currentActiveDogId  = dogId ?: logsResult.activeDogId
                    currentLogs         = logsResult.logs
                    currentSuggestions  = logsResult.trainingSuggestions
                    currentUnreadCount  = unreadCount
                    saveCachedDashboard()
                    renderDashboard()
                    setLoading(false, "Dashboard updated.")
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    val msg = friendlyMessage(e.message, "Could not refresh dashboard.")
                    if (keepSignedInOnFailure) { statusMessage = msg }
                    else {
                        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
                        showLoggedOut(friendlyMessage(e.message, "Session expired. Please sign in again."))
                    }
                    setLoading(false, msg)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    val msg = friendlyMessage(t.message, "Could not refresh dashboard.")
                    if (keepSignedInOnFailure) { statusMessage = msg }
                    else {
                        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
                        showLoggedOut(msg)
                    }
                    setLoading(false, msg)
                }
            }
        }
    }

    private fun renderDashboard() {
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        wearablesBody = if (activeDog == null)
            "Wearable data will feed the active dog timeline once a dog is selected."
        else
            "Wearable data is tied to ${activeDog.name} and will flow into the same handler timeline."
        trainMessage = if (activeDog == null)
            "Pick an active dog before saving a training log."
        else
            "Training log will save to ${activeDog.name}."
        statusMessage = "Signed in as ${currentMe?.username.orEmpty()}"
        loginMessage  = ""
    }

    private fun submitTrainingLog() {
        val token = currentToken ?: return
        val dogId = currentActiveDogId ?: return
        if (logLocation.isBlank()) { trainMessage = "Location name is required."; return }

        setLoading(true, "Saving training log...")
        worker.execute {
            try {
                val response = api.saveLog(
                    token        = token,
                    dogId        = dogId,
                    logId        = currentEditingLogId,
                    locationName = logLocation,
                    cityState    = logCityState,
                    locationType = logType,
                    focusLevel   = focusLevel,
                    skills       = selectedSkills.toList(),
                    notes        = logNotes,
                )
                runOnUiThread {
                    trainMessage   = response.message ?: "Training log saved."
                    logNotes       = ""
                    selectedSkills = emptySet()
                    clearEditLog()
                    refreshDashboard(token, dogId)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    trainMessage = friendlyMessage(e.message, "Could not save training log.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    trainMessage = friendlyMessage(t.message, "Could not save training log.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun showTwoFactorPrompt(message: String) {
        currentToken = null
        currentMe    = null
        showTwoFactor = true
        loginMessage  = message
        isLoading     = false
    }

    private fun showLoggedOut(message: String) {
        currentToken        = null
        currentMe           = null
        currentDogs         = emptyList()
        currentLogs         = emptyList()
        currentSuggestions  = emptyList()
        currentActiveDogId  = null
        currentEditingLogId = null
        currentUnreadCount  = 0
        prefs.edit().remove(KEY_CACHE).commit()
        loginMessage  = message
        statusMessage = "Signed out"
        isLoading     = false
        syncUpdateCardVisibility()
    }

    private fun signOut(message: String) {
        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
        twoFactorText   = ""
        recoveryKeyText = ""
        showTwoFactor   = false
        showLoggedOut(message)
    }

    private fun saveToken(token: String) {
        currentToken = token
        prefs.edit().putString(KEY_TOKEN, token).commit()
    }

    private fun beginEditLog(log: GuidePawLogItem) {
        currentEditingLogId = log.id
        logLocation         = log.locationName
        logCityState        = log.locationCityState ?: ""
        logType             = log.locationType ?: locationTypes.first()
        focusLevel          = log.focusLevel.coerceIn(1, 5)
        logNotes            = log.handlerNotes
        selectedSkills      = log.skillsPracticed.toSet()
        saveLogLabel        = "Update training log"
        currentSection      = NavSection.TRAINING
        trainMessage        = "Editing log from ${log.logDate.takeIf { it.isNotBlank() } ?: "recent session"}."
    }

    private fun clearEditLog() {
        currentEditingLogId = null
        saveLogLabel        = "Save training log"
    }

    private fun openWebPage(url: String) =
        GuidePawNavigation.openUrl(this, url, accessToken = currentToken)

    private fun setLoading(loading: Boolean, message: String?) {
        isLoading = loading
        if (message != null) statusMessage = message
    }

    private fun checkForAppUpdate() {
        worker.execute {
            try {
                val release = api.appRelease()
                runOnUiThread {
                    currentRelease = release
                    val local   = CompanionAppVersion.VERSION_CODE
                    val ignored = prefs.getInt(KEY_IGNORED_UPDATE_CODE, -1)
                    if (release.versionCode != local && release.versionCode != ignored) {
                        val direction = if (release.versionCode < local) "downgrade to" else "update to"
                        updateStatusText = "v${release.versionName} available — tap to $direction it. (${release.apkFile})"
                        showUpdateCard   = true
                    } else {
                        hideUpdateNotice()
                    }
                }
            } catch (_: Throwable) {
                runOnUiThread { if (currentRelease == null) showUpdateCard = false }
            }
        }
    }

    private fun syncUpdateCardVisibility() {
        val release = currentRelease ?: return
        val local   = CompanionAppVersion.VERSION_CODE
        val ignored = prefs.getInt(KEY_IGNORED_UPDATE_CODE, -1)
        showUpdateCard = release.versionCode != local && release.versionCode != ignored
    }

    private fun hideUpdateNotice() {
        currentRelease?.let { prefs.edit().putInt(KEY_IGNORED_UPDATE_CODE, it.versionCode).commit() }
        showUpdateCard = false
    }

    private fun startAppUpdate() {
        val release = currentRelease
        if (release == null || release.apkUrl.isBlank()) {
            updateStatusText = "No update package is available yet."
            return
        }
        updateStatusText   = "Downloading ${release.versionName}..."
        val manager        = getSystemService(DOWNLOAD_SERVICE) as DownloadManager
        val request        = DownloadManager.Request(Uri.parse(release.apkUrl))
            .setTitle("GuidePaw Companion update")
            .setDescription("Download ${release.apkFile}")
            .setMimeType("application/vnd.android.package-archive")
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setAllowedOverMetered(true)
            .setAllowedOverRoaming(true)
        pendingDownloadId  = manager.enqueue(request)
        registerUpdateReceiver()
    }

    private fun launchInstaller(apkUri: Uri) {
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(apkUri, "application/vnd.android.package-archive")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        try { startActivity(intent) }
        catch (t: Throwable) { updateStatusText = friendlyMessage(t.message, "Open the downloaded APK to install the update.") }
    }

    private fun registerUpdateReceiver() {
        if (updateReceiverRegistered) return
        val filter = IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(updateDownloadReceiver, filter, Context.RECEIVER_EXPORTED)
        } else {
            @Suppress("UnspecifiedRegisterReceiverFlag")
            registerReceiver(updateDownloadReceiver, filter)
        }
        updateReceiverRegistered = true
    }

    private fun unregisterUpdateReceiver() {
        if (!updateReceiverRegistered) return
        runCatching { unregisterReceiver(updateDownloadReceiver) }
        updateReceiverRegistered = false
    }


    // ── Cache helpers (unchanged) ───────────────────────────────────────────
    private fun restoreCachedDashboard() {
        val raw = prefs.getString(KEY_CACHE, null).orEmpty()
        if (raw.isBlank()) return
        runCatching {
            val cache = JSONObject(raw)
            currentMe = cache.optJSONObject("me")?.let {
                GuidePawMeResult(
                    username    = it.optString("username", ""),
                    activeDogId = optNullableInt(it, "activeDogId"),
                )
            }
            currentDogs = cache.optJSONArray("dogs")?.let { arr ->
                (0 until arr.length()).mapNotNull { i ->
                    val o = arr.optJSONObject(i) ?: return@mapNotNull null
                    GuidePawDogItem(
                        id              = o.optInt("id", 0),
                        name            = o.optString("name", "Dog"),
                        breed           = o.optText("breed"),
                        ownerUsername   = o.optText("ownerUsername"),
                        accessRole      = o.optText("accessRole"),
                        lifecycleStatus = o.optText("lifecycleStatus"),
                    )
                }
            }.orEmpty()
            currentLogs = cache.optJSONArray("logs")?.let { arr ->
                (0 until arr.length()).mapNotNull { i ->
                    val o = arr.optJSONObject(i) ?: return@mapNotNull null
                    GuidePawLogItem(
                        id                = o.optInt("id", 0),
                        logDate           = o.optString("logDate", ""),
                        locationName      = o.optString("locationName", ""),
                        locationCityState = o.optText("locationCityState"),
                        locationType      = o.optText("locationType"),
                        focusLevel        = o.optInt("focusLevel", 3),
                        skillsPracticed   = o.optJSONArray("skillsPracticed")?.toStringList().orEmpty(),
                        handlerNotes      = o.optString("handlerNotes", ""),
                    )
                }
            }.orEmpty()
            currentSuggestions = cache.optJSONArray("suggestions")?.toStringList().orEmpty()
            currentActiveDogId = optNullableInt(cache, "activeDogId")
            if (currentMe != null || currentDogs.isNotEmpty() || currentLogs.isNotEmpty()) {
                renderDashboard()
                setLoading(false, "Loaded saved dashboard state.")
            }
        }
    }

    private fun saveCachedDashboard() {
        val me = currentMe ?: return
        val cache = JSONObject()
            .put("me", JSONObject()
                .put("username", me.username)
                .put("activeDogId", me.activeDogId ?: JSONObject.NULL))
            .put("activeDogId", currentActiveDogId ?: JSONObject.NULL)
            .put("dogs", JSONArray(currentDogs.map { dog ->
                JSONObject()
                    .put("id", dog.id)
                    .put("name", dog.name)
                    .put("breed", dog.breed ?: JSONObject.NULL)
                    .put("ownerUsername", dog.ownerUsername ?: JSONObject.NULL)
                    .put("accessRole", dog.accessRole ?: JSONObject.NULL)
                    .put("lifecycleStatus", dog.lifecycleStatus ?: JSONObject.NULL)
            }))
            .put("logs", JSONArray(currentLogs.map { log ->
                JSONObject()
                    .put("id", log.id)
                    .put("logDate", log.logDate)
                    .put("locationName", log.locationName)
                    .put("locationCityState", log.locationCityState ?: JSONObject.NULL)
                    .put("locationType", log.locationType ?: JSONObject.NULL)
                    .put("focusLevel", log.focusLevel)
                    .put("skillsPracticed", JSONArray(log.skillsPracticed))
                    .put("handlerNotes", log.handlerNotes)
            }))
            .put("suggestions", JSONArray(currentSuggestions))
        prefs.edit().putString(KEY_CACHE, cache.toString()).commit()
    }

    private fun friendlyMessage(message: String?, fallback: String): String {
        val raw = message?.trim().orEmpty()
        if (raw.isBlank()) return fallback
        val suspicious = listOf("sqlstate", "select ", "insert ", "update ", "delete ",
            "pdo", "syntax error", "near \"", "column ", "table ")
            .any { raw.contains(it, ignoreCase = true) }
        return if (suspicious) fallback else raw
    }

    private fun optNullableInt(json: JSONObject, key: String): Int? {
        if (!json.has(key) || json.isNull(key)) return null
        val value = json.optString(key, "").trim()
        return if (value.isNotBlank()) value.toIntOrNull() ?: json.optInt(key, 0).takeIf { it > 0 } else null
    }

    private fun JSONObject.optText(key: String): String? =
        optString(key, "").trim().takeIf { it.isNotBlank() }

    private fun JSONArray.toStringList(): List<String> =
        (0 until length()).mapNotNull { optString(it, "").trim().takeIf { s -> s.isNotBlank() } }

    companion object {
        private val locationTypes = listOf("In-Cab", "Truck Stop", "Shipper/Receiver", "Public Store", "Rest Area", "Other")
        private val skillOptions  = listOf(
            "Focus / Watch me", "Loose leash", "Settle", "Recall", "Task work",
            "Sit/Stay", "Heel", "Leave It", "Under Tuck", "DPT Task", "PA Focus",
        )
        private const val PREFS_NAME              = "guidepaw_companion"
        private const val KEY_TOKEN               = "auth_token"
        private const val KEY_CACHE               = "dashboard_cache"
        private const val KEY_IGNORED_UPDATE_CODE = "ignored_update_code"
    }
}
