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
import androidx.compose.foundation.layout.width
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
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
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedCard
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Slider
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
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Pets
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
private enum class NavSection { OVERVIEW, TRAINING, DOGS, WEARABLES, MORE, GOAL_INTAKE, GOAL_BUILDER, HABIT_REPAIR, BEHAVIOR_RISK, REGRESSION, CANDIDATE_ASSESSMENT }

private val NAV_ITEMS = listOf(
    NavSection.OVERVIEW,
    NavSection.TRAINING,
    NavSection.DOGS,
    NavSection.WEARABLES,
    NavSection.MORE,
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

    private var isLoading          by mutableStateOf(false)
    private var isStatusError      by mutableStateOf(false)
    private var isPullingToRefresh by mutableStateOf(false)
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

    // ── Goal Intake state ──────────────────────────────────────────────────
    private var goalIntakeGoals       by mutableStateOf<List<GpTrainingGoalItem>>(emptyList())
    private var goalIntakeFilter      by mutableStateOf("active")
    private var goalIntakeMessage     by mutableStateOf("")
    private var goalIntakeDogId       by mutableStateOf(0)
    private var goalIntakeCategory    by mutableStateOf("potty")
    private var goalIntakeProblem     by mutableStateOf("")
    private var goalIntakeDesired     by mutableStateOf("")
    private var goalIntakeContext     by mutableStateOf("")
    private var goalIntakeTrigger     by mutableStateOf("")
    private var goalIntakeBudget      by mutableStateOf("3")
    private var goalIntakeReinforcer  by mutableStateOf("")
    private var goalIntakeSafetyRisk  by mutableStateOf(false)
    private var goalIntakeCriteria    by mutableStateOf("")
    private var goalIntakeMaintenance by mutableStateOf("")

    // ── Goal Builder state ─────────────────────────────────────────────────
    private var goalBuilderDogId       by mutableStateOf(0)
    private var goalBuilderCategory    by mutableStateOf("other")
    private var goalBuilderProblem     by mutableStateOf("")
    private var goalBuilderDesired     by mutableStateOf("")
    private var goalBuilderContext     by mutableStateOf("")
    private var goalBuilderTrigger     by mutableStateOf("")
    private var goalBuilderBudget      by mutableStateOf("3")
    private var goalBuilderReinforcer  by mutableStateOf("")
    private var goalBuilderSafetyRisk  by mutableStateOf(false)
    private var goalBuilderCriteria    by mutableStateOf("")
    private var goalBuilderMaintenance by mutableStateOf("")
    private var goalBuilderShowDraft   by mutableStateOf(false)
    private var goalBuilderMessage     by mutableStateOf("")

    // ── Habit Repair state ─────────────────────────────────────────────────
    private var habitRepairProtocols   by mutableStateOf<List<GpHabitRepairProtocol>>(emptyList())
    private var habitRepairIncidents   by mutableStateOf<List<GpBehaviorIncidentItem>>(emptyList())
    private var habitRepairProtocolKey by mutableStateOf("potty_accidents")
    private var habitRepairMessage     by mutableStateOf("")
    private var habitRepairContext     by mutableStateOf("")
    private var habitRepairTrigger     by mutableStateOf("")
    private var habitRepairSeverity    by mutableStateOf(2)
    private var habitRepairNotes       by mutableStateOf("")

    // ── Behavior Risk state ────────────────────────────────────────────────
    private var behaviorRiskResult  by mutableStateOf<GpBehaviorRiskResult?>(null)
    private var behaviorRiskMessage by mutableStateOf("")

    // ── Regression Engine state ────────────────────────────────────────────
    private var regressionResult          by mutableStateOf<GpRegressionResult?>(null)
    private var regressionMessage         by mutableStateOf("")
    private var regressionExpandedEventId by mutableStateOf(-1)
    private var regressionEditStatus      by mutableStateOf("open")
    private var regressionEditPlan        by mutableStateOf("")

    // ── Candidate Assessment state ─────────────────────────────────────────
    private var candidateResult     by mutableStateOf<GpCandidateAssessmentsResult?>(null)
    private var candidateMessage    by mutableStateOf("")
    private var candidateDogId      by mutableStateOf(0)
    private var candidateScores     by mutableStateOf(linkedMapOf(
        "confidence_score"         to 3,
        "startle_recovery_score"   to 3,
        "handler_engagement_score" to 3,
        "food_motivation_score"    to 3,
        "toy_motivation_score"     to 3,
        "settle_score"             to 3,
        "human_neutrality_score"   to 3,
        "dog_neutrality_score"     to 3,
        "environment_score"        to 3,
        "handling_score"           to 3,
    ))
    private var candidateHealthNotes  by mutableStateOf("")
    private var candidateSafetyFlags  by mutableStateOf("")
    private var candidateDogExpanded  by mutableStateOf(false)

    private var logLocation    by mutableStateOf("")
    private var logCityState   by mutableStateOf("")
    private var logType        by mutableStateOf(locationTypes.first())
    private var focusLevel     by mutableStateOf(3)
    private var logNotes       by mutableStateOf("")
    private var selectedSkills by mutableStateOf<Set<String>>(emptySet())
    private var trainMessage   by mutableStateOf("")
    private var saveLogLabel   by mutableStateOf("Save training log")

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
                if (isLoading && !isPullingToRefresh) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                if (statusMessage.isNotBlank()) {
                    Text(
                        text     = statusMessage,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
                        style    = MaterialTheme.typography.bodySmall,
                        color    = if (isStatusError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (currentToken == null) {
                    LoginSection()
                } else {
                    when (currentSection) {
                        NavSection.OVERVIEW      -> OverviewSection()
                        NavSection.TRAINING      -> TrainingSection()
                        NavSection.DOGS          -> DogsSection()
                        NavSection.WEARABLES     -> WearablesSection()
                        NavSection.MORE          -> OverviewSection()
                        NavSection.GOAL_INTAKE          -> GoalIntakeSection()
                        NavSection.GOAL_BUILDER         -> GoalBuilderSection()
                        NavSection.HABIT_REPAIR         -> HabitRepairSection()
                        NavSection.BEHAVIOR_RISK        -> BehaviorRiskSection()
                        NavSection.REGRESSION           -> RegressionSection()
                        NavSection.CANDIDATE_ASSESSMENT -> CandidateAssessmentSection()
                    }
                }
            }
        }
        if (showMenu) {
            MenuBottomSheet(onDismiss = { showMenu = false })
        }
    }

    // ── Bottom navigation ───────────────────────────────────────────────────
    @Composable
    private fun BottomNav() {
        NavigationBar {
            NAV_ITEMS.forEach { section ->
                val icon = when (section) {
                    NavSection.OVERVIEW  -> Icons.Filled.Home
                    NavSection.TRAINING  -> Icons.Filled.Bolt
                    NavSection.DOGS      -> Icons.Filled.Pets
                    NavSection.WEARABLES -> Icons.Filled.Notifications
                    else                 -> Icons.Filled.Menu
                }
                val title = when (section) {
                    NavSection.OVERVIEW  -> "Home"
                    NavSection.TRAINING  -> "Log"
                    NavSection.DOGS      -> "History"
                    NavSection.WEARABLES -> "Alerts"
                    else                 -> "Menu"
                }
                NavigationBarItem(
                    selected = section != NavSection.MORE && currentSection == section,
                    onClick  = {
                        if (section == NavSection.MORE) showMenu = true
                        else currentSection = section
                    },
                    icon     = {
                        if (section == NavSection.WEARABLES && currentUnreadCount > 0) {
                            BadgedBox(badge = { Badge { Text(currentUnreadCount.toString()) } }) {
                                Icon(icon, contentDescription = title)
                            }
                        } else {
                            Icon(icon, contentDescription = title)
                        }
                    },
                    label    = { Text(title) },
                )
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
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun OverviewSection() {
        val me        = currentMe
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        val recentLogs = currentLogs.take(2)
        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; refreshCurrent() },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Active dog hero card
            SummaryCard {
                if (activeDog != null) {
                    Text(
                        activeDog.name,
                        style      = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        buildString {
                            activeDog.breed?.let { append(it) }
                            activeDog.lifecycleStatus?.let {
                                if (isNotEmpty()) append(" • ")
                                append(it)
                            }
                        }.ifEmpty { "Active dog" },
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    if (currentDogs.size > 1) {
                        Text(
                            "${currentDogs.size} dogs accessible · tap Dogs to switch",
                            style = MaterialTheme.typography.bodySmall,
                            color = GpOnSurfaceVariant,
                        )
                    }
                } else {
                    Text("No active dog", fontWeight = FontWeight.SemiBold)
                    Text(
                        "Go to Dogs to select one.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
                if (me != null) {
                    Spacer(Modifier.height(2.dp))
                    Text(
                        "Signed in as ${me.username}",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }

            // Quick actions
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(
                    onClick  = { currentSection = NavSection.TRAINING },
                    modifier = Modifier.weight(1f),
                ) { Text("Log Training") }
                OutlinedButton(
                    onClick  = { currentSection = NavSection.DOGS },
                    modifier = Modifier.weight(1f),
                ) { Text("View History") }
            }

            // Recent activity
            SummaryCard {
                Text("Recent Activity", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                if (recentLogs.isEmpty()) {
                    Text(
                        "No training logs yet for this dog.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                } else {
                    recentLogs.forEach { log ->
                        Text(
                            listOfNotNull(
                                log.logDate.takeIf { it.isNotBlank() },
                                log.locationName.ifBlank { "Training session" },
                                log.locationCityState,
                                "Focus ${log.focusLevel}",
                            ).joinToString(" · "),
                            style = MaterialTheme.typography.bodySmall,
                        )
                        if (log.skillsPracticed.isNotEmpty()) {
                            Text(
                                log.skillsPracticed.joinToString(", "),
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )
                        }
                        if (log != recentLogs.last()) Spacer(Modifier.height(6.dp))
                    }
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
    }

    // ── Training section ────────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
    @Composable
    private fun TrainingSection() {
        var dropdownExpanded by remember { mutableStateOf(false) }
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        val isEditing = currentEditingLogId != null

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Active dog context
            SummaryCard {
                if (activeDog != null) {
                    Text("Logging for ${activeDog.name}", fontWeight = FontWeight.SemiBold)
                } else {
                    Text("No active dog", fontWeight = FontWeight.SemiBold)
                    Text(
                        "Go to Dogs to select one before logging.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }

            // Edit mode banner with Cancel, or plain status message after submit
            if (isEditing) {
                OutlinedCard(
                    modifier = Modifier.fillMaxWidth(),
                    colors   = CardDefaults.outlinedCardColors(containerColor = GpPrimaryContainer),
                ) {
                    Row(
                        modifier              = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 14.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment     = Alignment.CenterVertically,
                    ) {
                        Text(
                            trainMessage.ifBlank { "Editing existing log" },
                            style    = MaterialTheme.typography.bodySmall,
                            modifier = Modifier.weight(1f),
                        )
                        TextButton(onClick = {
                            clearEditLog()
                            logLocation    = ""
                            logCityState   = ""
                            logType        = locationTypes.first()
                            focusLevel     = 3
                            logNotes       = ""
                            selectedSkills = emptySet()
                            trainMessage   = ""
                        }) { Text("Cancel") }
                    }
                }
            } else if (trainMessage.isNotBlank()) {
                Text(trainMessage, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            // Location
            Text("Location", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
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

            // Focus
            Text("Focus level: $focusLevel", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
            Slider(
                value         = focusLevel.toFloat(),
                onValueChange = { focusLevel = it.roundToInt() },
                valueRange    = 1f..5f,
                steps         = 3,
                modifier      = Modifier.fillMaxWidth(),
            )

            // Skills
            Text("Skills practiced", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
            FlowRow(
                modifier              = Modifier.fillMaxWidth(),
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

            // Notes
            OutlinedTextField(
                value         = logNotes,
                onValueChange = { logNotes = it },
                label         = { Text("Handler notes (optional)") },
                minLines      = 3,
                modifier      = Modifier.fillMaxWidth(),
            )

            Button(
                onClick  = { submitTrainingLog() },
                enabled  = currentActiveDogId != null,
                modifier = Modifier.fillMaxWidth(),
            ) { Text(saveLogLabel) }

            if (currentActiveDogId == null) {
                Text(
                    "Select an active dog to enable logging.",
                    style     = MaterialTheme.typography.bodySmall,
                    color     = GpOnSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier  = Modifier.fillMaxWidth(),
                )
            }
        }
    }

    // ── Dogs section ────────────────────────────────────────────────────────
    @Composable
    private fun DogsSection() {
        var showDogPicker by remember { mutableStateOf(false) }
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Compact active-dog header with optional Switch toggle
            SummaryCard {
                if (activeDog != null) {
                    Row(
                        modifier             = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment    = Alignment.CenterVertically,
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(activeDog.name, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyLarge)
                            Text(
                                listOfNotNull(
                                    activeDog.breed,
                                    activeDog.lifecycleStatus?.replace('_', ' '),
                                ).joinToString(" • ").ifBlank { "Active dog" },
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )
                        }
                        if (currentDogs.size > 1) {
                            TextButton(onClick = { showDogPicker = !showDogPicker }) {
                                Text(if (showDogPicker) "Done" else "Switch")
                            }
                        }
                    }
                } else {
                    Text("No active dog", fontWeight = FontWeight.SemiBold)
                    Text(
                        if (currentDogs.isEmpty()) "No dogs are accessible on this account."
                        else "Select a dog below.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }

            // Dog picker — visible when Switch is tapped or no dog is active
            if (showDogPicker || activeDog == null) {
                currentDogs.forEach { dog ->
                    DogCard(dog, onSelected = { showDogPicker = false })
                }
            }

            // Log history for the active dog
            Text(
                if (activeDog != null) "${activeDog.name}'s Logs" else "Recent Logs",
                style      = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
            )
            if (currentLogs.isEmpty()) {
                SummaryCard {
                    Text(
                        "No training logs yet for this dog.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            } else {
                currentLogs.take(8).forEach { LogCard(it) }
            }
        }
    }

    @Composable
    private fun DogCard(dog: GuidePawDogItem, onSelected: () -> Unit = {}) {
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
                                        onSelected()
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
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Wearable data
            Text("Wearable Data", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
            SummaryCard {
                if (activeDog != null) {
                    Text("Tied to ${activeDog.name}", fontWeight = FontWeight.SemiBold)
                    Text(
                        "Health Connect, tracker imports, and vendor feeds flow into ${activeDog.name}'s handler timeline alongside training logs.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                } else {
                    Text("No active dog", fontWeight = FontWeight.SemiBold)
                    Text(
                        "Wearable data attaches to the active dog's timeline. Select a dog first.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }
            Button(
                onClick  = { openWebPage("https://guidepaw.app/wearable_integrations.php") },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Wearable Setup") }

            HorizontalDivider(modifier = Modifier.padding(vertical = 4.dp))

            // Notifications
            Text("Notifications", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
            if (currentUnreadCount > 0) {
                SummaryCard {
                    Text(
                        "$currentUnreadCount unread notification${if (currentUnreadCount == 1) "" else "s"}",
                        fontWeight = FontWeight.SemiBold,
                        color      = GpPrimary,
                    )
                }
            }
            Button(
                onClick  = { startActivity(Intent(this@MainActivity, NotificationCenterActivity::class.java)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Notification Center") }
            OutlinedButton(
                onClick  = { openWebPage("https://guidepaw.app/notifications.php") },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Notification Settings (Web)") }
        }
    }

    // ── Goal Intake section ─────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
    @Composable
    private fun GoalIntakeSection() {
        var catExpanded by remember { mutableStateOf(false) }
        var dogExpanded by remember { mutableStateOf(false) }
        val categoryLabels = mapOf(
            "potty"          to "Potty routine",
            "leash"          to "Loose leash / pulling",
            "barking"        to "Barking",
            "cab_calm"       to "Cab calm / settling",
            "jumping"        to "Jumping",
            "public_manners" to "Public manners",
            "psd_foundation" to "PSD/PTSD foundation",
            "other"          to "Other",
        )
        val activeDog = currentDogs.firstOrNull { it.id == (goalIntakeDogId.takeIf { it > 0 } ?: currentActiveDogId) }

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadGoalIntake(goalIntakeFilter) },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text(
                    "Goal Intake",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            // Filter tabs
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("active", "archived", "all").forEach { f ->
                    FilterChip(
                        selected = goalIntakeFilter == f,
                        onClick  = { goalIntakeFilter = f; loadGoalIntake(f) },
                        label    = { Text(f.replaceFirstChar { it.uppercase() }) },
                    )
                }
            }

            SectionMessage(goalIntakeMessage, onRetry = { loadGoalIntake(goalIntakeFilter) })

            // New goal form
            SummaryCard {
                Text("New Training Goal", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                if (currentDogs.isEmpty()) {
                    Text("No dogs on this account. Add a dog profile first.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                } else {
                    // Dog selector
                    ExposedDropdownMenuBox(expanded = dogExpanded, onExpandedChange = { dogExpanded = it }) {
                        OutlinedTextField(
                            value         = activeDog?.name ?: "Select dog",
                            onValueChange = {},
                            readOnly      = true,
                            label         = { Text("Dog") },
                            trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(dogExpanded) },
                            modifier      = Modifier.menuAnchor().fillMaxWidth(),
                        )
                        ExposedDropdownMenu(expanded = dogExpanded, onDismissRequest = { dogExpanded = false }) {
                            currentDogs.forEach { dog ->
                                DropdownMenuItem(
                                    text    = { Text(dog.name) },
                                    onClick = { goalIntakeDogId = dog.id; dogExpanded = false },
                                )
                            }
                        }
                    }
                    // Category selector
                    ExposedDropdownMenuBox(expanded = catExpanded, onExpandedChange = { catExpanded = it }) {
                        OutlinedTextField(
                            value         = categoryLabels[goalIntakeCategory] ?: goalIntakeCategory,
                            onValueChange = {},
                            readOnly      = true,
                            label         = { Text("Goal category") },
                            trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(catExpanded) },
                            modifier      = Modifier.menuAnchor().fillMaxWidth(),
                        )
                        ExposedDropdownMenu(expanded = catExpanded, onDismissRequest = { catExpanded = false }) {
                            categoryLabels.forEach { (key, label) ->
                                DropdownMenuItem(
                                    text    = { Text(label) },
                                    onClick = { goalIntakeCategory = key; catExpanded = false },
                                )
                            }
                        }
                    }
                    OutlinedTextField(
                        value         = goalIntakeProblem,
                        onValueChange = { goalIntakeProblem = it },
                        label         = { Text("What problem are you trying to solve? *") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeDesired,
                        onValueChange = { goalIntakeDesired = it },
                        label         = { Text("What should the dog do instead?") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeContext,
                        onValueChange = { goalIntakeContext = it },
                        label         = { Text("Where does it happen?") },
                        singleLine    = true,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeTrigger,
                        onValueChange = { goalIntakeTrigger = it },
                        label         = { Text("What triggers it?") },
                        singleLine    = true,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeBudget,
                        onValueChange = { goalIntakeBudget = it },
                        label         = { Text("Daily training time budget (minutes)") },
                        singleLine    = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeReinforcer,
                        onValueChange = { goalIntakeReinforcer = it },
                        label         = { Text("Best rewards") },
                        singleLine    = true,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier          = Modifier.fillMaxWidth(),
                    ) {
                        FilterChip(
                            selected = goalIntakeSafetyRisk,
                            onClick  = { goalIntakeSafetyRisk = !goalIntakeSafetyRisk },
                            label    = { Text("⚠️ This may be a safety issue") },
                        )
                    }
                    OutlinedTextField(
                        value         = goalIntakeCriteria,
                        onValueChange = { goalIntakeCriteria = it },
                        label         = { Text("Success criteria *") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = goalIntakeMaintenance,
                        onValueChange = { goalIntakeMaintenance = it },
                        label         = { Text("Maintenance plan") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    Button(
                        onClick  = { submitTrainingGoal() },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Save Goal") }
                }
            }

            // Recent goals
            if (goalIntakeGoals.isNotEmpty()) {
                Text("Recent Goals (${goalIntakeFilter})", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                goalIntakeGoals.forEach { goal ->
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Row(
                                modifier              = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment     = Alignment.Top,
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(goal.dogName, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                                    Text(
                                        categoryLabels[goal.goalCategory] ?: goal.goalCategory,
                                        style = MaterialTheme.typography.bodySmall,
                                        color = GpOnSurfaceVariant,
                                    )
                                }
                                Text(goal.status, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                            }
                            if (goal.currentProblem.isNotBlank()) {
                                Text(goal.currentProblem, style = MaterialTheme.typography.bodySmall)
                            }
                            if (goal.successCriteria.isNotBlank()) {
                                Text(
                                    "→ ${goal.successCriteria}",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = GpOnSurfaceVariant,
                                )
                            }
                            if (goal.status == "active") {
                                TextButton(
                                    onClick  = { archiveGoal(goal.id) },
                                    modifier = Modifier.align(Alignment.End),
                                ) { Text("Archive", fontSize = 12.sp) }
                            }
                        }
                    }
                }
            } else if (isLoading) {
                SummaryCard {
                    Text("Loading goals...", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
            } else if (goalIntakeMessage.isBlank()) {
                SummaryCard {
                    Text(
                        "No ${goalIntakeFilter} goals found.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
            }
        }
        }
    }

    // ── Goal Builder section ────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun GoalBuilderSection() {
        data class GbCat(
            val label: String, val icon: String,
            val problemHint: String, val desiredHint: String, val contextHint: String,
            val successHint: String, val maintenanceHint: String, val rewardHint: String,
            val pathTitle: String, val pathSummary: String,
        )
        val cats = remember {
            mapOf(
                "potty" to GbCat("Potty routine", "🚻",
                    "Accidents, schedule drift, or unclear potty habits.",
                    "Dog eliminates on schedule with clean routines.",
                    "Home, yard, truck stop, rest area, or hotel.",
                    "Potty happens promptly after wake-up, meals, and breaks.",
                    "Keep the routine consistent and reduce freedom only after success.",
                    "Use a fast food reward, praise, and immediate release.",
                    "Potty routine path", "Log the reps, keep timing tight, and reinforce the same potty cue."),
                "leash" to GbCat("Loose leash / pulling", "🐾",
                    "Pulling, forging, or hard leash pressure on walks.",
                    "Dog walks with a loose leash and checks in voluntarily.",
                    "Sidewalks, parking lots, truck stops, and quiet neighborhoods.",
                    "Walk 20 steps with loose leash and at least 2 check-ins.",
                    "Short routes, quick resets, and reward before the leash tightens.",
                    "Reward position, eye contact, and calm movement.",
                    "Loose leash path", "Build this through the training ladder first, then log sessions to keep the work short and repeatable."),
                "barking" to GbCat("Barking", "🔇",
                    "Barking at triggers, doors, windows, or social excitement.",
                    "Dog notices the trigger and chooses quiet recovery.",
                    "Cab, home, yard, or public threshold areas.",
                    "Dog can see the trigger and recover without repeated barking.",
                    "Lower the difficulty, manage distance, and reward quiet check-ins.",
                    "Use calm praise, food, and distance from the trigger.",
                    "Quiet recovery path", "Focus on calm recovery, distance management, and a short follow-up session after each trigger."),
                "cab_calm" to GbCat("Cab calm / settling", "🚚",
                    "Restlessness, whining, pacing, or difficulty settling in the truck.",
                    "Dog settles quietly in the cab or crate.",
                    "Truck cab, sleeper, crate, or resting mat.",
                    "Dog settles within 60 seconds and holds calm for the target time.",
                    "Start after potty and short movement, then slowly lengthen duration.",
                    "Use a chew, mat reward, and quiet reinforcement.",
                    "Cab calm path", "Record the settle reps so you can see what works in-cab."),
                "jumping" to GbCat("Jumping", "⬇️",
                    "Jumping on people during greetings or excitement.",
                    "Dog keeps four paws on the floor and offers a sit or settle.",
                    "Doorway, home greeting, store entrance, or truck stop.",
                    "Dog greets with four paws down in 3 repeated opportunities.",
                    "Prevent rehearsals, reward the floor, and keep greetings brief.",
                    "Use food, access, and calm greeting praise.",
                    "Greeting control path", "Keep the reward timing tied to four paws on the floor."),
                "public_manners" to GbCat("Public manners", "🏪",
                    "Over-arousal, scanning, or difficulty staying focused in public.",
                    "Dog stays neutral and responsive in public spaces.",
                    "Stores, lobbies, rest areas, sidewalks, and waiting rooms.",
                    "Dog completes one short outing without threshold blowups.",
                    "Use easier outings first, then add duration or distractions slowly.",
                    "Reward calm focus, loose leash, and clean exits.",
                    "Public manners path", "Use the training program so public access work and proofing stay aligned."),
                "psd_foundation" to GbCat("PSD foundation", "🎓",
                    "Need a structured foundation for service-dog work.",
                    "Dog builds neutrality, recovery, and handler engagement.",
                    "Low-distraction home setups, planned field trips, and controlled public reps.",
                    "Dog can complete the foundation behavior with stable recovery.",
                    "Keep the work short, clear, and repeatable.",
                    "Use high-value food and quick release.",
                    "PSD foundation path", "Start with Candidate Assessment, then use the training ladder for neutrality, recovery, and core service work."),
                "other" to GbCat("Other", "🧩",
                    "A different behavior or routine that needs a measurable plan.",
                    "A clear observable behavior the dog can repeat.",
                    "The environment where the issue happens most often.",
                    "One behavior the handler can measure without guessing.",
                    "Short sessions, clear cue, and predictable reinforcement.",
                    "Pick the reinforcer the dog works for most reliably.",
                    "Custom path", "Refine the problem, then attach the goal to the most relevant training page before saving."),
            )
        }

        var catExpanded by remember { mutableStateOf(false) }
        var dogExpanded by remember { mutableStateOf(false) }
        val activeDog = currentDogs.firstOrNull { it.id == (goalBuilderDogId.takeIf { it > 0 } ?: currentActiveDogId) }
        val cat = cats[goalBuilderCategory] ?: cats["other"]!!

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text(
                    "Goal Builder",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            SectionMessage(goalBuilderMessage)

            if (!goalBuilderShowDraft) {
                SummaryCard {
                    Text("${cat.icon} ${cat.label}", fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(2.dp))
                    Text(
                        "Fill in what you know — blanks will be filled with category hints when you tap Build Draft.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    Spacer(Modifier.height(8.dp))
                    if (currentDogs.isEmpty()) {
                        Text("No dogs on this account. Add a dog profile first.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    } else {
                        ExposedDropdownMenuBox(expanded = dogExpanded, onExpandedChange = { dogExpanded = it }) {
                            OutlinedTextField(
                                value         = activeDog?.name ?: "Select dog",
                                onValueChange = {},
                                readOnly      = true,
                                label         = { Text("Dog") },
                                trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(dogExpanded) },
                                modifier      = Modifier.menuAnchor().fillMaxWidth(),
                            )
                            ExposedDropdownMenu(expanded = dogExpanded, onDismissRequest = { dogExpanded = false }) {
                                currentDogs.forEach { dog ->
                                    DropdownMenuItem(text = { Text(dog.name) }, onClick = { goalBuilderDogId = dog.id; dogExpanded = false })
                                }
                            }
                        }
                        ExposedDropdownMenuBox(expanded = catExpanded, onExpandedChange = { catExpanded = it }) {
                            OutlinedTextField(
                                value         = "${cat.icon} ${cat.label}",
                                onValueChange = {},
                                readOnly      = true,
                                label         = { Text("Goal category") },
                                trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(catExpanded) },
                                modifier      = Modifier.menuAnchor().fillMaxWidth(),
                            )
                            ExposedDropdownMenu(expanded = catExpanded, onDismissRequest = { catExpanded = false }) {
                                cats.forEach { (key, c) ->
                                    DropdownMenuItem(text = { Text("${c.icon} ${c.label}") }, onClick = { goalBuilderCategory = key; catExpanded = false })
                                }
                            }
                        }
                        OutlinedTextField(
                            value         = goalBuilderProblem,
                            onValueChange = { goalBuilderProblem = it },
                            label         = { Text("What problem are you trying to solve?") },
                            placeholder   = { Text(cat.problemHint, style = MaterialTheme.typography.bodySmall) },
                            minLines      = 2,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value         = goalBuilderDesired,
                            onValueChange = { goalBuilderDesired = it },
                            label         = { Text("What should the dog do instead?") },
                            placeholder   = { Text(cat.desiredHint, style = MaterialTheme.typography.bodySmall) },
                            minLines      = 2,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value         = goalBuilderContext,
                            onValueChange = { goalBuilderContext = it },
                            label         = { Text("Where does it happen?") },
                            placeholder   = { Text(cat.contextHint, style = MaterialTheme.typography.bodySmall) },
                            singleLine    = true,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value         = goalBuilderTrigger,
                            onValueChange = { goalBuilderTrigger = it },
                            label         = { Text("What triggers it?") },
                            placeholder   = { Text("The most common trigger or context that causes the behavior.") },
                            singleLine    = true,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value           = goalBuilderBudget,
                            onValueChange   = { goalBuilderBudget = it },
                            label           = { Text("Daily training time budget (minutes)") },
                            singleLine      = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            modifier        = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value         = goalBuilderReinforcer,
                            onValueChange = { goalBuilderReinforcer = it },
                            label         = { Text("Best rewards") },
                            placeholder   = { Text(cat.rewardHint, style = MaterialTheme.typography.bodySmall) },
                            singleLine    = true,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                            FilterChip(
                                selected = goalBuilderSafetyRisk,
                                onClick  = { goalBuilderSafetyRisk = !goalBuilderSafetyRisk },
                                label    = { Text("⚠️ This may be a safety issue") },
                            )
                        }
                        OutlinedTextField(
                            value         = goalBuilderCriteria,
                            onValueChange = { goalBuilderCriteria = it },
                            label         = { Text("Success criteria") },
                            placeholder   = { Text(cat.successHint, style = MaterialTheme.typography.bodySmall) },
                            minLines      = 2,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value         = goalBuilderMaintenance,
                            onValueChange = { goalBuilderMaintenance = it },
                            label         = { Text("Maintenance plan") },
                            placeholder   = { Text(cat.maintenanceHint, style = MaterialTheme.typography.bodySmall) },
                            minLines      = 2,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                        Button(
                            onClick = {
                                if (goalBuilderProblem.isBlank())     goalBuilderProblem    = cat.problemHint
                                if (goalBuilderDesired.isBlank())     goalBuilderDesired    = cat.desiredHint
                                if (goalBuilderContext.isBlank())     goalBuilderContext    = cat.contextHint
                                if (goalBuilderTrigger.isBlank())     goalBuilderTrigger    = "The most common trigger or context that causes the behavior."
                                if (goalBuilderReinforcer.isBlank())  goalBuilderReinforcer = cat.rewardHint
                                if (goalBuilderCriteria.isBlank())    goalBuilderCriteria   = cat.successHint
                                if (goalBuilderMaintenance.isBlank()) goalBuilderMaintenance = cat.maintenanceHint
                                goalBuilderShowDraft = true
                            },
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text("Build Draft") }
                    }
                }
            } else {
                SummaryCard {
                    Text("${cat.icon} Draft Preview", fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(8.dp))
                    GoalBuilderDraftRow("Problem", goalBuilderProblem)
                    GoalBuilderDraftRow("Desired behavior", goalBuilderDesired)
                    GoalBuilderDraftRow("Context", goalBuilderContext)
                    GoalBuilderDraftRow("Trigger", goalBuilderTrigger)
                    GoalBuilderDraftRow("Success criteria", goalBuilderCriteria)
                    GoalBuilderDraftRow("Maintenance plan", goalBuilderMaintenance)
                    GoalBuilderDraftRow("Rewards", goalBuilderReinforcer)
                    GoalBuilderDraftRow("Time budget", "${goalBuilderBudget} min")
                    if (goalBuilderSafetyRisk) {
                        Text("⚠️ Safety risk flagged", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.height(4.dp))
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(cat.pathTitle, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                    Text(cat.pathSummary, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    Spacer(Modifier.height(12.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        OutlinedButton(onClick = { goalBuilderShowDraft = false }, modifier = Modifier.weight(1f)) { Text("Edit") }
                        Button(onClick = { submitGoalBuilderGoal() }, modifier = Modifier.weight(1f)) { Text("Save Goal") }
                    }
                }
            }
        }
    }

    @Composable
    private fun GoalBuilderDraftRow(label: String, value: String) {
        if (value.isBlank()) return
        Column(modifier = Modifier.padding(bottom = 6.dp)) {
            Text(label, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
            Text(value, style = MaterialTheme.typography.bodySmall)
        }
    }

    // ── Habit Repair section ────────────────────────────────────────────────
    @OptIn(ExperimentalLayoutApi::class, ExperimentalMaterial3Api::class)
    @Composable
    private fun HabitRepairSection() {
        var dogExpanded by remember { mutableStateOf(false) }
        var logDogId    by remember { mutableStateOf(currentActiveDogId ?: currentDogs.firstOrNull()?.id ?: 0) }
        val activeProtocol = habitRepairProtocols.firstOrNull { it.key == habitRepairProtocolKey }
            ?: habitRepairProtocols.firstOrNull()

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadHabitRepair() },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text(
                    "Habit Repair",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            SectionMessage(habitRepairMessage, onRetry = { loadHabitRepair() })

            // Protocol selector
            if (habitRepairProtocols.isNotEmpty()) {
                FlowRow(
                    modifier              = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalArrangement   = Arrangement.spacedBy(4.dp),
                ) {
                    habitRepairProtocols.forEach { protocol ->
                        FilterChip(
                            selected = habitRepairProtocolKey == protocol.key,
                            onClick  = { habitRepairProtocolKey = protocol.key },
                            label    = { Text(protocol.title, fontSize = 12.sp) },
                        )
                    }
                }
            }

            // Protocol detail
            if (activeProtocol != null) {
                SummaryCard {
                    Text(activeProtocol.title, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodyLarge)
                    Text(activeProtocol.time, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    activeProtocol.steps.forEachIndexed { i, step ->
                        Text(
                            "${i + 1}. $step",
                            style   = MaterialTheme.typography.bodySmall,
                            modifier = Modifier.padding(bottom = 4.dp),
                        )
                    }
                }
            } else if (isLoading) {
                SummaryCard { Text("Loading protocols...", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
            }

            // Log form
            SummaryCard {
                Text("Log this issue", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                if (currentDogs.isEmpty()) {
                    Text("No dogs on this account.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                } else {
                    val logDog = currentDogs.firstOrNull { it.id == logDogId }
                    ExposedDropdownMenuBox(expanded = dogExpanded, onExpandedChange = { dogExpanded = it }) {
                        OutlinedTextField(
                            value         = logDog?.name ?: "Select dog",
                            onValueChange = {},
                            readOnly      = true,
                            label         = { Text("Dog") },
                            trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(dogExpanded) },
                            modifier      = Modifier.menuAnchor().fillMaxWidth(),
                        )
                        ExposedDropdownMenu(expanded = dogExpanded, onDismissRequest = { dogExpanded = false }) {
                            currentDogs.forEach { dog ->
                                DropdownMenuItem(
                                    text    = { Text(dog.name) },
                                    onClick = { logDogId = dog.id; dogExpanded = false },
                                )
                            }
                        }
                    }
                    OutlinedTextField(
                        value         = habitRepairContext,
                        onValueChange = { habitRepairContext = it },
                        label         = { Text("Where did it happen?") },
                        singleLine    = true,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    OutlinedTextField(
                        value         = habitRepairTrigger,
                        onValueChange = { habitRepairTrigger = it },
                        label         = { Text("What triggered it?") },
                        singleLine    = true,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    Text(
                        "Severity: $habitRepairSeverity",
                        style      = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Medium,
                    )
                    Slider(
                        value         = habitRepairSeverity.toFloat(),
                        onValueChange = { habitRepairSeverity = it.roundToInt() },
                        valueRange    = 1f..5f,
                        steps         = 3,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    Text(
                        "1 minor · 2 mild · 3 moderate · 4 serious · 5 safety concern",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    OutlinedTextField(
                        value         = habitRepairNotes,
                        onValueChange = { habitRepairNotes = it },
                        label         = { Text("Notes") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth(),
                    )
                    Button(
                        onClick  = { submitBehaviorIncident(logDogId, activeProtocol?.key ?: "potty_accidents") },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Save Incident") }
                }
            }

            // Recent incidents
            if (habitRepairIncidents.isNotEmpty()) {
                Text("Recent Incidents", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                habitRepairIncidents.forEach { incident ->
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Row(
                                modifier              = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                Text(incident.dogName, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                                Text("Severity ${incident.severity}", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                            }
                            Text(incident.incidentType.replace('_', ' '), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            if (incident.contextEnvironment.isNotBlank()) Text(incident.contextEnvironment, style = MaterialTheme.typography.bodySmall)
                            TextButton(
                                onClick  = { archiveBehaviorIncident(incident.id) },
                                modifier = Modifier.align(Alignment.End),
                            ) { Text("Archive", fontSize = 12.sp) }
                        }
                    }
                }
            }
        }
        }
    }

    // ── Behavior Risk section ───────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun BehaviorRiskSection() {
        val result = behaviorRiskResult
        val bandColor = when (result?.band) {
            "high"     -> Color(0xFFB91C1C)
            "moderate" -> Color(0xFFB45309)
            else       -> Color(0xFF0F766E)
        }

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadBehaviorRisk(behaviorRiskResult?.dogId) },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text(
                    "Behavior Risk",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            SectionMessage(behaviorRiskMessage, onRetry = { loadBehaviorRisk(behaviorRiskResult?.dogId) })

            // Dog selector
            if (currentDogs.size > 1) {
                SummaryCard {
                    Text("Score for dog", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
                    Spacer(Modifier.height(6.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            onClick  = { loadBehaviorRisk(null) },
                            colors   = if (result?.dogId == null) ButtonDefaults.buttonColors() else ButtonDefaults.outlinedButtonColors(),
                        ) { Text("All dogs", fontSize = 12.sp) }
                        currentDogs.forEach { dog ->
                            OutlinedButton(
                                onClick  = { loadBehaviorRisk(dog.id) },
                                colors   = if (result?.dogId == dog.id) ButtonDefaults.buttonColors() else ButtonDefaults.outlinedButtonColors(),
                            ) { Text(dog.name, fontSize = 12.sp) }
                        }
                    }
                }
            }

            if (result == null) {
                SummaryCard {
                    Text(
                        if (isLoading) "Loading risk assessment..." else "No assessment loaded.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    if (!isLoading) {
                        Spacer(Modifier.height(8.dp))
                        Button(onClick = { loadBehaviorRisk(currentActiveDogId) }) { Text("Load Assessment") }
                    }
                }
            } else {
                // Score card
                SummaryCard {
                    Text("Current Risk", style = MaterialTheme.typography.labelLarge, color = GpOnSurfaceVariant)
                    Text(
                        result.score.toString(),
                        style      = MaterialTheme.typography.displayMedium,
                        fontWeight = FontWeight.Black,
                        color      = bandColor,
                    )
                    Surface(
                        shape = RoundedCornerShape(999.dp),
                        color = bandColor.copy(alpha = 0.12f),
                    ) {
                        Text(
                            result.band.replaceFirstChar { it.uppercase() },
                            modifier   = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                            style      = MaterialTheme.typography.labelMedium,
                            fontWeight = FontWeight.Bold,
                            color      = bandColor,
                        )
                    }
                    if (result.openRegressions > 0) {
                        Text(
                            "Open regressions: ${result.openRegressions}",
                            style = MaterialTheme.typography.bodySmall,
                            color = GpOnSurfaceVariant,
                        )
                    }
                }

                // What drove the score
                if (result.reasons.isNotEmpty()) {
                    SummaryCard {
                        Text("What drove the score", fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(4.dp))
                        result.reasons.forEach { reason ->
                            Text("• $reason", style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(bottom = 2.dp))
                        }
                    }
                }

                // Recommendations
                if (result.recommendations.isNotEmpty()) {
                    SummaryCard {
                        Text("Recommended next steps", fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(4.dp))
                        result.recommendations.forEach { rec ->
                            Text("• $rec", style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(bottom = 2.dp))
                        }
                    }
                }

                // Recent incidents
                if (result.incidents.isNotEmpty()) {
                    Text("Recent Behavior Incidents", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                    result.incidents.forEach { incident ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                ) {
                                    Text(incident.dogName, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.bodySmall)
                                    Text("Sev ${incident.severity}", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                }
                                Text(incident.incidentType.replace('_', ' '), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                if (incident.triggerDescription.isNotBlank()) Text(incident.triggerDescription, style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }

                // Candidate assessment
                val candidate = result.candidate
                if (candidate != null) {
                    SummaryCard {
                        Text("Latest Candidate Assessment", fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(4.dp))
                        Text("Dog: ${candidate.dogName}", style = MaterialTheme.typography.bodySmall)
                        Text("Focus level: ${candidate.focusLevelRecommended}", style = MaterialTheme.typography.bodySmall)
                        if (candidate.recommendation.isNotBlank()) {
                            Text(candidate.recommendation, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        }
                        if (candidate.safetyFlags.isNotBlank()) {
                            Text("Safety flags: ${candidate.safetyFlags}", style = MaterialTheme.typography.bodySmall, color = Color(0xFFB91C1C))
                        }
                    }
                }

                OutlinedButton(
                    onClick  = { loadBehaviorRisk(result.dogId) },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Refresh") }
            }
        }
        }
    }

    // ── Menu bottom sheet ───────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun MenuBottomSheet(onDismiss: () -> Unit) {
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
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
                // Identity
                SummaryCard {
                    if (activeDog != null) Text(activeDog.name, fontWeight = FontWeight.SemiBold)
                    currentMe?.let {
                        Text(
                            "Signed in as ${it.username}",
                            style = MaterialTheme.typography.bodySmall,
                            color = GpOnSurfaceVariant,
                        )
                    }
                }
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
                    "👤 Handler Profile" to { openWebPage("https://guidepaw.app/handler_profile.php") },
                    "🐕 Dogs"            to { currentSection = NavSection.DOGS },
                    "🪪 Dog Profile"     to { openWebPage("https://guidepaw.app/dog_profile.php") },
                    "🤝 Dog Access"      to { openWebPage("https://guidepaw.app/dog_access.php") },
                    "📡 QR Tracking"     to { openWebPage("https://guidepaw.app/qr_tracking.php") },
                    "📊 Stats"           to { openWebPage("https://guidepaw.app/stats.php") },
                ), onDismiss)
                MenuSheetSection("Training", listOf(
                    "⚡ Log Training"         to { currentSection = NavSection.TRAINING },
                    "🎯 Goal Intake"          to { loadGoalIntake(goalIntakeFilter); currentSection = NavSection.GOAL_INTAKE },
                    "🛠️ Habit Repair"        to { loadHabitRepair(); currentSection = NavSection.HABIT_REPAIR },
                    "⚠️ Behavior Risk"        to { loadBehaviorRisk(currentActiveDogId); currentSection = NavSection.BEHAVIOR_RISK },
                    "♻️ Regression Engine"   to { loadRegressionEvents(); currentSection = NavSection.REGRESSION },
                    "🐾 Candidate Assessment" to { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_ASSESSMENT },
                    "🧩 Goal Builder"         to { currentSection = NavSection.GOAL_BUILDER },
                ), onDismiss)
                MenuSheetSection("Care", listOf(
                    "🩺 Health Docs"      to { openWebPage("https://guidepaw.app/dog_health.php") },
                    "📅 Vet Appointments" to { openWebPage("https://guidepaw.app/appointments.php") },
                    "💊 Medications"      to { openWebPage("https://guidepaw.app/medications.php") },
                    "⌚ Wearable Sync"    to { openWebPage("https://guidepaw.app/wearable_integrations.php") },
                ), onDismiss)
                MenuSheetSection("More", listOf(
                    "🔔 Notifications"     to { startActivity(Intent(this@MainActivity, NotificationCenterActivity::class.java)) },
                    "🧠 Smart Alerts"      to { openWebPage("https://guidepaw.app/alerts.php") },
                    "💬 Feedback"          to { startActivity(Intent(this@MainActivity, FeedbackActivity::class.java)) },
                    "🪪 ADA Access Card"   to { openWebPage("https://guidepaw.app/ada_access_card.php") },
                    "✅ Certification"      to { openWebPage("https://guidepaw.app/certification.php") },
                    "🏷️ Plans"            to { openWebPage("https://guidepaw.app/paywalls.php") },
                    "❓ FAQ"               to { openWebPage("https://guidepaw.app/faq.php") },
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

    @Composable
    private fun SectionMessage(message: String, onRetry: (() -> Unit)? = null) {
        if (message.isBlank()) return
        val isError = isErrorText(message)
        if (isError) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
            ) {
                Row(
                    modifier          = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(
                        text     = message,
                        style    = MaterialTheme.typography.bodySmall,
                        color    = MaterialTheme.colorScheme.onErrorContainer,
                        modifier = Modifier.weight(1f),
                    )
                    if (onRetry != null) {
                        Spacer(Modifier.width(8.dp))
                        TextButton(onClick = onRetry) { Text("Retry") }
                    }
                }
            }
        } else {
            Text(
                text  = message,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.primary,
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
        trainMessage  = ""
        statusMessage = "Signed in as ${currentMe?.username.orEmpty()}"
        loginMessage  = ""
    }

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun RegressionSection() {
        val statusLabels = linkedMapOf(
            "open"             to "Open",
            "in_review"        to "In review",
            "paused_for_review" to "Paused",
            "resolved"         to "Resolved",
            "closed"           to "Closed",
        )
        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadRegressionEvents() },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("Regression Engine", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            SectionMessage(regressionMessage, onRetry = { loadRegressionEvents() })

            val result = regressionResult
            if (result != null) {
                SummaryCard {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
                        Column {
                            Text(result.dogName, fontWeight = FontWeight.SemiBold)
                        }
                        Surface(color = MaterialTheme.colorScheme.primaryContainer, shape = MaterialTheme.shapes.small) {
                            Text("${result.openCount} open", modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), style = MaterialTheme.typography.labelSmall)
                        }
                    }
                }

                // Reset plan (static)
                SummaryCard {
                    Text("Reset plan", fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(bottom = 8.dp))
                    listOf(
                        "1. Return to the easier step." to "Keep the current behavior short, quiet, and predictable until success returns.",
                        "2. Keep reinforcement high." to "Use the best reward available for one clean win instead of extending the session.",
                        "3. Re-check before raising difficulty." to "If the pattern repeats, keep the plan easy and revisit coach review when needed.",
                    ).forEach { (title, body) ->
                        Column(modifier = Modifier.padding(vertical = 4.dp)) {
                            Text(title, fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodyMedium)
                            Text(body, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        }
                    }
                }

                // Events
                SummaryCard {
                    Text("Open regression events", fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    if (result.events.isEmpty()) {
                        Text("No open regression events for this dog.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    } else {
                        result.events.forEach { event ->
                            val isEditing = regressionExpandedEventId == event.id
                            OutlinedCard(modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.Top,
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text(event.detectedReason.ifBlank { "Regression event" }, fontWeight = FontWeight.Medium)
                                            val meta = buildString {
                                                if (event.moduleTitle.isNotBlank()) append(event.moduleTitle)
                                                if (event.goalCategory.isNotBlank()) { if (isNotEmpty()) append(" • "); append(event.goalCategory) }
                                                if (event.createdAt.length >= 10) { if (isNotEmpty()) append(" • "); append(event.createdAt.take(10)) }
                                            }
                                            if (meta.isNotBlank()) Text(meta, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                        }
                                        Surface(
                                            color = if (event.status == "paused_for_review") Color(0xFFFEF3C7) else MaterialTheme.colorScheme.surfaceVariant,
                                            shape = MaterialTheme.shapes.small,
                                            modifier = Modifier.padding(start = 8.dp),
                                        ) {
                                            Text(statusLabels[event.status] ?: event.status, modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp), style = MaterialTheme.typography.labelSmall)
                                        }
                                    }
                                    if (event.recommendedAction.isNotBlank() && !isEditing) {
                                        Surface(color = Color(0xFFFFFBEB), shape = MaterialTheme.shapes.small, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                                            Text(event.recommendedAction, modifier = Modifier.padding(10.dp), style = MaterialTheme.typography.bodySmall)
                                        }
                                    }
                                    if (isEditing) {
                                        Spacer(Modifier.height(10.dp))
                                        Text("Status", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
                                        Row(modifier = Modifier.padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            statusLabels.forEach { (key, label) ->
                                                FilterChip(selected = regressionEditStatus == key, onClick = { regressionEditStatus = key }, label = { Text(label) })
                                            }
                                        }
                                        OutlinedTextField(
                                            value         = regressionEditPlan,
                                            onValueChange = { regressionEditPlan = it },
                                            label         = { Text("Reset plan") },
                                            placeholder   = { Text("What should the handler do next?") },
                                            minLines      = 3,
                                            modifier      = Modifier.fillMaxWidth().padding(top = 8.dp),
                                        )
                                        Row(modifier = Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Button(
                                                onClick  = { submitRegressionUpdate(event.id) },
                                                modifier = Modifier.weight(1f),
                                            ) { Text("Save update") }
                                            OutlinedButton(
                                                onClick  = { regressionExpandedEventId = -1 },
                                                modifier = Modifier.weight(1f),
                                            ) { Text("Cancel") }
                                        }
                                    } else {
                                        TextButton(
                                            onClick  = {
                                                regressionExpandedEventId = event.id
                                                regressionEditStatus      = event.status
                                                regressionEditPlan        = event.recommendedAction
                                            },
                                            modifier = Modifier.padding(top = 4.dp),
                                        ) { Text("Edit reset plan") }
                                    }
                                }
                            }
                        }
                    }
                }
                Button(onClick = { loadRegressionEvents() }, modifier = Modifier.fillMaxWidth()) { Text("Refresh") }
            } else if (regressionMessage.isBlank()) {
                SummaryCard {
                    Text(
                        "No regression data loaded yet. Pull down to refresh, or tap below.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick  = { loadRegressionEvents() },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Load Events") }
                }
            }
        }
        }
    }

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun CandidateAssessmentSection() {
        val result      = candidateResult
        val dogs        = result?.dogs ?: emptyList()
        val scoreLabels = result?.scoreLabels ?: emptyMap()
        val activeDog   = dogs.firstOrNull { it.id == candidateDogId.takeIf { it > 0 } } ?: dogs.firstOrNull()

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadCandidateAssessments() },
            modifier     = Modifier.fillMaxSize(),
        ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("Candidate Assessment", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }
            Text("Score 1 = major concern · 3 = acceptable foundation · 5 = excellent", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)

            SectionMessage(candidateMessage, onRetry = { loadCandidateAssessments() })

            SummaryCard {
                Text("New Assessment", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                if (dogs.isEmpty()) {
                    Text("No dogs on this account. Add a dog profile first.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                } else {
                    ExposedDropdownMenuBox(expanded = candidateDogExpanded, onExpandedChange = { candidateDogExpanded = it }) {
                        OutlinedTextField(
                            value         = activeDog?.name ?: "Select dog",
                            onValueChange = {},
                            readOnly      = true,
                            label         = { Text("Dog") },
                            trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(candidateDogExpanded) },
                            modifier      = Modifier.menuAnchor().fillMaxWidth(),
                        )
                        ExposedDropdownMenu(expanded = candidateDogExpanded, onDismissRequest = { candidateDogExpanded = false }) {
                            dogs.forEach { dog ->
                                DropdownMenuItem(
                                    text    = { Text(dog.name) },
                                    onClick = { candidateDogId = dog.id; candidateDogExpanded = false },
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    candidateScores.keys.forEach { key ->
                        val label = scoreLabels[key] ?: key.replace('_', ' ').replaceFirstChar { it.uppercase() }
                        val score = candidateScores[key] ?: 3
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 4.dp)) {
                            Text(label, modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium)
                            Text("$score", fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 8.dp), style = MaterialTheme.typography.bodyMedium)
                        }
                        Slider(
                            value         = score.toFloat(),
                            onValueChange = { v -> candidateScores = LinkedHashMap(candidateScores).also { it[key] = v.roundToInt() } },
                            valueRange    = 1f..5f,
                            steps         = 3,
                            modifier      = Modifier.fillMaxWidth(),
                        )
                    }
                    OutlinedTextField(
                        value         = candidateHealthNotes,
                        onValueChange = { candidateHealthNotes = it },
                        label         = { Text("Health notes") },
                        placeholder   = { Text("Health, structure, fatigue, pain, medication, vet concerns") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth().padding(top = 4.dp),
                    )
                    OutlinedTextField(
                        value         = candidateSafetyFlags,
                        onValueChange = { candidateSafetyFlags = it },
                        label         = { Text("Safety flags") },
                        placeholder   = { Text("Bite history, severe fear, shutdown, uncontrolled lunging") },
                        minLines      = 2,
                        modifier      = Modifier.fillMaxWidth().padding(top = 4.dp),
                    )
                    Button(onClick = { submitCandidateAssessment(activeDog?.id ?: 0) }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                        Text("Save Assessment")
                    }
                }
            }

            // Recent assessments
            val assessments = result?.assessments ?: emptyList()
            SummaryCard {
                Text("Recent Assessments", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                if (assessments.isEmpty()) {
                    Text("No assessments yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                } else {
                    assessments.forEach { a ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(a.dogName, fontWeight = FontWeight.Medium)
                                        Text("Focus level ${a.focusLevelRecommended} · Avg ${a.averageScore}", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                        if (a.createdAt.length >= 10) Text(a.createdAt.take(10), style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                    }
                                }
                                if (a.recommendation.isNotBlank()) {
                                    Text(a.recommendation, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 6.dp))
                                }
                                if (a.safetyFlags.isNotBlank()) {
                                    Text("⚠ ${a.safetyFlags}", style = MaterialTheme.typography.bodySmall, color = Color(0xFFB45309), modifier = Modifier.padding(top = 4.dp))
                                }
                                TextButton(onClick = { archiveCandidateAssessmentItem(a.id) }) { Text("Archive") }
                            }
                        }
                    }
                }
            }
        }
        }
    }

    private fun loadRegressionEvents() {
        val token = currentToken ?: return
        regressionMessage = "Loading..."
        worker.execute {
            try {
                val result = api.regressionEvents(token)
                runOnUiThread {
                    regressionResult          = result
                    regressionMessage         = ""
                    regressionExpandedEventId = -1
                    isPullingToRefresh        = false
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    regressionMessage  = friendlyMessage(t.message, "Could not load regression events.")
                    isPullingToRefresh = false
                }
            }
        }
    }

    private fun submitRegressionUpdate(eventId: Int) {
        val token = currentToken ?: return
        worker.execute {
            try {
                val result = api.updateRegressionEvent(token, eventId, regressionEditStatus, regressionEditPlan)
                runOnUiThread {
                    regressionResult          = result
                    regressionMessage         = "Regression event updated."
                    regressionExpandedEventId = -1
                }
            } catch (t: Throwable) {
                runOnUiThread { regressionMessage = friendlyMessage(t.message, "Could not update regression event.") }
            }
        }
    }

    private fun loadCandidateAssessments() {
        val token = currentToken ?: return
        candidateMessage = "Loading..."
        worker.execute {
            try {
                val result = api.candidateAssessments(token)
                runOnUiThread {
                    candidateResult    = result
                    candidateMessage   = ""
                    isPullingToRefresh = false
                    if (candidateDogId == 0) candidateDogId = result.dogs.firstOrNull()?.id ?: 0
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    candidateMessage   = friendlyMessage(t.message, "Could not load assessments.")
                    isPullingToRefresh = false
                }
            }
        }
    }

    private fun submitCandidateAssessment(dogId: Int) {
        val token = currentToken ?: return
        if (dogId <= 0) { candidateMessage = "Select a dog first."; return }
        worker.execute {
            try {
                val msg = api.createCandidateAssessment(token, dogId, candidateScores, candidateHealthNotes, candidateSafetyFlags)
                runOnUiThread {
                    candidateMessage     = msg
                    candidateHealthNotes = ""
                    candidateSafetyFlags = ""
                    candidateScores      = LinkedHashMap(candidateScores.mapValues { 3 })
                    loadCandidateAssessments()
                }
            } catch (t: Throwable) {
                runOnUiThread { candidateMessage = friendlyMessage(t.message, "Could not save assessment.") }
            }
        }
    }

    private fun archiveCandidateAssessmentItem(assessmentId: Int) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.archiveCandidateAssessment(token, assessmentId)
                runOnUiThread {
                    candidateMessage = "Assessment archived."
                    loadCandidateAssessments()
                }
            } catch (t: Throwable) {
                runOnUiThread { candidateMessage = friendlyMessage(t.message, "Could not archive assessment.") }
            }
        }
    }

    private fun loadGoalIntake(filter: String = "active") {
        val token = currentToken ?: return
        goalIntakeFilter = filter
        setLoading(true, "Loading goals...")
        worker.execute {
            try {
                val result = api.trainingGoals(token, filter)
                runOnUiThread {
                    goalIntakeGoals   = result.goals
                    goalIntakeMessage = if (result.goals.isEmpty()) "No ${filter} goals." else ""
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    goalIntakeMessage = friendlyMessage(t.message, "Could not load goals.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun submitTrainingGoal() {
        val token  = currentToken ?: return
        val dogId  = goalIntakeDogId.takeIf { it > 0 } ?: currentActiveDogId ?: return
        if (goalIntakeProblem.isBlank()) { goalIntakeMessage = "Problem description is required."; return }
        if (goalIntakeCriteria.isBlank()) { goalIntakeMessage = "Success criteria is required."; return }
        setLoading(true, "Saving goal...")
        worker.execute {
            try {
                api.createTrainingGoal(
                    token                    = token,
                    dogId                    = dogId,
                    goalCategory             = goalIntakeCategory,
                    currentProblem           = goalIntakeProblem,
                    desiredBehavior          = goalIntakeDesired,
                    contextEnvironment       = goalIntakeContext,
                    triggerDescription       = goalIntakeTrigger,
                    handlerTimeBudgetMinutes = goalIntakeBudget.trim().toIntOrNull()?.coerceIn(1, 30) ?: 3,
                    reinforcerPreference     = goalIntakeReinforcer,
                    safetyRisk               = goalIntakeSafetyRisk,
                    successCriteria          = goalIntakeCriteria,
                    maintenancePlan          = goalIntakeMaintenance,
                )
                runOnUiThread {
                    goalIntakeProblem     = ""
                    goalIntakeDesired     = ""
                    goalIntakeContext     = ""
                    goalIntakeTrigger     = ""
                    goalIntakeBudget      = "3"
                    goalIntakeReinforcer  = ""
                    goalIntakeSafetyRisk  = false
                    goalIntakeCriteria    = ""
                    goalIntakeMaintenance = ""
                    goalIntakeMessage     = "Training goal saved."
                    loadGoalIntake("active")
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    goalIntakeMessage = friendlyMessage(t.message, "Could not save goal.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun submitGoalBuilderGoal() {
        val token = currentToken ?: return
        val dogId = goalBuilderDogId.takeIf { it > 0 } ?: currentActiveDogId ?: run {
            goalBuilderMessage = "Select a dog first."; return
        }
        if (goalBuilderProblem.isBlank()) { goalBuilderMessage = "Problem description is required."; return }
        if (goalBuilderCriteria.isBlank()) { goalBuilderMessage = "Success criteria is required."; return }
        setLoading(true, "Saving goal...")
        worker.execute {
            try {
                api.createTrainingGoal(
                    token                    = token,
                    dogId                    = dogId,
                    goalCategory             = goalBuilderCategory,
                    currentProblem           = goalBuilderProblem,
                    desiredBehavior          = goalBuilderDesired,
                    contextEnvironment       = goalBuilderContext,
                    triggerDescription       = goalBuilderTrigger,
                    handlerTimeBudgetMinutes = goalBuilderBudget.trim().toIntOrNull()?.coerceIn(1, 30) ?: 3,
                    reinforcerPreference     = goalBuilderReinforcer,
                    safetyRisk               = goalBuilderSafetyRisk,
                    successCriteria          = goalBuilderCriteria,
                    maintenancePlan          = goalBuilderMaintenance,
                )
                runOnUiThread {
                    goalBuilderProblem     = ""
                    goalBuilderDesired     = ""
                    goalBuilderContext     = ""
                    goalBuilderTrigger     = ""
                    goalBuilderBudget      = "3"
                    goalBuilderReinforcer  = ""
                    goalBuilderSafetyRisk  = false
                    goalBuilderCriteria    = ""
                    goalBuilderMaintenance = ""
                    goalBuilderShowDraft   = false
                    setLoading(false, "Goal saved.")
                    goalBuilderMessage     = "Goal saved. Ready to build another."
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    setLoading(false, friendlyMessage(t.message, "Could not save goal."))
                    goalBuilderMessage = friendlyMessage(t.message, "Could not save goal.")
                }
            }
        }
    }

    private fun archiveGoal(goalId: Int) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.archiveTrainingGoal(token, goalId)
                runOnUiThread { loadGoalIntake(goalIntakeFilter) }
            } catch (t: Throwable) {
                runOnUiThread {
                    goalIntakeMessage = friendlyMessage(t.message, "Could not archive goal.")
                }
            }
        }
    }

    private fun loadHabitRepair() {
        val token = currentToken ?: return
        setLoading(true, "Loading habit repair...")
        worker.execute {
            try {
                val result = api.habitRepair(token)
                runOnUiThread {
                    habitRepairProtocols = result.protocols
                    habitRepairIncidents = result.incidents
                    if (habitRepairProtocolKey.isBlank() || result.protocols.none { it.key == habitRepairProtocolKey }) {
                        habitRepairProtocolKey = result.protocols.firstOrNull()?.key ?: "potty_accidents"
                    }
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    habitRepairMessage = friendlyMessage(t.message, "Could not load habit repair data.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun submitBehaviorIncident(dogId: Int, incidentType: String) {
        val token = currentToken ?: return
        if (dogId <= 0) { habitRepairMessage = "Select a dog first."; return }
        setLoading(true, "Saving incident...")
        worker.execute {
            try {
                api.createBehaviorIncident(
                    token              = token,
                    dogId              = dogId,
                    incidentType       = incidentType,
                    contextEnvironment = habitRepairContext,
                    triggerDescription = habitRepairTrigger,
                    severity           = habitRepairSeverity,
                    notes              = habitRepairNotes,
                )
                runOnUiThread {
                    habitRepairContext  = ""
                    habitRepairTrigger  = ""
                    habitRepairSeverity = 2
                    habitRepairNotes    = ""
                    habitRepairMessage  = "Incident logged."
                    loadHabitRepair()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    habitRepairMessage = friendlyMessage(t.message, "Could not save incident.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun archiveBehaviorIncident(incidentId: Int) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.archiveBehaviorIncident(token, incidentId)
                runOnUiThread { loadHabitRepair() }
            } catch (t: Throwable) {
                runOnUiThread {
                    habitRepairMessage = friendlyMessage(t.message, "Could not archive incident.")
                }
            }
        }
    }

    private fun loadBehaviorRisk(dogId: Int?) {
        val token = currentToken ?: return
        setLoading(true, "Loading risk assessment...")
        worker.execute {
            try {
                val result = api.behaviorRisk(token, dogId)
                runOnUiThread {
                    behaviorRiskResult  = result
                    behaviorRiskMessage = ""
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    behaviorRiskMessage = friendlyMessage(t.message, "Could not load risk assessment.")
                    setLoading(false, null)
                }
            }
        }
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
        if (!loading) isPullingToRefresh = false
        if (message != null) {
            statusMessage = message
            isStatusError = !loading && isErrorText(message)
        }
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

    private fun isErrorText(msg: String): Boolean =
        listOf("could not", "failed", "error", "unable", "invalid", "not found")
            .any { msg.startsWith(it, ignoreCase = true) }

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
