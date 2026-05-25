package com.guidepaw.companion

import android.Manifest
import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.SharedPreferences
import android.content.pm.PackageManager
import android.location.Address
import android.location.Geocoder
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.rememberCoroutineScope
import androidx.core.content.ContextCompat
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import java.util.Locale
import kotlin.coroutines.resume
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
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
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
import androidx.compose.ui.platform.LocalContext
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
import android.database.Cursor
import android.provider.OpenableColumns
import kotlin.math.roundToInt
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.Executors

private data class FeedbackAttachment(
    val uri         : Uri,
    val displayName : String,
    val mimeType    : String,
    val sizeBytes   : Long?,
)

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
private enum class NavSection { OVERVIEW, TRAINING, DOGS, WEARABLES, MORE, NOTIFICATIONS, FEEDBACK, GOAL_INTAKE, GOAL_BUILDER, HABIT_REPAIR, BEHAVIOR_RISK, REGRESSION, CANDIDATE_ASSESSMENT, CANDIDATE_COMPARISON, ADA_ACCESS_CARD, AIR_TRAVEL, HOUSING_FAQ, TACTICAL_TRAINING, MEDICATIONS, APPOINTMENTS, HEALTH_DOCS, CERTIFICATION, PROFILE, STATS, DOG_ACCESS, SMART_ALERTS }

