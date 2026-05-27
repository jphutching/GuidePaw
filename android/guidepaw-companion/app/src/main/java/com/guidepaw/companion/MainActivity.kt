package com.guidepaw.companion

import android.Manifest
import androidx.compose.foundation.clickable
import android.app.DownloadManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
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
import androidx.compose.foundation.Image
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.rememberCoroutineScope
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import java.io.File
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import androidx.compose.animation.AnimatedVisibility
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import java.util.Locale
import kotlin.coroutines.resume
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.layout.Box
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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.width
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.res.painterResource
import com.guidepaw.companion.R
import androidx.compose.material3.AlertDialog
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
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
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
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
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
private enum class NavSection { OVERVIEW, TRAINING, TRAINING_HISTORY, DOGS, WEARABLES, MORE, NOTIFICATIONS, FEEDBACK, GOAL_INTAKE, GOAL_BUILDER, HABIT_REPAIR, BEHAVIOR_RISK, REGRESSION, CANDIDATE_ASSESSMENT, CANDIDATE_COMPARISON, ADA_ACCESS_CARD, AIR_TRAVEL, HOUSING_FAQ, STATE_ACCESS, VET_FINDER, TACTICAL_TRAINING, TRUCKING_MODE, COMMUNITY_CHALLENGES, MEDICATIONS, APPOINTMENTS, HEALTH_DOCS, HEALTH_SUMMARY, CERTIFICATION, TRAINING_PROGRAM, PROFILE, STATS, DOG_ACCESS, QR_TRACKING, SMART_ALERTS, FORGOT_PASSWORD, DOG_PROFILE, SETTINGS, AI_ASSISTANT, ESA_LEGAL, BREED_QUIZ, FAQ, PLANS, TRAINER_MARKETPLACE, ADD_DOG, PUBLIC_DOG_PROFILE, REGISTER }

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
    private var forgotPasswordEmail   by mutableStateOf("")
    private var forgotPasswordMessage by mutableStateOf("")
    private var forgotPasswordSent    by mutableStateOf(false)
    private var showUpdateCard   by mutableStateOf(false)
    private var updateStatusText by mutableStateOf("")
    private var showMenu         by mutableStateOf(false)
    private var pendingLaunchSection by mutableStateOf<String?>(null)
    private var qrResult         by mutableStateOf<GpQrResult?>(null)
    private var qrBitmap         by mutableStateOf<Bitmap?>(null)
    private var qrMessage        by mutableStateOf("")
    private var qrIsLoading      by mutableStateOf(false)

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
    private var trainProgResult             by mutableStateOf<GpTrainingProgramResult?>(null)
    private var trainProgMessage            by mutableStateOf("")
    private var trainProgExpandedCategories by mutableStateOf<Set<String>>(emptySet())
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
    private var showWhatsNew       by mutableStateOf(false)
    private var selectedLogDetail  by mutableStateOf<GuidePawLogItem?>(null)

    // ── Registration state ─────────────────────────────────────────────────
    private var regFullName    by mutableStateOf("")
    private var regStreet      by mutableStateOf("")
    private var regApt         by mutableStateOf("")
    private var regCity        by mutableStateOf("")
    private var regState       by mutableStateOf("")
    private var regZip         by mutableStateOf("")
    private var regPhone       by mutableStateOf("")
    private var regEmail       by mutableStateOf("")
    private var regPassword    by mutableStateOf("")
    private var regConfirm     by mutableStateOf("")
    private var regLoading     by mutableStateOf(false)
    private var regMessage     by mutableStateOf("")
    private var regIsError     by mutableStateOf(false)
    private var docShowForm        by mutableStateOf(false)
    private var docPickUri         by mutableStateOf<Uri?>(null)
    private var docPickMime        by mutableStateOf("")
    private var docPickName        by mutableStateOf("")
    private var docPickType        by mutableStateOf("vet_record")
    private var docPickTitle       by mutableStateOf("")
    private var docPickProvider    by mutableStateOf("")
    private var docPickNotes       by mutableStateOf("")
    private var docPickMessage     by mutableStateOf("")
    private var docPickLoading     by mutableStateOf(false)

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
    private var medicationsResult     by mutableStateOf<GpMedicationsResult?>(null)
    private var medicationsMessage    by mutableStateOf("")
    private var healthSummary         by mutableStateOf<GpHealthSummary?>(null)
    private var healthMessage         by mutableStateOf("")
    private var healthIsLoading       by mutableStateOf(false)
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
    private var logMediaUri    by mutableStateOf<Uri?>(null)
    private var logMediaMime   by mutableStateOf("")
    private var logMediaName   by mutableStateOf("")

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
        val seenVersion = prefs.getInt(KEY_SEEN_WHATS_NEW, 0)
        if (CompanionAppVersion.VERSION_CODE > seenVersion) {
            showWhatsNew = true
            prefs.edit().putInt(KEY_SEEN_WHATS_NEW, CompanionAppVersion.VERSION_CODE).apply()
        }
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
            refreshDashboard(startToken, null, keepSignedInOnFailure = true)
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
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            brush = Brush.horizontalGradient(listOf(Color(0xFF0D6EFD), Color(0xFF2856C8))),
                            shape = RoundedCornerShape(bottomStart = 16.dp, bottomEnd = 16.dp),
                        )
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Image(
                        painter            = painterResource(R.drawable.guidepaw_logo),
                        contentDescription = null,
                        modifier           = Modifier.size(36.dp).clip(RoundedCornerShape(8.dp)),
                    )
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text("GuidePaw", color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleSmall)
                        Text("Handler tools. All in one place.", color = Color.White.copy(alpha = 0.75f), style = MaterialTheme.typography.labelSmall)
                    }
                    Text(
                        "v${CompanionAppVersion.VERSION_NAME}",
                        color    = Color.White.copy(alpha = 0.45f),
                        style    = MaterialTheme.typography.labelSmall,
                        fontSize = 9.sp,
                    )
                }
                if (showWhatsNew) WhatsNewDialog()
                selectedLogDetail?.let { LogDetailDialog(it) }
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
                val preAuthSections = setOf(NavSection.FORGOT_PASSWORD, NavSection.FEEDBACK, NavSection.REGISTER)
                if (currentToken == null && currentSection !in preAuthSections) {
                    LoginSection()
                } else {
                    when (currentSection) {
                        NavSection.OVERVIEW       -> OverviewSection()
                        NavSection.TRAINING         -> TrainingSection()
                        NavSection.TRAINING_HISTORY -> TrainingHistorySection()
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
                        NavSection.HEALTH_SUMMARY       -> HealthSummarySection()
                        NavSection.CERTIFICATION        -> CertificationSection()
                        NavSection.TRAINING_PROGRAM     -> TrainingProgramSection()
                        NavSection.PROFILE              -> ProfileSection()
                        NavSection.STATS                -> StatsSection()
                        NavSection.DOG_ACCESS           -> DogAccessSection()
                        NavSection.QR_TRACKING          -> QrTrackingSection()
                        NavSection.SMART_ALERTS         -> SmartAlertsSection()
                        NavSection.ADA_ACCESS_CARD      -> ADAAccessCardSection()
                        NavSection.AIR_TRAVEL           -> AirTravelSection()
                        NavSection.HOUSING_FAQ          -> HousingFAQSection()
                        NavSection.STATE_ACCESS         -> StateAccessSection()
                        NavSection.VET_FINDER           -> VetFinderSection()
                        NavSection.TACTICAL_TRAINING    -> TacticalTrainingSection()
                        NavSection.TRUCKING_MODE        -> TruckingModeSection()
                        NavSection.COMMUNITY_CHALLENGES -> CommunityChallengesSection()
                        NavSection.FORGOT_PASSWORD      -> ForgotPasswordSection()
                        NavSection.DOG_PROFILE          -> DogProfileSection()
                        NavSection.SETTINGS             -> SettingsSection()
                        NavSection.AI_ASSISTANT         -> AiAssistantSection()
                        NavSection.ESA_LEGAL            -> EsaLegalSection()
                        NavSection.BREED_QUIZ           -> BreedQuizSection()
                        NavSection.FAQ                  -> FaqSection()
                        NavSection.PLANS                -> PlansSection()
                        NavSection.TRAINER_MARKETPLACE  -> TrainerMarketplaceSection()
                        NavSection.ADD_DOG              -> AddDogSection()
                        NavSection.PUBLIC_DOG_PROFILE   -> PublicDogProfileSection()
                        NavSection.REGISTER             -> RegisterSection()
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

    // ── What's New dialog ───────────────────────────────────────────────────
    @Composable
    private fun WhatsNewDialog() {
        AlertDialog(
            onDismissRequest = { showWhatsNew = false },
            title = { Text("What's New", fontWeight = FontWeight.Bold) },
            text  = {
                Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                    CHANGELOG.take(5).forEachIndexed { index, entry ->
                        if (index > 0) HorizontalDivider(modifier = Modifier.padding(vertical = 10.dp))
                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment     = Alignment.CenterVertically,
                        ) {
                            Text("v${entry.versionName}", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = MaterialTheme.colorScheme.primary)
                            Text(entry.date, fontSize = 11.sp, color = GpOnSurfaceVariant)
                        }
                        Text(entry.title, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        Spacer(Modifier.height(4.dp))
                        entry.items.forEach { item ->
                            Text("• $item", fontSize = 12.sp, color = GpOnSurfaceVariant)
                        }
                    }
                }
            },
            confirmButton = {
                Button(onClick = { showWhatsNew = false }) { Text("Got it") }
            },
        )
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
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                TextButton(onClick = {
                    forgotPasswordEmail   = ""
                    forgotPasswordMessage = ""
                    forgotPasswordSent    = false
                    currentSection = NavSection.FORGOT_PASSWORD
                }) { Text("Forgot Password?", fontSize = 13.sp, color = Color(0xFF6B7280)) }
                TextButton(onClick = {
                    currentSection = NavSection.REGISTER
                }) { Text("Create Account", fontSize = 13.sp, color = Color(0xFF6B7280)) }
            }
            TextButton(
                onClick  = { currentSection = NavSection.FEEDBACK },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Report an issue", fontSize = 13.sp, color = Color(0xFF6B7280)) }
        }
    }

    // ── Forgot-password section ─────────────────────────────────────────────
    @Composable
    private fun ForgotPasswordSection() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Text(
                "Reset Password",
                style      = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )
            if (forgotPasswordSent) {
                Text(
                    forgotPasswordMessage.ifBlank { "Check your email for a reset link." },
                    color = MaterialTheme.colorScheme.primary,
                )
                Spacer(Modifier.height(4.dp))
                OutlinedButton(
                    onClick  = { currentSection = NavSection.OVERVIEW },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Back to Login") }
            } else {
                Text(
                    "Enter the email address on your account and we'll send you a reset link.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = GpOnSurfaceVariant,
                )
                if (forgotPasswordMessage.isNotBlank()) {
                    Text(forgotPasswordMessage, color = MaterialTheme.colorScheme.error)
                }
                OutlinedTextField(
                    value           = forgotPasswordEmail,
                    onValueChange   = { forgotPasswordEmail = it },
                    label           = { Text("Email address") },
                    singleLine      = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, autoCorrect = false),
                    modifier        = Modifier.fillMaxWidth(),
                )
                Button(
                    onClick  = { submitForgotPassword() },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Send Reset Link") }
                TextButton(
                    onClick  = { currentSection = NavSection.OVERVIEW },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Back to Login", fontSize = 13.sp, color = Color(0xFF6B7280)) }
            }
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
                    activeDog.dateOfBirth?.takeIf { it.isNotBlank() }?.let { dob ->
                        runCatching {
                            val born    = java.time.LocalDate.parse(dob.take(10))
                            val today   = java.time.LocalDate.now()
                            val years   = java.time.Period.between(born, today).years
                            val months  = java.time.Period.between(born, today).months
                            val ageText = if (years >= 1) "$years yr${if (years != 1) "s" else ""}" else "$months mo${if (months != 1) "s" else ""}"
                            val nextBirthday = born.withYear(today.year).let { if (!it.isAfter(today)) it.plusYears(1) else it }
                            val daysUntil = java.time.temporal.ChronoUnit.DAYS.between(today, nextBirthday)
                            val birthdayHint = if (daysUntil <= 7) " 🎂 Birthday in ${daysUntil}d!" else ""
                            Text(
                                "Age: $ageText$birthdayHint",
                                style = MaterialTheme.typography.bodySmall,
                                color = if (birthdayHint.isNotEmpty()) MaterialTheme.colorScheme.primary else GpOnSurfaceVariant,
                            )
                        }
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
                    me.nextAppointmentAt?.takeIf { it.isNotBlank() }?.let { apptAt ->
                        val daysUntil = runCatching {
                            val apptDate = java.time.LocalDate.parse(apptAt.take(10))
                            java.time.temporal.ChronoUnit.DAYS.between(java.time.LocalDate.now(), apptDate)
                        }.getOrNull()
                        if (daysUntil != null && daysUntil >= 0) {
                            val label = me.nextAppointmentTitle?.takeIf { it.isNotBlank() } ?: "Vet appointment"
                            val when_ = if (daysUntil == 0L) "today" else if (daysUntil == 1L) "tomorrow" else "in ${daysUntil}d"
                            Text(
                                "📅 $label — $when_",
                                style = MaterialTheme.typography.bodySmall,
                                color = if (daysUntil <= 3) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                                modifier = Modifier
                                    .padding(top = 2.dp)
                                    .clickable { loadAppointments(); currentSection = NavSection.APPOINTMENTS },
                            )
                        }
                    }
                    me.nextRefillDate?.takeIf { it.isNotBlank() }?.let { refillDate ->
                        val daysUntil = runCatching {
                            java.time.temporal.ChronoUnit.DAYS.between(java.time.LocalDate.now(), java.time.LocalDate.parse(refillDate.take(10)))
                        }.getOrNull()
                        if (daysUntil != null && daysUntil <= 14) {
                            val med = me.nextRefillMedName?.takeIf { it.isNotBlank() } ?: "Medication"
                            val when_ = if (daysUntil <= 0) "overdue" else if (daysUntil == 1L) "tomorrow" else "in ${daysUntil}d"
                            Text(
                                "💊 $med refill — $when_",
                                style = MaterialTheme.typography.bodySmall,
                                color = if (daysUntil <= 3) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                                modifier = Modifier
                                    .padding(top = 2.dp)
                                    .clickable { loadMedications(); currentSection = NavSection.MEDICATIONS },
                            )
                        }
                    }
                }
            }

            // Quick actions
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(
                    onClick  = { currentSection = NavSection.TRAINING },
                    modifier = Modifier.weight(1f),
                ) { Text("Log Training") }
                OutlinedButton(
                    onClick  = { currentSection = NavSection.TRAINING_HISTORY },
                    modifier = Modifier.weight(1f),
                ) { Text("View History") }
            }

            // Streak + this-week count — computed from already-loaded logs
            val streak = remember(currentLogs) {
                val today = java.time.LocalDate.now()
                val logDays = currentLogs.mapNotNull { log ->
                    runCatching { java.time.LocalDate.parse(log.logDate.take(10)) }.getOrNull()
                }.toSet()
                var day = today; var count = 0
                // Allow today to be absent (session not yet logged) — start from yesterday if today is missing
                if (!logDays.contains(day)) day = day.minusDays(1)
                while (logDays.contains(day)) { count++; day = day.minusDays(1) }
                count
            }
            val sessionsThisWeek = remember(currentLogs) {
                val weekAgo = java.time.LocalDate.now().minusDays(6)
                currentLogs.count { log ->
                    runCatching { java.time.LocalDate.parse(log.logDate.take(10)) }.getOrNull()
                        ?.let { !it.isBefore(weekAgo) } ?: false
                }
            }
            if (currentLogs.isNotEmpty()) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedCard(modifier = Modifier.weight(1f)) {
                        Column(modifier = Modifier.padding(14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(if (streak > 0) "🔥 $streak" else "—", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                            Text("day streak", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                        }
                    }
                    OutlinedCard(modifier = Modifier.weight(1f)) {
                        Column(modifier = Modifier.padding(14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("$sessionsThisWeek", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                            Text("this week", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                        }
                    }
                }
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
        var dropdownExpanded    by remember { mutableStateOf(false) }
        var cameraOutputUri     by remember { mutableStateOf<Uri?>(null) }
        var pendingCameraType   by remember { mutableStateOf("") }
        val context = LocalContext.current

        val galleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
            uri?.let {
                val att = feedbackResolveAttachment(it)
                logMediaUri  = it
                logMediaMime = att?.mimeType ?: contentResolver.getType(it).orEmpty()
                logMediaName = att?.displayName ?: "media"
            }
        }
        val takePictureLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { saved ->
            if (saved) cameraOutputUri?.let { logMediaUri = it; logMediaMime = "image/jpeg"; logMediaName = "photo_${System.currentTimeMillis()}.jpg" }
        }
        val captureVideoLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CaptureVideo()) { saved ->
            if (saved) cameraOutputUri?.let { logMediaUri = it; logMediaMime = "video/mp4"; logMediaName = "video_${System.currentTimeMillis()}.mp4" }
        }
        val cameraPermLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            if (!granted) { trainMessage = "Camera permission required."; return@rememberLauncherForActivityResult }
            val dir = File(context.cacheDir, "camera_captures").also { it.mkdirs() }
            if (pendingCameraType == "photo") {
                val file = File(dir, "photo_${System.currentTimeMillis()}.jpg")
                val uri  = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                cameraOutputUri = uri; takePictureLauncher.launch(uri)
            } else {
                val file = File(dir, "video_${System.currentTimeMillis()}.mp4")
                val uri  = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                cameraOutputUri = uri; captureVideoLauncher.launch(uri)
            }
        }

        fun launchCamera(type: String) {
            pendingCameraType = type
            if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                val dir = File(context.cacheDir, "camera_captures").also { it.mkdirs() }
                if (type == "photo") {
                    val file = File(dir, "photo_${System.currentTimeMillis()}.jpg")
                    val uri  = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                    cameraOutputUri = uri; takePictureLauncher.launch(uri)
                } else {
                    val file = File(dir, "video_${System.currentTimeMillis()}.mp4")
                    val uri  = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                    cameraOutputUri = uri; captureVideoLauncher.launch(uri)
                }
            } else {
                cameraPermLauncher.launch(Manifest.permission.CAMERA)
            }
        }
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

            // Media attachment
            if (logMediaUri != null) {
                Row(
                    modifier          = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text("📎 ${logMediaName.take(40)}", fontSize = 12.sp, modifier = Modifier.weight(1f), color = GpOnSurfaceVariant)
                    TextButton(onClick = { logMediaUri = null; logMediaMime = ""; logMediaName = "" }) { Text("Remove") }
                }
            } else {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    OutlinedButton(onClick = { galleryLauncher.launch("image/*") }, modifier = Modifier.weight(1f)) { Text("Gallery", fontSize = 12.sp) }
                    OutlinedButton(onClick = { launchCamera("photo") }, modifier = Modifier.weight(1f)) { Text("Photo", fontSize = 12.sp) }
                    OutlinedButton(onClick = { launchCamera("video") }, modifier = Modifier.weight(1f)) { Text("Record", fontSize = 12.sp) }
                }
            }

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
                        dog.dateOfBirth?.takeIf { it.isNotBlank() }?.let { dob ->
                            runCatching {
                                val born = java.time.LocalDate.parse(dob.take(10))
                                val today = java.time.LocalDate.now()
                                val y = java.time.Period.between(born, today).years
                                val m = java.time.Period.between(born, today).months
                                if (y >= 1) "$y yr${if (y != 1) "s" else ""} old" else "$m mo${if (m != 1) "s" else ""} old"
                            }.getOrNull()
                        },
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
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { selectedLogDetail = log },
        ) {
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
                    Text(log.handlerNotes.take(220) + if (log.handlerNotes.length > 220) "…" else "", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(
                        onClick  = { selectedLogDetail = log },
                        modifier = Modifier.weight(1f),
                    ) { Text("View") }
                    OutlinedButton(
                        onClick  = { beginEditLog(log) },
                        modifier = Modifier.weight(1f),
                    ) { Text("Edit") }
                }
            }
        }
    }

    // ── Log detail dialog ────────────────────────────────────────────────────
    @Composable
    private fun LogDetailDialog(log: GuidePawLogItem) {
        AlertDialog(
            onDismissRequest = { selectedLogDetail = null },
            title = {
                Text(
                    log.locationName.ifBlank { "Training session" },
                    fontWeight = FontWeight.Bold,
                )
            },
            text = {
                Column(
                    modifier              = Modifier.verticalScroll(rememberScrollState()),
                    verticalArrangement   = Arrangement.spacedBy(8.dp),
                ) {
                    Text(
                        listOfNotNull(
                            log.logDate.takeIf { it.isNotBlank() },
                            log.locationCityState,
                            log.locationType,
                        ).joinToString(" • "),
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Focus:", style = MaterialTheme.typography.labelMedium)
                        repeat(5) { i ->
                            Text(
                                if (i < log.focusLevel) "●" else "○",
                                color = if (i < log.focusLevel) MaterialTheme.colorScheme.primary else GpOnSurfaceVariant,
                                fontSize = 14.sp,
                            )
                        }
                        Text("${log.focusLevel}/5", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                    if (log.skillsPracticed.isNotEmpty()) {
                        HorizontalDivider()
                        Text("Skills practiced", style = MaterialTheme.typography.labelMedium)
                        Text(log.skillsPracticed.joinToString(" · "), style = MaterialTheme.typography.bodySmall)
                    }
                    if (log.handlerNotes.isNotBlank()) {
                        HorizontalDivider()
                        Text("Handler notes", style = MaterialTheme.typography.labelMedium)
                        Text(log.handlerNotes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                }
            },
            confirmButton = {
                Button(onClick = { selectedLogDetail = null; beginEditLog(log) }) { Text("Edit") }
            },
            dismissButton = {
                TextButton(onClick = { selectedLogDetail = null }) { Text("Close") }
            },
        )
    }

    // ── Training History section ────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun TrainingHistorySection() {
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }
        val logs = currentLogs

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; refreshDashboard(currentToken ?: "", currentActiveDogId); isPullingToRefresh = false },
            modifier     = Modifier.fillMaxSize(),
        ) {
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
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                        Text(
                            if (activeDog != null) "📋 ${activeDog.name}'s Logs" else "📋 Training History",
                            style      = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            modifier   = Modifier.padding(start = 4.dp),
                        )
                    }
                    TextButton(onClick = { currentSection = NavSection.TRAINING }) { Text("+ Log") }
                }

                if (logs.isEmpty()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("No training logs yet.", fontWeight = FontWeight.SemiBold)
                            Text(
                                "Pull down to refresh, or tap + Log to add your first session.",
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )
                        }
                    }
                } else {
                    Text(
                        "${logs.size} session${if (logs.size == 1) "" else "s"} (most recent first)",
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                    )
                    logs.forEach { LogCard(it) }
                }
            }
        }
    }

    // ── Community Challenges section ────────────────────────────────────────
    @Composable
    private fun CommunityChallengesSection() {
        data class Challenge(
            val key: String, val icon: String, val label: String,
            val summary: String, val dailyTarget: String,
            val bestFor: String, val avoid: String, val finishLine: String,
        )
        val challenges = remember {
            listOf(
                Challenge(
                    key = "consistency_streak", icon = "🔥", label = "7-Day Consistency",
                    summary = "Do one small training rep every day for a week and keep it easy enough to finish.",
                    dailyTarget = "1 short rep per day",
                    bestFor = "New routines, overdue follow-through, and handlers who need momentum.",
                    avoid = "Turning the streak into a long session or a hard proofing grind.",
                    finishLine = "Seven logged check-ins with at least one reward marker each day.",
                ),
                Challenge(
                    key = "engagement_burst", icon = "👀", label = "Engagement Burst",
                    summary = "Build quick eye contact, name response, and handler focus in tiny repeats.",
                    dailyTarget = "3 focus reps",
                    bestFor = "Dogs that know the cue but are drifting or slow to re-engage.",
                    avoid = "Repeating cues, stacking distractions, or chasing perfect attention.",
                    finishLine = "Three clean check-ins with fast reward delivery.",
                ),
                Challenge(
                    key = "settle_reset", icon = "🧘", label = "Calm Reset",
                    summary = "Practice mat work, crate calm, and quiet recovery after one success.",
                    dailyTarget = "2 settle reps",
                    bestFor = "Travel days, stressful handlers, and dogs that need off-switch practice.",
                    avoid = "Prolonged duration work before the dog can settle quickly.",
                    finishLine = "Dog settles within 30 seconds twice in one day.",
                ),
                Challenge(
                    key = "loose_leash", icon = "🚶", label = "Leash Walking Sprint",
                    summary = "Keep leash pressure low for a short, boring, successful walk.",
                    dailyTarget = "1 short walk",
                    bestFor = "Dogs ready to generalize loose leash outside the house or truck.",
                    avoid = "Crowded settings or long walks that drain the dog before success.",
                    finishLine = "Three pauses, three check-ins, and a calm return.",
                ),
                Challenge(
                    key = "public_neutrality", icon = "🏪", label = "Public Neutrality Check",
                    summary = "Rehearse ignoring people, carts, and noise at an easy public distance.",
                    dailyTarget = "1 controlled outing",
                    bestFor = "Teams already comfortable with home skills and short outings.",
                    avoid = "Too much distance, too much duration, or asking for perfect neutrality too soon.",
                    finishLine = "One outing where the dog stays under threshold and leaves with a reward.",
                ),
            )
        }

        var selectedKey by remember { mutableStateOf(prefs.getString(KEY_CHALLENGE_KEY, "consistency_streak") ?: "consistency_streak") }
        var checkIns    by remember { mutableIntStateOf(prefs.getInt(KEY_CHALLENGE_CHECK_INS, 0)) }
        var notes       by remember { mutableStateOf(prefs.getString(KEY_CHALLENGE_NOTES, "") ?: "") }
        val selected = challenges.firstOrNull { it.key == selectedKey } ?: challenges.first()

        fun save() {
            prefs.edit()
                .putString(KEY_CHALLENGE_KEY, selectedKey)
                .putInt(KEY_CHALLENGE_CHECK_INS, checkIns)
                .putString(KEY_CHALLENGE_NOTES, notes)
                .apply()
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🏆 Community Challenges", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            Text(
                "Pick a weekly challenge and log a check-in each time you complete a rep.",
                style = MaterialTheme.typography.bodySmall,
                color = GpOnSurfaceVariant,
            )

            // Challenge picker grid
            challenges.chunked(2).forEach { pair ->
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    pair.forEach { challenge ->
                        val isSelected = challenge.key == selectedKey
                        OutlinedButton(
                            onClick  = { selectedKey = challenge.key; checkIns = 0; save() },
                            modifier = Modifier.weight(1f),
                            colors   = if (isSelected)
                                ButtonDefaults.outlinedButtonColors(containerColor = GpPrimaryContainer)
                            else
                                ButtonDefaults.outlinedButtonColors(),
                            border   = if (isSelected)
                                androidx.compose.foundation.BorderStroke(2.dp, MaterialTheme.colorScheme.primary)
                            else
                                ButtonDefaults.outlinedButtonBorder,
                        ) {
                            Text("${challenge.icon} ${challenge.label}", fontSize = 12.sp, textAlign = TextAlign.Center, maxLines = 2)
                        }
                    }
                    if (pair.size == 1) Spacer(Modifier.weight(1f))
                }
            }

            // Plan card for selected challenge
            OutlinedCard(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.outlinedCardColors(containerColor = GpPrimaryContainer),
            ) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("${selected.icon} ${selected.label}", fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleSmall)
                    Text(selected.summary, style = MaterialTheme.typography.bodySmall)
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row { Text("🎯 ", style = MaterialTheme.typography.bodySmall); Text(selected.dailyTarget, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold) }
                            Row { Text("✅ ", style = MaterialTheme.typography.bodySmall); Text(selected.bestFor, style = MaterialTheme.typography.bodySmall) }
                            Row { Text("🚫 ", style = MaterialTheme.typography.bodySmall); Text(selected.avoid, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                            Row { Text("🏁 ", style = MaterialTheme.typography.bodySmall); Text(selected.finishLine, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }

            // Check-in counter
            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier              = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment     = Alignment.CenterVertically,
                ) {
                    Column {
                        Text("Check-ins", style = MaterialTheme.typography.labelMedium, color = GpOnSurfaceVariant)
                        Text(checkIns.toString(), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                    }
                    Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Button(onClick = { checkIns++; save() }) { Text("✅ Log Check-In") }
                        if (checkIns > 0) {
                            TextButton(onClick = { checkIns = 0; save() }) {
                                Text("Reset", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                            }
                        }
                    }
                }
            }

            // Notes
            OutlinedTextField(
                value         = notes,
                onValueChange = { notes = it; save() },
                label         = { Text("Notes (optional)") },
                placeholder   = { Text("Observations, wins, what to adjust…") },
                modifier      = Modifier.fillMaxWidth(),
                minLines      = 3,
            )

            Button(
                onClick  = { currentSection = NavSection.TRAINING },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("⚡ Log a Training Session") }
        }
    }

    // ── Vet Finder section ──────────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun VetFinderSection() {
        val context = LocalContext.current
        val scope   = rememberCoroutineScope()

        var currentLat   by remember { mutableStateOf<Double?>(null) }
        var currentLng   by remember { mutableStateOf<Double?>(null) }
        var locationLabel  by remember { mutableStateOf("") }
        var manualLocation by remember { mutableStateOf("") }
        var detecting    by remember { mutableStateOf(false) }
        var destination  by remember { mutableStateOf("") }
        var afterHours   by remember { mutableStateOf(false) }
        var searching    by remember { mutableStateOf(false) }
        var searchResult by remember { mutableStateOf<GpVetFinderResponse?>(null) }
        var errorMsg     by remember { mutableStateOf("") }

        fun doSearch(lat: Double, lng: Double) {
            val token = currentToken ?: return
            searching = true
            errorMsg  = ""
            scope.launch {
                try {
                    val result = withContext(kotlinx.coroutines.Dispatchers.IO) {
                        api.findVets(token, lat, lng, 50, destination.trim(), afterHours)
                    }
                    searchResult = result
                    if (result.vets.isEmpty()) errorMsg = "No vets found. Try increasing your search area or adjusting filters."
                } catch (t: Throwable) {
                    errorMsg = friendlyMessage(t.message, "Could not search for vets.")
                } finally {
                    searching = false
                }
            }
        }

        fun detectAndSearch() {
            detecting = true
            scope.launch {
                val result = resolveLocation(context)
                detecting = false
                if (result != null) {
                    locationLabel = result.second
                }
            }
        }

        val permLauncher = rememberLauncherForActivityResult(
            ActivityResultContracts.RequestMultiplePermissions()
        ) { perms ->
            if (perms[Manifest.permission.ACCESS_FINE_LOCATION] == true ||
                perms[Manifest.permission.ACCESS_COARSE_LOCATION] == true) {
                detectAndSearch()
            } else {
                errorMsg = "Location permission denied — cannot search without a location."
            }
        }

        LaunchedEffect(Unit) {
            val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
            val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
            if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                detecting = true
                val loc = resolveLocation(context)
                detecting = false
                if (loc != null) {
                    locationLabel = loc.second
                }
            }
        }

        fun openNavigation(vet: GpVetFinderResult) {
            val uri = android.net.Uri.parse("google.navigation:q=${vet.lat},${vet.lng}&mode=d")
            val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, uri).apply {
                setPackage("com.google.android.apps.maps")
            }
            if (intent.resolveActivity(context.packageManager) != null) {
                context.startActivity(intent)
            } else {
                val fallback = android.net.Uri.parse("https://maps.google.com/maps?daddr=${vet.lat},${vet.lng}")
                context.startActivity(android.content.Intent(android.content.Intent.ACTION_VIEW, fallback))
            }
        }

        fun callVet(phone: String) {
            context.startActivity(android.content.Intent(android.content.Intent.ACTION_DIAL,
                android.net.Uri.parse("tel:$phone")))
        }

        fun fmtRating(r: Float?): String = if (r != null) "★ ${"%.1f".format(r)}" else ""

        PullToRefreshBox(
            isRefreshing = searching,
            onRefresh    = { currentLat?.let { la -> currentLng?.let { ln -> doSearch(la, ln) } } },
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
                    Text("🏥 Find a Vet", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
                }

                // ── Location row ──────────────────────────────────────────
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment     = Alignment.CenterVertically,
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text("Your location", style = MaterialTheme.typography.labelMedium)
                                if (detecting) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp)
                                        Text("Detecting…", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                } else {
                                    Text(
                                        locationLabel.ifBlank { "Not detected" },
                                        style = MaterialTheme.typography.bodySmall,
                                        color = if (locationLabel.isBlank()) MaterialTheme.colorScheme.error else GpOnSurfaceVariant,
                                    )
                                }
                            }
                            TextButton(
                                onClick = {
                                    val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
                                    val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
                                    if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                                        detectAndSearch()
                                    } else {
                                        permLauncher.launch(arrayOf(
                                            Manifest.permission.ACCESS_FINE_LOCATION,
                                            Manifest.permission.ACCESS_COARSE_LOCATION,
                                        ))
                                    }
                                },
                                enabled = !detecting,
                            ) { Text("📍 Refresh") }
                        }

                        OutlinedTextField(
                            value         = manualLocation,
                            onValueChange = { manualLocation = it },
                            label         = { Text("Your location (optional override)") },
                            placeholder   = { Text("City, state or full address") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                        )

                        OutlinedTextField(
                            value         = destination,
                            onValueChange = { destination = it },
                            label         = { Text("Heading toward (optional)") },
                            placeholder   = { Text("e.g. Dallas TX, Chicago IL") },
                            modifier      = Modifier.fillMaxWidth(),
                            singleLine    = true,
                        )

                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment     = Alignment.CenterVertically,
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                androidx.compose.material3.Switch(
                                    checked         = afterHours,
                                    onCheckedChange = { afterHours = it },
                                )
                                Text("Emergency / after hours only", style = MaterialTheme.typography.bodySmall)
                            }
                        }

                        Button(
                            onClick  = {
                                if (manualLocation.isNotBlank()) {
                                    detecting = true
                                    errorMsg  = ""
                                    scope.launch {
                                        @Suppress("DEPRECATION")
                                        val addresses = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                            try { Geocoder(context, Locale.getDefault()).getFromLocationName(manualLocation.trim(), 1) }
                                            catch (_: Exception) { null }
                                        }
                                        detecting = false
                                        val addr = addresses?.firstOrNull()
                                        if (addr != null) {
                                            locationLabel = addr.getAddressLine(0) ?: manualLocation.trim()
                                            currentLat = addr.latitude
                                            currentLng = addr.longitude
                                            doSearch(addr.latitude, addr.longitude)
                                        } else {
                                            errorMsg = "Could not find that location. Try a city name or zip code."
                                        }
                                    }
                                } else {
                                    val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
                                    val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
                                    if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                                        detecting = true
                                        scope.launch {
                                            val loc = resolveLocation(context)
                                            detecting = false
                                            if (loc != null) {
                                                locationLabel = loc.second
                                                val geoClient = com.google.android.gms.location.LocationServices.getFusedLocationProviderClient(context)
                                                try {
                                                    geoClient.getCurrentLocation(com.google.android.gms.location.Priority.PRIORITY_HIGH_ACCURACY, null)
                                                        .addOnSuccessListener { location ->
                                                            if (location != null) {
                                                                currentLat = location.latitude
                                                                currentLng = location.longitude
                                                                doSearch(location.latitude, location.longitude)
                                                            } else {
                                                                errorMsg = "Could not get precise location."
                                                            }
                                                        }
                                                } catch (_: SecurityException) {
                                                    errorMsg = "Location permission required."
                                                }
                                            } else {
                                                errorMsg = "Could not determine location."
                                            }
                                        }
                                    } else {
                                        permLauncher.launch(arrayOf(
                                            Manifest.permission.ACCESS_FINE_LOCATION,
                                            Manifest.permission.ACCESS_COARSE_LOCATION,
                                        ))
                                    }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            enabled  = !searching && !detecting,
                        ) {
                            if (searching) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                                Spacer(Modifier.width(8.dp))
                                Text("Searching…")
                            } else {
                                Text(if (destination.isBlank()) "Search nearby" else "Search along route")
                            }
                        }
                    }
                }

                SectionMessage(errorMsg)

                // ── Results ───────────────────────────────────────────────
                val vets = searchResult?.vets.orEmpty()
                if (searchResult != null) {
                    val dest = searchResult!!.routeDestination
                    if (dest.isNotBlank()) {
                        Text("Route toward $dest", style = MaterialTheme.typography.labelMedium, color = GpOnSurfaceVariant)
                    }
                    Text(
                        if (afterHours) "Emergency / after-hours results" else "Vets & animal hospitals",
                        style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold,
                    )
                }

                // Group by leg label
                val grouped = vets.groupBy { it.legLabel }
                grouped.forEach { (legLabel, legVets) ->
                    if (grouped.size > 1) {
                        Text(legLabel, style = MaterialTheme.typography.labelMedium, color = GpOnSurfaceVariant,
                            modifier = Modifier.padding(top = 4.dp))
                    }
                    legVets.forEach { vet ->
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                // Name row + badges
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.Top,
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(vet.name, fontWeight = FontWeight.Bold)
                                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            if (vet.isEmergency || vet.is24hr) {
                                                Text("🚨 Emergency", style = MaterialTheme.typography.labelSmall,
                                                    color = androidx.compose.ui.graphics.Color(0xFFB71C1C))
                                            }
                                            if (vet.openNow == true) {
                                                Text("✅ Open now", style = MaterialTheme.typography.labelSmall,
                                                    color = androidx.compose.ui.graphics.Color(0xFF2E7D32))
                                            } else if (vet.openNow == false) {
                                                Text("❌ Closed", style = MaterialTheme.typography.labelSmall,
                                                    color = GpOnSurfaceVariant)
                                            }
                                        }
                                    }
                                    Text("${vet.distanceMiles} mi", style = MaterialTheme.typography.bodySmall,
                                        color = GpOnSurfaceVariant, fontWeight = FontWeight.SemiBold)
                                }

                                // Address + hours
                                if (vet.address.isNotBlank()) {
                                    Text("📍 ${vet.address}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (vet.hoursToday.isNotBlank()) {
                                    Text("🕐 Today: ${vet.hoursToday}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (vet.rating != null) {
                                    Text("${fmtRating(vet.rating)}  (${vet.userRatingsTotal} reviews)",
                                        style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }

                                // Action buttons
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Button(
                                        onClick  = { openNavigation(vet) },
                                        modifier = Modifier.weight(1f),
                                    ) { Text("🧭 Navigate", fontSize = 12.sp) }
                                    if (vet.phone.isNotBlank()) {
                                        OutlinedButton(
                                            onClick  = { callVet(vet.phone) },
                                            modifier = Modifier.weight(1f),
                                        ) { Text("📞 Call", fontSize = 12.sp) }
                                    }
                                }
                            }
                        }
                    }
                }

                if (vets.isEmpty() && searchResult != null && errorMsg.isBlank()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Text(
                            "No results found. Try turning off the after-hours filter or entering a destination city.",
                            style    = MaterialTheme.typography.bodySmall,
                            color    = GpOnSurfaceVariant,
                            modifier = Modifier.padding(14.dp),
                        )
                    }
                }

                Text(
                    "Results from Google Places. For truck access, look for clinics on main roads near highway exits. Always call ahead to confirm hours.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
            }
        }
    }

    // ── Trucking Mode section ───────────────────────────────────────────────
    @Composable
    private fun TruckingModeSection() {
        data class TruckMode(
            val key: String, val icon: String, val label: String,
            val summary: String, val sessionLength: String,
            val priority: String, val avoid: String, val nextStep: String,
        )
        val modes = remember {
            listOf(
                TruckMode("driving_day",   "🚚", "Driving Day",
                    "Keep the session short, structured, and easy to pause between stops.",
                    "5 to 10 minutes", "Loose leash, settle, and quick resets",
                    "Long drills, high-rep fatigue work, and advanced public-access pushes",
                    "Finish with a calm crate or mat settle before the next drive segment."),
                TruckMode("reset_day",     "🔄", "Reset Day",
                    "Use this when the dog needs a lighter restart after a rough stretch or travel gap.",
                    "10 to 15 minutes", "Confidence, engagement, and one easy win",
                    "Multi-step proofing, stress stacking, or exposing the dog to crowded settings too soon",
                    "End on a clean cue the dog already knows well."),
                TruckMode("weather_day",   "🌦️", "Weather Day",
                    "Adjust the plan for wind, rain, heat, glare, or reduced footing.",
                    "5 to 12 minutes", "Comfort checks, footwork, and controlled exposure",
                    "Overheating, slick surfaces, and long exposure to bad footing or extreme weather",
                    "Practice calm entry and exit from the truck, crate, or shelter."),
                TruckMode("low_energy_day","🪫", "Low Energy Day",
                    "Keep the dog in a low-demand mode and protect motivation.",
                    "3 to 8 minutes", "Easy cues, settle work, and reinforcement",
                    "High arousal games or heavy obedience sequences",
                    "Reward a clean settle and stop while the dog still has some spark left."),
                TruckMode("high_stress_day","🧯", "High Stress Day",
                    "Use when the handler day is rough and the dog should get a narrow, predictable task.",
                    "2 to 6 minutes", "Neutrality, settle, and one repeated success",
                    "Crowded routes, long sessions, and new challenges",
                    "Finish with a short recovery period and a calm return to the crate or mat."),
            )
        }

        var selectedKey by remember { mutableStateOf(prefs.getString(KEY_TRUCKING_MODE, "driving_day") ?: "driving_day") }
        var notes       by remember { mutableStateOf(prefs.getString(KEY_TRUCKING_NOTES, "") ?: "") }
        val selected = modes.firstOrNull { it.key == selectedKey } ?: modes.first()

        fun save() { prefs.edit().putString(KEY_TRUCKING_MODE, selectedKey).putString(KEY_TRUCKING_NOTES, notes).apply() }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.TACTICAL_TRAINING }) { Text("← Back") }
                Text("🚚 Trucking Mode", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            Text(
                "Pick the day type that matches the route, weather, and energy level.",
                style = MaterialTheme.typography.bodySmall,
                color = GpOnSurfaceVariant,
            )

            // Mode picker grid
            modes.chunked(2).forEach { pair ->
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    pair.forEach { mode ->
                        val isSelected = mode.key == selectedKey
                        OutlinedButton(
                            onClick   = { selectedKey = mode.key; save() },
                            modifier  = Modifier.weight(1f),
                            colors    = if (isSelected)
                                ButtonDefaults.outlinedButtonColors(containerColor = GpPrimaryContainer)
                            else
                                ButtonDefaults.outlinedButtonColors(),
                            border    = if (isSelected)
                                androidx.compose.foundation.BorderStroke(2.dp, MaterialTheme.colorScheme.primary)
                            else
                                ButtonDefaults.outlinedButtonBorder,
                        ) {
                            Text("${mode.icon} ${mode.label}", fontSize = 12.sp, textAlign = TextAlign.Center, maxLines = 2)
                        }
                    }
                    if (pair.size == 1) Spacer(Modifier.weight(1f))
                }
            }

            // Plan card for selected mode
            OutlinedCard(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.outlinedCardColors(containerColor = GpPrimaryContainer),
            ) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("${selected.icon} ${selected.label}", fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleSmall)
                    Text(selected.summary, style = MaterialTheme.typography.bodySmall)
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row { Text("⏱ ", style = MaterialTheme.typography.bodySmall); Text(selected.sessionLength, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold) }
                            Row { Text("✅ ", style = MaterialTheme.typography.bodySmall); Text(selected.priority, style = MaterialTheme.typography.bodySmall) }
                            Row { Text("🚫 ", style = MaterialTheme.typography.bodySmall); Text(selected.avoid, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                            Row { Text("➡️ ", style = MaterialTheme.typography.bodySmall); Text(selected.nextStep, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }

            // Optional notes
            OutlinedTextField(
                value         = notes,
                onValueChange = { notes = it; save() },
                label         = { Text("Notes (optional)") },
                placeholder   = { Text("Route conditions, dog observations…") },
                modifier      = Modifier.fillMaxWidth(),
                minLines      = 3,
            )

            Button(
                onClick  = { currentSection = NavSection.TRAINING },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("⚡ Log a Training Session") }
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
                    "State laws vary — many states grant SDITs public access, and most states criminalize misrepresenting a pet as a service dog.",
                    style = MaterialTheme.typography.bodySmall,
                    color = GpOnSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
                OutlinedButton(
                    onClick  = { currentSection = NavSection.STATE_ACCESS },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("View state-by-state access laws") }
            }
        }
    }

    // ── State Access Laws section ───────────────────────────────────────────
    @OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
    @Composable
    private fun StateAccessSection() {
        data class StateSDLaw(
            val name: String,
            val abbr: String,
            val sditAccess: String,
            val misrepLaw: Boolean?,
            val misrepNote: String,
            val keyNote: String,
            val requirements: List<String> = emptyList(),
        )

        val states = listOf(
            StateSDLaw("Alabama", "AL", "Yes — visible ID required", true,
                "Code of Ala. § 21-7-8; misdemeanor",
                "Alabama training access recognized. Trainers have same access rights as handlers.",
                listOf("Service animals must be under handler control unless disability or task prevents tether use.", "A trainer engaged in training has the same public-accommodation access rights and damage liability.", "A service animal in training must have visible written identification readable from a distance.", "Misrepresentation signage and penalties are addressed separately in state law."),
            ),
            StateSDLaw("Alaska", "AK", "Yes — with trainer", true,
                "AS § 18.80.395; class B misdemeanor",
                "Guide dog laws in AS § 18.80.395. Federal ADA baseline applies in covered public places.",
                listOf("Federal ADA public-access rules apply in covered Alaska public accommodations.", "Verify Alaska-specific training-access rules before relying on SDIT access in non-ADA settings.", "Emotional support or companionship alone does not create ADA public-access rights."),
            ),
            StateSDLaw("Arizona", "AZ", "Yes — in-training included in definition", true,
                "A.R.S. § 11-1024; class 3 misdemeanor",
                "Arizona's service-animal definition includes dogs or miniature horses trained or in training.",
                listOf("Dogs and miniature horses that are trained or in training may qualify.", "Public accommodations may not require service-animal identification.", "Only limited task-related questions are allowed when the need is not obvious.", "Emotional support or comfort alone does not meet the task-work standard."),
            ),
            StateSDLaw("Arkansas", "AR", "Yes — trainer in act of training", true,
                "Ark. Code § 20-14-305; Class A misdemeanor",
                "Arkansas recognizes trainers actively training guide, signal, or service dogs.",
                listOf("Dog trainers in the act of training may not be denied access to listed public places.", "Access covers public ways, public places, public accommodations, and housing accommodations.", "No extra charge may be required, but damage liability may apply.", "Misrepresentation of a service animal or SDIT can carry a civil penalty."),
            ),
            StateSDLaw("California", "CA", "Yes — same as trained SD", true,
                "Penal Code § 365.7; misdemeanor, fine up to \$1,000",
                "Civil Code § 54.2 grants SDITs the same public access as fully trained service dogs. Broadest state protections in the US.",
                listOf("Health & Safety Code § 113903 includes dogs trained or in training for disability-related tasks.", "SDITs have the same public-access rights as fully trained service dogs.", "Comfort or companionship alone does not meet the task-work standard.", "California public guidance follows the ADA two-question framework."),
            ),
            StateSDLaw("Colorado", "CO", "Yes — may require ID on transit", true,
                "C.R.S. § 24-34-803.5; petty offense",
                "Colorado training access is broad, but transportation may add visible-identification requirements.",
                listOf("Colorado covers public accommodations, public-entity programs, public transportation, employment, and housing.", "Trainers of service animals have access rights.", "Extra charges are barred for service animals and animals in training.", "Common-carrier rules may require visible identification for animals in training."),
            ),
            StateSDLaw("Connecticut", "CT", "Yes — with trainer", true,
                "CGS § 1-1f; infraction",
                "SDITs allowed with trainer. Misrepresentation is an infraction.",
                listOf("Federal ADA public-access rules apply in covered public accommodations.", "SDITs are recognized in state law when accompanied by a trainer.", "Verify current state-specific SDIT identification or age requirements."),
            ),
            StateSDLaw("Delaware", "DE", "Yes — with trainer", true,
                "Del. Code tit. 6 § 4504A; fine up to \$1,000",
                "SDITs allowed with trainer. Verify current state-specific requirements.",
                listOf("Federal ADA public-access rules apply in covered public accommodations.", "SDITs recognized when accompanied by a trainer.", "Verify current Delaware SDIT identification or control requirements."),
            ),
            StateSDLaw("District of Columbia", "DC", "Yes — owner may serve as trainer; ID gear required", true,
                "D.C. Code § 7-1009; misdemeanor",
                "D.C. Law 23-208 allows owner of SDIT to serve as trainer. Age, accompaniment, and identifying-gear requirements apply.",
                listOf("D.C. recognizes service animals in training and allows the owner to serve as the trainer.", "Service animals in training have age, training, accompaniment, and identifying-gear requirements.", "Misrepresentation of a service animal is a misdemeanor under D.C. Code.", "Verify current D.C. identifying-gear specifications before public access."),
            ),
            StateSDLaw("Florida", "FL", "Yes — with trainer or handler", true,
                "F.S. § 413.081; second-degree misdemeanor",
                "F.S. § 413.08 expressly recognizes trainer access while training service animals.",
                listOf("Florida public accommodations must permit service animals used by people with disabilities.", "Trainers of service animals have the same public-facility access rights and liability while training.", "Training documentation cannot be required as a precondition for access.", "Extra compensation for a service animal is barred in housing, though damage liability may apply."),
            ),
            StateSDLaw("Georgia", "GA", "Yes — with trainer", true,
                "O.C.G.A. § 30-4-2; misdemeanor",
                "Georgia recognizes trainers of guide dogs or service dogs while training.",
                listOf("Access includes transportation, lodging, public accommodations, amusement/resort places, and other public places.", "Covered persons may be accompanied by a guide dog or service dog.", "Georgia has separate training-access language for trainers.", "Extra charges may not be required, but damage liability may apply."),
            ),
            StateSDLaw("Hawaii", "HI", "Yes — with trainer", true,
                "HRS § 347-13; misdemeanor",
                "Federal ADA baseline applies. Verify Hawaii-specific SDIT-access rules for non-ADA settings.",
                listOf("Federal ADA public-access rules apply in covered Hawaii public accommodations.", "Verify Hawaii-specific training-access rules before relying on SDIT access.", "Emotional support or comfort alone does not meet the ADA public-access standard."),
            ),
            StateSDLaw("Idaho", "ID", "Yes — visible ID required for SDIT", true,
                "Idaho Code § 56-704; fine up to \$1,000",
                "Idaho Code §§ 56-704A and 18-5812B. Training access requires leash/control and visible identification.",
                listOf("Service dogs must be permitted for individuals with disabilities or authorized handlers.", "Exclusion may apply when out of control or not housebroken.", "Dogs-in-training have public-accommodation and public-transportation access.", "Dogs-in-training must be controlled and visually identified."),
            ),
            StateSDLaw("Illinois", "IL", "Yes — recognized training programs only", true,
                "775 ILCS 5/5-102.2; petty offense/Class B misdemeanor",
                "Illinois training access is tied to recognized training programs and trainer status.",
                listOf("Illinois recognizes guide, hearing, and support dogs in state law.", "Trainers affiliated with recognized training programs may have access while training.", "Extra charges are barred, but damage liability may apply.", "Federal ADA rules still control in ADA-covered public accommodations."),
            ),
            StateSDLaw("Indiana", "IN", "Yes — SDIT recognized in state law", true,
                "IC § 16-32-3; Class C infraction",
                "Indiana recognizes service-animal-in-training access; verify trainer and setting-specific requirements.",
                listOf("Indiana protects access for people with disabilities using service animals.", "Service-animal-in-training access is recognized in state law.", "Verify current trainer qualification and setting-specific requirements.", "Federal ADA rules apply in covered public accommodations."),
            ),
            StateSDLaw("Iowa", "IA", "Yes — with trainer", true,
                "Iowa Code § 216C.11; simple misdemeanor",
                "Iowa expressly protects trainers of service dogs and assistive animals while training.",
                listOf("Iowa protects access to public facilities for people with disabilities using service dogs or assistive animals.", "Trainers of service dogs and assistive animals are protected while training in listed public places.", "Extra charges are barred, but the person or trainer may be liable for damage.", "Federal ADA rules still apply in ADA-covered public accommodations."),
            ),
            StateSDLaw("Kansas", "KS", "Yes — recognized training centers only", null,
                "Verify current statutes — K.S.A. § 39-1109",
                "Kansas training access is narrower, tied to professional trainers from recognized training centers.",
                listOf("Professional trainers from recognized training centers may access listed places while training.", "Trainer damage liability applies.", "Comfort or emotional support animals are distinct from trained assistance dogs.", "Federal ADA rules still apply in covered public accommodations."),
            ),
            StateSDLaw("Kentucky", "KY", "Check current statutes", true,
                "KRS § 258.500; fine",
                "Kentucky reviewed materials focus on housing accommodation. Public-access SDIT rule not confirmed.",
                listOf("Federal ADA public-accommodation rules remain the baseline for public access.", "Kentucky housing law defines assistance animals to include task-trained service animals and emotional support animals.", "Misrepresentation of an assistance animal is addressed in the housing statute.", "Verify current Kentucky public-access rules for service animals in training."),
            ),
            StateSDLaw("Louisiana", "LA", "Yes — trainers and puppy raisers", true,
                "La. R.S. § 46:1958; misdemeanor",
                "Louisiana expressly gives trainers and puppy raisers access while training.",
                listOf("Public accommodations and conveyances are covered.", "Trainers and puppy raisers have access during training.", "Housing protections are included.", "Misrepresentation language exists for service dogs and service dogs-in-training."),
            ),
            StateSDLaw("Maine", "ME", "Yes — with trainer", true,
                "5 M.R.S.A. § 4553; civil violation",
                "Maine references especially trained service dog trainers in some agency policy; verify current training-access details.",
                listOf("Maine defines service animal for public accommodations as a dog trained for disability-related work or tasks.", "Misrepresentation of a service animal or assistance animal can be a civil violation.", "Maine has separate materials on assistance animals in housing.", "Verify current Maine SDIT-specific access details."),
            ),
            StateSDLaw("Maryland", "MD", "Yes — trainers being trained or raised", true,
                "Md. Code Human Services §§ 7-704; misdemeanor",
                "Maryland expressly recognizes service animal trainers accompanied by animals being trained or raised.",
                listOf("Maryland protects access for individuals with disabilities and parents of minor children with disabilities using service animals.", "Service animal trainers accompanied by animals being trained or raised are also included.", "The right extends to public places, common carriers, public accommodations, and places open to the public.", "Maryland law also protects access even when the animal is not wearing an orange license tag or collar."),
            ),
            StateSDLaw("Massachusetts", "MA", "Yes — with trainer", true,
                "M.G.L. c. 272 § 98A; fine up to \$500",
                "Massachusetts has separate trainer-rights statutes for hearing-dog and service-dog trainers.",
                listOf("Massachusetts public guidance uses the two-question framework.", "Certification or identification is not required for assistance animals in Massachusetts, though municipal dog registration may apply.", "Massachusetts has separate assistance-animal housing guidance.", "Verify applicable trainer-rights statutes for the specific training setting."),
            ),
            StateSDLaw("Michigan", "MI", "Yes — trainer/raiser/organization required", true,
                "MCL § 750.502b; misdemeanor",
                "Michigan training access is tied to trainer/raiser/organization status. Voluntary ID provisions exist.",
                listOf("Service animals in training may be covered when handled by a trainer, raiser, or representative of a service-animal training organization.", "Michigan has voluntary service-animal identification provisions; ADA public-access rights do not depend on registration.", "Misrepresentation and interference provisions may apply under Michigan law.", "Verify current Michigan trainer/organization qualification requirements."),
            ),
            StateSDLaw("Minnesota", "MN", "Yes — with trainer", true,
                "Minn. Stat. § 256C.02; misdemeanor",
                "Minnesota recognizes trainers and service animals in training in public places.",
                listOf("Minnesota protects access for people with disabilities accompanied by service animals.", "Service animals in training and trainers are recognized in state law.", "Public accommodations may not require extra charges because of the service animal.", "Confirm setting-specific details before relying on training access."),
            ),
            StateSDLaw("Mississippi", "MS", "Check current statutes", null,
                "Verify current statutes — Miss. Code § 43-6-155",
                "Mississippi SDIT access not confirmed in reviewed public-access section. Federal baseline recommended.",
                listOf("The animal must be specifically trained for guide, leader, listener, or other necessary assistance.", "Covered categories include blind, mobility-impaired, veterans with PTSD, and hearing-impaired persons.", "Public accommodations covered by ADA must also follow federal rules.", "Emotional support alone does not meet the ADA public-access task-work standard."),
            ),
            StateSDLaw("Missouri", "MO", "Yes — recognized trainers/team members", true,
                "Mo. Rev. Stat. § 209.150; class D misdemeanor",
                "Missouri training access is tied to recognized training centers or service-dog team members.",
                listOf("Missouri's service-dog definition includes dogs being trained.", "Recognized trainers may access listed premises while training.", "Service-dog team members may have training access under state law.", "Damage liability may apply."),
            ),
            StateSDLaw("Montana", "MT", "Yes — visible ID required", null,
                "Verify current statutes — MCA § 49-4-214",
                "Montana training access recognized. Service animal in training must wear visible written identification.",
                listOf("Service animals and service animals in training may access listed public places without extra charge.", "Trainers have the same rights and responsibilities.", "A service animal in training must wear visible written identification.", "Damage liability applies."),
            ),
            StateSDLaw("Nebraska", "NE", "Yes — bona fide trainers", true,
                "Neb. Rev. Stat. § 20-128; fine",
                "Nebraska training access applies to bona fide trainers in listed public places.",
                listOf("Covered places include streets, public facilities, carriers, lodging, accommodations, and amusement/resort places.", "People with disabilities may be accompanied by a service animal.", "Bona fide trainers have access with animals in training.", "Damage liability applies."),
            ),
            StateSDLaw("Nevada", "NV", "Yes — training explicitly recognized", true,
                "NRS § 651.075; misdemeanor",
                "Nevada expressly recognizes service animals in training in public-accommodation access.",
                listOf("Refusal of admittance or service because of a service animal is barred.", "Refusal because a person is training a service animal is also barred.", "Additional fees or deposits are barred.", "Questions may cover whether the animal is a service animal or in training and what tasks it performs or is being trained to perform."),
            ),
            StateSDLaw("New Hampshire", "NH", "Yes — handler or trainer", null,
                "Verify current statutes — RSA § 167-D:4",
                "NH law allows service animal to accompany handler or trainer into any public facility.",
                listOf("A service animal may accompany its handler or trainer into any public facility, housing accommodation, or place of public accommodation.", "A service animal trainer has the same access rights and responsibilities while engaged in training.", "Housing accommodations are included in the access statute.", "Verify current New Hampshire identifying or control requirements for SDITs."),
            ),
            StateSDLaw("New Jersey", "NJ", "Yes — with trainer", true,
                "N.J.S.A. § 10:5-29.2; disorderly persons offense",
                "NJ law recognizes service animals. Verify current SDIT-specific access in public accommodations.",
                listOf("Federal ADA public-access rules apply in covered New Jersey public accommodations.", "NJ school law permits students with disabilities to bring service animals into school buildings and on school grounds.", "Schools may ask two ADA-style questions unless the need is obvious.", "Verify current NJ SDIT-specific rules for non-school public accommodations."),
            ),
            StateSDLaw("New Mexico", "NM", "Yes — with owner/trainer/handler", true,
                "NMSA § 28-11-2; misdemeanor",
                "New Mexico references control by owner, trainer, or handler. Verify specific in-training scenarios.",
                listOf("Access covers public buildings, public accommodations, and common carriers.", "The qualified service animal must be controlled by an owner, trainer, or handler.", "No-pets policies cannot be used to deny entry to a qualifying animal.", "Interference with qualified service animals is separately prohibited."),
            ),
            StateSDLaw("New York", "NY", "Yes — with trainer", true,
                "Civil Rights Law § 47-b; violation/fine",
                "Civil Rights Law § 47-b grants SDIT access with trainer. Verify current NY SDIT-specific details.",
                listOf("New York service-dog access is protected by state and federal public-accommodation law.", "Civil Rights Law § 47-b grants SDIT access with trainer.", "Federal ADA two-question and no-documentation rules remain the safest public-access baseline.", "New York City and state guidance may add local context in housing, employment, or public-facing services."),
            ),
            StateSDLaw("North Carolina", "NC", "Yes — trainer + collar/harness/cape ID required", true,
                "N.C.G.S. § 168-4.2; Class 3 misdemeanor",
                "NC recognizes SDITs when accompanied by trainer and identified by collar/leash, harness, or cape.",
                listOf("Animals in training may enter listed places when accompanied by a trainer.", "The animal in training must wear a collar and leash, harness, or cape identifying it as in training.", "North Carolina references a state service-animal registration tag or proof that the animal is trained or being trained.", "A person with a disability may be accompanied by a service animal trained for the person's specific disability."),
            ),
            StateSDLaw("North Dakota", "ND", "Yes — with trainer", null,
                "Verify current statutes — N.D. Cent. Code § 25-13-01",
                "North Dakota recognizes SDIT access when accompanied by a trainer.",
                listOf("North Dakota protects access to public accommodations for people with disabilities using service animals.", "Service animals in training may be admitted when accompanied by a trainer.", "A trainer or handler can be liable for damage caused by the animal.", "Federal ADA two-question and documentation limits remain the baseline in ADA-covered places."),
            ),
            StateSDLaw("Ohio", "OH", "Yes — assistance-dog trainers recognized", true,
                "Ohio Rev. Code § 955.011; fine up to \$1,000",
                "Ohio recognizes assistance-dog trainers and assistance dogs in training.",
                listOf("Ohio protects access for people with disabilities using trained assistance dogs.", "Ohio has public-accommodation and housing-accommodation language.", "Trainers of assistance dogs are recognized in state law.", "Extra charges are barred, but damage liability may apply."),
            ),
            StateSDLaw("Oklahoma", "OK", "Check current statutes", true,
                "7 Okl. St. § 19.1; misdemeanor",
                "A broad Oklahoma SDIT public-access right was not confirmed in the reviewed materials.",
                listOf("Oklahoma uses 28 C.F.R. § 36.104 definitions.", "Emotional support and therapy animals are excluded from service-animal status.", "Qualification questions must comply with federal ADA limits.", "Oklahoma has misrepresentation language in the cited source."),
            ),
            StateSDLaw("Oregon", "OR", "Yes — assistance animal trainee explicitly recognized", true,
                "ORS § 659A.143; violation",
                "Oregon provides specific access language for assistance animal trainees and trainers.",
                listOf("Oregon defines 'assistance animal trainee' as an animal training for disability-related work or tasks.", "Public accommodations and state government services are covered.", "Documentation proving trainee status cannot be required.", "Asking about the nature or extent of disability is barred."),
            ),
            StateSDLaw("Pennsylvania", "PA", "Yes — with trainer", true,
                "43 P.S. § 953; summary offense/fine",
                "Pennsylvania recognizes service-dog-in-training access; verify trainer and identification details by setting.",
                listOf("Pennsylvania protects access for people with disabilities accompanied by guide, signal, or service dogs.", "Service dogs in training and handlers/trainers may be recognized in state access law.", "Extra charges are generally barred, while damage liability may apply.", "Federal ADA limits on documentation and inquiry still apply in ADA-covered public accommodations."),
            ),
            StateSDLaw("Rhode Island", "RI", "Yes — with trainer", true,
                "RIGL § 40-9.1-1; fine",
                "Rhode Island protects service-animal access. Verify current SDIT-specific rules.",
                listOf("Rhode Island protects service-animal access in listed public places and facilities.", "No extra charge may be required for the service animal.", "The handler is liable for damage done to people, premises, or facilities by the service animal.", "Verify current SDIT-specific access details."),
            ),
            StateSDLaw("South Carolina", "SC", "Yes — SDIT recognized; verify control requirements", true,
                "S.C. Code § 43-33-20; fine up to \$500",
                "South Carolina recognizes SDITs but handlers should verify setting-specific details and control requirements.",
                listOf("Service animals must be trained to assist or accommodate a sensory, mental, or physical disability.", "State materials recognize service-animals-in-training.", "Emotional support, well-being, comfort, or companionship alone does not constitute service-animal work.", "No vest, marking, or documentation is required for a trained service animal under state materials."),
            ),
            StateSDLaw("South Dakota", "SD", "Yes — trainer + collar/harness/cape ID required", null,
                "Verify current statutes — SDCL § 20-13-23.2",
                "South Dakota recognizes SDITs when accompanied by trainer and properly identified.",
                listOf("A service animal trainer may be accompanied by a service animal in training in listed places.", "The animal in training must wear a collar and leash, harness, or cape identifying it as in training.", "Businesses serving the public must allow people with disabilities to bring service animals into customer areas.", "Extra charges are barred, but damage liability applies."),
            ),
            StateSDLaw("Tennessee", "TN", "Yes — recognized training agency or school", true,
                "Tenn. Code § 62-7-112; Class B misdemeanor",
                "Tennessee training recognition is tied to recognized training agencies or schools.",
                listOf("State definition includes animals individually trained for disability-related work or tasks.", "Also includes animals being trained by an employee or puppy raiser from a recognized training agency or school.", "Tennessee has misrepresentation penalties for public-accommodation and housing-related false claims.", "Other species not specified are not service animals under this definition."),
            ),
            StateSDLaw("Texas", "TX", "Yes — approved trainer required", true,
                "Tex. Human Resources Code § 121.006; Class A misdemeanor, fine up to \$300",
                "Texas Human Resources Code § 121.003 grants SDIT access when accompanied by an approved trainer.",
                listOf("A service animal in training must not be denied admittance when accompanied by an approved trainer.", "Trained service animals are protected in public places.", "Texas limits demands or inquiries about qualifications or certifications.", "When disability is not apparent, the ADA-style two questions apply."),
            ),
            StateSDLaw("Utah", "UT", "Yes — broader than federal ADA", true,
                "Utah Code § 62A-5b-102; fine",
                "Utah training access is broader than the federal ADA baseline, subject to behavior/control and covered-place rules.",
                listOf("Public-access rights are recognized for people with disabilities accompanied by service animals.", "Individuals training animals to become service animals also have access rights.", "Reasonable repair costs for damage may be recovered.", "Exclusion remains possible where federal law permits."),
            ),
            StateSDLaw("Vermont", "VT", "Yes — with trainer", null,
                "Verify current statutes — 9 V.S.A. § 4502",
                "Vermont public accommodations covered by federal ADA baseline. Verify VT-specific SDIT details.",
                listOf("Federal ADA public-access rules apply in covered Vermont public accommodations.", "The ADA task-work definition and two-question limit are the safest baseline.", "Verify Vermont-specific SDIT access rules for non-ADA settings.", "Emotional support or companionship alone does not create ADA public-access rights."),
            ),
            StateSDLaw("Virginia", "VA", "Yes — 6+ months old, ID gear required", true,
                "Va. Code § 51.5-44; Class 4 misdemeanor",
                "Virginia recognizes dogs in training at least six months old when trainer and identification conditions are met.",
                listOf("Dogs in training must be at least six months old to receive state-law SDIT access.", "Must be handled by experienced trainers, recognized organizations, or certain continuing-training teams.", "Virginia specifies identification gear: harnesses, blaze orange leashes, vests, backpacks, or organization jackets depending on dog type/training status.", "Emotional support, well-being, comfort, or companionship alone is not service-dog work."),
            ),
            StateSDLaw("Washington", "WA", "Yes — expanded to SDITs in public accommodations", true,
                "RCW § 49.60.215; civil infraction, fine up to \$250",
                "Washington public-accommodation access appears broader for SDITs; employment settings may differ.",
                listOf("Washington public-accommodation guidance covers trained dog guides and service animals.", "State materials note expansion to service animals in training.", "Some public resources state documentation is not required in public accommodation spaces.", "Employment settings can differ from public-accommodation settings."),
            ),
            StateSDLaw("West Virginia", "WV", "Yes — trainers included in White Cane Law", null,
                "Verify current statutes — W. Va. Code § 5-15-4",
                "West Virginia White Cane Law includes service-animal and trainer language; verify current in-training details.",
                listOf("Covered places include public buildings, public facilities, transportation, lodging, restaurants, hospitals, and other places open to the public.", "People with disabilities are entitled to full and equal accommodations in covered places.", "The statute includes service animals and trainers.", "Federal ADA rules still control documentation and two-question limits in ADA-covered public accommodations."),
            ),
            StateSDLaw("Wisconsin", "WI", "Yes — with trainer in covered public places", true,
                "Wis. Stat. § 174.056; forfeiture/fine",
                "Wisconsin recognizes service animals in training when accompanied by trainers in covered public places.",
                listOf("Wisconsin protects access to public places and accommodations for people with disabilities using service animals.", "Service animals in training accompanied by trainers are recognized under state law.", "Extra charges are barred, though handlers/trainers may be liable for damage.", "Federal ADA rules remain the baseline for ADA-covered public accommodations."),
            ),
            StateSDLaw("Wyoming", "WY", "Yes — in-training included in definition", null,
                "Verify current statutes — Wyo. Stat. § 35-9-305",
                "Wyoming includes dogs in training within its service-animal definition.",
                listOf("Wyoming protects public buildings, public facilities, public places, and public accommodations.", "Wyoming uses federal ADA service-animal definitions and includes miniature horses under cited federal regulations.", "The definition includes a dog being trained for disability-related work or tasks.", "Residential-property language references assistance animals and FHA treatment."),
            ),
        )

        var expanded by remember { mutableStateOf(false) }
        var selectedState by remember { mutableStateOf<StateSDLaw?>(null) }
        var query by remember { mutableStateOf("") }
        var detecting by remember { mutableStateOf(false) }
        var gpsStatus by remember { mutableStateOf("") }
        val filtered = if (query.isBlank()) states
                       else states.filter { it.name.contains(query, ignoreCase = true) || it.abbr.contains(query, ignoreCase = true) }

        val context = LocalContext.current
        val scope   = rememberCoroutineScope()

        fun matchStateFromAdminArea(adminArea: String): StateSDLaw? =
            states.firstOrNull { it.name.equals(adminArea.trim(), ignoreCase = true) }

        fun runGpsDetect() {
            detecting = true
            gpsStatus = ""
            scope.launch {
                val result = resolveLocation(context)
                detecting = false
                if (result != null) {
                    val stateName = result.second.split(", ").lastOrNull()?.trim() ?: ""
                    val match = matchStateFromAdminArea(stateName)
                    if (match != null) {
                        selectedState = match
                        gpsStatus = "📍 Auto-detected: ${match.name}"
                    } else {
                        gpsStatus = "📍 Location found but state not matched — select manually"
                    }
                } else {
                    gpsStatus = "Could not get location — select manually"
                }
            }
        }

        val permLauncher = rememberLauncherForActivityResult(
            ActivityResultContracts.RequestMultiplePermissions()
        ) { perms ->
            if (perms[Manifest.permission.ACCESS_FINE_LOCATION] == true ||
                perms[Manifest.permission.ACCESS_COARSE_LOCATION] == true) {
                runGpsDetect()
            } else {
                gpsStatus = "Location permission denied — select state manually"
            }
        }

        LaunchedEffect(Unit) {
            val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
            val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
            if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                runGpsDetect()
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.ADA_ACCESS_CARD }) { Text("← Back") }
                Text(
                    "State Access Laws",
                    style      = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier   = Modifier.padding(start = 4.dp),
                )
            }

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors   = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer),
            ) {
                Text(
                    "Federal ADA rights apply in every state. State laws may add broader protections — especially for service dogs in training (SDITs). Always verify current statutes before relying on this summary.",
                    style    = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.padding(12.dp),
                    color    = MaterialTheme.colorScheme.onSecondaryContainer,
                )
            }

            // GPS detect row
            Row(
                modifier              = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment     = Alignment.CenterVertically,
            ) {
                if (detecting) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                        Text("Detecting your state…", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }
                } else {
                    Text(
                        gpsStatus.ifBlank { if (selectedState == null) "Select your state below" else "" },
                        style = MaterialTheme.typography.bodySmall,
                        color = GpOnSurfaceVariant,
                        modifier = Modifier.weight(1f),
                    )
                }
                TextButton(
                    onClick = {
                        val fine   = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
                        val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
                        if (fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED) {
                            runGpsDetect()
                        } else {
                            permLauncher.launch(arrayOf(
                                Manifest.permission.ACCESS_FINE_LOCATION,
                                Manifest.permission.ACCESS_COARSE_LOCATION,
                            ))
                        }
                    },
                    enabled = !detecting,
                ) { Text("📍 Detect", style = MaterialTheme.typography.bodySmall) }
            }

            // State picker
            ExposedDropdownMenuBox(
                expanded         = expanded,
                onExpandedChange = { expanded = !expanded },
            ) {
                OutlinedTextField(
                    value            = if (expanded) query else (selectedState?.let { "${it.abbr} — ${it.name}" } ?: ""),
                    onValueChange    = { query = it; expanded = true },
                    label            = { Text("Select a state") },
                    trailingIcon     = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
                    modifier         = Modifier.fillMaxWidth().menuAnchor(),
                    singleLine       = true,
                )
                ExposedDropdownMenu(
                    expanded         = expanded,
                    onDismissRequest = { expanded = false; query = "" },
                ) {
                    filtered.forEach { law ->
                        DropdownMenuItem(
                            text    = { Text("${law.abbr} — ${law.name}") },
                            onClick = { selectedState = law; expanded = false; query = "" },
                        )
                    }
                    if (filtered.isEmpty()) {
                        DropdownMenuItem(
                            text    = { Text("No match", color = GpOnSurfaceVariant) },
                            onClick = {},
                        )
                    }
                }
            }

            // State detail
            val law = selectedState
            if (law != null) {
                val sditColor = when {
                    law.sditAccess.startsWith("Yes — same") -> androidx.compose.ui.graphics.Color(0xFF2E7D32)
                    law.sditAccess.startsWith("Yes")        -> androidx.compose.ui.graphics.Color(0xFF1565C0)
                    else                                    -> GpOnSurfaceVariant
                }

                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("${law.name} (${law.abbr})", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)

                        // SDIT access
                        Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("SDIT Access", style = MaterialTheme.typography.labelMedium, modifier = Modifier.width(90.dp))
                            Text(law.sditAccess, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold, color = sditColor, modifier = Modifier.weight(1f))
                        }

                        // State-specific requirements
                        if (law.requirements.isNotEmpty()) {
                            HorizontalDivider()
                            Text("State Requirements", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                            law.requirements.forEach { req ->
                                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Text("•", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    Text(req, style = MaterialTheme.typography.bodySmall)
                                }
                            }
                        }

                        // Misrep law
                        Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Misrep Law", style = MaterialTheme.typography.labelMedium, modifier = Modifier.width(90.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    when (law.misrepLaw) {
                                        true  -> "Yes — criminal/civil penalty"
                                        false -> "No state law found"
                                        null  -> "Verify current statutes"
                                    },
                                    style      = MaterialTheme.typography.bodySmall,
                                    fontWeight = FontWeight.SemiBold,
                                    color      = if (law.misrepLaw == true) androidx.compose.ui.graphics.Color(0xFF2E7D32) else GpOnSurfaceVariant,
                                )
                                if (law.misrepNote.isNotBlank() && law.misrepNote != "Verify current statutes") {
                                    Text(law.misrepNote, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                            }
                        }

                        if (law.keyNote.isNotBlank()) {
                            HorizontalDivider()
                            Text(law.keyNote, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        }
                    }
                }
            } else {
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Text(
                        "Select a state above to see its SDIT access rights, misrepresentation laws, and key notes.",
                        style    = MaterialTheme.typography.bodySmall,
                        color    = GpOnSurfaceVariant,
                        modifier = Modifier.padding(16.dp),
                    )
                }
            }

            // ADA baseline reminder
            SummaryCard {
                Text("Federal ADA baseline (all states)", fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(6.dp))
                listOf(
                    "Fully trained service dogs have public access in all 50 states and DC.",
                    "Staff may only ask the two permitted questions.",
                    "No documentation, vest, or certification required.",
                    "Access may be denied only if the dog is out of control or not housebroken.",
                ).forEach {
                    Text("• $it", style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(2.dp))
                }
            }

            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(onClick = { currentSection = NavSection.ADA_ACCESS_CARD }, modifier = Modifier.weight(1f)) {
                    Text("ADA Card", fontSize = 12.sp)
                }
                OutlinedButton(onClick = { currentSection = NavSection.AIR_TRAVEL }, modifier = Modifier.weight(1f)) {
                    Text("Air Travel", fontSize = 12.sp)
                }
                OutlinedButton(onClick = { currentSection = NavSection.HOUSING_FAQ }, modifier = Modifier.weight(1f)) {
                    Text("Housing", fontSize = 12.sp)
                }
            }

            Text(
                "This is a general reference only, not legal advice. Laws change — verify current statutes before relying on any state-specific information.",
                style = MaterialTheme.typography.bodySmall,
                color = GpOnSurfaceVariant,
            )
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
                        onClick   = { loadTrainingProgram(); currentSection = NavSection.TRAINING_PROGRAM },
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
                        onClick   = { currentSection = NavSection.TRUCKING_MODE },
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

    // ── Training Program / Ladder section ──────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun TrainingProgramSection() {
        val result    = trainProgResult
        val byCategory = result?.items?.groupBy { it.category } ?: emptyMap()

        val statusLabel = mapOf(
            "not_started" to "Not started",
            "in_progress" to "In progress",
            "proofing"    to "Proofing",
            "mastered"    to "Mastered",
        )
        val statusOrder = listOf("not_started", "in_progress", "proofing", "mastered")

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadTrainingProgram(); isPullingToRefresh = false },
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
                        if (result != null) "🪜 ${result.dogName}" else "🪜 Training Ladder",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                SectionMessage(trainProgMessage, onRetry = { loadTrainingProgram() })

                if (result != null) {
                    SummaryCard {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            StatChip("Total",      result.total.toString(),      Modifier.weight(1f))
                            StatChip("Mastered",   result.mastered.toString(),   Modifier.weight(1f))
                            StatChip("In progress", result.inProgress.toString(), Modifier.weight(1f))
                            StatChip("Proofing",   result.proofing.toString(),   Modifier.weight(1f))
                        }
                    }
                }

                if (result != null && result.items.isEmpty()) {
                    SummaryCard {
                        Text("No training ladder loaded yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        Spacer(Modifier.height(8.dp))
                        Button(onClick = { seedTrainingProgram() }, modifier = Modifier.fillMaxWidth()) {
                            Text("Load starter training ladder")
                        }
                    }
                }

                byCategory.forEach { (category, items) ->
                    val expanded = trainProgExpandedCategories.contains(category)
                    val masteredInCat = items.count { it.status == "mastered" }
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column {
                            TextButton(
                                onClick  = {
                                    trainProgExpandedCategories = if (expanded)
                                        trainProgExpandedCategories - category
                                    else
                                        trainProgExpandedCategories + category
                                },
                                modifier = Modifier.fillMaxWidth(),
                                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 10.dp),
                            ) {
                                Row(
                                    modifier              = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment     = Alignment.CenterVertically,
                                ) {
                                    Text(category, fontWeight = FontWeight.SemiBold, color = GpOnSurface, modifier = Modifier.weight(1f))
                                    Text(
                                        "$masteredInCat/${items.size}  ${if (expanded) "▲" else "▼"}",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = GpOnSurfaceVariant,
                                    )
                                }
                            }

                            if (expanded) {
                                HorizontalDivider()
                                Column(
                                    modifier = Modifier.padding(12.dp),
                                    verticalArrangement = Arrangement.spacedBy(14.dp),
                                ) {
                                    items.forEach { item ->
                                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                            Row(
                                                verticalAlignment     = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                            ) {
                                                Text(
                                                    item.itemName,
                                                    fontWeight = FontWeight.Medium,
                                                    style      = MaterialTheme.typography.bodySmall,
                                                    modifier   = Modifier.weight(1f),
                                                )
                                                if (item.trackCode.isNotBlank()) {
                                                    Surface(
                                                        shape = MaterialTheme.shapes.extraSmall,
                                                        color = GpPrimaryContainer,
                                                    ) {
                                                        Text(
                                                            item.trackCode.uppercase().replace('_', ' '),
                                                            style    = MaterialTheme.typography.labelSmall,
                                                            color    = GpOnSurface,
                                                            modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp),
                                                        )
                                                    }
                                                }
                                            }
                                            if (item.description.isNotBlank()) {
                                                Text(item.description, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                            }
                                            // 2×2 status button grid
                                            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                                statusOrder.chunked(2).forEach { pair ->
                                                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                        pair.forEach { value ->
                                                            val label = statusLabel[value] ?: value
                                                            if (item.status == value) {
                                                                Button(
                                                                    onClick        = {},
                                                                    modifier       = Modifier.weight(1f),
                                                                    contentPadding = PaddingValues(horizontal = 2.dp, vertical = 6.dp),
                                                                ) { Text(label, style = MaterialTheme.typography.labelSmall, maxLines = 1) }
                                                            } else {
                                                                OutlinedButton(
                                                                    onClick        = { updateTrainingProgramItem(item.id, value) },
                                                                    modifier       = Modifier.weight(1f),
                                                                    contentPadding = PaddingValues(horizontal = 2.dp, vertical = 6.dp),
                                                                ) { Text(label, style = MaterialTheme.typography.labelSmall, maxLines = 1) }
                                                            }
                                                        }
                                                        if (pair.size == 1) Spacer(Modifier.weight(1f))
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
        var docTypeExpanded by remember { mutableStateOf(false) }
        val docTypeOptions = listOf("vet_record" to "Vet Record", "esa_letter" to "ESA Letter", "service_dog_letter" to "Service Dog Letter")
        val selectedDocTypeLabel = docTypeOptions.firstOrNull { it.first == docPickType }?.second ?: "Vet Record"

        val docGalleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
            uri?.let {
                val att = feedbackResolveAttachment(it)
                docPickUri  = it
                docPickMime = att?.mimeType ?: contentResolver.getType(it).orEmpty()
                docPickName = att?.displayName ?: "document"
            }
        }

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

                // Native document upload
                OutlinedButton(
                    onClick  = { docShowForm = !docShowForm; if (!docShowForm) { docPickUri = null; docPickMessage = "" } },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text(if (docShowForm) "Cancel" else "+ Upload Document") }

                if (docShowForm) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text("Upload Document", fontWeight = FontWeight.SemiBold)

                            // File picker
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedButton(onClick = { docGalleryLauncher.launch("*/*") }, modifier = Modifier.weight(1f)) {
                                    Text(if (docPickUri == null) "Choose File" else "Change File", fontSize = 13.sp)
                                }
                                if (docPickUri != null) {
                                    Text(docPickName.take(30), fontSize = 12.sp, color = GpOnSurfaceVariant, modifier = Modifier.weight(1f))
                                }
                            }

                            // Doc type dropdown
                            ExposedDropdownMenuBox(expanded = docTypeExpanded, onExpandedChange = { docTypeExpanded = it }) {
                                OutlinedTextField(
                                    value         = selectedDocTypeLabel,
                                    onValueChange = {},
                                    readOnly      = true,
                                    label         = { Text("Document type") },
                                    trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(docTypeExpanded) },
                                    modifier      = Modifier.fillMaxWidth().menuAnchor(),
                                )
                                ExposedDropdownMenu(expanded = docTypeExpanded, onDismissRequest = { docTypeExpanded = false }) {
                                    docTypeOptions.forEach { (value, label) ->
                                        DropdownMenuItem(text = { Text(label) }, onClick = { docPickType = value; docTypeExpanded = false })
                                    }
                                }
                            }

                            OutlinedTextField(
                                value = docPickTitle, onValueChange = { docPickTitle = it },
                                label = { Text("Title *") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                            )
                            OutlinedTextField(
                                value = docPickProvider, onValueChange = { docPickProvider = it },
                                label = { Text("Provider / clinic") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                            )
                            OutlinedTextField(
                                value = docPickNotes, onValueChange = { docPickNotes = it },
                                label = { Text("Notes") }, modifier = Modifier.fillMaxWidth(), minLines = 2,
                            )

                            if (docPickMessage.isNotBlank()) {
                                Text(docPickMessage, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            }
                            Button(
                                onClick  = { submitDocUpload() },
                                enabled  = !docPickLoading && docPickUri != null,
                                modifier = Modifier.fillMaxWidth(),
                            ) { Text(if (docPickLoading) "Uploading…" else "Upload Document") }
                        }
                    }
                }
            }
        }
    }

    // ── Health Summary section ──────────────────────────────────────────────
    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun HealthSummarySection() {
        val summary = healthSummary

        fun fmtDate(raw: String?): String {
            if (raw.isNullOrBlank()) return "—"
            return runCatching {
                java.time.LocalDate.parse(raw.take(10)).format(
                    java.time.format.DateTimeFormatter.ofPattern("MMM d, yyyy"),
                )
            }.getOrDefault(raw)
        }

        fun fmtDateTime(raw: String?): String {
            if (raw.isNullOrBlank()) return "—"
            return runCatching {
                java.time.LocalDateTime.parse(raw.take(19)).format(
                    java.time.format.DateTimeFormatter.ofPattern("MMM d, yyyy h:mm a"),
                )
            }.getOrElse { fmtDate(raw) }
        }

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadHealthSummary() },
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
                        if (summary != null) "🏥 ${summary.dogName}" else "🏥 Health Summary",
                        style      = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(start = 4.dp),
                    )
                }

                if (healthIsLoading) {
                    LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                }

                SectionMessage(healthMessage, onRetry = { loadHealthSummary() })

                if (summary == null && !healthIsLoading) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("No health summary loaded", fontWeight = FontWeight.SemiBold)
                            Text(
                                "Load the latest care summary from the More menu to see checkups, medications, and recent records.",
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )
                            Button(onClick = { loadHealthSummary() }, modifier = Modifier.fillMaxWidth()) {
                                Text("Load Health Summary")
                            }
                        }
                    }
                }

                if (summary != null) {
                    // ── Stat tiles ─────────────────────────────────────────────
                    Row(
                        modifier              = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        StatTile("Last Checkup", fmtDate(summary.lastCheckupDate), Modifier.weight(1f))
                        StatTile("Weight", summary.weightLbs?.let { "%.1f lbs".format(it) } ?: "—", Modifier.weight(1f))
                        StatTile("Active Meds", summary.activeMedicationCount.toString(), Modifier.weight(1f))
                    }

                    // ── Vet contact ────────────────────────────────────────────
                    if (summary.primaryVetClinic.isNotBlank() || summary.primaryVetName.isNotBlank()) {
                        Text("Primary Vet", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                if (summary.primaryVetClinic.isNotBlank()) {
                                    Text(summary.primaryVetClinic, fontWeight = FontWeight.SemiBold)
                                }
                                if (summary.primaryVetName.isNotBlank()) {
                                    Text(summary.primaryVetName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                                if (summary.primaryVetPhone.isNotBlank()) {
                                    val ctx = androidx.compose.ui.platform.LocalContext.current
                                    TextButton(
                                        onClick = {
                                            val intent = android.content.Intent(android.content.Intent.ACTION_DIAL,
                                                android.net.Uri.parse("tel:${summary.primaryVetPhone}"))
                                            ctx.startActivity(intent)
                                        },
                                        contentPadding = androidx.compose.foundation.layout.PaddingValues(0.dp),
                                    ) {
                                        Text("📞 ${summary.primaryVetPhone}", style = MaterialTheme.typography.bodySmall)
                                    }
                                }
                            }
                        }
                    }

                    // ── Upcoming appointments ──────────────────────────────────
                    Text("Upcoming Appointments", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    if (summary.upcomingAppointments.isEmpty()) {
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Text(
                                "No upcoming appointments scheduled.",
                                style    = MaterialTheme.typography.bodySmall,
                                color    = GpOnSurfaceVariant,
                                modifier = Modifier.padding(14.dp),
                            )
                        }
                    } else {
                        summary.upcomingAppointments.forEach { appt ->
                            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Row(
                                        modifier              = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment     = Alignment.Top,
                                    ) {
                                        Text(appt.title.ifBlank { "Appointment" }, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                        Text(fmtDateTime(appt.appointmentAt), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (appt.clinicName.isNotBlank()) {
                                        Text(appt.clinicName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (appt.locationText.isNotBlank()) {
                                        Text("📍 ${appt.locationText}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (appt.notes.isNotBlank()) {
                                        Text(appt.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                }
                            }
                        }
                    }

                    // ── Active medications ─────────────────────────────────────
                    Text("Active Medications", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    if (summary.activeMedications.isEmpty()) {
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Text(
                                "No active medications.",
                                style    = MaterialTheme.typography.bodySmall,
                                color    = GpOnSurfaceVariant,
                                modifier = Modifier.padding(14.dp),
                            )
                        }
                    } else {
                        summary.activeMedications.forEach { med ->
                            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Row(
                                        modifier              = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment     = Alignment.CenterVertically,
                                    ) {
                                        Text(med.medicationName, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                        if (med.dosage.isNotBlank()) {
                                            Text(med.dosage, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        }
                                    }
                                    if (med.scheduleText.isNotBlank()) {
                                        Text("🕐 ${med.scheduleText}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (med.refillDate.isNotBlank()) {
                                        val daysUntil = runCatching {
                                            java.time.temporal.ChronoUnit.DAYS.between(
                                                java.time.LocalDate.now(),
                                                java.time.LocalDate.parse(med.refillDate.take(10)),
                                            )
                                        }.getOrDefault(Long.MAX_VALUE)
                                        val refillColor = if (daysUntil <= 14) androidx.compose.ui.graphics.Color(0xFFE65100) else GpOnSurfaceVariant
                                        Text(
                                            "🔁 Refill: ${fmtDate(med.refillDate)}" + if (daysUntil in 0..14) " (${daysUntil}d)" else "",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = refillColor,
                                        )
                                    }
                                    if (med.instructions.isNotBlank()) {
                                        Text(med.instructions, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                }
                            }
                        }
                    }

                    // ── Recent appointments ────────────────────────────────────
                    Text("Recent Appointments", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    if (summary.recentAppointments.isEmpty()) {
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Text(
                                "No completed appointments on record.",
                                style    = MaterialTheme.typography.bodySmall,
                                color    = GpOnSurfaceVariant,
                                modifier = Modifier.padding(14.dp),
                            )
                        }
                    } else {
                        summary.recentAppointments.forEach { appt ->
                            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Row(
                                        modifier              = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment     = Alignment.Top,
                                    ) {
                                        Text(appt.title.ifBlank { "Appointment" }, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                        Text(fmtDate(appt.appointmentAt), style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (appt.clinicName.isNotBlank()) {
                                        Text(appt.clinicName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                    if (appt.notes.isNotBlank()) {
                                        Text(appt.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    }
                                }
                            }
                        }
                    }

                    // ── Quick nav ──────────────────────────────────────────────
                    Row(
                        modifier              = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        OutlinedButton(
                            onClick  = { loadAppointments(); currentSection = NavSection.APPOINTMENTS },
                            modifier = Modifier.weight(1f),
                        ) { Text("📅 Appointments", fontSize = 12.sp) }
                        OutlinedButton(
                            onClick  = { loadHealthDocs(); currentSection = NavSection.HEALTH_DOCS },
                            modifier = Modifier.weight(1f),
                        ) { Text("🩺 Health Docs", fontSize = 12.sp) }
                    }
                }
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
                        onClick  = { onDismiss(); currentSection = NavSection.SETTINGS },
                        modifier = Modifier.weight(1f),
                    ) { Text("⚙️ Settings") }
                    OutlinedButton(
                        onClick  = { onDismiss(); signOut("Signed out.") },
                        modifier = Modifier.weight(1f),
                        colors   = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                    ) { Text("↩️ Sign Out") }
                }
                MenuSheetSection("Dog & Profile", listOf(
                    "👤 Handler Profile" to { if (profileResult == null) loadProfile(); currentSection = NavSection.PROFILE },
                    "🐕 Dogs"            to { currentSection = NavSection.DOGS },
                    "🪪 Dog Profile"     to { currentSection = NavSection.DOG_PROFILE },
                    "🐾 Breed Finder"   to { currentSection = NavSection.BREED_QUIZ },
                    "➕ Add Dog"        to { currentSection = NavSection.ADD_DOG },
                    "🤝 Dog Access"      to { if (dogAccessResult == null) loadDogAccess(); currentSection = NavSection.DOG_ACCESS },
                    "📡 QR Tracking"     to { loadQrTracking(); currentSection = NavSection.QR_TRACKING },
                    "📊 Stats"           to { if (statsResult == null) loadStats(); currentSection = NavSection.STATS },
                ), onDismiss, defaultExpanded = true)
                MenuSheetSection("Training", listOf(
                    "⚡ Log Training"         to { currentSection = NavSection.TRAINING },
                    "📋 Training History"     to { currentSection = NavSection.TRAINING_HISTORY },
                    "🎯 Goal Intake"          to { loadGoalIntake(goalIntakeFilter); currentSection = NavSection.GOAL_INTAKE },
                    "🧩 Goal Builder"         to { currentSection = NavSection.GOAL_BUILDER },
                    "🛠️ Habit Repair"        to { loadHabitRepair(); currentSection = NavSection.HABIT_REPAIR },
                    "⚠️ Behavior Risk"        to { loadBehaviorRisk(currentActiveDogId); currentSection = NavSection.BEHAVIOR_RISK },
                    "♻️ Regression Engine"   to { loadRegressionEvents(); currentSection = NavSection.REGRESSION },
                    "🐾 Candidate Assessment" to { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_ASSESSMENT },
                    "📊 Compare Dogs"         to { loadCandidateAssessments(); currentSection = NavSection.CANDIDATE_COMPARISON },
                    "🎖️ Tactical Training"   to { currentSection = NavSection.TACTICAL_TRAINING },
                    "🚚 Trucking Mode"        to { currentSection = NavSection.TRUCKING_MODE },
                    "🪜 Training Ladder"      to { loadTrainingProgram(); currentSection = NavSection.TRAINING_PROGRAM },
                    "🏆 Challenges"           to { currentSection = NavSection.COMMUNITY_CHALLENGES },
                    "🤖 AI Coach"             to { currentSection = NavSection.AI_ASSISTANT },
                ), onDismiss, defaultExpanded = true)
                MenuSheetSection("Care & Health", listOf(
                    "🏥 Health Summary"   to { loadHealthSummary(); currentSection = NavSection.HEALTH_SUMMARY },
                    "🩺 Health Docs"      to { loadHealthDocs(); currentSection = NavSection.HEALTH_DOCS },
                    "📅 Vet Appointments" to { loadAppointments(); currentSection = NavSection.APPOINTMENTS },
                    "💊 Medications"      to { loadMedications(); currentSection = NavSection.MEDICATIONS },
                    "🔍 Find a Vet"       to { currentSection = NavSection.VET_FINDER },
                ), onDismiss, defaultExpanded = true)
                MenuSheetSection("Laws & Rights", listOf(
                    "🗺️ State Access Laws" to { currentSection = NavSection.STATE_ACCESS },
                    "🪪 ADA Access Card"   to { currentSection = NavSection.ADA_ACCESS_CARD },
                    "✈️ Air Travel Rights" to { currentSection = NavSection.AIR_TRAVEL },
                    "🏠 Housing & Access"  to { currentSection = NavSection.HOUSING_FAQ },
                    "✅ Certification"      to { loadCertification(); currentSection = NavSection.CERTIFICATION },
                    "ℹ️ ESA Legal Info"    to { currentSection = NavSection.ESA_LEGAL },
                ), onDismiss, defaultExpanded = false)
                MenuSheetSection("App & Account", listOf(
                    "🔔 Notifications"   to { currentSection = NavSection.NOTIFICATIONS; if (notifResult == null) refreshNotifications() },
                    "🧠 Smart Alerts"    to { loadAlerts(); currentSection = NavSection.SMART_ALERTS },
                    "⌚ Wearable Sync"   to { if (wearableResult == null) loadWearables(); currentSection = NavSection.WEARABLES },
                    "🏷️ Plans"          to { currentSection = NavSection.PLANS },
                    "🤝 My Trainers"    to { currentSection = NavSection.TRAINER_MARKETPLACE },
                    "❓ FAQ"             to { currentSection = NavSection.FAQ },
                    "💬 Feedback"        to { currentSection = NavSection.FEEDBACK },
                ), onDismiss, defaultExpanded = false)
            }
        }
    }

    @Composable
    private fun MenuSheetSection(
        title: String,
        items: List<Pair<String, () -> Unit>>,
        onDismiss: () -> Unit,
        defaultExpanded: Boolean = true,
    ) {
        var expanded by remember { mutableStateOf(defaultExpanded) }
        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
            Column {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { expanded = !expanded }
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment     = Alignment.CenterVertically,
                ) {
                    Text(
                        title,
                        style      = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Bold,
                        color      = GpOnSurfaceVariant,
                    )
                    Text(
                        if (expanded) "▲" else "▼",
                        style = MaterialTheme.typography.labelSmall,
                        color = GpOnSurfaceVariant,
                    )
                }
                AnimatedVisibility(visible = expanded) {
                    Column(
                        modifier = Modifier.padding(start = 12.dp, end = 12.dp, bottom = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
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

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    private fun QrTrackingSection() {
        val result = qrResult
        val bitmap = qrBitmap
        val context = LocalContext.current

        PullToRefreshBox(
            isRefreshing = isPullingToRefresh,
            onRefresh    = { isPullingToRefresh = true; loadQrTracking() },
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
                    Text("QR Tracking", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
                }

                SectionMessage(qrMessage, onRetry = { loadQrTracking() })

                if (qrIsLoading) {
                    LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                }

                if (result == null && !qrIsLoading) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("No QR tracking loaded yet.", fontWeight = FontWeight.SemiBold)
                            Text("Load the active dog's public QR code and recent scan activity.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            Button(onClick = { loadQrTracking() }, modifier = Modifier.fillMaxWidth()) { Text("Load QR Tracking") }
                        }
                    }
                } else if (result != null) {
                    SummaryCard {
                        Text(result.dogName, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.titleSmall)
                        Text("Public QR profile", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    }

                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                        ) {
                            if (bitmap != null) {
                                Image(
                                    bitmap = bitmap.asImageBitmap(),
                                    contentDescription = "QR code for ${result.dogName}",
                                    modifier = Modifier.size(280.dp),
                                )
                            } else {
                                Text("QR image unavailable.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            }

                            SummaryCard {
                                Text("Total views", style = MaterialTheme.typography.labelLarge, color = GpOnSurfaceVariant)
                                Text("${result.totalViews}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                                if (result.lastViewed.isNotBlank()) {
                                    Text("Last viewed: ${result.lastViewed}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                            }

                            Text(
                                text = result.publicUrl,
                                style = MaterialTheme.typography.bodySmall,
                                color = GpOnSurfaceVariant,
                            )

                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                OutlinedButton(
                                    onClick = {
                                        val cb = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                        cb.setPrimaryClip(ClipData.newPlainText("GuidePaw QR public URL", result.publicUrl))
                                        qrMessage = "Public URL copied to clipboard."
                                    },
                                    modifier = Modifier.weight(1f),
                                ) { Text("Copy URL") }
                                Button(
                                    onClick = { loadQrTracking() },
                                    modifier = Modifier.weight(1f),
                                ) { Text("Refresh") }
                            }
                        }
                    }

                    SummaryCard {
                        Text("Recent scans", fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(8.dp))
                        if (result.recentViews.isEmpty()) {
                            Text("No recent scans yet.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        } else {
                            result.recentViews.forEach { view ->
                                OutlinedCard(modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                        Text(view.viewedAt.ifBlank { "Unknown time" }, fontWeight = FontWeight.Medium)
                                        Text(view.device.ifBlank { "Unknown device" }, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        if (view.referrer.isNotBlank()) {
                                            Text(view.referrer, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
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

    private fun submitForgotPassword() {
        val email = forgotPasswordEmail.trim()
        if (email.isBlank()) { forgotPasswordMessage = "Please enter your email address."; return }
        setLoading(true, "Sending reset link...")
        worker.execute {
            try {
                val result = api.forgotPassword(email)
                runOnUiThread {
                    forgotPasswordSent    = true
                    forgotPasswordMessage = result.message
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    forgotPasswordMessage = friendlyMessage(t.message, "Could not send reset email. Check your connection.")
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
                    if (currentSection == NavSection.HEALTH_SUMMARY) {
                        loadHealthSummary()
                    }
                    setLoading(false, "Dashboard updated.")
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    val msg = friendlyMessage(e.message, "Could not refresh dashboard.")
                    // Only wipe the stored token on explicit auth rejection (401).
                    // Network errors, 5xx, etc. should not log the user out — they may
                    // just be a temporary server issue (e.g. Render waking from sleep).
                    if (e.statusCode == 401) {
                        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
                        showLoggedOut(friendlyMessage(e.message, "Session expired. Please sign in again."))
                    } else if (keepSignedInOnFailure) {
                        statusMessage = msg
                    } else {
                        prefs.edit().remove(KEY_CACHE).commit()
                        showLoggedOut(msg)
                    }
                    setLoading(false, msg)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    val msg = friendlyMessage(t.message, "Could not refresh dashboard.")
                    // Non-API exceptions are network/parse errors — keep stored token.
                    if (keepSignedInOnFailure) { statusMessage = msg }
                    else {
                        prefs.edit().remove(KEY_CACHE).commit()
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

    private fun loadTrainingProgram() {
        val token = currentToken ?: return
        trainProgMessage = "Loading..."
        worker.execute {
            try {
                val result = api.trainingProgram(token)
                runOnUiThread {
                    trainProgResult  = result
                    trainProgMessage = ""
                    if (trainProgExpandedCategories.isEmpty() && result.items.isNotEmpty()) {
                        trainProgExpandedCategories = setOf(result.items.first().category)
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread { trainProgMessage = friendlyMessage(t.message, "Could not load training ladder.") }
            }
        }
    }

    private fun seedTrainingProgram() {
        val token = currentToken ?: return
        setLoading(true, "Loading starter ladder...")
        worker.execute {
            try {
                api.seedTrainingProgram(token)
                runOnUiThread { setLoading(false, "Ladder loaded."); loadTrainingProgram() }
            } catch (t: Throwable) {
                runOnUiThread { setLoading(false, ""); trainProgMessage = friendlyMessage(t.message, "Could not load template.") }
            }
        }
    }

    private fun updateTrainingProgramItem(itemId: Int, status: String) {
        val token = currentToken ?: return
        worker.execute {
            try {
                api.updateTrainingItem(token, itemId, status)
                runOnUiThread { loadTrainingProgram() }
            } catch (t: Throwable) {
                runOnUiThread { trainProgMessage = friendlyMessage(t.message, "Could not update item.") }
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

    private fun loadHealthSummary() {
        val token = currentToken ?: return
        healthIsLoading = true
        healthMessage = ""
        worker.execute {
            try {
                val result = api.getHealthSummary(token)
                runOnUiThread {
                    healthSummary = result
                    healthMessage = ""
                    healthIsLoading = false
                    isPullingToRefresh = false
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    healthSummary = null
                    healthIsLoading = false
                    healthMessage = friendlyMessage(t.message, "Could not load health summary.")
                    isPullingToRefresh = false
                }
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

    private fun loadQrTracking() {
        val token = currentToken ?: return
        qrIsLoading = true
        qrMessage   = "Loading..."
        worker.execute {
            try {
                val result = api.getQrTracking(token)
                val bitmap = result?.publicUrl?.takeIf { it.isNotBlank() }?.let { publicUrl ->
                    val encoded = URLEncoder.encode(publicUrl, "UTF-8")
                    val connection = URL("https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=$encoded").openConnection() as HttpURLConnection
                    try {
                        connection.connectTimeout = 10_000
                        connection.readTimeout = 10_000
                        connection.doInput = true
                        connection.requestMethod = "GET"
                        connection.inputStream.use { BitmapFactory.decodeStream(it) }
                    } finally {
                        connection.disconnect()
                    }
                }
                runOnUiThread {
                    qrResult = result
                    qrBitmap = bitmap
                    qrIsLoading = false
                    qrMessage = if (result == null) "No active dog." else ""
                    isPullingToRefresh = false
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    qrIsLoading = false
                    isPullingToRefresh = false
                    qrMessage = friendlyMessage(t.message, "Could not load QR tracking.")
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

        val mediaUri  = logMediaUri
        val mediaMime = logMediaMime
        val mediaName = logMediaName

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
                if (mediaUri != null && response.logId != null) {
                    runCatching {
                        api.uploadLogMedia(
                            token           = token,
                            logId           = response.logId,
                            attachment      = GuidePawFeedbackAttachmentInput(mediaUri, mediaName, mediaMime),
                            contentResolver = contentResolver,
                        )
                    }
                }
                runOnUiThread {
                    trainMessage   = response.message ?: "Training log saved."
                    logNotes       = ""
                    selectedSkills = emptySet()
                    logMediaUri    = null
                    logMediaMime   = ""
                    logMediaName   = ""
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

    private fun submitDocUpload() {
        val token = currentToken ?: return
        val uri   = docPickUri ?: return
        if (docPickTitle.isBlank()) { docPickMessage = "Document title is required."; return }

        docPickLoading = true
        docPickMessage = ""
        worker.execute {
            try {
                api.uploadHealthDocument(
                    token           = token,
                    docType         = docPickType,
                    title           = docPickTitle,
                    providerName    = docPickProvider,
                    notes           = docPickNotes,
                    attachment      = GuidePawFeedbackAttachmentInput(uri, docPickName, docPickMime),
                    contentResolver = contentResolver,
                )
                runOnUiThread {
                    docPickLoading  = false
                    docPickMessage  = "Document uploaded."
                    docPickUri      = null
                    docPickMime     = ""
                    docPickName     = ""
                    docPickTitle    = ""
                    docPickProvider = ""
                    docPickNotes    = ""
                    docShowForm     = false
                    loadHealthDocs()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { docPickLoading = false; docPickMessage = friendlyMessage(e.message, "Upload failed.") }
            } catch (t: Throwable) {
                runOnUiThread { docPickLoading = false; docPickMessage = friendlyMessage(t.message, "Upload failed.") }
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
                        dateOfBirth     = o.optText("dateOfBirth"),
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
                    .put("dateOfBirth", dog.dateOfBirth ?: JSONObject.NULL)
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

    // ── Dog Profile section ───────────────────────────────────────────────────
    @Composable
    private fun DogProfileSection() {
        val scope = rememberCoroutineScope()
        var profile   by remember { mutableStateOf<GpDogProfile?>(null) }
        var loading   by remember { mutableStateOf(true) }
        var saving    by remember { mutableStateOf(false) }
        var errorMsg  by remember { mutableStateOf("") }
        var successMsg by remember { mutableStateOf("") }

        var name      by remember { mutableStateOf("") }
        var breed     by remember { mutableStateOf("") }
        var chipNum   by remember { mutableStateOf("") }
        var weightStr by remember { mutableStateOf("") }
        var dob       by remember { mutableStateOf("") }
        var notes     by remember { mutableStateOf("") }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { errorMsg = "Not signed in."; loading = false; return@LaunchedEffect }
            try {
                val p = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getDogProfile(token) }
                profile   = p
                name      = p.name
                breed     = p.breed
                chipNum   = p.chipNumber
                weightStr = if (p.weightLbs != null) "${"%.1f".format(p.weightLbs)}" else ""
                dob       = p.dateOfBirth
                notes     = p.notes
            } catch (t: Throwable) {
                errorMsg = friendlyMessage(t.message, "Could not load dog profile.")
            } finally {
                loading = false
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🪪 Dog Profile", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            if (loading) {
                Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                return@Column
            }
            SectionMessage(errorMsg)

            if (successMsg.isNotBlank()) {
                Text(successMsg, color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(horizontal = 4.dp))
            }

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedTextField(value = name, onValueChange = { name = it }, label = { Text("Name") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = breed, onValueChange = { breed = it }, label = { Text("Breed") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = chipNum, onValueChange = { chipNum = it }, label = { Text("Microchip Number") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = weightStr, onValueChange = { weightStr = it }, label = { Text("Weight (lbs)") }, modifier = Modifier.fillMaxWidth(), singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.Decimal))
                    OutlinedTextField(value = dob, onValueChange = { dob = it }, label = { Text("Date of Birth (YYYY-MM-DD)") }, placeholder = { Text("e.g. 2020-06-15") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = notes, onValueChange = { notes = it }, label = { Text("Notes") }, modifier = Modifier.fillMaxWidth(), minLines = 3)

                    OutlinedButton(
                        onClick  = { currentSection = NavSection.PUBLIC_DOG_PROFILE },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("🐾 View Public Profile") }

                    Button(
                        onClick = {
                            val token = currentToken ?: return@Button
                            if (name.isBlank()) { errorMsg = "Name is required."; return@Button }
                            saving = true; errorMsg = ""; successMsg = ""
                            scope.launch {
                                try {
                                    val msg = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                        api.updateDogProfile(
                                            token            = token,
                                            name             = name.trim(),
                                            breed            = breed.trim(),
                                            chipNumber       = chipNum.trim(),
                                            weightLbs        = weightStr.trim().toFloatOrNull(),
                                            dateOfBirth      = dob.trim(),
                                            birthIsApproximate = false,
                                            notes            = notes.trim(),
                                        )
                                    }
                                    successMsg = msg
                                    currentDogs = currentDogs.map { d ->
                                        if (d.id == (profile?.id ?: 0)) d.copy(name = name.trim()) else d
                                    }
                                } catch (t: Throwable) {
                                    errorMsg = friendlyMessage(t.message, "Could not save.")
                                } finally {
                                    saving = false
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled  = !saving,
                    ) {
                        if (saving) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(8.dp)) }
                        Text("Save Changes")
                    }
                }
            }
        }
    }

    // ── Public Dog Profile section ────────────────────────────────────────────
    @Composable
    private fun PublicDogProfileSection() {
        val context = LocalContext.current
        val scope   = rememberCoroutineScope()
        var profile  by remember { mutableStateOf<GpPublicDogProfile?>(null) }
        var loading  by remember { mutableStateOf(true) }
        var errorMsg by remember { mutableStateOf("") }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { errorMsg = "Not signed in."; loading = false; return@LaunchedEffect }
            try {
                profile = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getPublicProfile(token) }
            } catch (t: Throwable) {
                errorMsg = friendlyMessage(t.message, "Could not load public profile.")
            } finally {
                loading = false
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.DOG_PROFILE }) { Text("← Back") }
                Text("🐾 Public Profile", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            if (loading) { Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }; return@Column }
            SectionMessage(errorMsg)

            profile?.let { p ->
                // Identity card
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(p.name, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                        if (p.breed.isNotBlank()) Text(p.breed, style = MaterialTheme.typography.bodyMedium, color = GpOnSurfaceVariant)
                        if (p.accessRole.isNotBlank()) Text(p.accessRole.replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
                        if (p.supportBadge.isNotBlank()) Text(p.supportBadge, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                    }
                }

                // Handler contact
                if (p.handlerName.isNotBlank() || p.handlerPhone.isNotBlank() || p.handlerEmail.isNotBlank()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("Handler Contact", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                            if (p.handlerName.isNotBlank()) Text(p.handlerName, style = MaterialTheme.typography.bodyMedium)
                            if (p.handlerPhone.isNotBlank()) TextButton(onClick = { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${p.handlerPhone}"))) }, contentPadding = PaddingValues(0.dp)) { Text(p.handlerPhone, style = MaterialTheme.typography.bodySmall) }
                            if (p.handlerEmail.isNotBlank()) TextButton(onClick = { openWebPage("mailto:${p.handlerEmail}") }, contentPadding = PaddingValues(0.dp)) { Text(p.handlerEmail, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }

                // Backup contact
                if (p.backupContactName.isNotBlank() || p.backupContactPhone.isNotBlank()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("Backup Contact", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                            if (p.backupContactName.isNotBlank()) Text(p.backupContactName, style = MaterialTheme.typography.bodyMedium)
                            if (p.backupContactPhone.isNotBlank()) TextButton(onClick = { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${p.backupContactPhone}"))) }, contentPadding = PaddingValues(0.dp)) { Text(p.backupContactPhone, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }

                // Critical allergies (highlighted)
                if (p.criticalAllergies.isNotBlank()) {
                    OutlinedCard(modifier = Modifier.fillMaxWidth(), border = BorderStroke(1.5.dp, MaterialTheme.colorScheme.error)) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("⚠️ Critical Allergies", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.error)
                            Text(p.criticalAllergies, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }

                // Service tasks
                if (p.serviceTasks.isNotBlank()) {
                    SummaryCard {
                        Text("Service Tasks", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                        Spacer(Modifier.height(4.dp))
                        Text(p.serviceTasks, style = MaterialTheme.typography.bodySmall)
                    }
                }

                // Found dog instructions
                if (p.foundDogInstructions.isNotBlank()) {
                    SummaryCard {
                        Text("If Found", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                        Spacer(Modifier.height(4.dp))
                        Text(p.foundDogInstructions, style = MaterialTheme.typography.bodySmall)
                    }
                }

                // Public notes
                if (p.publicNotes.isNotBlank()) {
                    SummaryCard {
                        Text("Public Notes", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                        Spacer(Modifier.height(4.dp))
                        Text(p.publicNotes, style = MaterialTheme.typography.bodySmall)
                    }
                }

                // Actions
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick  = { openWebPage(p.qrUrl) },
                        modifier = Modifier.weight(1f),
                    ) { Text("View QR Code") }
                    Button(
                        onClick  = {
                            val share = Intent(Intent.ACTION_SEND).apply { type = "text/plain"; putExtra(Intent.EXTRA_TEXT, p.publicUrl) }
                            context.startActivity(Intent.createChooser(share, "Share public profile"))
                        },
                        modifier = Modifier.weight(1f),
                    ) { Text("Share Profile") }
                }
            }
        }
    }

    // ── Settings section ─────────────────────────────────────────────────────
    @Composable
    private fun SettingsSection() {
        val scope = rememberCoroutineScope()

        var pwdCurrent  by remember { mutableStateOf("") }
        var pwdNew      by remember { mutableStateOf("") }
        var pwdConfirm  by remember { mutableStateOf("") }
        var pwdSaving   by remember { mutableStateOf(false) }
        var pwdMsg      by remember { mutableStateOf("") }
        var pwdError    by remember { mutableStateOf("") }

        var tokens      by remember { mutableStateOf<List<GpApiToken>>(emptyList()) }
        var tokensLoading by remember { mutableStateOf(true) }
        var tokensError by remember { mutableStateOf("") }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { tokensError = "Not signed in."; tokensLoading = false; return@LaunchedEffect }
            try {
                tokens = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getApiTokens(token) }
            } catch (t: Throwable) {
                tokensError = friendlyMessage(t.message, "Could not load tokens.")
            } finally {
                tokensLoading = false
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("⚙️ Settings", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            // Change password
            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Change Password", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    OutlinedTextField(value = pwdCurrent, onValueChange = { pwdCurrent = it }, label = { Text("Current password") }, modifier = Modifier.fillMaxWidth(), singleLine = true, visualTransformation = androidx.compose.ui.text.input.PasswordVisualTransformation())
                    OutlinedTextField(value = pwdNew, onValueChange = { pwdNew = it }, label = { Text("New password") }, modifier = Modifier.fillMaxWidth(), singleLine = true, visualTransformation = androidx.compose.ui.text.input.PasswordVisualTransformation())
                    OutlinedTextField(value = pwdConfirm, onValueChange = { pwdConfirm = it }, label = { Text("Confirm new password") }, modifier = Modifier.fillMaxWidth(), singleLine = true, visualTransformation = androidx.compose.ui.text.input.PasswordVisualTransformation())
                    if (pwdError.isNotBlank()) Text(pwdError, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                    if (pwdMsg.isNotBlank()) Text(pwdMsg, color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.bodySmall)
                    Button(
                        onClick = {
                            val token = currentToken ?: return@Button
                            pwdError = ""; pwdMsg = ""
                            if (pwdCurrent.isBlank() || pwdNew.isBlank()) { pwdError = "All fields required."; return@Button }
                            if (pwdNew != pwdConfirm) { pwdError = "New passwords don't match."; return@Button }
                            if (pwdNew.length < 8) { pwdError = "New password must be at least 8 characters."; return@Button }
                            pwdSaving = true
                            scope.launch {
                                try {
                                    val msg = withContext(kotlinx.coroutines.Dispatchers.IO) { api.changePassword(token, pwdCurrent, pwdNew) }
                                    pwdMsg = msg; pwdCurrent = ""; pwdNew = ""; pwdConfirm = ""
                                } catch (t: Throwable) {
                                    pwdError = friendlyMessage(t.message, "Could not change password.")
                                } finally {
                                    pwdSaving = false
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled  = !pwdSaving,
                    ) {
                        if (pwdSaving) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(8.dp)) }
                        Text("Update Password")
                    }
                }
            }

            // API tokens
            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("API Tokens", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    Text("Tokens grant access to your account. Revoke any you don't recognize.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    if (tokensError.isNotBlank()) Text(tokensError, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                    if (tokensLoading) {
                        CircularProgressIndicator(modifier = Modifier.size(20.dp).align(Alignment.CenterHorizontally), strokeWidth = 2.dp)
                    } else if (tokens.isEmpty()) {
                        Text("No tokens found.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    } else {
                        tokens.forEach { tok ->
                            HorizontalDivider()
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(tok.label, style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.SemiBold)
                                    Text("${tok.prefix}… · ${if (tok.isActive) "Active" else "Revoked"}", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                    if (tok.lastUsedAt != null) Text("Last used: ${tok.lastUsedAt}", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                }
                                if (tok.isActive) {
                                    OutlinedButton(
                                        onClick = {
                                            val token = currentToken ?: return@OutlinedButton
                                            scope.launch {
                                                try {
                                                    withContext(kotlinx.coroutines.Dispatchers.IO) { api.revokeApiToken(token, tok.id) }
                                                    tokens = tokens.map { t -> if (t.id == tok.id) t.copy(isActive = false) else t }
                                                } catch (t: Throwable) {
                                                    tokensError = friendlyMessage(t.message, "Could not revoke.")
                                                }
                                            }
                                        },
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                                    ) { Text("Revoke", style = MaterialTheme.typography.labelSmall) }
                                }
                            }
                        }
                    }
                }
            }

            // Sign out
            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text("Account", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick  = { signOut("Signed out.") },
                        colors   = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error),
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Sign Out") }
                }
            }
        }
    }

    // ── AI Training Assistant section ─────────────────────────────────────────
    @Composable
    private fun AiAssistantSection() {
        val scope = rememberCoroutineScope()

        var topics   by remember { mutableStateOf<List<GpAssistantTopic>>(emptyList()) }
        var loading  by remember { mutableStateOf(true) }
        var errorMsg by remember { mutableStateOf("") }

        var selectedTopic by remember { mutableStateOf("general") }
        var issue         by remember { mutableStateOf("") }
        var context       by remember { mutableStateOf("") }
        var whatTried     by remember { mutableStateOf("") }
        var safetyFlags   by remember { mutableStateOf("") }
        var running       by remember { mutableStateOf(false) }
        var plan          by remember { mutableStateOf<GpTrainingPlan?>(null) }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { errorMsg = "Not signed in."; loading = false; return@LaunchedEffect }
            try {
                topics = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getAssistantTopics(token) }
            } catch (t: Throwable) {
                errorMsg = friendlyMessage(t.message, "Could not load topics.")
            } finally {
                loading = false
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🤖 AI Coach", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }
            Text("Describe what's going wrong and get a focused next-step plan.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)

            if (loading) { Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }; return@Column }
            SectionMessage(errorMsg)

            if (plan != null) {
                val p = plan!!
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("${p.icon} ${p.title}", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                        Text(p.summary, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                        if (p.safety.isNotEmpty()) {
                            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Text("⚠️ Safety flags", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.error)
                                    p.safety.forEach { Text("• $it", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error) }
                                }
                            }
                        }
                        if (p.nextSteps.isNotEmpty()) {
                            Text("Next steps", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                            p.nextSteps.forEachIndexed { i, s -> Text("${i + 1}. $s", style = MaterialTheme.typography.bodySmall) }
                        }
                        if (p.avoid.isNotEmpty()) {
                            Text("Avoid", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.error)
                            p.avoid.forEach { Text("✗ $it", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error) }
                        }
                        if (p.followUp.isNotEmpty()) {
                            Text("Follow-up questions", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                            p.followUp.forEach { Text("? $it", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                        }
                        OutlinedButton(onClick = { plan = null; issue = ""; context = ""; whatTried = ""; safetyFlags = "" }, modifier = Modifier.fillMaxWidth()) { Text("Start Over") }
                    }
                }
                return@Column
            }

            // Topic chips
            if (topics.isNotEmpty()) {
                @OptIn(ExperimentalLayoutApi::class)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    topics.forEach { t ->
                        FilterChip(
                            selected = selectedTopic == t.key,
                            onClick  = { selectedTopic = t.key },
                            label    = { Text("${t.icon} ${t.label}", style = MaterialTheme.typography.labelSmall) },
                        )
                    }
                }
            }

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(value = issue, onValueChange = { issue = it }, label = { Text("Describe the issue *") }, placeholder = { Text("What is happening?") }, modifier = Modifier.fillMaxWidth(), minLines = 3)
                    OutlinedTextField(value = context, onValueChange = { context = it }, label = { Text("Training context") }, placeholder = { Text("e.g. busy store, home, outdoors") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = whatTried, onValueChange = { whatTried = it }, label = { Text("What have you tried?") }, modifier = Modifier.fillMaxWidth(), minLines = 2)
                    OutlinedTextField(value = safetyFlags, onValueChange = { safetyFlags = it }, label = { Text("Safety concerns (optional)") }, placeholder = { Text("e.g. bite history, fear response") }, modifier = Modifier.fillMaxWidth(), minLines = 2)

                    Button(
                        onClick = {
                            if (issue.isBlank()) { errorMsg = "Describe the issue first."; return@Button }
                            val token = currentToken ?: return@Button
                            running = true; errorMsg = ""
                            scope.launch {
                                try {
                                    plan = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                        api.runTrainingAssistant(token, selectedTopic, issue, context, whatTried, safetyFlags)
                                    }
                                } catch (t: Throwable) {
                                    errorMsg = friendlyMessage(t.message, "Could not get guidance.")
                                } finally {
                                    running = false
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled  = !running,
                    ) {
                        if (running) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(8.dp)) }
                        Text("Get Guidance")
                    }
                }
            }
        }
    }

    // ── ESA Legal Info section ────────────────────────────────────────────────
    @Composable
    private fun EsaLegalSection() {
        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.STATE_ACCESS }) { Text("← Back") }
                Text("ℹ️ ESA Legal Info", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            data class EsaFaq(val q: String, val a: String)
            val faqs = listOf(
                EsaFaq("What is an ESA?", "An Emotional Support Animal (ESA) is a pet prescribed by a licensed mental health professional to provide therapeutic benefit. ESAs are not service dogs."),
                EsaFaq("Do ESAs have ADA public-access rights?", "No. ESAs do not have ADA public-access rights. They are generally treated like pets in public spaces such as stores, restaurants, and transit."),
                EsaFaq("Can an ESA be denied housing?", "Under the Fair Housing Act, housing providers must provide reasonable accommodations for ESAs in no-pet housing, unless doing so would cause undue hardship or a direct threat."),
                EsaFaq("Are ESAs allowed on airplanes?", "As of 2021, major U.S. airlines are no longer required to accommodate ESAs in the cabin under DOT rules. Most airlines now treat ESAs as regular pets and charge pet fees."),
                EsaFaq("Does an ESA need training?", "No specific task training is required for ESAs. Their presence alone provides the therapeutic benefit. However, basic manners and house training are expected."),
                EsaFaq("What does an ESA letter include?", "A valid ESA letter is written by a licensed mental health professional on official letterhead, states the handler has a disability, and explains how the ESA helps. It does not need to be renewed annually unless the housing provider requires it."),
                EsaFaq("Is there a national ESA registry?", "No. There is no official national registry or certification for ESAs. Websites selling ESA certificates or ID cards have no legal authority."),
                EsaFaq("What is the difference between an ESA and a service dog?", "Service dogs are trained to perform specific tasks for a person with a disability and have broad ADA public-access rights. ESAs provide comfort through presence and have limited legal protections — primarily housing."),
                EsaFaq("Can a landlord ask what disability I have?", "No. A landlord may ask whether you have a disability-related need and whether the animal provides disability-related assistance, but cannot ask for your diagnosis or detailed medical history."),
                EsaFaq("What if my landlord denies my ESA?", "You may file a complaint with HUD (U.S. Department of Housing and Urban Development) or consult a tenant rights attorney if you believe you were unlawfully denied a reasonable accommodation."),
            )

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text("Federal baseline", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                    Text("ESAs are primarily protected by the Fair Housing Act. The ADA does not cover ESAs for public access. State laws vary — some states offer additional housing and employment protections.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                }
            }

            faqs.forEach { faq ->
                var expanded by remember { mutableStateOf(false) }
                OutlinedCard(modifier = Modifier.fillMaxWidth().clickable { expanded = !expanded }) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                            Text(faq.q, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                            Text(if (expanded) "▲" else "▼", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                        }
                        AnimatedVisibility(visible = expanded) {
                            Text(faq.a, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant, modifier = Modifier.padding(top = 8.dp))
                        }
                    }
                }
            }

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("More resources", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text("State-level access laws vary. Check our State Access Laws screen for state-by-state SDIT rules.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(onClick = { currentSection = NavSection.STATE_ACCESS }, modifier = Modifier.fillMaxWidth()) { Text("View State Access Laws") }
                }
            }
        }
    }

    // ── Breed Finder section ──────────────────────────────────────────────────
    @Composable
    private fun BreedQuizSection() {
        val scope = rememberCoroutineScope()

        val sizeOpts       = listOf("any" to "Any size", "tiny" to "Toy / Tiny", "small" to "Small", "medium" to "Medium", "large" to "Large", "giant" to "Giant")
        val energyOpts     = listOf("any" to "Any energy", "low" to "Low / calm", "moderate" to "Moderate", "high" to "High / athletic")
        val purposeOpts    = listOf("any" to "Any purpose", "general" to "General service", "mobility" to "Mobility / brace", "emotional" to "Emotional support", "alert" to "Alert / task work")
        val experienceOpts = listOf("any" to "Any experience", "new" to "New to service dogs", "experienced" to "Experienced handler")

        var size       by remember { mutableStateOf("any") }
        var energy     by remember { mutableStateOf("any") }
        var purpose    by remember { mutableStateOf("any") }
        var experience by remember { mutableStateOf("any") }

        var searching  by remember { mutableStateOf(false) }
        var errorMsg   by remember { mutableStateOf("") }
        var matches    by remember { mutableStateOf<List<GpBreedMatch>?>(null) }
        var expanded   by remember { mutableStateOf<String?>(null) }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🐾 Breed Finder", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }
            Text("Answer a few questions to find breeds that may suit your service dog program.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)

            @Composable
            fun OptionRow(label: String, options: List<Pair<String, String>>, current: String, onSelect: (String) -> Unit) {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(label, style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                    @OptIn(ExperimentalLayoutApi::class)
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        options.forEach { (key, display) ->
                            FilterChip(selected = current == key, onClick = { onSelect(key) }, label = { Text(display, style = MaterialTheme.typography.labelSmall) })
                        }
                    }
                }
            }

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OptionRow("Size preference", sizeOpts, size) { size = it }
                    OptionRow("Energy level", energyOpts, energy) { energy = it }
                    OptionRow("Primary purpose", purposeOpts, purpose) { purpose = it }
                    OptionRow("Your experience", experienceOpts, experience) { experience = it }

                    SectionMessage(errorMsg)

                    Button(
                        onClick = {
                            val token = currentToken ?: return@Button
                            searching = true; errorMsg = ""; expanded = null
                            scope.launch {
                                try {
                                    matches = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                        api.getBreedMatches(token, size, energy, purpose, experience)
                                    }
                                } catch (t: Throwable) {
                                    errorMsg = friendlyMessage(t.message, "Could not fetch breed matches.")
                                } finally {
                                    searching = false
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled  = !searching,
                    ) {
                        if (searching) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(8.dp)) }
                        Text("Find Matching Breeds")
                    }
                }
            }

            matches?.let { list ->
                if (list.isEmpty()) {
                    SectionMessage("No strong matches found. Try broadening your filters.")
                } else {
                    Text("Top ${list.size} matches", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold, color = GpOnSurfaceVariant)
                    list.forEachIndexed { i, m ->
                        val isExpanded = expanded == m.breed
                        OutlinedCard(modifier = Modifier.fillMaxWidth().clickable { expanded = if (isExpanded) null else m.breed }) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text("${i + 1}. ${m.breed}", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                                        Text(m.group, style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                    }
                                    Text(if (isExpanded) "▲" else "▼", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                }
                                AnimatedVisibility(visible = isExpanded) {
                                    Column(modifier = Modifier.padding(top = 8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                        if (m.temperament.isNotBlank()) Text("Temperament: ${m.temperament}", style = MaterialTheme.typography.bodySmall)
                                        if (m.traits.isNotBlank()) Text("Traits: ${m.traits}", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                        if (m.notes.isNotBlank()) { HorizontalDivider(modifier = Modifier.padding(vertical = 4.dp)); Text(m.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant) }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ── FAQ section ───────────────────────────────────────────────────────────
    @Composable
    private fun FaqSection() {
        data class FaqItem(val q: String, val a: String)
        val faqs = listOf(
            FaqItem("What is GuidePaw?", "GuidePaw is a handler-focused platform for dog training logs, public profiles, breed research, and support tools — with a native Android companion app for training, goal intake, behavior tracking, and wearable data."),
            FaqItem("Is there a mobile app?", "Yes. The GuidePaw Companion is a native Android app with training logs, goal intake, habit repair, behavior risk scoring, regression event tracking, and wearable data. All screens are fully native — no browser wrappers."),
            FaqItem("What training tools are built in?", "Goal intake (define training goals with success criteria and a reinforcement plan), habit repair, behavior risk scoring, regression event tracking, candidate assessment, training ladder, community challenges, and the AI Coach for focused guidance."),
            FaqItem("Do I need an account to use public breed tools?", "No. The breed questionnaire, comparison content, and other public pages are readable without signing in. The Breed Finder in the app requires a sign-in for the scored results."),
            FaqItem("How is support different from the free core?", "The free core stays available for the first handler and first dog. Plus and Pro plans unlock additional features and add-ons that help fund ongoing development."),
            FaqItem("What do public dog profiles show?", "Public dog profiles show only the contact and public notes you choose to share, plus the found-dog reporting flow. They do not expose private logs or account history."),
            FaqItem("Does GuidePaw replace ADA.gov or airline rules?", "No. GuidePaw is a practical organizer and reference tool, but you should still rely on official ADA, HUD, DOT, and airline guidance for final rules."),
            FaqItem("Where do housing and access disputes fit?", "Housing requests are different from public access. Use the Housing & Access screen in Laws & Rights for a plain-language summary, then verify with HUD and ADA guidance."),
            FaqItem("How do I change my active dog?", "Open the menu → Dogs. Tap Switch if you have more than one dog, then tap the dog you want to make active."),
            FaqItem("How do I add a new dog?", "Open the menu → Dog & Profile → Add Dog. Enter the dog's name and details and tap Save. The new dog becomes your active dog immediately."),
            FaqItem("What is the AI Coach?", "The AI Coach is a rule-based training advisor. Describe what's going wrong, pick a topic, and receive a focused plan with next steps, things to avoid, and follow-up questions. It requires the Pro plan."),
            FaqItem("How do I report a found dog?", "Scan the QR code on the dog's tag or profile. You'll be taken to the public report page without needing an account."),
        )

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("❓ FAQ", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }
            faqs.forEach { faq ->
                var expanded by remember { mutableStateOf(false) }
                OutlinedCard(modifier = Modifier.fillMaxWidth().clickable { expanded = !expanded }) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text(faq.q, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                            Text(if (expanded) "▲" else "▼", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                        }
                        AnimatedVisibility(visible = expanded) {
                            Text(faq.a, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant, modifier = Modifier.padding(top = 8.dp))
                        }
                    }
                }
            }
        }
    }

    // ── Plans section ─────────────────────────────────────────────────────────
    @Composable
    private fun PlansSection() {
        val scope = rememberCoroutineScope()
        var billing  by remember { mutableStateOf<GpBillingInfo?>(null) }
        var loading  by remember { mutableStateOf(true) }
        var errorMsg by remember { mutableStateOf("") }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { errorMsg = "Not signed in."; loading = false; return@LaunchedEffect }
            try {
                billing = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getBilling(token) }
            } catch (t: Throwable) {
                errorMsg = friendlyMessage(t.message, "Could not load plan info.")
            } finally {
                loading = false
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🏷️ Plans", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            if (loading) { Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }; return@Column }
            SectionMessage(errorMsg)

            billing?.let { b ->
                // Current tier badge
                OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                    Row(modifier = Modifier.padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("Current plan", style = MaterialTheme.typography.labelMedium, color = GpOnSurfaceVariant)
                            Text(b.currentTierLabel, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                        }
                        Badge { Text(b.currentTierLabel) }
                    }
                }

                // Plan comparison cards
                b.plans.forEach { plan ->
                    OutlinedCard(
                        modifier = Modifier.fillMaxWidth(),
                        border   = if (plan.isCurrent) BorderStroke(2.dp, MaterialTheme.colorScheme.primary) else BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                    ) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Text(plan.label, style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                                if (plan.isCurrent) Badge { Text("Current") }
                            }
                            if (plan.summary.isNotBlank()) Text(plan.summary, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                            plan.includedText.forEach { line ->
                                if (line.isNotBlank()) Text("✓ $line", style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }

                // Add-on services
                if (b.services.isNotEmpty()) {
                    Spacer(Modifier.height(4.dp))
                    Text("Add-ons", fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge)
                    b.services.forEach { svc ->
                        val priceLabel = if (svc.priceCents > 0) {
                            val dollars = svc.priceCents / 100
                            val cents   = svc.priceCents % 100
                            "$${dollars}.%02d".format(cents)
                        } else ""
                        OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Text(svc.label, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                                    if (svc.active) Badge { Text("Active") }
                                    else if (priceLabel.isNotBlank()) Text(priceLabel, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(start = 8.dp))
                                }
                                if (svc.summary.isNotBlank()) Text(svc.summary, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                if (!svc.active && svc.checkoutAvailable) {
                                    val scope2 = rememberCoroutineScope()
                                    var buying  by remember { mutableStateOf(false) }
                                    var buyErr  by remember { mutableStateOf("") }
                                    if (buyErr.isNotBlank()) Text(buyErr, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
                                    Button(
                                        onClick = {
                                            val token = currentToken ?: return@Button
                                            buying = true; buyErr = ""
                                            scope2.launch {
                                                try {
                                                    val url = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                                        api.startBillingCheckout(token, "service", serviceSlug = svc.slug)
                                                    }
                                                    if (url.isNotBlank()) openWebPage(url)
                                                    else buyErr = "Checkout unavailable."
                                                } catch (t: Throwable) {
                                                    buyErr = friendlyMessage(t.message, "Could not start checkout.")
                                                } finally { buying = false }
                                            }
                                        },
                                        modifier = Modifier.fillMaxWidth(),
                                        enabled  = !buying,
                                    ) {
                                        if (buying) { CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(6.dp)) }
                                        Text(if (priceLabel.isNotBlank()) "Buy — $priceLabel" else svc.actionLabel)
                                    }
                                }
                            }
                        }
                    }
                }

                // Upgrade plan tier
                if (b.currentTier != "pro") {
                    OutlinedButton(
                        onClick  = { currentSection = NavSection.PLANS },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Upgrade Plan") }
                }
            }
        }
    }

    // ── Trainer Marketplace section ───────────────────────────────────────────
    @Composable
    private fun TrainerMarketplaceSection() {
        val scope    = rememberCoroutineScope()
        var trainers by remember { mutableStateOf<List<GpTrainerEntry>?>(null) }
        var loading  by remember { mutableStateOf(true) }
        var errorMsg by remember { mutableStateOf("") }
        var query    by remember { mutableStateOf("") }
        var expanded by remember { mutableStateOf<String?>(null) }

        LaunchedEffect(Unit) {
            val token = currentToken ?: run { errorMsg = "Not signed in."; loading = false; return@LaunchedEffect }
            try {
                trainers = withContext(kotlinx.coroutines.Dispatchers.IO) { api.getTrainerMarketplace(token) }
            } catch (t: Throwable) {
                errorMsg = friendlyMessage(t.message, "Could not load trainers.")
            } finally {
                loading = false
            }
        }

        val filtered = trainers?.filter { t ->
            if (query.isBlank()) true
            else {
                val hay = "${t.trainerName} ${t.businessName} ${t.credentials} ${t.trainingFocus} ${t.dogs.joinToString()}".lowercase()
                hay.contains(query.lowercase())
            }
        }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("🤝 My Trainers", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }
            Text("Trainers associated with your dogs via dog training profiles.", style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)

            if (loading) { Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }; return@Column }
            SectionMessage(errorMsg)

            if (!trainers.isNullOrEmpty()) {
                OutlinedTextField(value = query, onValueChange = { query = it }, label = { Text("Search trainers") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
            }

            when {
                errorMsg.isNotBlank() -> { /* shown above */ }
                trainers.isNullOrEmpty() -> SectionMessage("No trainer entries found. Add trainer info in your dog's training profile on the web.")
                filtered.isNullOrEmpty() -> SectionMessage("No trainers match \"$query\".")
                else -> filtered!!.forEach { t ->
                    val key = "${t.trainerName}|${t.businessName}"
                    val isExpanded = expanded == key
                    OutlinedCard(modifier = Modifier.fillMaxWidth().clickable { expanded = if (isExpanded) null else key }) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(t.trainerName.ifBlank { t.businessName.ifBlank { "Unnamed trainer" } }, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
                                    if (t.businessName.isNotBlank() && t.businessName != t.trainerName) Text(t.businessName, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                    if (t.dogs.isNotEmpty()) Text(t.dogs.joinToString(", "), style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                                }
                                Text(if (isExpanded) "▲" else "▼", style = MaterialTheme.typography.labelSmall, color = GpOnSurfaceVariant)
                            }
                            AnimatedVisibility(visible = isExpanded) {
                                Column(modifier = Modifier.padding(top = 8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    if (t.credentials.isNotBlank()) Text("Credentials: ${t.credentials}", style = MaterialTheme.typography.bodySmall)
                                    if (t.trainingFocus.isNotBlank()) Text("Focus: ${t.trainingFocus}", style = MaterialTheme.typography.bodySmall)
                                    if (t.phone.isNotBlank()) {
                                        HorizontalDivider(modifier = Modifier.padding(vertical = 4.dp))
                                        TextButton(onClick = { openWebPage("tel:${t.phone}") }, modifier = Modifier.fillMaxWidth()) { Text("📞 ${t.phone}") }
                                    }
                                    if (t.email.isNotBlank()) {
                                        TextButton(onClick = { openWebPage("mailto:${t.email}") }, modifier = Modifier.fillMaxWidth()) { Text("✉️ ${t.email}") }
                                    }
                                    if (t.website.isNotBlank()) {
                                        TextButton(onClick = { openWebPage(t.website) }, modifier = Modifier.fillMaxWidth()) { Text("🌐 Website") }
                                    }
                                    if (t.notes.isNotBlank()) Text(t.notes, style = MaterialTheme.typography.bodySmall, color = GpOnSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Add Dog section ───────────────────────────────────────────────────────
    @Composable
    private fun AddDogSection() {
        val scope = rememberCoroutineScope()
        var name    by remember { mutableStateOf("") }
        var breed   by remember { mutableStateOf("") }
        var dob     by remember { mutableStateOf("") }
        var notes   by remember { mutableStateOf("") }
        var saving  by remember { mutableStateOf(false) }
        var errorMsg by remember { mutableStateOf("") }

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.DOGS }) { Text("← Back") }
                Text("➕ Add Dog", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(start = 4.dp))
            }

            SectionMessage(errorMsg)

            OutlinedCard(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedTextField(value = name, onValueChange = { name = it }, label = { Text("Dog's name *") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = breed, onValueChange = { breed = it }, label = { Text("Breed") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = dob, onValueChange = { dob = it }, label = { Text("Date of birth (YYYY-MM-DD)") }, placeholder = { Text("e.g. 2021-03-10") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(value = notes, onValueChange = { notes = it }, label = { Text("Notes") }, modifier = Modifier.fillMaxWidth(), minLines = 2)

                    Button(
                        onClick = {
                            val token = currentToken ?: return@Button
                            if (name.isBlank()) { errorMsg = "Dog name is required."; return@Button }
                            saving = true; errorMsg = ""
                            scope.launch {
                                try {
                                    val (newId, updatedDogs) = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                        api.createDog(token, name.trim(), breed.trim(), dob.trim(), notes.trim())
                                    }
                                    currentDogs = updatedDogs
                                    currentActiveDogId = newId
                                    currentSection = NavSection.DOGS
                                } catch (t: Throwable) {
                                    errorMsg = friendlyMessage(t.message, "Could not add dog.")
                                } finally {
                                    saving = false
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled  = !saving,
                    ) {
                        if (saving) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary); Spacer(Modifier.width(8.dp)) }
                        Text("Add Dog")
                    }
                }
            }
        }
    }

    // ── Registration section ─────────────────────────────────────────────────
    @Composable
    private fun RegisterSection() {
        val scope = rememberCoroutineScope()
        var showPassword by remember { mutableStateOf(false) }
        val usStates = remember {
            listOf("AL","AK","AZ","AR","CA","CO","CT","DE","FL","GA","HI","ID","IL","IN","IA","KS",
                "KY","LA","ME","MD","MA","MI","MN","MS","MO","MT","NE","NV","NH","NJ","NM","NY",
                "NC","ND","OH","OK","OR","PA","RI","SC","SD","TN","TX","UT","VT","VA","WA","WV","WI","WY",
                "DC","PR","GU","VI","AS","MP")
        }
        var stateExpanded by remember { mutableStateOf(false) }

        Column(
            modifier            = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { currentSection = NavSection.OVERVIEW }) { Text("← Back") }
                Text("Create Account", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            }
            Text(
                "Create your handler profile. Dog profiles are added after first login.",
                style = MaterialTheme.typography.bodySmall,
                color = GpOnSurfaceVariant,
            )

            if (regMessage.isNotBlank()) {
                OutlinedCard(
                    colors   = CardDefaults.outlinedCardColors(
                        containerColor = if (regIsError) MaterialTheme.colorScheme.errorContainer else GpPrimaryContainer,
                    ),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(
                        regMessage,
                        modifier = Modifier.padding(12.dp),
                        style    = MaterialTheme.typography.bodySmall,
                        color    = if (regIsError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSurface,
                    )
                    if (!regIsError) {
                        Button(
                            onClick  = { regMessage = ""; currentSection = NavSection.OVERVIEW },
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp),
                        ) { Text("Go to Login") }
                    }
                }
            }

            OutlinedTextField(
                value         = regFullName,
                onValueChange = { regFullName = it },
                label         = { Text("Handler full name") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
            )
            OutlinedTextField(
                value         = regStreet,
                onValueChange = { regStreet = it },
                label         = { Text("Street address") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
            )
            OutlinedTextField(
                value         = regApt,
                onValueChange = { regApt = it },
                label         = { Text("Apt / Suite (optional)") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value         = regCity,
                    onValueChange = { regCity = it },
                    label         = { Text("City") },
                    modifier      = Modifier.weight(1f),
                    singleLine    = true,
                )
                ExposedDropdownMenuBox(
                    expanded        = stateExpanded,
                    onExpandedChange = { stateExpanded = it },
                    modifier        = Modifier.width(100.dp),
                ) {
                    OutlinedTextField(
                        value         = regState,
                        onValueChange = {},
                        readOnly      = true,
                        label         = { Text("State") },
                        trailingIcon  = { ExposedDropdownMenuDefaults.TrailingIcon(stateExpanded) },
                        modifier      = Modifier.menuAnchor(),
                        singleLine    = true,
                    )
                    ExposedDropdownMenu(
                        expanded        = stateExpanded,
                        onDismissRequest = { stateExpanded = false },
                    ) {
                        usStates.forEach { code ->
                            DropdownMenuItem(
                                text    = { Text(code) },
                                onClick = { regState = code; stateExpanded = false },
                            )
                        }
                    }
                }
                OutlinedTextField(
                    value         = regZip,
                    onValueChange = { regZip = it },
                    label         = { Text("ZIP") },
                    modifier      = Modifier.width(100.dp),
                    singleLine    = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                )
            }
            OutlinedTextField(
                value         = regPhone,
                onValueChange = { regPhone = it },
                label         = { Text("Phone number") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            )
            OutlinedTextField(
                value         = regEmail,
                onValueChange = { regEmail = it },
                label         = { Text("Email / login") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
            )
            OutlinedTextField(
                value         = regPassword,
                onValueChange = { regPassword = it },
                label         = { Text("Password (min 10 chars)") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
                visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                trailingIcon  = {
                    TextButton(onClick = { showPassword = !showPassword }, contentPadding = PaddingValues(horizontal = 8.dp)) {
                        Text(if (showPassword) "Hide" else "Show", style = MaterialTheme.typography.labelSmall)
                    }
                },
            )
            OutlinedTextField(
                value         = regConfirm,
                onValueChange = { regConfirm = it },
                label         = { Text("Confirm password") },
                modifier      = Modifier.fillMaxWidth(),
                singleLine    = true,
                visualTransformation = PasswordVisualTransformation(),
                supportingText = if (regConfirm.isNotBlank()) {
                    {
                        Text(
                            if (regPassword == regConfirm) "Passwords match." else "Passwords do not match.",
                            color = if (regPassword == regConfirm) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error,
                        )
                    }
                } else null,
            )

            Button(
                onClick  = {
                    if (regPassword != regConfirm) {
                        regMessage = "Passwords do not match."; regIsError = true; return@Button
                    }
                    regLoading = true; regMessage = ""; regIsError = false
                    scope.launch {
                        try {
                            val result = withContext(kotlinx.coroutines.Dispatchers.IO) {
                                api.register(
                                    fullName    = regFullName.trim(),
                                    homeStreet  = regStreet.trim(),
                                    homeApt     = regApt.trim(),
                                    homeCity    = regCity.trim(),
                                    homeState   = regState.trim(),
                                    homeZip     = regZip.trim(),
                                    phone       = regPhone.trim(),
                                    email       = regEmail.trim(),
                                    password    = regPassword,
                                )
                            }
                            regIsError  = !result.success
                            regMessage  = result.message ?: if (result.success) "Account created! You can now log in." else "Registration failed."
                            if (result.success && result.token != null) {
                                currentToken = result.token
                                regMessage = ""; regIsError = false
                                regFullName = ""; regStreet = ""; regApt = ""; regCity = ""
                                regState = ""; regZip = ""; regPhone = ""; regEmail = ""
                                regPassword = ""; regConfirm = ""
                                refreshDashboard(result.token, null)
                                currentSection = NavSection.OVERVIEW
                            }
                        } catch (t: Throwable) {
                            regIsError = true
                            regMessage = friendlyMessage(t.message, "Registration failed. Please try again.")
                        } finally {
                            regLoading = false
                        }
                    }
                },
                enabled  = !regLoading,
                modifier = Modifier.fillMaxWidth(),
            ) {
                if (regLoading) {
                    CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Spacer(Modifier.width(8.dp))
                }
                Text("Create Account")
            }
        }
    }

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
        private const val KEY_TRUCKING_MODE        = "trucking_mode"
        private const val KEY_TRUCKING_NOTES       = "trucking_notes"
        private const val KEY_CHALLENGE_KEY        = "challenge_key"
        private const val KEY_CHALLENGE_CHECK_INS  = "challenge_check_ins"
        private const val KEY_CHALLENGE_NOTES      = "challenge_notes"
        private const val KEY_SEEN_WHATS_NEW        = "seen_whats_new_code"

        private data class ChangelogEntry(val versionName: String, val date: String, val title: String, val items: List<String>)
        private val CHANGELOG = listOf(
            ChangelogEntry("0.087", "May 27, 2026", "Rotation fix", listOf(
                "Rotating the phone no longer triggers a data refresh.",
            )),
            ChangelogEntry("0.086", "May 27, 2026", "Native registration, log detail & UI cleanup", listOf(
                "Create Account opens a native sign-up form.",
                "Tap any training log to see full notes and focus indicator.",
                "Upgrade Plan routes to in-app Plans screen.",
            )),
            ChangelogEntry("0.085", "May 27, 2026", "What's New dialog", listOf(
                "App now shows a What's New summary after each update.",
                "Web changelog and API endpoint added.",
            )),
            ChangelogEntry("0.084", "May 27, 2026", "Phone-first uploads", listOf(
                "Gallery, Take Photo, and Record Video on training logs.",
                "Native health document upload — PDF and images without a browser.",
            )),
            ChangelogEntry("0.083", "May 27, 2026", "Public Dog Profile & native billing", listOf(
                "Public Dog Profile viewer — share ID, contacts, and found-dog info.",
                "Native Stripe checkout for add-on services.",
                "Removed remaining web-only action buttons.",
            )),
            ChangelogEntry("0.082", "May 27, 2026", "Brand header", listOf(
                "GuidePaw logo and tagline shown at the top of every screen.",
            )),
            ChangelogEntry("0.081", "May 27, 2026", "Navigation fix", listOf(
                "Training Programs opens natively instead of launching a browser.",
            )),
            ChangelogEntry("0.080", "May 27, 2026", "State Access Laws restored", listOf(
                "Full state-by-state access law requirements reinstated.",
            )),
            ChangelogEntry("0.079", "May 26, 2026", "FAQ, Add Dog, Plans & Trainer Marketplace", listOf(
                "Native FAQ, Add Dog wizard, Plans tier cards.",
                "Trainer Marketplace listings in-app.",
            )),
            ChangelogEntry("0.078", "May 26, 2026", "Five new native screens", listOf(
                "Dog Profile editor, Settings, AI Coach, ESA Legal info, Breed Finder.",
            )),
            ChangelogEntry("0.077", "May 26, 2026", "Find a Vet", listOf(
                "Manual location entry with up to 250-mile route coverage.",
            )),
            ChangelogEntry("0.073", "May 26, 2026", "Collapsible grouped menu", listOf(
                "Navigation reorganised into collapsible sections.",
            )),
            ChangelogEntry("0.070", "May 26, 2026", "State Access Laws", listOf(
                "State-by-state service dog and ESA access rules.",
            )),
            ChangelogEntry("0.060", "May 25, 2026", "First public release", listOf(
                "Training logs, goal intake, behavior risk, regression tracking.",
                "Wearables, medications, appointments, and notifications.",
            )),
        )
    }
}