private val NAV_ITEMS = listOf(
    NavSection.OVERVIEW,
    NavSection.TRAINING,
    NavSection.DOGS,
    NavSection.NOTIFICATIONS,
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
    private var pendingLaunchSection by mutableStateOf<String?>(null)

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

    // ── Certification state ────────────────────────────────────────────────
    private var certResult             by mutableStateOf<GpCertResult?>(null)
    private var certMessage            by mutableStateOf("")
    private var certExpandedCategories by mutableStateOf<Set<String>>(emptySet())
    private var certAsmDate            by mutableStateOf("")
    private var certAsmPublic          by mutableStateOf("")
    private var certAsmTask            by mutableStateOf("")
    private var certAsmObedience       by mutableStateOf("")
    private var certAsmEnv             by mutableStateOf("")
    private var certAsmNotes           by mutableStateOf("")
    private var certShowAsmForm        by mutableStateOf(false)

    // ── Health Docs state ──────────────────────────────────────────────────
    private var healthDocsResult   by mutableStateOf<GpHealthDocsResult?>(null)
    private var healthDocsMessage  by mutableStateOf("")
    private var vetClinic          by mutableStateOf("")
    private var vetName            by mutableStateOf("")
    private var vetPhone           by mutableStateOf("")
    private var vetAddress         by mutableStateOf("")
    private var vetNotes           by mutableStateOf("")
    private var vetIsPrimary       by mutableStateOf(false)
    private var vetShowForm        by mutableStateOf(false)

    // ── Appointments state ─────────────────────────────────────────────────
    private var appointmentsResult  by mutableStateOf<GpAppointmentsResult?>(null)
    private var appointmentsMessage by mutableStateOf("")
    private var apptTitle           by mutableStateOf("")
    private var apptAt              by mutableStateOf("")
    private var apptReminderAt      by mutableStateOf("")
    private var apptLocation        by mutableStateOf("")
    private var apptNotes           by mutableStateOf("")
    private var apptVetId           by mutableStateOf(0)
    private var apptVetExpanded     by mutableStateOf(false)
    private var apptShowForm        by mutableStateOf(false)

    // ── Medications state ──────────────────────────────────────────────────
    private var medicationsResult  by mutableStateOf<GpMedicationsResult?>(null)
    private var medicationsMessage by mutableStateOf("")
    private var medName            by mutableStateOf("")
    private var medDosage          by mutableStateOf("")
    private var medSchedule        by mutableStateOf("")
    private var medStatus          by mutableStateOf("active")
    private var medRefillDate      by mutableStateOf("")
    private var medProvider        by mutableStateOf("")
    private var medInstructions    by mutableStateOf("")
    private var medNotes           by mutableStateOf("")
    private var medShowForm        by mutableStateOf(false)

    // ── Feedback state ──────────────────────────────────────────────────────
    private var feedbackCategory     by mutableStateOf("bug")
    private var feedbackPageWorkflow by mutableStateOf("")
    private var feedbackEmail        by mutableStateOf("")
    private var feedbackDetails      by mutableStateOf("")
    private var feedbackAttachments  by mutableStateOf<List<FeedbackAttachment>>(emptyList())
    private var feedbackMessage      by mutableStateOf("")
    private var feedbackIsLoading    by mutableStateOf(false)

    // ── Notification Center state ───────────────────────────────────────────
    private var notifResult      by mutableStateOf<GuidePawNotificationsResult?>(null)
    private var notifMessage     by mutableStateOf("")
    private var notifPrefAccess  by mutableStateOf(true)
    private var notifPrefCare    by mutableStateOf(true)
    private var notifPrefAdmin   by mutableStateOf(true)
    private var notifPrefGeneral by mutableStateOf(true)
    private var notifSelectedIds by mutableStateOf<Set<Int>>(emptySet())

    // ── Wearables state ────────────────────────────────────────────────────
    private var wearableResult        by mutableStateOf<GuidePawWearableResult?>(null)
    private var wearableMessage       by mutableStateOf("")
    private var wearableIsLoading     by mutableStateOf(false)
    private var wearableHandlerSlug   by mutableStateOf("")
    private var wearableDogSlug       by mutableStateOf("")
    private var wearableSyncMode      by mutableStateOf("")
    private var wearableNotes         by mutableStateOf("")
    private var wearableShowSetupForm by mutableStateOf(false)

    // ── Handler Profile state ──────────────────────────────────────────────
    private var profileResult        by mutableStateOf<GpHandlerProfile?>(null)
    private var profileMessage       by mutableStateOf("")
    private var profileIsLoading     by mutableStateOf(false)
    private var profileDisplayName   by mutableStateOf("")
    private var profileHomeStreet    by mutableStateOf("")
    private var profileHomeApt       by mutableStateOf("")
    private var profileHomeCity      by mutableStateOf("")
    private var profileHomeState     by mutableStateOf("")
    private var profileHomeZip       by mutableStateOf("")
    private var profilePhone         by mutableStateOf("")
    private var profilePublicEmail   by mutableStateOf("")
    private var profileFacebookUrl   by mutableStateOf("")
    private var profileBackupName    by mutableStateOf("")
    private var profileBackupPhone   by mutableStateOf("")
    private var profilePublicNotes   by mutableStateOf("")
    private var profileSmsPhone      by mutableStateOf("")
    private var profileSmsEnabled    by mutableStateOf(false)

    // ── Stats state ────────────────────────────────────────────────────────
    private var statsResult      by mutableStateOf<GpStats?>(null)
    private var statsMessage     by mutableStateOf("")
    private var statsIsLoading   by mutableStateOf(false)

    // ── Dog Access state ───────────────────────────────────────────────────
    private var dogAccessResult      by mutableStateOf<GpDogAccessResult?>(null)
    private var dogAccessMessage     by mutableStateOf("")
    private var dogAccessIsLoading   by mutableStateOf(false)
    private var dogAccessInviteId    by mutableStateOf("")
    private var dogAccessInviteRole  by mutableStateOf("co-op handler")
    private var dogAccessInvitePerm  by mutableStateOf("view")
    private var dogAccessInviteEnds  by mutableStateOf("")
    private var dogAccessShowInvite  by mutableStateOf(false)

    // ── Smart Alerts state ─────────────────────────────────────────────────
    private var alertsResult    by mutableStateOf<List<GpAlert>>(emptyList())
    private var alertsMessage   by mutableStateOf("")
    private var alertsIsLoading by mutableStateOf(false)

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

        pendingLaunchSection = intent?.getStringExtra(GuidePawNavigation.EXTRA_OPEN_SECTION)
        val launchToken = intent?.getStringExtra(GuidePawWebActivity.EXTRA_ACCESS_TOKEN).orEmpty().trim().takeIf { it.isNotBlank() }
        val storedToken = prefs.getString(KEY_TOKEN, null)
        val startToken = storedToken ?: launchToken
        if (!startToken.isNullOrBlank()) {
            currentToken = startToken
            if (storedToken.isNullOrBlank() && !launchToken.isNullOrBlank()) {
                prefs.edit().putString(KEY_TOKEN, launchToken).commit()
            }
            statusMessage = "Restoring saved session..."
            restoreCachedDashboard()
            refreshDashboard(startToken, null)
            if (pendingLaunchSection == GuidePawNavigation.OPEN_SECTION_NOTIFICATIONS) {
                currentSection = NavSection.NOTIFICATIONS
                refreshNotifications()
            }
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

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        pendingLaunchSection = intent.getStringExtra(GuidePawNavigation.EXTRA_OPEN_SECTION)
        val launchToken = intent.getStringExtra(GuidePawWebActivity.EXTRA_ACCESS_TOKEN).orEmpty().trim().takeIf { it.isNotBlank() }
        if (currentToken.isNullOrBlank() && !launchToken.isNullOrBlank()) {
            currentToken = launchToken
            prefs.edit().putString(KEY_TOKEN, launchToken).commit()
        }
        if (pendingLaunchSection == GuidePawNavigation.OPEN_SECTION_NOTIFICATIONS) {
            currentSection = NavSection.NOTIFICATIONS
            if (!currentToken.isNullOrBlank()) refreshNotifications()
        }
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
                        NavSection.OVERVIEW       -> OverviewSection()
                        NavSection.TRAINING       -> TrainingSection()
                        NavSection.DOGS           -> DogsSection()
                        NavSection.WEARABLES      -> WearablesSection()
                        NavSection.MORE           -> OverviewSection()
                        NavSection.NOTIFICATIONS  -> NotificationsSection()
                        NavSection.FEEDBACK       -> FeedbackSection()
                        NavSection.GOAL_INTAKE          -> GoalIntakeSection()
                        NavSection.GOAL_BUILDER         -> GoalBuilderSection()
                        NavSection.HABIT_REPAIR         -> HabitRepairSection()
                        NavSection.BEHAVIOR_RISK        -> BehaviorRiskSection()
                        NavSection.REGRESSION           -> RegressionSection()
                        NavSection.CANDIDATE_ASSESSMENT -> CandidateAssessmentSection()
                        NavSection.CANDIDATE_COMPARISON -> CandidateComparisonSection()
                        NavSection.MEDICATIONS          -> MedicationsSection()
                        NavSection.APPOINTMENTS         -> VetAppointmentsSection()
                        NavSection.HEALTH_DOCS          -> HealthDocsSection()
                        NavSection.CERTIFICATION        -> CertificationSection()
                        NavSection.PROFILE              -> ProfileSection()
                        NavSection.STATS                -> StatsSection()
                        NavSection.DOG_ACCESS           -> DogAccessSection()
                        NavSection.SMART_ALERTS         -> SmartAlertsSection()
                        NavSection.ADA_ACCESS_CARD      -> ADAAccessCardSection()
                        NavSection.AIR_TRAVEL           -> AirTravelSection()
                        NavSection.HOUSING_FAQ          -> HousingFAQSection()
                        NavSection.TACTICAL_TRAINING    -> TacticalTrainingSection()
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
                    NavSection.OVERVIEW       -> Icons.Filled.Home
                    NavSection.TRAINING       -> Icons.Filled.Bolt
                    NavSection.DOGS           -> Icons.Filled.Pets
                    NavSection.NOTIFICATIONS  -> Icons.Filled.Notifications
                    else                      -> Icons.Filled.Menu
                }
                val title = when (section) {
                    NavSection.OVERVIEW       -> "Home"
                    NavSection.TRAINING       -> "Log"
                    NavSection.DOGS           -> "History"
                    NavSection.NOTIFICATIONS  -> "Notifications"
                    else                      -> "Menu"
                }
                NavigationBarItem(
                    selected = section != NavSection.MORE && currentSection == section,
                    onClick  = {
                        if (section == NavSection.MORE) showMenu = true
                        else {
                            currentSection = section
                            if (section == NavSection.NOTIFICATIONS && notifResult == null) refreshNotifications()
                        }
                    },
                    icon     = {
                        if (section == NavSection.NOTIFICATIONS && currentUnreadCount > 0) {
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
                onClick  = { currentSection = NavSection.FEEDBACK },
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

    // ── GPS location helpers ────────────────────────────────────────────────

    private suspend fun resolveLocation(context: Context): Pair<String, String>? =
        suspendCancellableCoroutine { cont ->
            val client = LocationServices.getFusedLocationProviderClient(context)
            try {
                client.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, null)
                    .addOnSuccessListener { loc ->
                        if (loc == null) { cont.resume(null); return@addOnSuccessListener }
                        try {
                            val geocoder = Geocoder(context, Locale.getDefault())
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                geocoder.getFromLocation(loc.latitude, loc.longitude, 1) { addresses ->
                                    cont.resume(formatAddress(addresses.firstOrNull()))
                                }
                            } else {
                                @Suppress("DEPRECATION")
                                val addresses = geocoder.getFromLocation(loc.latitude, loc.longitude, 1)
                                cont.resume(formatAddress(addresses?.firstOrNull()))
                            }
                        } catch (_: Exception) { cont.resume(null) }
                    }
                    .addOnFailureListener { cont.resume(null) }
            } catch (_: SecurityException) { cont.resume(null) }
        }

    private fun formatAddress(address: Address?): Pair<String, String>? {
        address ?: return null
        val feature    = address.featureName?.trim().orEmpty()
        val streetNum  = address.subThoroughfare?.trim().orEmpty()
        val streetName = address.thoroughfare?.trim().orEmpty()
        // featureName is a business/POI name when it's non-numeric and differs from the street
        val isBusinessName = feature.isNotBlank()
            && feature != streetName
            && !feature.all { it.isDigit() || it == '-' || it == ' ' }
        val fullStreet = when {
            streetNum.isNotBlank() && streetName.isNotBlank() -> "$streetNum $streetName"
            streetName.isNotBlank() -> streetName
            else -> ""
        }
        val name = when {
            isBusinessName -> feature
            fullStreet.isNotBlank() -> fullStreet
            else -> (address.premises ?: address.subLocality ?: "").trim()
        }
        val city      = (address.locality ?: address.subAdminArea ?: "").trim()
        val state     = (address.adminArea ?: "").trim()
        val cityState = listOf(city, state).filter { it.isNotBlank() }.joinToString(", ")
        return Pair(name.ifBlank { cityState }, cityState)
    }

    @Composable
    private fun LocationPickerButton(
        onLocationFound: (locationName: String, cityState: String) -> Unit
    ) {
        val context = LocalContext.current
        val scope   = rememberCoroutineScope()
        var isLocating    by remember { mutableStateOf(false) }
        var locationError by remember { mutableStateOf("") }

        fun doFetch() {
            isLocating = true
            locationError = ""
            scope.launch {
                val result = resolveLocation(context)
                isLocating = false
                if (result != null) onLocationFound(result.first, result.second)
                else locationError = "Could not determine location. Try again."
            }
        }

        val launcher = rememberLauncherForActivityResult(
            ActivityResultContracts.RequestMultiplePermissions()
        ) { perms ->
            if (perms[Manifest.permission.ACCESS_FINE_LOCATION] == true ||
                perms[Manifest.permission.ACCESS_COARSE_LOCATION] == true) {
                doFetch()
            } else {
                locationError = "Location permission denied"
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            OutlinedButton(
                onClick = {
                    val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
                    val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
                    if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                        doFetch()
                    } else {
                        launcher.launch(arrayOf(
                            Manifest.permission.ACCESS_FINE_LOCATION,
                            Manifest.permission.ACCESS_COARSE_LOCATION,
                        ))
                    }
                },
                enabled  = !isLocating,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Icon(
                    Icons.Filled.MyLocation,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                )
                Spacer(Modifier.width(6.dp))
                Text(if (isLocating) "Getting location…" else "Use my location")
            }
            if (locationError.isNotBlank()) {
                Text(
                    locationError,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                )
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
            LocationPickerButton { name, cityState ->
                if (logLocation.isBlank()) logLocation = name
                if (logCityState.isBlank()) logCityState = cityState
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

    // ── Dog Access section ──────────────────────────────────────────────────
    @Composable
    private fun DogAccessSection() {
        val result = dogAccessResult
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                Text("Dog Access", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                TextButton(onClick = { loadDogAccess() }) { Text("Refresh") }
            }

            if (dogAccessIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            if (dogAccessMessage.isNotBlank()) {
                Text(
                    dogAccessMessage,
                    color = if (isErrorText(dogAccessMessage)) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            if (result == null && !dogAccessIsLoading) {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Not loaded", fontWeight = FontWeight.SemiBold)
                        Button(onClick = { loadDogAccess() }, modifier = Modifier.fillMaxWidth()) { Text("Load Dog Access") }
                    }
                }
            }

            if (result != null) {
                // Dog info
                val dog = result.dog
                SummaryCard {
                    if (dog != null) {
                        Text(dog.name, fontWeight = FontWeight.SemiBold)
                        Text("Owner: @${dog.ownerUsername}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        Text("Status: ${dog.lifecycleStatus}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    } else {
                        Text("No active dog selected.", style = MaterialTheme.typography.bodySmall)
                    }
                }

                // Incoming transfer requests
                if (result.incomingTransfers.isNotEmpty()) {
                    Text("Ownership Transfer Requests", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.error)
                    result.incomingTransfers.forEach { transfer ->
                        OutlinedCard(
                            modifier = Modifier.fillMaxWidth(),
                            border   = BorderStroke(1.dp, MaterialTheme.colorScheme.error),
                        ) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text("${transfer.dogName} from @${transfer.fromUsername}", fontWeight = FontWeight.SemiBold)
                                if (transfer.note.isNotBlank())
                                    Text(transfer.note, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Button(
                                        onClick  = { dogAccessRespondTransfer(transfer.id, true) },
                                        modifier = Modifier.weight(1f),
                                    ) { Text("Accept") }
                                    OutlinedButton(
                                        onClick  = { dogAccessRespondTransfer(transfer.id, false) },
                                        modifier = Modifier.weight(1f),
                                        colors   = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                                    ) { Text("Decline") }
                                }
                            }
                        }
                    }
                }

                // Pending invites received by me
                if (result.pendingInvites.isNotEmpty()) {
                    Text("Your Pending Invites", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                    result.pendingInvites.forEach { invite ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text(invite.dogName, fontWeight = FontWeight.SemiBold)
                                Text("from @${invite.ownerUsername} · ${invite.role}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Button(
                                        onClick  = { dogAccessRespondInvite(invite.id, true) },
                                        modifier = Modifier.weight(1f),
                                    ) { Text("Accept") }
                                    OutlinedButton(
                                        onClick  = { dogAccessRespondInvite(invite.id, false) },
                                        modifier = Modifier.weight(1f),
                                        colors   = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                                    ) { Text("Decline") }
                                }
                            }
                        }
                    }
                }

                // Handlers list
                if (result.handlers.isNotEmpty()) {
                    Text("Shared Handlers", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                    result.handlers.forEach { handler ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.CenterVertically,
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        val name = handler.displayName.ifBlank { handler.username }
                                        Text(name, fontWeight = FontWeight.SemiBold)
                                        Text("@${handler.username}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        Text("${handler.role} · ${handler.permissionLevel}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        val statusColor = when (handler.accessStatus) {
                                            "accepted" -> GpPrimary
                                            "pending"  -> MaterialTheme.colorScheme.tertiary
                                            else       -> MaterialTheme.colorScheme.error
                                        }
                                        Text(handler.accessStatus, style = MaterialTheme.typography.labelSmall, color = statusColor)
                                    }
                                    if (result.isOwner && handler.accessStatus != "revoked") {
                                        TextButton(
                                            onClick = { dogAccessRevoke(handler.id) },
                                            colors  = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                                        ) { Text("Revoke") }
                                    }
                                }
                                if (handler.accessEndsAt.isNotBlank())
                                    Text("Expires: ${handler.accessEndsAt}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            }
                        }
                    }
                } else if (result.pendingInvites.isEmpty()) {
                    SummaryCard {
                        Text("No shared handlers yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                }

                // Invite form (owner only)
                if (result.isOwner && dog != null) {
                    HorizontalDivider()
                    Row(
                        modifier              = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment     = Alignment.CenterVertically,
                    ) {
                        Text("Invite a Handler", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        TextButton(onClick = { dogAccessShowInvite = !dogAccessShowInvite }) {
                            Text(if (dogAccessShowInvite) "Cancel" else "New Invite")
                        }
                    }
                    if (dogAccessShowInvite) {
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                OutlinedTextField(
                                    value         = dogAccessInviteId,
                                    onValueChange = { dogAccessInviteId = it },
                                    label         = { Text("Username or email *") },
                                    modifier      = Modifier.fillMaxWidth(),
                                    singleLine    = true,
                                )
                                OutlinedTextField(
                                    value         = dogAccessInviteRole,
                                    onValueChange = { dogAccessInviteRole = it },
                                    label         = { Text("Role (e.g. co-op handler)") },
                                    modifier      = Modifier.fillMaxWidth(),
                                    singleLine    = true,
                                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                                )
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    listOf("view" to "View only", "edit" to "Can edit").forEach { (value, label) ->
                                        FilterChip(
                                            selected = dogAccessInvitePerm == value,
                                            onClick  = { dogAccessInvitePerm = value },
                                            label    = { Text(label) },
                                        )
                                    }
                                }
                                OutlinedTextField(
                                    value         = dogAccessInviteEnds,
                                    onValueChange = { dogAccessInviteEnds = it },
                                    label         = { Text("Access ends (YYYY-MM-DD, optional)") },
                                    modifier      = Modifier.fillMaxWidth(),
                                    singleLine    = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                )
                                Button(
                                    onClick  = { dogAccessGrant(dog.id) },
                                    modifier = Modifier.fillMaxWidth(),
                                    enabled  = dogAccessInviteId.isNotBlank(),
                                ) { Text("Send Invite") }
                            }
                        }
                    }
                }
            }

            TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
        }
    }

    // ── Smart Alerts section ────────────────────────────────────────────────
    @Composable
    private fun SmartAlertsSection() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                Text("Smart Alerts", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                TextButton(onClick = { loadAlerts() }) { Text("Refresh") }
            }

            if (alertsIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            if (alertsMessage.isNotBlank()) {
                val isError = alertsMessage.startsWith("Could not")
                Text(
                    alertsMessage,
                    color = if (isError) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                    style = MaterialTheme.typography.bodyMedium,
                )
            }

            if (!alertsIsLoading && alertsResult.isEmpty() && alertsMessage.isBlank()) {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text("✅ All clear", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                        Text("No active alerts for your dog.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                }
            }

            alertsResult.forEach { alert ->
                val (borderColor, badgeColor, badgeText) = when (alert.level) {
                    "danger"  -> Triple(MaterialTheme.colorScheme.error, MaterialTheme.colorScheme.error, "⚠ Urgent")
                    "warning" -> Triple(Color(0xFFF59E0B), Color(0xFFF59E0B), "⚡ Warning")
                    else      -> Triple(GpPrimary, GpPrimary, "ℹ Info")
                }
                OutlinedCard(
                    modifier = Modifier.fillMaxWidth(),
                    border   = BorderStroke(1.5.dp, borderColor),
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment     = Alignment.Top,
                        ) {
                            Text(
                                alert.title,
                                style      = MaterialTheme.typography.titleSmall,
                                fontWeight = FontWeight.SemiBold,
                                modifier   = Modifier.weight(1f),
                            )
                            Text(
                                badgeText,
                                style  = MaterialTheme.typography.labelSmall,
                                color  = badgeColor,
                                modifier = Modifier.padding(start = 8.dp),
                            )
                        }
                        if (alert.detail.isNotBlank()) {
                            Text(alert.detail, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        }
                        if (alert.actionUrl.isNotBlank() && alert.actionLabel.isNotBlank()) {
                            TextButton(
                                onClick  = { openWebPage(alert.actionUrl) },
                                contentPadding = PaddingValues(0.dp),
                            ) {
                                Text(alert.actionLabel + " →", style = MaterialTheme.typography.labelMedium)
                            }
                        }
                    }
                }
            }

            TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
        }
    }

    // ── Stats section ───────────────────────────────────────────────────────
    @Composable
    private fun StatsSection() {
        val stats = statsResult
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                Column {
                    Text("Training Stats", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                    if (activeDog != null)
                        Text(activeDog.name, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
                TextButton(onClick = { loadStats() }) { Text("Refresh") }
            }

            if (statsIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            if (statsMessage.isNotBlank()) {
                Text(
                    statsMessage,
                    color = if (isErrorText(statsMessage)) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            if (stats == null && !statsIsLoading) {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("No stats loaded", fontWeight = FontWeight.SemiBold)
                        Button(onClick = { loadStats() }, modifier = Modifier.fillMaxWidth()) { Text("Load Stats") }
                    }
                }
            }

            if (stats != null) {
                // Summary row
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatTile("Total Logs",   stats.totalLogs.toString(),              Modifier.weight(1f))
                    StatTile("Avg Focus",    if (stats.totalLogs > 0) "%.2f".format(stats.avgFocus) else "—", Modifier.weight(1f))
                }
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatTile("This Week",  stats.logsThisWeek.toString(),  Modifier.weight(1f))
                    StatTile("This Month", stats.logsThisMonth.toString(), Modifier.weight(1f))
                }

                // Top skills
                if (stats.topSkills.isNotEmpty()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Top Skills", fontWeight = FontWeight.SemiBold)
                            val maxCount = stats.topSkills.maxOf { it.count }.coerceAtLeast(1)
                            stats.topSkills.forEach { s ->
                                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                    Row(
                                        modifier              = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                    ) {
                                        Text(s.skill, style = MaterialTheme.typography.bodySmall)
                                        Text(s.count.toString(), style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                                    }
                                    LinearProgressIndicator(
                                        progress = { s.count.toFloat() / maxCount },
                                        modifier = Modifier.fillMaxWidth(),
                                        color    = GpPrimary,
                                    )
                                }
                            }
                        }
                    }
                }

                // Environment breakdown
                if (stats.locationBreakdown.isNotEmpty()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Environment Breakdown", fontWeight = FontWeight.SemiBold)
                            val maxEnv = stats.locationBreakdown.maxOf { it.count }.coerceAtLeast(1)
                            stats.locationBreakdown.forEach { e ->
                                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                    Row(
                                        modifier              = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                    ) {
                                        Text(e.type, style = MaterialTheme.typography.bodySmall)
                                        Text(e.count.toString(), style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                                    }
                                    LinearProgressIndicator(
                                        progress = { e.count.toFloat() / maxEnv },
                                        modifier = Modifier.fillMaxWidth(),
                                        color    = GpPrimary,
                                    )
                                }
                            }
                        }
                    }
                }

                // 14-day trend
                if (stats.trend14d.isNotEmpty()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Last 14 Days", fontWeight = FontWeight.SemiBold)
                            Row(modifier = Modifier.fillMaxWidth()) {
                                Text("Date",      style = MaterialTheme.typography.labelSmall, modifier = Modifier.weight(2f), color = GpOnSurfaceVariant)
                                Text("Logs",      style = MaterialTheme.typography.labelSmall, modifier = Modifier.weight(1f), color = GpOnSurfaceVariant)
                                Text("Avg Focus", style = MaterialTheme.typography.labelSmall, modifier = Modifier.weight(1f), color = GpOnSurfaceVariant)
                            }
                            HorizontalDivider()
                            stats.trend14d.forEach { day ->
                                Row(modifier = Modifier.fillMaxWidth()) {
                                    Text(day.date.takeLast(5), style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(2f))
                                    Text(day.logs.toString(),  style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f))
                                    Text("%.2f".format(day.avgFocus), style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f))
                                }
                            }
                        }
                    }
                }
            }

            TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
        }
    }

    @Composable
    private fun StatTile(label: String, value: String, modifier: Modifier = Modifier) {
        OutlinedCard(modifier = modifier) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(label, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                Text(value, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            }
        }
    }

    // ── Handler Profile section ─────────────────────────────────────────────
    @Composable
    private fun ProfileSection() {
        val profile = profileResult
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                Text("Handler Profile", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                Button(onClick = { profileSave() }, enabled = !profileIsLoading) { Text("Save") }
            }

            if (profileIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            if (profileMessage.isNotBlank()) {
                Text(
                    profileMessage,
                    color = if (isErrorText(profileMessage)) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            if (profile == null && !profileIsLoading) {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Profile not loaded", fontWeight = FontWeight.SemiBold)
                        Button(onClick = { loadProfile() }, modifier = Modifier.fillMaxWidth()) { Text("Load Profile") }
                    }
                }
            }

            if (profile != null) {
                // Identity
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("Identity", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        Text("@${profile.username}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        OutlinedTextField(
                            value         = profileDisplayName,
                            onValueChange = { profileDisplayName = it },
                            label         = { Text("Display Name") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                        )
                        OutlinedTextField(
                            value         = profilePublicEmail,
                            onValueChange = { profilePublicEmail = it },
                            label         = { Text("Public Email") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                        )
                        OutlinedTextField(
                            value         = profilePhone,
                            onValueChange = { profilePhone = it },
                            label         = { Text("Phone") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                        )
                        OutlinedTextField(
                            value         = profileFacebookUrl,
                            onValueChange = { profileFacebookUrl = it },
                            label         = { Text("Facebook URL") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                        )
                    }
                }

                // Home address
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("Home Address", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        OutlinedTextField(
                            value         = profileHomeStreet,
                            onValueChange = { profileHomeStreet = it },
                            label         = { Text("Street") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                        )
                        OutlinedTextField(
                            value         = profileHomeApt,
                            onValueChange = { profileHomeApt = it },
                            label         = { Text("Apt / Unit") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                        )
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value         = profileHomeCity,
                                onValueChange = { profileHomeCity = it },
                                label         = { Text("City") },
                                modifier      = Modifier.weight(2f),
                                singleLine    = true,
                                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                            )
                            OutlinedTextField(
                                value         = profileHomeState,
                                onValueChange = { profileHomeState = it.uppercase().take(2) },
                                label         = { Text("State") },
                                modifier      = Modifier.weight(1f),
                                singleLine    = true,
                                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters),
                            )
                        }
                        OutlinedTextField(
                            value         = profileHomeZip,
                            onValueChange = { profileHomeZip = it },
                            label         = { Text("ZIP") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        )
                    }
                }

                // Backup contact
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("Backup Contact", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        OutlinedTextField(
                            value         = profileBackupName,
                            onValueChange = { profileBackupName = it },
                            label         = { Text("Name") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                        )
                        OutlinedTextField(
                            value         = profileBackupPhone,
                            onValueChange = { profileBackupPhone = it },
                            label         = { Text("Phone") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                        )
                    }
                }

                // SMS / Notifications
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("SMS Notifications", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        OutlinedTextField(
                            value         = profileSmsPhone,
                            onValueChange = { profileSmsPhone = it },
                            label         = { Text("SMS Phone (defaults to phone if blank)") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                        )
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            androidx.compose.material3.Switch(
                                checked         = profileSmsEnabled,
                                onCheckedChange = { profileSmsEnabled = it },
                            )
                            Text("Enable SMS notifications")
                        }
                    }
                }

                // Public notes
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("Public Notes", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                        OutlinedTextField(
                            value         = profilePublicNotes,
                            onValueChange = { profilePublicNotes = it },
                            label         = { Text("Notes (visible on your public profile)") },
                            modifier      = Modifier.fillMaxWidth(),
                            minLines      = 3,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                        )
                    }
                }

                Button(onClick = { profileSave() }, modifier = Modifier.fillMaxWidth(), enabled = !profileIsLoading) {
                    Text("Save Profile")
                }
            }

            TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
        }
    }

    // ── Wearables section ───────────────────────────────────────────────────
    @Composable
    private fun WearablesSection() {
        val result = wearableResult
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                Text("Wearable Sync", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                TextButton(onClick = { loadWearables() }) { Text("Refresh") }
            }

            if (wearableIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            if (wearableMessage.isNotBlank()) {
                Text(
                    wearableMessage,
                    color = if (isErrorText(wearableMessage)) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            if (result == null && !wearableIsLoading) {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Not loaded", fontWeight = FontWeight.SemiBold)
                        Button(onClick = { loadWearables() }, modifier = Modifier.fillMaxWidth()) { Text("Load Wearables") }
                    }
                }
            }

            if (result != null) {
                // ── Your wearable ───────────────────────────────────────────
                Text("Your Wearable", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                WearablePickerRow(
                    items       = result.handlerWearables,
                    selectedSlug = wearableHandlerSlug,
                    onSelect    = { slug ->
                        wearableHandlerSlug = slug
                        val suggested = suggestSyncMode(slug)
                        if (suggested.isNotBlank()) wearableSyncMode = suggested
                    },
                )

                // ── Dog's tracker ───────────────────────────────────────────
                Text("Dog's Tracker", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                WearablePickerRow(
                    items        = result.dogTrackers,
                    selectedSlug = wearableDogSlug,
                    onSelect     = { wearableDogSlug = it },
                )

                // ── Sync mode ───────────────────────────────────────────────
                val autoSuggested = suggestSyncMode(wearableHandlerSlug)
                Text("Sync Mode", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        result.syncModes.forEach { (key, info) ->
                            val isSelected = key == wearableSyncMode
                            val isAuto     = key == autoSuggested && wearableHandlerSlug.isNotBlank()
                            OutlinedCard(
                                onClick = { wearableSyncMode = key },
                                border  = BorderStroke(if (isSelected) 2.dp else 1.dp, if (isSelected) GpPrimary else GpOutline),
                                colors  = CardDefaults.outlinedCardColors(
                                    containerColor = if (isSelected) GpPrimaryContainer else Color.Transparent,
                                ),
                            ) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth().padding(10.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.CenterVertically,
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Text(info.label, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal)
                                            if (isAuto) Text("· auto", style = MaterialTheme.typography.labelSmall, color = GpPrimary)
                                        }
                                        if (info.notes.isNotBlank())
                                            Text(info.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (isSelected) Text("✓", color = GpPrimary, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }

                // ── Notes ───────────────────────────────────────────────────
                OutlinedTextField(
                    value           = wearableNotes,
                    onValueChange   = { wearableNotes = it },
                    label           = { Text("Notes (optional)") },
                    modifier        = Modifier.fillMaxWidth(),
                    minLines        = 2,
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                )

                Button(onClick = { wearableSave() }, modifier = Modifier.fillMaxWidth()) {
                    Text("Save Setup")
                }

                // ── Recent activity ─────────────────────────────────────────
                if (result.recentEvents.isNotEmpty()) {
                    Text("Recent Activity", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                    result.recentEvents.forEach { event -> WearableEventCard(event) }
                }
            }

            TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
        }
    }

    @Composable
    private fun WearablePickerRow(
        items: List<GuidePawWearableCatalogItem>,
        selectedSlug: String,
        onSelect: (String) -> Unit,
    ) {
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                // "None" deselect chip
                if (selectedSlug.isNotBlank()) {
                    TextButton(
                        onClick  = { onSelect("") },
                        modifier = Modifier.align(Alignment.End),
                    ) { Text("Clear selection", style = MaterialTheme.typography.labelSmall) }
                }
                items.forEach { item ->
                    val isSelected = item.slug == selectedSlug
                    OutlinedCard(
                        onClick = { onSelect(item.slug) },
                        border  = BorderStroke(if (isSelected) 2.dp else 1.dp, if (isSelected) GpPrimary else GpOutline),
                        colors  = CardDefaults.outlinedCardColors(
                            containerColor = if (isSelected) GpPrimaryContainer else Color.Transparent,
                        ),
                    ) {
                        Row(
                            modifier              = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment     = Alignment.CenterVertically,
                        ) {
                            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text(item.label, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.SemiBold, style = MaterialTheme.typography.bodyMedium)
                                Text(item.dataFocus, style = MaterialTheme.typography.labelSmall, color = GpPrimary)
                                if (item.notes.isNotBlank())
                                    Text(item.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            }
                            if (isSelected) Text("✓", color = GpPrimary, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 8.dp))
                        }
                    }
                }
            }
        }
    }

    private fun suggestSyncMode(handlerSlug: String): String = when (handlerSlug) {
        "apple-watch"                   -> "healthkit"
        "samsung-galaxy-watch",
        "samsung-galaxy-watch-6-classic" -> "samsung_health"
        "google-pixel-watch"            -> "health_connect"
        "garmin-watch"                  -> "garmin_health"
        "fitbit"                        -> "fitbit_api"
        "oura-ring"                     -> "oura_api"
        "polar-suunto"                  -> "polar_accesslink"
        "fitbark"                       -> "fitbark_api"
        else                            -> ""
    }

    @Composable
    private fun WearableEventCard(event: GuidePawWearableEvent) {
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(
                    modifier              = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(event.deviceName.ifBlank { event.source }, fontWeight = FontWeight.SemiBold)
                    Text(event.recordedForDate, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
                if (event.steps != null) Text("Steps: ${event.steps}", style = MaterialTheme.typography.bodySmall)
                if (event.distanceMiles != null) Text("Distance: ${"%.2f".format(event.distanceMiles)} mi", style = MaterialTheme.typography.bodySmall)
                if (event.activeMinutes != null) Text("Active: ${event.activeMinutes} min", style = MaterialTheme.typography.bodySmall)
                if (event.sleepHours != null) Text("Sleep: ${"%.1f".format(event.sleepHours)} hr", style = MaterialTheme.typography.bodySmall)
                if (event.avgHeartRate != null) Text("Avg HR: ${event.avgHeartRate} bpm", style = MaterialTheme.typography.bodySmall)
                if (event.batteryPercent != null) Text("Battery: ${event.batteryPercent}%", style = MaterialTheme.typography.bodySmall)
                if (event.summaryText.isNotBlank()) Text(event.summaryText, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }
        }
    }

    // ── Notifications section ───────────────────────────────────────────────
    @Composable
    private fun NotificationsSection() {
        val token  = currentToken
        val result = notifResult
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Notification Center", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)

            if (notifMessage.isNotBlank()) {
                Text(notifMessage, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            if (token.isNullOrBlank()) {
                SummaryCard {
                    Text("Sign in first to view notifications.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
            } else {
                // Inbox snapshot
                SummaryCard {
                    Text("Inbox", fontWeight = FontWeight.SemiBold)
                    if (result != null) {
                        Text(
                            buildString {
                                append("Signed in as ")
                                append(if (result.username.isBlank()) "your account" else result.username)
                                result.activeDogId?.let { append(" · active dog #$it") }
                                if (result.hiddenCount > 0) append(" · ${result.hiddenCount} hidden")
                            },
                            style = MaterialTheme.typography.bodySmall,
                            color = GpOnSurfaceVariant,
                        )
                        Text(
                            "Unread: ${result.unreadCount}  ·  Visible unread: ${result.visibleUnreadCount}",
                            style = MaterialTheme.typography.bodySmall,
                        )
                    } else {
                        Text("Loading notifications...", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                }

                // Preferences
                SummaryCard {
                    Text("Notification preferences", fontWeight = FontWeight.SemiBold)
                    Text(
                        if (result != null) "Filter your inbox by category."
                        else "Tap Refresh if the inbox does not load.",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    Row(
                        modifier              = Modifier.padding(top = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
                        FilterChip(selected = notifPrefAccess,  onClick = { notifPrefAccess  = !notifPrefAccess  }, label = { Text("Access",  fontSize = 12.sp) })
                        FilterChip(selected = notifPrefCare,    onClick = { notifPrefCare    = !notifPrefCare    }, label = { Text("Care",    fontSize = 12.sp) })
                        FilterChip(selected = notifPrefAdmin,   onClick = { notifPrefAdmin   = !notifPrefAdmin   }, label = { Text("Admin",   fontSize = 12.sp) })
                        FilterChip(selected = notifPrefGeneral, onClick = { notifPrefGeneral = !notifPrefGeneral }, label = { Text("General", fontSize = 12.sp) })
                    }
                    Row(
                        modifier              = Modifier
                            .fillMaxWidth()
                            .padding(top = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        Button(onClick = { notifSavePreferences() }, modifier = Modifier.weight(1f)) { Text("Save prefs") }
                        OutlinedButton(onClick = { notifMarkAllRead() }, modifier = Modifier.weight(1f)) { Text("Mark all read") }
                    }
                    OutlinedButton(
                        onClick  = { refreshNotifications() },
                        modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
                    ) { Text("Refresh") }
                }

                // Notification list
                SummaryCard {
                    val notifications = result?.notifications ?: emptyList()
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text("Notifications", fontWeight = FontWeight.SemiBold)
                        if (notifications.isNotEmpty()) {
                            Text(
                                "${notifSelectedIds.size} selected",
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )
                        }
                    }
                    if (notifications.isNotEmpty()) {
                        Row(
                            modifier = Modifier.padding(top = 8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            OutlinedButton(
                                onClick = { notifSelectedIds = notifications.map { it.id }.toSet() },
                                modifier = Modifier.weight(1f),
                            ) { Text("Select all") }
                            OutlinedButton(
                                onClick = { notifSelectedIds = emptySet() },
                                modifier = Modifier.weight(1f),
                            ) { Text("Clear") }
                            Button(
                                onClick = { notifDeleteSelected(notifSelectedIds.toList()) },
                                enabled = notifSelectedIds.isNotEmpty(),
                                modifier = Modifier.weight(1f),
                            ) { Text("Delete selected") }
                        }
                    }
                    if (notifications.isEmpty()) {
                        Text(
                            "No notifications yet.",
                            style    = MaterialTheme.typography.bodySmall,
                            color    = GpOnSurfaceVariant,
                            modifier = Modifier.padding(top = 4.dp),
                        )
                    } else {
                        notifications.forEach { notification ->
                            NotifNotificationCard(
                                notification = notification,
                                selected = notifSelectedIds.contains(notification.id),
                                onSelectedChange = { selected ->
                                    notifSelectedIds = if (selected) {
                                        notifSelectedIds + notification.id
                                    } else {
                                        notifSelectedIds - notification.id
                                    }
                                },
                            )
                        }
                    }
                }

                // Pending invites
                SummaryCard {
                    Text("Pending invites", fontWeight = FontWeight.SemiBold)
                    val invites = result?.pendingInvites ?: emptyList()
                    if (invites.isEmpty()) {
                        Text(
                            "No pending dog access invites.",
                            style    = MaterialTheme.typography.bodySmall,
                            color    = GpOnSurfaceVariant,
                            modifier = Modifier.padding(top = 4.dp),
                        )
                    } else {
                        invites.forEach { NotifInviteCard(it) }
                    }
                }
            }
        }
    }

    @Composable
    private fun NotifNotificationCard(
        notification: GuidePawNotificationItem,
        selected: Boolean,
        onSelectedChange: (Boolean) -> Unit,
    ) {
        val context = LocalContext.current
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp),
        ) {
            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.Top,
                ) {
                    Checkbox(
                        checked = selected,
                        onCheckedChange = onSelectedChange,
                    )
                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(notification.title, fontWeight = FontWeight.SemiBold)
                        val meta = buildString {
                            append(notification.category)
                            if (notification.dogName.isNotBlank()) { append(" · "); append(notification.dogName) }
                            append(" · ")
                            append(if (notification.isRead) "read" else "unread")
                            if (notification.priority.isNotBlank()) { append(" · "); append(notification.priority) }
                        }
                        Text(meta, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                }
                if (notification.body.isNotBlank()) {
                    Text(notification.body, style = MaterialTheme.typography.bodySmall)
                }
                if (notification.createdAt.isNotBlank()) {
                    Text(
                        notification.createdAt.replace('T', ' ').take(19),
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                }
                Row(
                    modifier              = Modifier.padding(top = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (notification.actionUrl.isNotBlank()) {
                        Button(onClick = { openWebPage(notification.actionUrl) }, modifier = Modifier.weight(1f)) { Text("Open") }
                        OutlinedButton(
                            onClick = {
                                val cb = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                cb.setPrimaryClip(ClipData.newPlainText("GuidePaw notification link", notification.actionUrl))
                            },
                            modifier = Modifier.weight(1f),
                        ) { Text("Copy link") }
                    }
                    if (!notification.isRead) {
                        Button(onClick = { notifMarkSelectedRead(notification.id) }, modifier = Modifier.weight(1f)) { Text("Mark read") }
                    }
                    OutlinedButton(
                        onClick  = { notifDeleteSelected(listOf(notification.id)) },
                        modifier = Modifier.weight(1f),
                    ) { Text("Delete") }
                }
            }
        }
    }

    @Composable
    private fun NotifInviteCard(invite: GuidePawNotificationInviteItem) {
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp),
        ) {
            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text("Dog access invite", fontWeight = FontWeight.SemiBold)
                Text(
                    buildString {
                        append(invite.dogName.ifBlank { "Dog" })
                        if (invite.role.isNotBlank()) { append(" · "); append(invite.role) }
                        if (invite.permissionLevel.isNotBlank()) { append(" · "); append(invite.permissionLevel) }
                        if (invite.accessEndsAt.isNotBlank()) { append("\nEnds: "); append(invite.accessEndsAt.take(10)) }
                    },
                    style = MaterialTheme.typography.bodySmall,
                )
                Text(
                    listOf(invite.ownerDisplayName, invite.ownerUsername).filter { it.isNotBlank() }.joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Row(
                    modifier              = Modifier.padding(top = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Button(onClick = { notifAcceptInvite(invite.handlerId) }, modifier = Modifier.weight(1f)) { Text("Accept") }
                    OutlinedButton(onClick = { notifDeclineInvite(invite.handlerId) }, modifier = Modifier.weight(1f)) { Text("Decline") }
                }
            }
        }
    }

    // ── Feedback section ────────────────────────────────────────────────────
    @Composable
    private fun FeedbackSection() {
        val attachmentLauncher = rememberLauncherForActivityResult(
            ActivityResultContracts.StartActivityForResult()
        ) { result ->
            if (result.resultCode != RESULT_OK) return@rememberLauncherForActivityResult
            val data = result.data ?: return@rememberLauncherForActivityResult
            val picked = mutableListOf<FeedbackAttachment>()
            val clip = data.clipData
            if (clip != null) {
                for (i in 0 until clip.itemCount) {
                    feedbackResolveAttachment(clip.getItemAt(i).uri ?: continue)?.let(picked::add)
                }
            } else {
                data.data?.let { feedbackResolveAttachment(it)?.let(picked::add) }
            }
            if (picked.isNotEmpty()) feedbackAttachments = feedbackAttachments + picked
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Feedback / Bug Report", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)

            if (feedbackMessage.isNotBlank()) {
                Text(feedbackMessage, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }
            if (feedbackIsLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            SummaryCard {
                Text(
                    "Submitted from GuidePaw Companion v${CompanionAppVersion.VERSION_NAME} on Android.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )

                Spacer(Modifier.height(8.dp))
                Text("Category", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
                Row(
                    modifier              = Modifier.fillMaxWidth().padding(top = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    listOf("bug" to "Bug", "feature" to "Feature", "enhancement" to "Enhancement").forEach { (value, label) ->
                        FilterChip(
                            selected = feedbackCategory == value,
                            onClick  = { feedbackCategory = value },
                            label    = { Text(label, fontSize = 12.sp) },
                            modifier = Modifier.weight(1f),
                        )
                    }
                }

                OutlinedTextField(
                    value           = feedbackPageWorkflow,
                    onValueChange   = { feedbackPageWorkflow = it },
                    label           = { Text("Page or workflow") },
                    placeholder     = { Text("dogs.php, login, training log", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) },
                    singleLine      = true,
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                    modifier        = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value           = feedbackEmail,
                    onValueChange   = { feedbackEmail = it },
                    label           = { Text("Contact email (optional)") },
                    singleLine      = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, autoCorrect = false),
                    modifier        = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value           = feedbackDetails,
                    onValueChange   = { feedbackDetails = it },
                    label           = { Text("Details") },
                    placeholder     = { Text("What happened, what you expected, steps to reproduce.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) },
                    minLines        = 5,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text, capitalization = KeyboardCapitalization.Sentences),
                    modifier        = Modifier.fillMaxWidth(),
                )

                Spacer(Modifier.height(4.dp))
                Text("Attachments", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Medium)
                if (feedbackAttachments.isEmpty()) {
                    Text("No attachments selected.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                } else {
                    feedbackAttachments.forEachIndexed { i, a ->
                        Text(
                            "${i + 1}. ${a.displayName} (${a.mimeType.ifBlank { "file" }}${a.sizeBytes?.let { ", ${feedbackFormatBytes(it)}" } ?: ""})",
                            style = MaterialTheme.typography.bodySmall,
                            color = GpOnSurfaceVariant,
                        )
                    }
                }
                Row(
                    modifier              = Modifier.fillMaxWidth().padding(top = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Button(
                        onClick  = {
                            val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
                                addCategory(Intent.CATEGORY_OPENABLE)
                                type = "*/*"
                                putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
                                putExtra(Intent.EXTRA_MIME_TYPES, arrayOf(
                                    "image/*", "video/*", "audio/*", "application/pdf",
                                    "text/plain", "text/csv", "application/json",
                                    "application/msword",
                                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                                ))
                            }
                            attachmentLauncher.launch(Intent.createChooser(intent, "Select attachments"))
                        },
                        enabled  = !feedbackIsLoading,
                        modifier = Modifier.weight(1f),
                    ) { Text("Attach file", fontSize = 13.sp) }
                    OutlinedButton(
                        onClick  = { feedbackAttachments = emptyList() },
                        enabled  = !feedbackIsLoading && feedbackAttachments.isNotEmpty(),
                    ) { Text("Clear") }
                }

                Spacer(Modifier.height(4.dp))
                Button(
                    onClick  = { feedbackSubmit() },
                    enabled  = !feedbackIsLoading,
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Send feedback") }
                OutlinedButton(
                    onClick  = { openWebPage("https://guidepaw.app/feedback.php") },
                    enabled  = !feedbackIsLoading,
                    modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
                ) { Text("Open web feedback") }
            }
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

    // ── ADA Access Card section ─────────────────────────────────────────────
    @Composable
    private fun ADAAccessCardSection() {
        val context    = LocalContext.current
        val activeDog  = currentDogs.firstOrNull { it.id == currentActiveDogId }
        val handlerName = currentMe?.username ?: "Handler"
        val dogName     = activeDog?.name ?: "your dog"
        val calmScript  = "This is my service dog. You may ask whether the dog is required because of a disability and what work or task the dog is trained to perform."
        var copied by remember { mutableStateOf(false) }

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
                    "ADA Access Card",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            // Handler / dog identity
            SummaryCard {
                Text(
                    "Handler: $handlerName  ·  Dog: $dogName",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
            }

            // Calm script — most prominent card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
            ) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Calm Script", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimaryContainer)
                    Text(
                        "\"$calmScript\"",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color      = MaterialTheme.colorScheme.onPrimaryContainer,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            onClick = {
                                val cb = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                cb.setPrimaryClip(ClipData.newPlainText("ADA calm script", calmScript))
                                copied = true
                            },
                        ) { Text(if (copied) "Copied!" else "Copy") }
                        OutlinedButton(
                            onClick = {
                                val intent = Intent(Intent.ACTION_SEND).apply {
                                    type = "text/plain"
                                    putExtra(Intent.EXTRA_TEXT, calmScript)
                                }
                                context.startActivity(Intent.createChooser(intent, "Share ADA calm script"))
                            },
                        ) { Text("Share") }
                    }
                }
            }

            // Two permitted questions
            SummaryCard {
                Text("Only two questions staff may ask", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                Text("1. Is the dog required because of a disability?", style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(4.dp))
                Text("2. What work or task has the dog been trained to perform?", style = MaterialTheme.typography.bodyMedium)
            }

            // What's not required
            SummaryCard {
                Text("What staff may NOT require", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Certification, registration papers, or an ID card",
                    "Medical records or diagnosis details",
                    "A task demonstration on demand",
                    "A vest, special harness, or any identifying equipment",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Definitions
            SummaryCard {
                Text("Definitions", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                Text("Service dog", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Text("Individually trained to do work or perform tasks related to a person's disability. Training may be done by a professional or the handler.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                Text("SDIT (service dog in training)", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Text("Not a service animal under the ADA until training is complete. State law may provide separate public access for training teams.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                Text("ESA (emotional support animal)", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Text("Comfort-only animals — not task-trained service dogs. No ADA public-access rights. Housing and airline rules are separate.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            // Scam warning
            SummaryCard {
                Text("Scam warning", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Online registrations, certificates, ID cards, and vests do not create ADA rights. The ADA does not require certification, registration, a vest, or a special harness.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            // When access can be denied
            SummaryCard {
                Text("When access can be denied", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                Text("• The dog is out of control and the handler does not take effective action.", style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(2.dp))
                Text("• The dog is not housebroken.", style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(6.dp))
                Text("Fear of dogs or allergies alone are not valid reasons to deny access.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            // DOJ phone
            SummaryCard {
                Text("DOJ ADA Information Line", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                TextButton(
                    onClick  = { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:8005140301"))) },
                    contentPadding = PaddingValues(0.dp),
                ) {
                    Text("800-514-0301", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                }
                Text("TTY: 800-514-0383", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            // State law note
            SummaryCard {
                Text("State law notes", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "This screen shows federal ADA guidance only. For state-specific law notes and GPS state detection, use the web version.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
                OutlinedButton(
                    onClick  = { openWebPage("https://guidepaw.app/ada_access_card.php") },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Open web version for state law") }
            }
        }
    }

    // ── Air Travel section ──────────────────────────────────────────────────
    @Composable
    private fun AirTravelSection() {
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
                    "Air Travel Rights",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            // Warning banner
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("Use this as a practical reference, not legal advice.", fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onErrorContainer, style = MaterialTheme.typography.bodySmall)
                    Text("Air travel is covered by the Air Carrier Access Act, not the ADA.", color = MaterialTheme.colorScheme.onErrorContainer, style = MaterialTheme.typography.bodySmall)
                }
            }

            // Service dogs covered
            SummaryCard {
                Text("Service dogs are covered on flights", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                Text(
                    "Under DOT air-travel rules, airlines must recognize dogs that are individually trained to do work or perform tasks for a person with a disability.",
                    style = MaterialTheme.typography.bodySmall,
                )
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Airlines must allow a covered service dog in the cabin on flights to, within, and from the United States.",
                    "Airlines may require DOT service animal forms and may ask the two travel-specific questions.",
                    "The dog must fit in the handler's foot space or under the seat in front of the handler when required by the airline.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // What airlines can ask/require
            SummaryCard {
                Text("What airlines can ask or require", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "A U.S. DOT Service Animal Air Transportation Form for health, behavior, and training.",
                    "A U.S. DOT Relief Attestation Form for flights of 8 hours or more.",
                    "Advance submission if the reservation was made before the 48-hour deadline.",
                    "Harness, leash, or tether control in the airport and on the aircraft when required by the airline.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // When travel can be denied
            SummaryCard {
                Text("When airlines can refuse transport", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "The dog is too large or heavy to fit safely in the cabin space.",
                    "The dog poses a direct threat to the health or safety of others.",
                    "The dog causes a significant disruption in the cabin or gate area.",
                    "The dog fails required health or destination-entry rules.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // SDIT note
            SummaryCard {
                Text("Service dogs in training (SDIT)", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                Text(
                    "DOT air-travel rules do not treat service dogs in training as service animals for flights.",
                    style = MaterialTheme.typography.bodySmall,
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    "If you are traveling with an SDIT, check the airline's current animal policy before you book. The carrier may treat the dog as a pet or may have its own handling rules, and destination rules can also matter.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
            }

            // Practical reminders
            SummaryCard {
                Text("Practical reminders", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Call the airline before you travel if you need a specific seating or relief-area plan.",
                    "Keep a copy of the DOT forms and reservation confirmation with you.",
                    "Check destination-country rules for international travel — foreign entry rules can override the U.S. baseline.",
                    "Ask for a Complaints Resolution Official if you believe your rights under air-travel disability rules are being denied.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // ADA Access Card cross-link
            SummaryCard {
                Text("Also useful", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text("For general ADA public-access rights, the calm script, and handler reference cards:", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(8.dp))
                OutlinedButton(onClick = { currentSection = NavSection.ADA_ACCESS_CARD }, modifier = Modifier.fillMaxWidth()) {
                    Text("Open ADA Access Card")
                }
            }

            // Sources note
            Text(
                "Sources: U.S. Department of Transportation service-animal guidance, final rule on traveling by air with service animals, and the Air Carrier Access Act summary.",
                style = MaterialTheme.typography.bodySmall,
                color = GpOnSurfaceVariant,
            )
        }
    }

    // ── Housing & Access FAQ section ────────────────────────────────────────
    @Composable
    private fun HousingFAQSection() {
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
                    "Housing & Access FAQ",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            // Public access
            SummaryCard {
                Text("Public access", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(2.dp))
                Text("Businesses can ask only limited questions", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "For public access, businesses may ask whether the dog is required because of a disability.",
                    "They may also ask what work or task the dog has been trained to perform.",
                    "They should not require certification, registration, or proof of training as a condition of entry.",
                    "If the dog is out of control or not housebroken, the business may remove the dog.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Housing
            SummaryCard {
                Text("Housing", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(2.dp))
                Text("Housing questions follow a different rule set", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Housing providers are not asking the same questions as a store or restaurant.",
                    "Fair Housing Act requests may involve reliable disability-related information when a need is not obvious.",
                    "HUD guidance is the right place to check when a housing provider wants documentation.",
                    "ESAs can matter in housing even though they do not have ADA public-access rights.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Common disputes
            SummaryCard {
                Text("Common disputes", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))

                Text("A business wants a vest, card, or certificate.", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(2.dp))
                Text("That is not required by the ADA. The business should be guided by the two permitted questions and the dog's actual behavior.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(10.dp))

                Text("A landlord wants something different from a store.", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(2.dp))
                Text("Housing requests use Fair Housing Act rules. The right response depends on whether the animal is a service dog or an assistance animal in housing.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                Spacer(Modifier.height(10.dp))

                Text("A public place says the dog is too disruptive.", fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(2.dp))
                Text("If the dog is out of control and the handler does not correct it, or if the dog is not housebroken, removal can be allowed under ADA rules.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
            }

            // Keep the category straight
            SummaryCard {
                Text("Keep the category straight", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Public access follows ADA service-animal rules.",
                    "Housing follows Fair Housing Act assistance-animal rules.",
                    "Air travel follows DOT / ACAA service-animal rules.",
                    "ESAs are not service dogs — do not treat them as the same thing in public access or on flights.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Official sources
            SummaryCard {
                Text("Official sources", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                listOf(
                    "ADA Service Animals FAQ" to "https://www.ada.gov/resources/service-animals-faqs/",
                    "ADA Service Animals" to "https://www.ada.gov/topics/service-animals/",
                    "HUD Assistance Animals" to "https://www.hud.gov/program_offices/fair_housing_equal_opp/assistance_animals",
                ).forEach { (label, url) ->
                    TextButton(
                        onClick        = { openWebPage(url) },
                        contentPadding = PaddingValues(0.dp),
                    ) { Text(label, style = MaterialTheme.typography.bodySmall) }
                }
            }

            // Cross-links to companion screens
            SummaryCard {
                Text("Related screens", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(onClick = { currentSection = NavSection.ADA_ACCESS_CARD }, modifier = Modifier.weight(1f)) { Text("ADA Card") }
                    OutlinedButton(onClick = { currentSection = NavSection.AIR_TRAVEL }, modifier = Modifier.weight(1f)) { Text("Air Travel") }
                }
            }
        }
    }

    // ── Tactical Training section ───────────────────────────────────────────
    @Composable
    private fun TacticalTrainingSection() {
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
                    "Tactical Training",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            // Who this is for
            SummaryCard {
                Text("Who this is for", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Security and executive protection handlers",
                    "Police K9 handlers in training",
                    "Fire and EMS teams with detection dogs",
                    "Military working dog handlers",
                    "Search and rescue (SAR) teams",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Module 1
            SummaryCard {
                Text("1. Operational foundation", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Assess the dog's readiness and review structured training programs before moving to field work.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(10.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick   = { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_ASSESSMENT },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Candidate Assessment") }
                    OutlinedButton(
                        onClick   = { openWebPage("https://guidepaw.app/training_program.php") },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Training Programs") }
                }
            }

            // Module 2
            SummaryCard {
                Text("2. Search and response", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Build precise task goals and log every field session to track consistency across environments.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(10.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick   = { currentSection = NavSection.GOAL_BUILDER },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Goal Builder") }
                    OutlinedButton(
                        onClick   = { currentSection = NavSection.TRAINING },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Log Training") }
                }
            }

            // Module 3
            SummaryCard {
                Text("3. Distraction resilience", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Score current behavior risks and use structured distraction protocols to raise the threshold.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(10.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick   = { loadBehaviorRisk(currentActiveDogId); currentSection = NavSection.BEHAVIOR_RISK },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Behavior Risk") }
                    OutlinedButton(
                        onClick   = { openWebPage("https://guidepaw.app/trucking_mode.php") },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Trucking Mode") }
                }
            }

            // Module 4
            SummaryCard {
                Text("4. Team proofing", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Review full training history and regression events to confirm readiness before operational deployment.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(10.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick   = { loadRegressionEvents(); currentSection = NavSection.REGRESSION },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Regression Engine") }
                    OutlinedButton(
                        onClick   = { currentSection = NavSection.DOGS },
                        modifier  = Modifier.weight(1f),
                    ) { Text("Training History") }
                }
            }

            // Suggested tactical focus
            SummaryCard {
                Text("Suggested tactical focus", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Focus on task reliability under distraction before expanding to new environments.",
                    "Use short, high-value training sessions in each new environment.",
                    "Log every session — regression triggers appear in the pattern over time.",
                    "Reassess candidate readiness every 4–6 weeks during active training phases.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            // Web version for access management
            OutlinedButton(
                onClick   = { openWebPage("https://guidepaw.app/tactical_training.php") },
                modifier  = Modifier.fillMaxWidth(),
            ) { Text("Manage tactical access on web") }
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

    // ── Certification section ───────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun CertificationSection() {
        val result = certResult
        val byCategory = result?.items?.groupBy { it.category } ?: emptyMap()

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadCertification(); isPullingToRefresh = false },
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
                        if (result != null) "✅ ${result.dogName}" else "✅ Certification",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                SectionMessage(certMessage, onRetry = { loadCertification() })

                // Summary stats
                if (result != null) {
                    SummaryCard {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            StatChip("Items", result.total.toString(), Modifier.weight(1f))
                            StatChip("Proficient", result.proficient.toString(), Modifier.weight(1f))
                            StatChip("In training", result.inTraining.toString(), Modifier.weight(1f))
                            StatChip("Ready", "${result.readinessPct}%", Modifier.weight(1f))
                        }
                    }
                }

                // Checklist
                if (result != null && result.items.isEmpty()) {
                    SummaryCard {
                        Text("No checklist loaded yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        Spacer(Modifier.height(8.dp))
                        Button(onClick = { seedCertTemplate() }, modifier = Modifier.fillMaxWidth()) {
                            Text("Load starter checklist")
                        }
                    }
                }

                byCategory.forEach { (category, items) ->
                    val expanded = certExpandedCategories.contains(category)
                    val proficientInCat = items.count { it.status == "proficient" }
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column {
                            // Category header — tap to expand/collapse
                            TextButton(
                                onClick  = {
                                    certExpandedCategories = if (expanded)
                                        certExpandedCategories - category
                                    else
                                        certExpandedCategories + category
                                },
                                modifier = Modifier.fillMaxWidth(),
                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 10.dp),
                            ) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.CenterVertically,
                                ) {
                                    Text(category, fontWeight = FontWeight.SemiBold, color = GpOnSurface)
                                    Text(
                                        "$proficientInCat/${items.size}  ${if (expanded) "▲" else "▼"}",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = GpOnSurfaceVariant,
                                    )
                                }
                            }

                            if (expanded) {
                                HorizontalDivider()
                                Column(
                                    modifier = Modifier.padding(12.dp),
                                    verticalArrangement = Arrangement.spacedBy(12.dp),
                                ) {
                                    items.forEach { item ->
                                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Text(item.itemName, fontWeight = FontWeight.Medium, style = MaterialTheme.typography.bodySmall)
                                            if (item.description.isNotBlank()) {
                                                Text(item.description, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                            }
                                            if (item.notes.isNotBlank()) {
                                                Text("Note: ${item.notes}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                            }
                                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                listOf("not_started" to "Not started", "in_training" to "In training", "proficient" to "Proficient")
                                                    .forEach { (value, label) ->
                                                        if (item.status == value) {
                                                            Button(
                                                                onClick        = {},
                                                                modifier       = Modifier.weight(1f),
                                                                contentPadding = PaddingValues(horizontal = 2.dp, vertical = 6.dp),
                                                            ) { Text(label, style = MaterialTheme.typography.labelSmall, maxLines = 1) }
                                                        } else {
                                                            OutlinedButton(
                                                                onClick        = { updateCertItem(item.id, value, item.notes) },
                                                                modifier       = Modifier.weight(1f),
                                                                contentPadding = PaddingValues(horizontal = 2.dp, vertical = 6.dp),
                                                            ) { Text(label, style = MaterialTheme.typography.labelSmall, maxLines = 1) }
                                                        }
                                                    }
                                            }
                                            if (item !== items.last()) HorizontalDivider(color = GpOutline)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Assessment snapshot
                if (result?.assessment != null) {
                    val asm = result.assessment
                    SummaryCard {
                        Text("Latest assessment snapshot", fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(4.dp))
                        Text(formatAssessmentDate(asm.assessmentDate), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                            StatChip("Public", asm.publicAccessScore?.let { "$it%" } ?: "—", Modifier.weight(1f))
                            StatChip("Task", asm.taskReliabilityScore?.let { "$it%" } ?: "—", Modifier.weight(1f))
                            StatChip("Obedience", asm.obedienceScore?.let { "$it%" } ?: "—", Modifier.weight(1f))
                            StatChip("Environ.", asm.environmentalScore?.let { "$it%" } ?: "—", Modifier.weight(1f))
                        }
                        if (asm.notes.isNotBlank()) {
                            Spacer(Modifier.height(6.dp))
                            Text(asm.notes, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }

                // Add assessment form
                OutlinedButton(onClick = { certShowAsmForm = !certShowAsmForm }, modifier = Modifier.fillMaxWidth()) {
                    Text(if (certShowAsmForm) "Cancel" else "+ Add Assessment")
                }
                if (certShowAsmForm) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text("New assessment", fontWeight = FontWeight.SemiBold)
                            OutlinedTextField(
                                value = certAsmDate, onValueChange = { certAsmDate = it },
                                label = { Text("Date") }, placeholder = { Text("YYYY-MM-DD") },
                                modifier = Modifier.fillMaxWidth(), singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            )
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = certAsmPublic, onValueChange = { certAsmPublic = it },
                                    label = { Text("Public access %") }, modifier = Modifier.weight(1f), singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                )
                                OutlinedTextField(
                                    value = certAsmTask, onValueChange = { certAsmTask = it },
                                    label = { Text("Task %") }, modifier = Modifier.weight(1f), singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                )
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = certAsmObedience, onValueChange = { certAsmObedience = it },
                                    label = { Text("Obedience %") }, modifier = Modifier.weight(1f), singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                )
                                OutlinedTextField(
                                    value = certAsmEnv, onValueChange = { certAsmEnv = it },
                                    label = { Text("Environmental %") }, modifier = Modifier.weight(1f), singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                )
                            }
                            OutlinedTextField(
                                value = certAsmNotes, onValueChange = { certAsmNotes = it },
                                label = { Text("Notes") }, modifier = Modifier.fillMaxWidth(), minLines = 2,
                            )
                            Button(onClick = { submitCertAssessment() }, modifier = Modifier.fillMaxWidth()) {
                                Text("Save Assessment")
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Health Docs section ─────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun HealthDocsSection() {
        val result = healthDocsResult

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadHealthDocs(); isPullingToRefresh = false },
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
                        if (result != null) "🩺 ${result.dogName}" else "🩺 Health & Docs",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                SectionMessage(healthDocsMessage, onRetry = { loadHealthDocs() })

                // ── Vet contacts ──────────────────────────────────────────
                Text("Vet Contacts", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)

                if (result != null && result.vets.isNotEmpty()) {
                    result.vets.forEach { vet ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text(vet.clinicName, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                    if (vet.isPrimary) {
                                        Text(
                                            "Primary",
                                            style      = MaterialTheme.typography.labelSmall,
                                            color      = MaterialTheme.colorScheme.primary,
                                            fontWeight = FontWeight.SemiBold,
                                        )
                                    }
                                }
                                if (vet.vetName.isNotBlank()) {
                                    Text(vet.vetName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (vet.phone.isNotBlank()) {
                                    val context = LocalContext.current
                                    TextButton(
                                        onClick        = { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${vet.phone}"))) },
                                        contentPadding = PaddingValues(0.dp),
                                    ) { Text(vet.phone, style = MaterialTheme.typography.bodySmall) }
                                }
                                if (vet.address.isNotBlank()) {
                                    Text(vet.address, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (vet.notes.isNotBlank()) {
                                    Text(vet.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                            }
                        }
                    }
                } else if (result != null) {
                    SummaryCard { Text("No vets saved yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                }

                // Add vet form
                OutlinedButton(onClick = { vetShowForm = !vetShowForm }, modifier = Modifier.fillMaxWidth()) {
                    Text(if (vetShowForm) "Cancel" else "+ Add Vet Contact")
                }
                if (vetShowForm) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text("New vet contact", fontWeight = FontWeight.SemiBold)
                            OutlinedTextField(
                                value = vetClinic, onValueChange = { vetClinic = it },
                                label = { Text("Clinic name *") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                            )
                            OutlinedTextField(
                                value = vetName, onValueChange = { vetName = it },
                                label = { Text("Vet doctor name") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                            )
                            OutlinedTextField(
                                value = vetPhone, onValueChange = { vetPhone = it },
                                label = { Text("Phone") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                            )
                            OutlinedTextField(
                                value = vetAddress, onValueChange = { vetAddress = it },
                                label = { Text("Address") }, modifier = Modifier.fillMaxWidth(), minLines = 2,
                            )
                            OutlinedTextField(
                                value = vetNotes, onValueChange = { vetNotes = it },
                                label = { Text("Hours / notes") }, modifier = Modifier.fillMaxWidth(), minLines = 2,
                            )
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                androidx.compose.material3.Checkbox(checked = vetIsPrimary, onCheckedChange = { vetIsPrimary = it })
                                Text("Primary / home vet", style = MaterialTheme.typography.bodySmall)
                            }
                            Button(onClick = { submitAddVet() }, modifier = Modifier.fillMaxWidth()) { Text("Save Vet") }
                        }
                    }
                }

                // ── Documents ─────────────────────────────────────────────
                Spacer(Modifier.height(4.dp))
                Text("Documents", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)

                if (result != null && result.documents.isNotEmpty()) {
                    result.documents.forEach { doc ->
                        val typeLabel = when (doc.docType) {
                            "esa_letter"          -> "ESA Letter"
                            "service_dog_letter"  -> "Service Dog Letter"
                            else                  -> "Vet Record"
                        }
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.Top,
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(doc.title, fontWeight = FontWeight.SemiBold)
                                        Text(typeLabel, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                                    }
                                    Text(
                                        formatAssessmentDate(doc.createdAt),
                                        style = MaterialTheme.typography.bodySmall,
                                        color = GpOnSurfaceVariant,
                                        modifier = Modifier.padding(start = 8.dp),
                                    )
                                }
                                if (doc.providerName.isNotBlank()) {
                                    Text(doc.providerName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (doc.fileUrl.isNotBlank()) {
                                    TextButton(
                                        onClick        = { openWebPage(doc.fileUrl) },
                                        contentPadding = PaddingValues(0.dp),
                                    ) { Text("Open file", style = MaterialTheme.typography.bodySmall) }
                                }
                            }
                        }
                    }
                } else if (result != null) {
                    SummaryCard { Text("No documents uploaded yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                }

                OutlinedButton(
                    onClick  = { openWebPage("https://guidepaw.app/dog_health.php") },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Upload documents on web") }
            }
        }
    }

    // ── Vet Appointments section ────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun VetAppointmentsSection() {
        val result = appointmentsResult

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadAppointments(); isPullingToRefresh = false },
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
                        if (result != null) "📅 ${result.dogName}" else "📅 Vet Appointments",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                SectionMessage(appointmentsMessage, onRetry = { loadAppointments() })

                OutlinedButton(
                    onClick  = { apptShowForm = !apptShowForm },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text(if (apptShowForm) "Cancel" else "+ Schedule Appointment") }

                if (apptShowForm) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text("New appointment", fontWeight = FontWeight.SemiBold)

                            OutlinedTextField(
                                value         = apptTitle,
                                onValueChange = { apptTitle = it },
                                label         = { Text("Title *") },
                                placeholder   = { Text("Annual wellness exam") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )

                            // Vet picker
                            if (!result?.vets.isNullOrEmpty()) {
                                val vets = result!!.vets
                                val selectedVet = vets.firstOrNull { it.id == apptVetId }
                                ExposedDropdownMenuBox(
                                    expanded         = apptVetExpanded,
                                    onExpandedChange = { apptVetExpanded = it },
                                ) {
                                    OutlinedTextField(
                                        value         = selectedVet?.clinicName ?: "No vet selected",
                                        onValueChange = {},
                                        readOnly      = true,
                                        label         = { Text("Vet / Clinic") },
                                        trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(apptVetExpanded) },
                                        modifier      = Modifier.menuAnchor().fillMaxWidth(),
                                    )
                                    ExposedDropdownMenu(
                                        expanded         = apptVetExpanded,
                                        onDismissRequest = { apptVetExpanded = false },
                                    ) {
                                        DropdownMenuItem(
                                            text    = { Text("No vet selected") },
                                            onClick = { apptVetId = 0; apptVetExpanded = false },
                                        )
                                        vets.forEach { vet ->
                                            DropdownMenuItem(
                                                text    = { Text(if (vet.vetName.isNotBlank()) "${vet.clinicName} — ${vet.vetName}" else vet.clinicName) },
                                                onClick = { apptVetId = vet.id; apptVetExpanded = false },
                                            )
                                        }
                                    }
                                }
                            }

                            OutlinedTextField(
                                value         = apptAt,
                                onValueChange = { apptAt = it },
                                label         = { Text("Appointment time *") },
                                placeholder   = { Text("YYYY-MM-DD HH:MM") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            )
                            OutlinedTextField(
                                value         = apptReminderAt,
                                onValueChange = { apptReminderAt = it },
                                label         = { Text("Reminder time") },
                                placeholder   = { Text("YYYY-MM-DD HH:MM") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            )
                            LocationPickerButton { name, _ ->
                                if (apptLocation.isBlank()) apptLocation = name
                            }
                            OutlinedTextField(
                                value         = apptLocation,
                                onValueChange = { apptLocation = it },
                                label         = { Text("Location") },
                                placeholder   = { Text("Clinic address") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )
                            OutlinedTextField(
                                value         = apptNotes,
                                onValueChange = { apptNotes = it },
                                label         = { Text("Notes") },
                                placeholder   = { Text("Shots due, paperwork to bring…") },
                                modifier      = Modifier.fillMaxWidth(),
                                minLines      = 2,
                            )
                            Button(onClick = { submitAddAppointment() }, modifier = Modifier.fillMaxWidth()) {
                                Text("Save Appointment")
                            }
                        }
                    }
                }

                // Appointment list
                if (result != null && result.appointments.isNotEmpty()) {
                    result.appointments.forEach { appt ->
                        val statusColor = when (appt.status) {
                            "scheduled" -> Color(0xFFCA8A04)
                            "completed" -> Color(0xFF16A34A)
                            else        -> GpOnSurfaceVariant
                        }
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.Top,
                                ) {
                                    Text(appt.title, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                    Text(
                                        appt.status.replaceFirstChar { it.uppercase() },
                                        style      = MaterialTheme.typography.labelSmall,
                                        color      = statusColor,
                                        fontWeight = FontWeight.SemiBold,
                                        modifier   = Modifier.padding(start = 8.dp),
                                    )
                                }
                                Text(
                                    formatAppointmentDateTime(appt.appointmentAt) +
                                        if (appt.clinicName.isNotBlank()) " • ${appt.clinicName}" else "",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = GpOnSurfaceVariant,
                                )
                                if (appt.locationText.isNotBlank()) {
                                    Text(appt.locationText, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (appt.reminderAt.isNotBlank()) {
                                    Text("Reminder: ${formatAppointmentDateTime(appt.reminderAt)}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (appt.notes.isNotBlank()) {
                                    Spacer(Modifier.height(2.dp))
                                    Text(appt.notes, style = MaterialTheme.typography.bodySmall)
                                }

                                if (appt.status == "scheduled") {
                                    Spacer(Modifier.height(6.dp))
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Button(
                                            onClick        = { updateAppointmentStatus(appt.id, "completed") },
                                            modifier       = Modifier.weight(1f),
                                            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
                                        ) { Text("Complete", style = MaterialTheme.typography.labelSmall) }
                                        OutlinedButton(
                                            onClick        = { updateAppointmentStatus(appt.id, "cancelled") },
                                            modifier       = Modifier.weight(1f),
                                            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
                                            colors         = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                                        ) { Text("Cancel", style = MaterialTheme.typography.labelSmall) }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private fun formatAppointmentDateTime(raw: String): String {
        if (raw.isBlank()) return "—"
        return try {
            val sdf = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US)
            val out = java.text.SimpleDateFormat("MMM d, yyyy h:mm a", java.util.Locale.US)
            out.format(sdf.parse(raw.take(19)) ?: return raw)
        } catch (_: Exception) { raw }
    }

    // ── Medications section ─────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun MedicationsSection() {
        val result = medicationsResult
        val statusOptions = listOf("active", "paused", "completed")

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadMedications(); isPullingToRefresh = false },
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
                        if (result != null) "💊 ${result.dogName}" else "💊 Medications",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                SectionMessage(medicationsMessage, onRetry = { loadMedications() })

                // Add medication toggle
                OutlinedButton(
                    onClick  = { medShowForm = !medShowForm },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text(if (medShowForm) "Cancel" else "+ Add Medication") }

                // Add medication form
                if (medShowForm) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text("New medication", fontWeight = FontWeight.SemiBold)

                            OutlinedTextField(
                                value         = medName,
                                onValueChange = { medName = it },
                                label         = { Text("Medication name *") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )
                            OutlinedTextField(
                                value         = medDosage,
                                onValueChange = { medDosage = it },
                                label         = { Text("Dosage") },
                                placeholder   = { Text("e.g. 25 mg") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )
                            OutlinedTextField(
                                value         = medSchedule,
                                onValueChange = { medSchedule = it },
                                label         = { Text("Schedule") },
                                placeholder   = { Text("e.g. Twice daily with food") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )

                            // Status picker
                            var statusExpanded by remember { mutableStateOf(false) }
                            ExposedDropdownMenuBox(
                                expanded         = statusExpanded,
                                onExpandedChange = { statusExpanded = it },
                            ) {
                                OutlinedTextField(
                                    value         = medStatus.replaceFirstChar { it.uppercase() },
                                    onValueChange = {},
                                    readOnly      = true,
                                    label         = { Text("Status") },
                                    trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(statusExpanded) },
                                    modifier      = Modifier.menuAnchor().fillMaxWidth(),
                                )
                                ExposedDropdownMenu(
                                    expanded         = statusExpanded,
                                    onDismissRequest = { statusExpanded = false },
                                ) {
                                    statusOptions.forEach { s ->
                                        DropdownMenuItem(
                                            text    = { Text(s.replaceFirstChar { it.uppercase() }) },
                                            onClick = { medStatus = s; statusExpanded = false },
                                        )
                                    }
                                }
                            }

                            OutlinedTextField(
                                value         = medRefillDate,
                                onValueChange = { medRefillDate = it },
                                label         = { Text("Refill date") },
                                placeholder   = { Text("YYYY-MM-DD") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            )
                            OutlinedTextField(
                                value         = medProvider,
                                onValueChange = { medProvider = it },
                                label         = { Text("Prescribing provider") },
                                modifier      = Modifier.fillMaxWidth(),
                                singleLine    = true,
                            )
                            OutlinedTextField(
                                value         = medInstructions,
                                onValueChange = { medInstructions = it },
                                label         = { Text("Instructions") },
                                modifier      = Modifier.fillMaxWidth(),
                                minLines      = 2,
                            )
                            OutlinedTextField(
                                value         = medNotes,
                                onValueChange = { medNotes = it },
                                label         = { Text("Notes") },
                                modifier      = Modifier.fillMaxWidth(),
                                minLines      = 2,
                            )
                            Button(onClick = { submitAddMedication() }, modifier = Modifier.fillMaxWidth()) {
                                Text("Save Medication")
                            }
                        }
                    }
                }

                // Medication list
                if (result != null && result.medications.isNotEmpty()) {
                    result.medications.forEach { med ->
                        val statusColor = when (med.status) {
                            "active"    -> Color(0xFF16A34A)
                            "paused"    -> Color(0xFFCA8A04)
                            else        -> GpOnSurfaceVariant
                        }
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.Top,
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(med.medicationName, fontWeight = FontWeight.SemiBold)
                                        if (med.dosage.isNotBlank() || med.scheduleText.isNotBlank()) {
                                            Text(
                                                listOf(med.dosage, med.scheduleText).filter { it.isNotBlank() }.joinToString(" • "),
                                                style = MaterialTheme.typography.bodySmall,
                                                color = GpOnSurfaceVariant,
                                            )
                                        }
                                    }
                                    Text(
                                        med.status.replaceFirstChar { it.uppercase() },
                                        style      = MaterialTheme.typography.labelSmall,
                                        color      = statusColor,
                                        fontWeight = FontWeight.SemiBold,
                                        modifier   = Modifier.padding(start = 8.dp),
                                    )
                                }
                                if (med.refillDate.isNotBlank()) {
                                    Text("Refill: ${formatAssessmentDate(med.refillDate)}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (med.prescribingProvider.isNotBlank()) {
                                    Text("Provider: ${med.prescribingProvider}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (med.instructions.isNotBlank()) {
                                    Spacer(Modifier.height(2.dp))
                                    Text(med.instructions, style = MaterialTheme.typography.bodySmall)
                                }
                                if (med.notes.isNotBlank()) {
                                    Text(med.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }

                                // Status update
                                Spacer(Modifier.height(6.dp))
                                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    statusOptions.forEach { s ->
                                        if (s == med.status) {
                                            Button(
                                                onClick        = {},
                                                modifier       = Modifier.weight(1f),
                                                contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
                                            ) { Text(s.replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.labelSmall) }
                                        } else {
                                            OutlinedButton(
                                                onClick        = { updateMedicationStatus(med.id, s) },
                                                modifier       = Modifier.weight(1f),
                                                contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp),
                                            ) { Text(s.replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.labelSmall) }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                OutlinedButton(
                    onClick  = { openWebPage("https://guidepaw.app/medications.php") },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Full medication form on web") }
            }
        }
    }

    // ── Candidate Comparison section ────────────────────────────────────────
    @Composable
    private fun CandidateComparisonSection() {
        val result = candidateResult
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
                    "Compare Dogs",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            if (result == null) {
                SummaryCard {
                    Text("Load candidate data to compare your dogs.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = { loadCandidateAssessments() }, modifier = Modifier.fillMaxWidth()) {
                        Text("Load Comparison")
                    }
                }
            } else {
                val dogs = result.dogs
                val latestByDog = result.assessments.groupBy { it.dogId }.mapValues { (_, list) -> list.first() }
                val dogsWithAssessments = dogs.count { latestByDog.containsKey(it.id) }
                val scores = dogs.mapNotNull { latestByDog[it.id]?.averageScore }
                val overallAvg = if (scores.isNotEmpty()) scores.average() else null

                // Summary stats
                SummaryCard {
                    Text("Summary", fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        StatChip("Dogs", dogs.size.toString(), Modifier.weight(1f))
                        StatChip("Assessed", dogsWithAssessments.toString(), Modifier.weight(1f))
                        StatChip("Avg score", overallAvg?.let { String.format("%.1f", it) } ?: "—", Modifier.weight(1f))
                    }
                }

                if (dogs.isEmpty()) {
                    SummaryCard {
                        Text("No dogs found. Add a dog profile first.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                } else {
                    dogs.forEach { dog ->
                        val assessment = latestByDog[dog.id]
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text(dog.name, fontWeight = FontWeight.Bold)
                                if (assessment == null) {
                                    Text("No assessment yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                } else {
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text(
                                            "Focus Level ${assessment.focusLevelRecommended}",
                                            style      = MaterialTheme.typography.labelSmall,
                                            color      = MaterialTheme.colorScheme.primary,
                                            fontWeight = FontWeight.SemiBold,
                                        )
                                        Text(
                                            "Avg ${String.format("%.1f", assessment.averageScore)}/5",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = GpOnSurfaceVariant,
                                        )
                                    }
                                    Text(assessment.recommendation, style = MaterialTheme.typography.bodySmall)
                                    if (assessment.safetyFlags.isNotBlank()) {
                                        Text(
                                            "Safety: ${assessment.safetyFlags}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.error,
                                        )
                                    }
                                    Text(
                                        "Assessed ${formatAssessmentDate(assessment.createdAt)}",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = GpOnSurfaceVariant,
                                    )
                                }
                            }
                        }
                    }
                }

                OutlinedButton(
                    onClick  = { openWebPage("https://guidepaw.app/candidate_comparison.php") },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Full score table on web") }
            }
        }
    }

    @Composable
    private fun StatChip(label: String, value: String, modifier: Modifier = Modifier) {
        OutlinedCard(modifier = modifier) {
            Column(modifier = Modifier.padding(10.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(value, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
                Text(label, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
            }
        }
    }

    private fun formatAssessmentDate(createdAt: String): String {
        return try {
            val sdf = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
            val out = java.text.SimpleDateFormat("MMM d, yyyy", java.util.Locale.US)
            out.format(sdf.parse(createdAt.take(10)) ?: return createdAt)
        } catch (_: Exception) { createdAt }
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
                    "👤 Handler Profile" to { if (profileResult == null) loadProfile(); currentSection = NavSection.PROFILE },
                    "🐕 Dogs"            to { currentSection = NavSection.DOGS },
                    "🪪 Dog Profile"     to { openWebPage("https://guidepaw.app/dog_profile.php") },
                    "🤝 Dog Access"      to { if (dogAccessResult == null) loadDogAccess(); currentSection = NavSection.DOG_ACCESS },
                    "📡 QR Tracking"     to { openWebPage("https://guidepaw.app/qr_tracking.php") },
                    "📊 Stats"           to { if (statsResult == null) loadStats(); currentSection = NavSection.STATS },
                ), onDismiss)
                MenuSheetSection("Training", listOf(
                    "⚡ Log Training"         to { currentSection = NavSection.TRAINING },
                    "🎯 Goal Intake"          to { loadGoalIntake(goalIntakeFilter); currentSection = NavSection.GOAL_INTAKE },
                    "🛠️ Habit Repair"        to { loadHabitRepair(); currentSection = NavSection.HABIT_REPAIR },
                    "⚠️ Behavior Risk"        to { loadBehaviorRisk(currentActiveDogId); currentSection = NavSection.BEHAVIOR_RISK },
                    "♻️ Regression Engine"   to { loadRegressionEvents(); currentSection = NavSection.REGRESSION },
                    "🐾 Candidate Assessment" to { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_ASSESSMENT },
                    "📊 Compare Dogs"         to { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_COMPARISON },
                    "🧩 Goal Builder"         to { currentSection = NavSection.GOAL_BUILDER },
                    "🎖️ Tactical Training"   to { currentSection = NavSection.TACTICAL_TRAINING },
                ), onDismiss)
                MenuSheetSection("Care", listOf(
                    "🩺 Health Docs"      to { loadHealthDocs(); currentSection = NavSection.HEALTH_DOCS },
                    "📅 Vet Appointments" to { loadAppointments(); currentSection = NavSection.APPOINTMENTS },
                    "💊 Medications"      to { loadMedications(); currentSection = NavSection.MEDICATIONS },
                    "⌚ Wearable Sync"    to { if (wearableResult == null) loadWearables(); currentSection = NavSection.WEARABLES },
                ), onDismiss)
                MenuSheetSection("More", listOf(
                    "🔔 Notification Center" to { currentSection = NavSection.NOTIFICATIONS; if (notifResult == null) refreshNotifications() },
                    "🧠 Smart Alerts"      to { loadAlerts(); currentSection = NavSection.SMART_ALERTS },
                    "💬 Feedback"          to { currentSection = NavSection.FEEDBACK },
                    "🪪 ADA Access Card"   to { currentSection = NavSection.ADA_ACCESS_CARD },
                    "✈️ Air Travel Rights" to { currentSection = NavSection.AIR_TRAVEL },
                    "🏠 Housing & Access"  to { currentSection = NavSection.HOUSING_FAQ },
                    "✅ Certification"      to { loadCertification(); currentSection = NavSection.CERTIFICATION },
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

    // ── Notification Center actions ─────────────────────────────────────────

    private fun notifSyncPreferences(preferences: Map<String, Boolean>) {
        notifPrefAccess  = preferences["access"]  ?: true
        notifPrefCare    = preferences["care"]    ?: true
        notifPrefAdmin   = preferences["admin"]   ?: true
        notifPrefGeneral = preferences["general"] ?: true
    }

    private fun refreshNotifications() {
        val token = currentToken ?: return
        notifMessage = "Loading notifications..."
        worker.execute {
            try {
                val result = api.notifications(token)
                runOnUiThread {
                    notifResult       = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = ""
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not load notifications.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not load notifications.") }
            }
        }
    }

    private fun notifSavePreferences() {
        val token = currentToken ?: return
        val prefsMap = linkedMapOf(
            "access"  to notifPrefAccess,
            "care"    to notifPrefCare,
            "admin"   to notifPrefAdmin,
            "general" to notifPrefGeneral,
        )
        notifMessage = "Saving preferences..."
        worker.execute {
            try {
                val result = api.saveNotificationPreferences(token, prefsMap)
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = "Preferences saved."
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not save preferences.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not save preferences.") }
            }
        }
    }

    private fun notifMarkAllRead() {
        val token = currentToken ?: return
        notifMessage = "Marking all read..."
        worker.execute {
            try {
                val result = api.markNotificationsRead(token)
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = "All notifications marked read."
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not update notifications.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not update notifications.") }
            }
        }
    }

    private fun notifMarkSelectedRead(notificationId: Int) {
        val token = currentToken ?: return
        notifMessage = "Marking read..."
        worker.execute {
            try {
                val result = api.markNotificationsRead(token, listOf(notificationId))
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = ""
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not update notification.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not update notification.") }
            }
        }
    }

    private fun notifDeleteSelected(notificationIds: List<Int>) {
        val token = currentToken ?: return
        if (notificationIds.isEmpty()) return
        notifMessage = "Deleting..."
        worker.execute {
            try {
                val result = api.deleteNotifications(token, notificationIds)
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = ""
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not delete notification.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not delete notification.") }
            }
        }
    }

    private fun notifAcceptInvite(handlerId: Int) {
        val token = currentToken ?: return
        notifMessage = "Accepting invite..."
        worker.execute {
            try {
                val result = api.acceptDogAccessInvite(token, handlerId)
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = "Invite accepted."
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not accept invite.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not accept invite.") }
            }
        }
    }

    private fun notifDeclineInvite(handlerId: Int) {
        val token = currentToken ?: return
        notifMessage = "Declining invite..."
        worker.execute {
            try {
                val result = api.declineDogAccessInvite(token, handlerId)
                runOnUiThread {
                    notifResult = result
                    currentUnreadCount = result.visibleUnreadCount
                    notifSyncPreferences(result.preferences)
                    notifSelectedIds = emptySet()
                    notifMessage = "Invite declined."
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { notifMessage = friendlyMessage(e.message, "Could not decline invite.") }
            } catch (t: Throwable) {
                runOnUiThread { notifMessage = friendlyMessage(t.message, "Could not decline invite.") }
            }
        }
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

    private fun loadCertification() {
        val token = currentToken ?: return
        certMessage = "Loading..."
        worker.execute {
            try {
                val result = api.certification(token)
                runOnUiThread {
                    certResult  = result
                    certMessage = ""
                    if (certExpandedCategories.isEmpty() && result.items.isNotEmpty()) {
                        certExpandedCategories = setOf(result.items.first().category)
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread { certMessage = friendlyMessage(t.message, "Could not load certification data.") }
            }
        }
    }

    private fun seedCertTemplate() {
        val token = currentToken ?: return
        setLoading(true, "Loading template...")
        worker.execute {
            try {
                api.seedCertTemplate(token)
                runOnUiThread { setLoading(false, "Checklist loaded."); loadCertification() }
            } catch (t: Throwable) {
                runOnUiThread { setLoading(false, ""); certMessage = friendlyMessage(t.message, "Could not load template.") }
            }
        }
    }

    private fun updateCertItem(itemId: Int, status: String, notes: String) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.updateCertItem(token, itemId, status, notes)
                runOnUiThread { loadCertification() }
            } catch (t: Throwable) {
                runOnUiThread { certMessage = friendlyMessage(t.message, "Could not update item.") }
            }
        }
    }

    private fun submitCertAssessment() {
        val token = currentToken ?: return
        setLoading(true, "Saving assessment...")
        worker.execute {
            try {
                api.addCertAssessment(
                    token           = token,
                    assessmentDate  = certAsmDate.ifBlank { java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Date()) },
                    publicAccess    = certAsmPublic.trim().toIntOrNull()?.coerceIn(0, 100) ?: 0,
                    taskReliability = certAsmTask.trim().toIntOrNull()?.coerceIn(0, 100) ?: 0,
                    obedience       = certAsmObedience.trim().toIntOrNull()?.coerceIn(0, 100) ?: 0,
                    environmental   = certAsmEnv.trim().toIntOrNull()?.coerceIn(0, 100) ?: 0,
                    notes           = certAsmNotes.trim(),
                )
                runOnUiThread {
                    certAsmDate = ""; certAsmPublic = ""; certAsmTask = ""; certAsmObedience = ""; certAsmEnv = ""; certAsmNotes = ""
                    certShowAsmForm = false
                    setLoading(false, "Assessment saved.")
                    loadCertification()
                }
            } catch (t: Throwable) {
                runOnUiThread { setLoading(false, ""); certMessage = friendlyMessage(t.message, "Could not save assessment.") }
            }
        }
    }

    private fun loadHealthDocs() {
        val token = currentToken ?: return
        healthDocsMessage = "Loading..."
        worker.execute {
            try {
                val result = api.healthDocs(token)
                runOnUiThread {
                    healthDocsResult  = result
                    healthDocsMessage = ""
                }
            } catch (t: Throwable) {
                runOnUiThread { healthDocsMessage = friendlyMessage(t.message, "Could not load health records.") }
            }
        }
    }

    private fun submitAddVet() {
        val token = currentToken ?: return
        if (vetClinic.isBlank()) { healthDocsMessage = "Clinic name is required."; return }
        setLoading(true, "Saving vet...")
        worker.execute {
            try {
                api.addVet(
                    token      = token,
                    clinicName = vetClinic.trim(),
                    vetName    = vetName.trim(),
                    phone      = vetPhone.trim(),
                    address    = vetAddress.trim(),
                    notes      = vetNotes.trim(),
                    isPrimary  = vetIsPrimary,
                )
                runOnUiThread {
                    vetClinic = ""; vetName = ""; vetPhone = ""; vetAddress = ""; vetNotes = ""; vetIsPrimary = false
                    vetShowForm = false
                    setLoading(false, "Vet saved.")
                    loadHealthDocs()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    setLoading(false, "")
                    healthDocsMessage = friendlyMessage(t.message, "Could not save vet.")
                }
            }
        }
    }

    private fun loadAppointments() {
        val token = currentToken ?: return
        appointmentsMessage = "Loading..."
        worker.execute {
            try {
                val result = api.appointments(token)
                runOnUiThread {
                    appointmentsResult  = result
                    appointmentsMessage = if (result.appointments.isEmpty()) "No appointments for ${result.dogName} yet." else ""
                }
            } catch (t: Throwable) {
                runOnUiThread { appointmentsMessage = friendlyMessage(t.message, "Could not load appointments.") }
            }
        }
    }

    private fun submitAddAppointment() {
        val token = currentToken ?: return
        if (apptTitle.isBlank()) { appointmentsMessage = "Title is required."; return }
        if (apptAt.isBlank()) { appointmentsMessage = "Appointment time is required."; return }
        setLoading(true, "Saving appointment...")
        worker.execute {
            try {
                api.addAppointment(
                    token         = token,
                    title         = apptTitle.trim(),
                    appointmentAt = apptAt.trim(),
                    reminderAt    = apptReminderAt.trim(),
                    locationText  = apptLocation.trim(),
                    notes         = apptNotes.trim(),
                    vetId         = apptVetId,
                )
                runOnUiThread {
                    apptTitle = ""; apptAt = ""; apptReminderAt = ""; apptLocation = ""; apptNotes = ""; apptVetId = 0
                    apptShowForm = false
                    setLoading(false, "Appointment saved.")
                    loadAppointments()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    setLoading(false, "")
                    appointmentsMessage = friendlyMessage(t.message, "Could not save appointment.")
                }
            }
        }
    }

    private fun updateAppointmentStatus(apptId: Int, newStatus: String) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.markAppointmentStatus(token, apptId, newStatus)
                runOnUiThread { loadAppointments() }
            } catch (t: Throwable) {
                runOnUiThread { appointmentsMessage = friendlyMessage(t.message, "Could not update appointment.") }
            }
        }
    }

    private fun loadMedications() {
        val token = currentToken ?: return
        medicationsMessage = "Loading..."
        worker.execute {
            try {
                val result = api.medications(token)
                runOnUiThread {
                    medicationsResult  = result
                    medicationsMessage = if (result.medications.isEmpty()) "No medications tracked for ${result.dogName} yet." else ""
                }
            } catch (t: Throwable) {
                runOnUiThread { medicationsMessage = friendlyMessage(t.message, "Could not load medications.") }
            }
        }
    }

    private fun submitAddMedication() {
        val token = currentToken ?: return
        if (medName.isBlank()) { medicationsMessage = "Medication name is required."; return }
        setLoading(true, "Saving medication...")
        worker.execute {
            try {
                api.addMedication(
                    token               = token,
                    medicationName      = medName.trim(),
                    dosage              = medDosage.trim(),
                    scheduleText        = medSchedule.trim(),
                    status              = medStatus,
                    refillDate          = medRefillDate.trim(),
                    prescribingProvider = medProvider.trim(),
                    instructions        = medInstructions.trim(),
                    notes               = medNotes.trim(),
                )
                runOnUiThread {
                    medName = ""; medDosage = ""; medSchedule = ""; medStatus = "active"
                    medRefillDate = ""; medProvider = ""; medInstructions = ""; medNotes = ""
                    medShowForm = false
                    setLoading(false, "Medication saved.")
                    loadMedications()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    setLoading(false, "")
                    medicationsMessage = friendlyMessage(t.message, "Could not save medication.")
                }
            }
        }
    }

    private fun updateMedicationStatus(medId: Int, status: String) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.setMedicationStatus(token, medId, status)
                runOnUiThread { loadMedications() }
            } catch (t: Throwable) {
                runOnUiThread { medicationsMessage = friendlyMessage(t.message, "Could not update status.") }
            }
        }
    }

    private fun loadDogAccess() {
        val token = currentToken ?: return
        dogAccessIsLoading = true
        dogAccessMessage   = "Loading..."
        worker.execute {
            try {
                val result = api.getDogAccess(token, currentActiveDogId)
                runOnUiThread { dogAccessResult = result; dogAccessIsLoading = false; dogAccessMessage = "" }
            } catch (t: Throwable) {
                runOnUiThread { dogAccessIsLoading = false; dogAccessMessage = friendlyMessage(t.message, "Could not load dog access.") }
            }
        }
    }

    private fun dogAccessGrant(dogId: Int) {
        val token = currentToken ?: return
        if (dogAccessInviteId.isBlank()) { dogAccessMessage = "Username or email is required."; return }
        dogAccessIsLoading = true; dogAccessMessage = "Sending invite..."
        worker.execute {
            try {
                val result = api.grantDogAccess(token, dogId, dogAccessInviteId.trim(), dogAccessInviteRole.trim(), dogAccessInvitePerm, dogAccessInviteEnds.trim())
                runOnUiThread {
                    dogAccessResult = result; dogAccessIsLoading = false
                    dogAccessMessage = "Invite sent."; dogAccessShowInvite = false
                    dogAccessInviteId = ""; dogAccessInviteRole = "co-op handler"; dogAccessInvitePerm = "view"; dogAccessInviteEnds = ""
                }
            } catch (t: Throwable) {
                runOnUiThread { dogAccessIsLoading = false; dogAccessMessage = friendlyMessage(t.message, "Could not send invite.") }
            }
        }
    }

    private fun dogAccessRevoke(handlerId: Int) {
        val token = currentToken ?: return
        dogAccessIsLoading = true; dogAccessMessage = "Revoking..."
        worker.execute {
            try {
                val result = api.revokeDogAccess(token, handlerId)
                runOnUiThread { dogAccessResult = result; dogAccessIsLoading = false; dogAccessMessage = "Access revoked." }
            } catch (t: Throwable) {
                runOnUiThread { dogAccessIsLoading = false; dogAccessMessage = friendlyMessage(t.message, "Could not revoke access.") }
            }
        }
    }

    private fun dogAccessRespondInvite(handlerId: Int, accept: Boolean) {
        val token = currentToken ?: return
        dogAccessIsLoading = true; dogAccessMessage = if (accept) "Accepting..." else "Declining..."
        worker.execute {
            try {
                val result = api.respondDogAccessInvite(token, handlerId, accept)
                runOnUiThread { dogAccessResult = result; dogAccessIsLoading = false; dogAccessMessage = if (accept) "Invite accepted." else "Invite declined." }
            } catch (t: Throwable) {
                runOnUiThread { dogAccessIsLoading = false; dogAccessMessage = friendlyMessage(t.message, "Could not respond to invite.") }
            }
        }
    }

    private fun dogAccessRespondTransfer(transferId: Int, accept: Boolean) {
        val token = currentToken ?: return
        dogAccessIsLoading = true; dogAccessMessage = if (accept) "Accepting transfer..." else "Declining transfer..."
        worker.execute {
            try {
                val result = api.respondTransfer(token, transferId, accept)
                runOnUiThread { dogAccessResult = result; dogAccessIsLoading = false; dogAccessMessage = if (accept) "Transfer accepted." else "Transfer declined." }
            } catch (t: Throwable) {
                runOnUiThread { dogAccessIsLoading = false; dogAccessMessage = friendlyMessage(t.message, "Could not respond to transfer.") }
            }
        }
    }

    private fun loadStats() {
        val token = currentToken ?: return
        statsIsLoading = true
        statsMessage   = "Loading..."
        worker.execute {
            try {
                val result = api.getStats(token)
                runOnUiThread {
                    statsResult    = result
                    statsIsLoading = false
                    statsMessage   = if (result == null) "No active dog." else ""
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statsIsLoading = false
                    statsMessage   = friendlyMessage(t.message, "Could not load stats.")
                }
            }
        }
    }

    private fun loadAlerts() {
        val token = currentToken ?: return
        alertsIsLoading = true
        alertsMessage   = "Loading..."
        worker.execute {
            try {
                val result = api.getAlerts(token)
                runOnUiThread {
                    alertsResult    = result
                    alertsIsLoading = false
                    alertsMessage   = if (result.isEmpty()) "No active alerts for your dog." else ""
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    alertsIsLoading = false
                    alertsMessage   = friendlyMessage(t.message, "Could not load alerts.")
                }
            }
        }
    }

    private fun loadProfile() {
        val token = currentToken ?: return
        profileIsLoading = true
        profileMessage   = "Loading..."
        worker.execute {
            try {
                val p = api.getProfile(token)
                runOnUiThread {
                    profileResult      = p
                    profileIsLoading   = false
                    profileMessage     = ""
                    profileDisplayName = p.displayName
                    profileHomeStreet  = p.homeStreet
                    profileHomeApt     = p.homeApt
                    profileHomeCity    = p.homeCity
                    profileHomeState   = p.homeState
                    profileHomeZip     = p.homeZip
                    profilePhone       = p.phone
                    profilePublicEmail = p.publicEmail
                    profileFacebookUrl = p.facebookUrl
                    profileBackupName  = p.backupContactName
                    profileBackupPhone = p.backupContactPhone
                    profilePublicNotes = p.publicNotes
                    profileSmsPhone    = p.smsPhone
                    profileSmsEnabled  = p.smsNotificationsEnabled
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    profileIsLoading = false
                    profileMessage   = friendlyMessage(t.message, "Could not load profile.")
                }
            }
        }
    }

    private fun profileSave() {
        val token = currentToken ?: return
        profileIsLoading = true
        profileMessage   = "Saving..."
        worker.execute {
            try {
                val msg = api.saveProfile(
                    token                  = token,
                    displayName            = profileDisplayName.trim(),
                    homeStreet             = profileHomeStreet.trim(),
                    homeApt                = profileHomeApt.trim(),
                    homeCity               = profileHomeCity.trim(),
                    homeState              = profileHomeState.trim(),
                    homeZip                = profileHomeZip.trim(),
                    phone                  = profilePhone.trim(),
                    publicEmail            = profilePublicEmail.trim(),
                    facebookUrl            = profileFacebookUrl.trim(),
                    backupContactName      = profileBackupName.trim(),
                    backupContactPhone     = profileBackupPhone.trim(),
                    publicNotes            = profilePublicNotes.trim(),
                    smsPhone               = profileSmsPhone.trim(),
                    smsNotificationsEnabled = profileSmsEnabled,
                )
                runOnUiThread {
                    profileIsLoading = false
                    profileMessage   = msg
                    loadProfile()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    profileIsLoading = false
                    profileMessage   = friendlyMessage(t.message, "Could not save profile.")
                }
            }
        }
    }

    private fun loadWearables() {
        val token = currentToken ?: return
        wearableIsLoading = true
        wearableMessage   = "Loading..."
        worker.execute {
            try {
                val result = api.wearables(token, currentActiveDogId)
                runOnUiThread {
                    wearableResult    = result
                    wearableIsLoading = false
                    wearableMessage   = ""
                    val setup = result.currentSetup
                    if (setup != null) {
                        wearableHandlerSlug = setup.handlerWearableSlug.orEmpty()
                        wearableDogSlug     = setup.dogTrackerSlug.orEmpty()
                        wearableSyncMode    = setup.syncMode.orEmpty()
                        wearableNotes       = setup.notes.orEmpty()
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    wearableIsLoading = false
                    wearableMessage   = friendlyMessage(t.message, "Could not load wearable data.")
                }
            }
        }
    }

    private fun wearableSave() {
        val token = currentToken ?: return
        val dogId = currentActiveDogId ?: run { wearableMessage = "Select an active dog first."; return }
        wearableIsLoading = true
        wearableMessage   = "Saving..."
        worker.execute {
            try {
                api.saveWearableSetup(
                    token               = token,
                    dogId               = dogId,
                    handlerWearableSlug = wearableHandlerSlug,
                    dogTrackerSlug      = wearableDogSlug,
                    syncMode            = wearableSyncMode,
                    notes               = wearableNotes,
                )
                runOnUiThread {
                    wearableShowSetupForm = false
                    wearableIsLoading     = false
                    wearableMessage       = "Setup saved."
                    loadWearables()
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    wearableIsLoading = false
                    wearableMessage   = friendlyMessage(t.message, "Could not save wearable setup.")
                }
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

    // ── Feedback helpers ────────────────────────────────────────────────────

    private fun feedbackSubmit() {
        val detailsVal = feedbackDetails.trim()
        if (detailsVal.isBlank()) { feedbackMessage = "Add details before sending."; return }
        feedbackIsLoading = true
        feedbackMessage   = "Sending feedback..."
        val token         = currentToken
        val category      = feedbackCategory
        val pageVal       = feedbackPageWorkflow.trim()
        val emailVal      = feedbackEmail.trim()
        val attaches      = feedbackAttachments.toList()
        worker.execute {
            try {
                val attachmentInputs = attaches.map { a ->
                    GuidePawFeedbackAttachmentInput(uri = a.uri, displayName = a.displayName, mimeType = a.mimeType)
                }
                val response = api.submitFeedback(
                    token           = token,
                    category        = category,
                    pageWorkflow    = pageVal,
                    contactEmail    = emailVal,
                    details         = detailsVal,
                    sourceVersion   = CompanionAppVersion.VERSION_NAME,
                    sourceDevice    = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}".trim(),
                    attachments     = attachmentInputs,
                    contentResolver = contentResolver,
                )
                runOnUiThread {
                    feedbackMessage = buildString {
                        append(response.message ?: "Feedback sent.")
                        if (response.uploadDebug.isNotEmpty()) append(" ${response.uploadDebug.joinToString(" | ")}")
                    }
                    feedbackAttachments  = emptyList()
                    feedbackDetails      = ""
                    feedbackPageWorkflow = ""
                    feedbackEmail        = ""
                    feedbackCategory     = "bug"
                    feedbackIsLoading    = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { feedbackMessage = friendlyMessage(e.message, "Could not send feedback."); feedbackIsLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { feedbackMessage = friendlyMessage(t.message, "Could not send feedback."); feedbackIsLoading = false }
            }
        }
    }

    private fun feedbackResolveAttachment(uri: Uri): FeedbackAttachment? {
        val projection  = arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE)
        val name = runCatching {
            contentResolver.query(uri, projection, null, null, null)?.use { it.feedbackReadName(uri) }
        }.getOrNull()
        val mime        = contentResolver.getType(uri).orEmpty()
        val displayName = feedbackEnsureExtension(
            name ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() } ?: "attachment",
            mime,
        )
        val size = runCatching {
            contentResolver.query(uri, arrayOf(OpenableColumns.SIZE), null, null, null)?.use { c ->
                if (!c.moveToFirst()) null
                else { val idx = c.getColumnIndex(OpenableColumns.SIZE); if (idx >= 0 && !c.isNull(idx)) c.getLong(idx).takeIf { it > 0 } else null }
            }
        }.getOrNull()
        return FeedbackAttachment(uri = uri, displayName = displayName, mimeType = mime.ifBlank { feedbackInferMime(displayName) }, sizeBytes = size)
    }

    private fun Cursor.feedbackReadName(uri: Uri): String? {
        if (!moveToFirst()) return null
        val idx = getColumnIndex(OpenableColumns.DISPLAY_NAME)
        return (if (idx >= 0 && !isNull(idx)) getString(idx)?.trim()?.takeIf { it.isNotBlank() } else null)
            ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() }
    }

    private fun feedbackInferMime(name: String): String {
        val lower = name.lowercase()
        return when {
            lower.endsWith(".pdf")  -> "application/pdf"
            lower.endsWith(".doc")  -> "application/msword"
            lower.endsWith(".docx") -> "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            lower.endsWith(".csv")  -> "text/csv"
            lower.endsWith(".json") -> "application/json"
            lower.endsWith(".txt") || lower.endsWith(".log") -> "text/plain"
            lower.endsWith(".mp3")  -> "audio/mpeg"
            lower.endsWith(".m4a")  -> "audio/mp4"
            lower.endsWith(".wav")  -> "audio/wav"
            lower.endsWith(".mp4")  -> "video/mp4"
            lower.endsWith(".mov")  -> "video/quicktime"
            lower.endsWith(".jpg") || lower.endsWith(".jpeg") -> "image/jpeg"
            lower.endsWith(".png")  -> "image/png"
            lower.endsWith(".gif")  -> "image/gif"
            lower.endsWith(".webp") -> "image/webp"
            else                    -> "application/octet-stream"
        }
    }

    private fun feedbackEnsureExtension(displayName: String, mimeType: String): String {
        val current = displayName.trim().ifBlank { "attachment" }
        if (current.contains('.')) return current
        val ext = when (mimeType.lowercase()) {
            "application/pdf"     -> "pdf"; "application/msword" -> "doc"
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" -> "docx"
            "text/csv"            -> "csv"; "application/json"   -> "json"; "text/plain" -> "txt"
            "audio/mpeg"          -> "mp3"; "audio/mp4"          -> "m4a";  "audio/wav"  -> "wav"
            "video/mp4"           -> "mp4"; "video/quicktime"    -> "mov"
            "image/jpeg"          -> "jpg"; "image/png"          -> "png";  "image/gif"  -> "gif"
            "image/webp"          -> "webp"
            else                  -> ""
        }
        return if (ext.isBlank()) current else "$current.$ext"
    }

    private fun feedbackFormatBytes(bytes: Long): String = when {
        bytes >= 1024 * 1024 -> String.format("%.1f MB", bytes / 1024.0 / 1024.0)
        bytes >= 1024        -> String.format("%.1f KB", bytes / 1024.0)
        else                 -> "$bytes B"
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
