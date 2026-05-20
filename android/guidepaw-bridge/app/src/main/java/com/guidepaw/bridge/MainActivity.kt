package com.guidepaw.bridge

import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.CheckBox
import android.widget.ImageView
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.SeekBar
import android.widget.Switch
import android.widget.Spinner
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.health.connect.client.PermissionController
import androidx.health.connect.client.permission.HealthPermission
import androidx.health.connect.client.records.HeartRateRecord
import androidx.health.connect.client.records.StepsRecord
import androidx.lifecycle.lifecycleScope
import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.sync.ApiResult
import com.guidepaw.bridge.sync.GuidePawApiClient
import com.guidepaw.bridge.sync.GuidePawSyncScheduler
import com.guidepaw.bridge.sync.HealthConnectRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.DateFormat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MainActivity : AppCompatActivity() {
    private lateinit var prefs: BridgePreferences
    private lateinit var endpointInput: EditText
    private lateinit var usernameInput: EditText
    private lateinit var passwordInput: EditText
    private lateinit var totpInput: EditText
    private lateinit var recoveryInput: EditText
    private lateinit var tokenLabelInput: EditText
    private lateinit var tokenInput: EditText
    private lateinit var dogIdInput: EditText
    private lateinit var dogNameInput: EditText
    private lateinit var sourceInput: EditText
    private lateinit var statusText: TextView
    private lateinit var lastSyncText: TextView
    private lateinit var accountText: TextView
    private lateinit var dogsText: TextView
    private lateinit var dogsListContainer: LinearLayout
    private lateinit var trainingSuggestionsText: TextView
    private lateinit var trainingLogsSummaryText: TextView
    private lateinit var trainingLogDetailText: TextView
    private lateinit var trainingLogModeText: TextView
    private lateinit var trainingLogDateInput: EditText
    private lateinit var trainingLogLocationInput: EditText
    private lateinit var trainingLogCityStateInput: EditText
    private lateinit var trainingLogNotesInput: EditText
    private lateinit var trainingLogTypeSpinner: Spinner
    private lateinit var trainingLogFocusSeek: SeekBar
    private lateinit var trainingLogFocusValueText: TextView
    private lateinit var trainingSkillsContainer: LinearLayout
    private lateinit var trainingLogsListContainer: LinearLayout
    private lateinit var saveTrainingLogButton: Button
    private lateinit var clearTrainingLogButton: Button
    private lateinit var publicProfileSummaryText: TextView
    private lateinit var publicProfileDetailText: TextView
    private lateinit var publicProfileStatusText: TextView
    private lateinit var publicProfileQrImage: ImageView
    private lateinit var publicProfileRefreshButton: Button
    private lateinit var publicProfileShareButton: Button
    private lateinit var publicProfileOpenButton: Button
    private lateinit var publicReportOpenButton: Button
    private lateinit var publicReportSubmitButton: Button
    private lateinit var foundLocationInput: EditText
    private lateinit var foundNameInput: EditText
    private lateinit var foundPhoneInput: EditText
    private lateinit var foundMessageInput: EditText
    private lateinit var billingSummaryText: TextView
    private lateinit var billingStatusText: TextView
    private lateinit var billingRefreshButton: Button
    private lateinit var billingSupportOnceButton: Button
    private lateinit var billingSupportMonthlyButton: Button
    private lateinit var billingPlanContainer: LinearLayout
    private lateinit var billingServiceContainer: LinearLayout
    private lateinit var billingSupportHistoryContainer: LinearLayout
    private lateinit var billingPurchaseHistoryContainer: LinearLayout
    private lateinit var autoSyncSwitch: Switch
    private val trainingSkillOptions = listOf(
        "Focus / Watch me",
        "Loose leash",
        "Settle",
        "Recall",
        "Task work",
        "CGC prep",
        "Sit/Stay",
        "Heel",
        "Leave It",
        "Under Tuck",
        "DPT Task",
        "PA Focus",
    )
    private val trainingLocationTypes = listOf(
        "In-Cab",
        "Truck Stop",
        "Shipper/Receiver",
        "Public Store",
        "Rest Area",
        "Other",
    )
    private var selectedTrainingLogId: Long? = null
    private var selectedTrainingSkills: LinkedHashSet<String> = linkedSetOf()
    private var currentPublicProfile: com.guidepaw.bridge.model.PublicProfileOverview? = null
    private var currentBillingOverview: com.guidepaw.bridge.model.BillingOverview? = null

    private val requiredPermissions = setOf(
        HealthPermission.getReadPermission(StepsRecord::class),
        HealthPermission.getReadPermission(HeartRateRecord::class),
        HealthPermission.PERMISSION_READ_HEALTH_DATA_IN_BACKGROUND,
    )

    private val permissionLauncher = registerForActivityResult(
        PermissionController.createRequestPermissionResultContract()
    ) { granted ->
        val hasAll = granted.containsAll(requiredPermissions)
        updateStatus(
            if (hasAll) "Health Connect access granted."
            else "Health Connect permissions are incomplete."
        )
        if (hasAll && pendingSyncAfterPermission) {
            pendingSyncAfterPermission = false
            syncNow()
        }
    }

    private var pendingSyncAfterPermission = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        prefs = BridgePreferences(this)
        bindViews()
        wireButtons()
        loadStoredConfig()
        handleIntent(intent)
        refreshLastSync()
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleIntent(intent)
    }

    private fun bindViews() {
        endpointInput = findViewById(R.id.endpointInput)
        usernameInput = findViewById(R.id.usernameInput)
        passwordInput = findViewById(R.id.passwordInput)
        totpInput = findViewById(R.id.totpInput)
        recoveryInput = findViewById(R.id.recoveryInput)
        tokenLabelInput = findViewById(R.id.tokenLabelInput)
        tokenInput = findViewById(R.id.tokenInput)
        dogIdInput = findViewById(R.id.dogIdInput)
        dogNameInput = findViewById(R.id.dogNameInput)
        sourceInput = findViewById(R.id.sourceInput)
        statusText = findViewById(R.id.statusText)
        lastSyncText = findViewById(R.id.lastSyncText)
        accountText = findViewById(R.id.accountText)
        dogsText = findViewById(R.id.dogsText)
        dogsListContainer = findViewById(R.id.dogsListContainer)
        trainingSuggestionsText = findViewById(R.id.trainingSuggestionsText)
        trainingLogsSummaryText = findViewById(R.id.trainingLogsSummaryText)
        trainingLogDetailText = findViewById(R.id.trainingLogDetailText)
        trainingLogModeText = findViewById(R.id.trainingLogModeText)
        trainingLogDateInput = findViewById(R.id.trainingLogDateInput)
        trainingLogLocationInput = findViewById(R.id.trainingLogLocationInput)
        trainingLogCityStateInput = findViewById(R.id.trainingLogCityStateInput)
        trainingLogNotesInput = findViewById(R.id.trainingLogNotesInput)
        trainingLogTypeSpinner = findViewById(R.id.trainingLogTypeSpinner)
        trainingLogFocusSeek = findViewById(R.id.trainingLogFocusSeek)
        trainingLogFocusValueText = findViewById(R.id.trainingLogFocusValueText)
        trainingSkillsContainer = findViewById(R.id.trainingSkillsContainer)
        trainingLogsListContainer = findViewById(R.id.trainingLogsListContainer)
        saveTrainingLogButton = findViewById(R.id.saveTrainingLogButton)
        clearTrainingLogButton = findViewById(R.id.clearTrainingLogButton)
        publicProfileSummaryText = findViewById(R.id.publicProfileSummaryText)
        publicProfileDetailText = findViewById(R.id.publicProfileDetailText)
        publicProfileStatusText = findViewById(R.id.publicProfileStatusText)
        publicProfileQrImage = findViewById(R.id.publicProfileQrImage)
        publicProfileRefreshButton = findViewById(R.id.publicProfileRefreshButton)
        publicProfileShareButton = findViewById(R.id.publicProfileShareButton)
        publicProfileOpenButton = findViewById(R.id.publicProfileOpenButton)
        publicReportOpenButton = findViewById(R.id.publicReportOpenButton)
        publicReportSubmitButton = findViewById(R.id.publicReportSubmitButton)
        foundLocationInput = findViewById(R.id.foundLocationInput)
        foundNameInput = findViewById(R.id.foundNameInput)
        foundPhoneInput = findViewById(R.id.foundPhoneInput)
        foundMessageInput = findViewById(R.id.foundMessageInput)
        billingSummaryText = findViewById(R.id.billingSummaryText)
        billingStatusText = findViewById(R.id.billingStatusText)
        billingRefreshButton = findViewById(R.id.billingRefreshButton)
        billingSupportOnceButton = findViewById(R.id.billingSupportOnceButton)
        billingSupportMonthlyButton = findViewById(R.id.billingSupportMonthlyButton)
        billingPlanContainer = findViewById(R.id.billingPlanContainer)
        billingServiceContainer = findViewById(R.id.billingServiceContainer)
        billingSupportHistoryContainer = findViewById(R.id.billingSupportHistoryContainer)
        billingPurchaseHistoryContainer = findViewById(R.id.billingPurchaseHistoryContainer)
        autoSyncSwitch = findViewById(R.id.autoSyncSwitch)
    }

    private fun wireButtons() {
        findViewById<Button>(R.id.loginButton).setOnClickListener { loginAndSaveToken() }
        findViewById<Button>(R.id.saveButton).setOnClickListener { saveConfigFromForm() }
        findViewById<Button>(R.id.refreshAccountButton).setOnClickListener { refreshAccountSummary() }
        findViewById<Button>(R.id.refreshLogsButton).setOnClickListener { refreshTrainingLogs() }
        findViewById<Button>(R.id.requestAccessButton).setOnClickListener { requestHealthConnectAccess() }
        findViewById<Button>(R.id.syncNowButton).setOnClickListener { syncNow() }
        findViewById<Button>(R.id.openBridgeButton).setOnClickListener { openBridgeLink() }
        findViewById<Button>(R.id.manageAccessButton).setOnClickListener { openHealthConnectSettings() }
        publicProfileRefreshButton.setOnClickListener { refreshPublicProfile() }
        publicProfileShareButton.setOnClickListener { sharePublicProfileLink() }
        publicProfileOpenButton.setOnClickListener { openPublicProfile() }
        publicReportOpenButton.setOnClickListener { openFoundDogReportPage() }
        publicReportSubmitButton.setOnClickListener { submitFoundDogReport() }
        billingRefreshButton.setOnClickListener { refreshBillingOverview() }
        billingSupportOnceButton.setOnClickListener { startSupportCheckout("one_time") }
        billingSupportMonthlyButton.setOnClickListener { startSupportCheckout("monthly") }
        saveTrainingLogButton.setOnClickListener { saveTrainingLogEntry() }
        clearTrainingLogButton.setOnClickListener { clearTrainingLogEditor() }
        autoSyncSwitch.setOnCheckedChangeListener { _, enabled ->
            prefs.setAutoSyncEnabled(enabled)
            GuidePawSyncScheduler.schedule(this, enabled)
            updateStatus(if (enabled) "Automatic sync enabled every 6 hours." else "Automatic sync disabled.")
        }
        trainingLogFocusSeek.max = 4
        trainingLogFocusSeek.progress = 2
        trainingLogFocusSeek.setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
            override fun onProgressChanged(seekBar: SeekBar?, progress: Int, fromUser: Boolean) {
                trainingLogFocusValueText.text = "Focus level: ${progress + 1}/5"
            }

            override fun onStartTrackingTouch(seekBar: SeekBar?) = Unit
            override fun onStopTrackingTouch(seekBar: SeekBar?) = Unit
        })
        populateTrainingLocationTypes()
        populateTrainingSkillOptions()
        trainingLogFocusValueText.text = "Focus level: 3/5"
    }

    private fun handleIntent(intent: Intent?) {
        val data: Uri = intent?.data ?: return
        if (data.scheme != "guidepawbridge" || data.host != "pair") return

        val endpoint = data.getQueryParameter("endpoint").orEmpty()
        val token = data.getQueryParameter("token").orEmpty()
        val dogId = data.getQueryParameter("dog_id").orEmpty()
        val dogName = data.getQueryParameter("dog_name").orEmpty()
        val source = data.getQueryParameter("source").orEmpty()

        if (endpoint.isNotBlank()) endpointInput.setText(endpoint)
        if (token.isNotBlank()) tokenInput.setText(token)
        if (dogId.isNotBlank()) dogIdInput.setText(dogId)
        if (dogName.isNotBlank()) dogNameInput.setText(dogName)
        if (source.isNotBlank()) sourceInput.setText(source)

        saveConfigFromForm(showMessage = false)
        updateStatus("Pairing link loaded. Save the pairing code, then grant Health Connect access.")
    }

    private fun loadStoredConfig() {
        prefs.load()?.let { config ->
            endpointInput.setText(config.endpoint)
            tokenInput.setText(config.token)
            dogIdInput.setText(config.dogId.toString())
            dogNameInput.setText(config.dogName)
            sourceInput.setText(config.source)
            refreshAccountSummary()
        } ?: run {
            accountText.text = getString(R.string.account_summary_empty)
            dogsText.text = getString(R.string.dogs_summary_empty)
            renderTrainingLogs(null, null)
            renderPublicProfile(null)
        }
        autoSyncSwitch.isChecked = prefs.isAutoSyncEnabled()
        refreshLastSync()
    }

    private fun loginAndSaveToken() {
        val endpoint = endpointInput.text.toString().trim()
        val username = usernameInput.text.toString().trim()
        val password = passwordInput.text.toString()
        val totpCode = totpInput.text.toString().trim()
        val recoveryKey = recoveryInput.text.toString().trim()
        val tokenLabel = tokenLabelInput.text.toString().trim().ifBlank { "Android Companion" }

        if (endpoint.isBlank() || username.isBlank() || password.isBlank()) {
            updateStatus("Enter the API endpoint, username, and password.")
            return
        }

        lifecycleScope.launch {
            updateStatus("Signing in to GuidePaw...")
            val apiBase = endpoint.substringBefore("/api/", endpoint).trimEnd('/')
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().login(apiBase, username, password, tokenLabel, totpCode, recoveryKey)
            }

            when (result) {
                is ApiResult.Success -> {
                    val session = result.data
                    val currentDogId = dogIdInput.text.toString().trim().toLongOrNull() ?: 0L
                    val currentDogName = dogNameInput.text.toString().trim()
                    val currentSource = sourceInput.text.toString().trim().ifBlank { "health_connect" }
                    prefs.save(
                        BridgeConfig(
                            endpoint = endpoint,
                            token = session.token,
                            dogId = currentDogId,
                            dogName = currentDogName,
                            source = currentSource,
                        )
                    )
                    tokenInput.setText(session.token)
                    updateStatus("Signed in as ${session.username}. Token expires ${session.expiresAt}.")
                    refreshAccountSummary()
                }
                is ApiResult.Failure -> {
                    updateStatus("Sign-in failed: ${result.message}")
                }
            }
        }
    }

    private fun saveConfigFromForm(showMessage: Boolean = true) {
        val endpoint = endpointInput.text.toString().trim()
        val token = tokenInput.text.toString().trim()
        val dogId = dogIdInput.text.toString().trim().toLongOrNull() ?: 0L
        val dogName = dogNameInput.text.toString().trim()
        val source = sourceInput.text.toString().trim().ifBlank { "health_connect" }

        if (endpoint.isBlank() || token.isBlank() || dogId <= 0L) {
            if (showMessage) updateStatus("Enter the endpoint, pairing code, and dog ID.")
            return
        }

        prefs.save(
            BridgeConfig(
                endpoint = endpoint,
                token = token,
                dogId = dogId,
                dogName = dogName,
                source = source,
            )
        )

        if (showMessage) updateStatus("Pairing saved. You can request access and sync now.")
        refreshAccountSummary()
    }

    private fun requestHealthConnectAccess() {
        pendingSyncAfterPermission = false
        permissionLauncher.launch(requiredPermissions)
    }

    private fun syncNow() {
        val config = prefs.load()
        if (config == null) {
            updateStatus("Save the pairing code first.")
            return
        }
        if (config.dogId <= 0L) {
            updateStatus("Choose a dog before syncing.")
            return
        }

        lifecycleScope.launch {
            val granted = runCatching {
                withContext(Dispatchers.IO) {
                    val hc = androidx.health.connect.client.HealthConnectClient.getOrCreate(this@MainActivity)
                    hc.permissionController.getGrantedPermissions().containsAll(requiredPermissions)
                }
            }.getOrDefault(false)

            if (!granted) {
                pendingSyncAfterPermission = true
                permissionLauncher.launch(requiredPermissions)
                return@launch
            }

            updateStatus("Reading Health Connect data...")
            val snapshot = withContext(Dispatchers.IO) {
                HealthConnectRepository(this@MainActivity).buildTodaySnapshot()
            }
            val uploadResult = withContext(Dispatchers.IO) {
                GuidePawApiClient().postSnapshot(config, snapshot)
            }
            when (uploadResult) {
                is com.guidepaw.bridge.sync.UploadResult.Success -> {
                    prefs.setLastSyncAt(System.currentTimeMillis())
                    refreshLastSync()
                    updateStatus("Synced successfully.")
                    refreshAccountSummary()
                }
                is com.guidepaw.bridge.sync.UploadResult.Failure -> updateStatus("Sync failed: ${uploadResult.message}")
            }
        }
    }

    private fun refreshAccountSummary() {
        val config = prefs.load()
        if (config == null) {
            accountText.text = getString(R.string.account_summary_empty)
            dogsText.text = getString(R.string.dogs_summary_empty)
            renderDogsList(null, emptyList())
            renderTrainingLogs(null, null)
            renderPublicProfile(null)
            renderBillingOverview(null)
            return
        }

        lifecycleScope.launch {
            updateStatus("Loading GuidePaw account summary...")
            val accountResult = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchAccountOverview(config)
            }
            val dogsResult = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchAccessibleDogs(config)
            }

            when (accountResult) {
                is ApiResult.Success -> {
                    val account = accountResult.data
                    accountText.text = getString(
                        R.string.account_summary_loaded,
                        account.username,
                        account.dbDriver,
                        account.schemaVersion,
                    )
                }
                is ApiResult.Failure -> {
                    accountText.text = getString(R.string.account_summary_error, accountResult.message)
                }
            }

            when (dogsResult) {
                is ApiResult.Success -> {
                    val overview = dogsResult.data
                    val dogs = overview.dogs
                    dogsText.text = if (dogs.isEmpty()) {
                        getString(R.string.dogs_summary_none)
                    } else {
                        buildString {
                            append(getString(R.string.dogs_summary_loaded, dogs.size))
                            dogs.take(5).forEach { dog ->
                                append('\n')
                                append("• ")
                                append(dog.name)
                                if (dog.breed.isNotBlank()) {
                                    append(" — ")
                                    append(dog.breed)
                                }
                                append(" (")
                                append(dog.accessRole)
                                append(")")
                            }
                        }
                    }
                    renderDogsList(overview.activeDogId.takeIf { it > 0L }, dogs)
                }
                is ApiResult.Failure -> {
                    dogsText.text = getString(R.string.dogs_summary_error, dogsResult.message)
                    renderDogsList(null, emptyList())
                    renderPublicProfile(null)
                }
            }

            if ((prefs.load()?.dogId ?: 0L) <= 0L && dogsResult is ApiResult.Success && dogsResult.data.dogs.isNotEmpty()) {
                val firstDog = dogsResult.data.dogs.first()
                dogIdInput.setText(firstDog.id.toString())
                dogNameInput.setText(firstDog.name)
                saveConfigFromForm(showMessage = false)
            }

            val activeDogId = when {
                dogsResult is ApiResult.Success && dogsResult.data.activeDogId > 0L -> dogsResult.data.activeDogId
                (prefs.load()?.dogId ?: 0L) > 0L -> prefs.load()?.dogId ?: 0L
                else -> null
            }
            refreshTrainingLogs(activeDogId)
            refreshPublicProfile(activeDogId)
            refreshBillingOverview(activeDogId)
            updateStatus("GuidePaw account summary loaded.")
        }
    }

    private fun openBridgeLink() {
        val config = prefs.load()
        if (config == null) {
            updateStatus("Save the pairing code first.")
            return
        }
        if (config.dogId <= 0L) {
            updateStatus("Choose a dog before opening the pairing page.")
            return
        }
        val uri = Uri.parse(
            "guidepawbridge://pair?endpoint=${Uri.encode(config.endpoint)}&token=${Uri.encode(config.token)}&dog_id=${config.dogId}&dog_name=${Uri.encode(config.dogName)}&source=${Uri.encode(config.source)}"
        )
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW, uri))
        }.onFailure {
            updateStatus("Open the pairing link from the GuidePaw page instead.")
        }
    }

    private fun openHealthConnectSettings() {
        val intent = Intent(android.provider.Settings.ACTION_SETTINGS)
        runCatching { startActivity(intent) }
    }

    private fun renderDogsList(activeDogId: Long?, dogs: List<com.guidepaw.bridge.model.AccessibleDogSummary>) {
        dogsListContainer.removeAllViews()
        if (dogs.isEmpty()) {
            return
        }

        dogs.forEach { dog ->
            val row = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(0, 0, 0, 20)
            }
            val title = TextView(this).apply {
                text = buildString {
                    append(dog.name)
                    if (activeDogId != null && activeDogId == dog.id) append(" • Active")
                }
                textSize = 16f
                setTextColor(resources.getColor(android.R.color.black, theme))
            }
            val meta = TextView(this).apply {
                text = "${dog.breed.ifBlank { "Breed not set" }} • ${dog.accessRole} • ${dog.lifecycleStatus}"
            }
            val button = Button(this).apply {
                text = if (activeDogId != null && activeDogId == dog.id) "Active Dog" else "Use this dog"
                isEnabled = !(activeDogId != null && activeDogId == dog.id)
                setOnClickListener {
                    lifecycleScope.launch {
                        updateStatus("Switching active dog...")
                        val config = prefs.load()
                        if (config == null) {
                            updateStatus("Save the pairing code first.")
                            return@launch
                        }
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().setActiveDog(config, dog.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                dogIdInput.setText(dog.id.toString())
                                dogNameInput.setText(dog.name)
                                saveConfigFromForm(showMessage = false)
                                refreshAccountSummary()
                                updateStatus("Active dog switched to ${dog.name}.")
                            }
                            is ApiResult.Failure -> updateStatus("Could not switch dog: ${result.message}")
                        }
                    }
                }
            }
            row.addView(title)
            row.addView(meta)
            row.addView(button)
            dogsListContainer.addView(row)
        }
    }

    private fun populateTrainingLocationTypes() {
        val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_item, trainingLocationTypes)
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        trainingLogTypeSpinner.adapter = adapter
    }

    private fun populateTrainingSkillOptions() {
        trainingSkillsContainer.removeAllViews()
        trainingSkillOptions.forEach { skill ->
            val checkBox = CheckBox(this).apply {
                text = skill
                setOnCheckedChangeListener { _, isChecked ->
                    if (isChecked) {
                        selectedTrainingSkills.add(skill)
                    } else {
                        selectedTrainingSkills.remove(skill)
                    }
                }
            }
            trainingSkillsContainer.addView(checkBox)
        }
    }

    private fun clearTrainingLogEditor() {
        selectedTrainingLogId = null
        selectedTrainingSkills.clear()
        trainingLogModeText.text = "New log"
        trainingLogDateInput.setText(SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).format(Date()))
        trainingLogLocationInput.setText("")
        trainingLogCityStateInput.setText("")
        trainingLogNotesInput.setText("")
        trainingLogFocusSeek.progress = 2
        trainingLogFocusValueText.text = "Focus level: 3/5"
        trainingLogTypeSpinner.setSelection(trainingLocationTypes.indexOf("Other").coerceAtLeast(0))
        for (i in 0 until trainingSkillsContainer.childCount) {
            val child = trainingSkillsContainer.getChildAt(i)
            if (child is CheckBox) {
                child.isChecked = false
            }
        }
        saveTrainingLogButton.text = "Save log"
    }

    private fun populateTrainingLogEditor(log: com.guidepaw.bridge.model.TrainingLogEntry) {
        selectedTrainingLogId = log.id
        trainingLogModeText.text = "Editing log #${log.id}"
        trainingLogDateInput.setText(formatTrainingLogDateForEditor(log.logDate))
        trainingLogLocationInput.setText(log.locationName)
        trainingLogCityStateInput.setText(log.locationCityState)
        val typeIndex = trainingLocationTypes.indexOf(log.locationType).takeIf { it >= 0 } ?: trainingLocationTypes.indexOf("Other").coerceAtLeast(0)
        trainingLogTypeSpinner.setSelection(typeIndex)
        trainingLogFocusSeek.progress = (log.focusLevel.coerceIn(1, 5) - 1)
        trainingLogFocusValueText.text = "Focus level: ${log.focusLevel.coerceIn(1, 5)}/5"
        trainingLogNotesInput.setText(log.handlerNotes)
        selectedTrainingSkills.clear()
        selectedTrainingSkills.addAll(log.skillsPracticed)
        for (i in 0 until trainingSkillsContainer.childCount) {
            val child = trainingSkillsContainer.getChildAt(i)
            if (child is CheckBox) {
                val skill = child.text?.toString().orEmpty()
                child.isChecked = skill in selectedTrainingSkills
            }
        }
        saveTrainingLogButton.text = "Update log"
    }

    private fun renderTrainingLogs(feed: com.guidepaw.bridge.model.TrainingLogFeed?, selectedLog: com.guidepaw.bridge.model.TrainingLogEntry? = null) {
        trainingLogsListContainer.removeAllViews()
        if (feed == null) {
            trainingSuggestionsText.text = "Training suggestions: sign in and load a dog to see next-step guidance."
            trainingLogsSummaryText.text = "Training logs: save pairing and choose a dog to load history."
            trainingLogDetailText.text = "Select a log to view detail."
            clearTrainingLogEditor()
            return
        }

        val suggestions = feed.trainingSuggestions
        trainingSuggestionsText.text = if (suggestions.isEmpty()) {
            "Training suggestions: no suggestions yet."
        } else {
            buildString {
                append("Training suggestions:\n")
                suggestions.take(5).forEachIndexed { index, suggestion ->
                    append("• ")
                    append(suggestion)
                    if (index < suggestions.size - 1) append('\n')
                }
            }
        }
        trainingLogsSummaryText.text = if (feed.logs.isEmpty()) {
            "Training logs: no logs yet for this dog."
        } else {
            "Training logs: ${feed.logs.size} recent entries loaded."
        }

        if (selectedLog != null) {
            renderTrainingLogDetail(selectedLog, suggestions)
        } else {
            trainingLogDetailText.text = if (feed.logs.isEmpty()) {
                "Select a log to view detail."
            } else {
                "Tap View to read a log detail, or Edit to load one into the form."
            }
        }

        if (feed.logs.isEmpty()) {
            return
        }

        feed.logs.forEach { log ->
            val row = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(0, 0, 0, 20)
            }
            val title = TextView(this).apply {
                text = log.locationName
                textSize = 16f
                setTextColor(resources.getColor(android.R.color.black, theme))
            }
            val meta = TextView(this).apply {
                text = buildString {
                    append(formatTrainingLogDateForDisplay(log.logDate))
                    if (log.locationCityState.isNotBlank()) {
                        append(" • ")
                        append(log.locationCityState)
                    }
                    if (log.locationType.isNotBlank()) {
                        append(" • ")
                        append(log.locationType)
                    }
                    append(" • Focus ")
                    append(log.focusLevel)
                    append("/5")
                }
            }
            val note = TextView(this).apply {
                text = if (log.handlerNotes.isBlank()) "No notes saved." else log.handlerNotes
                maxLines = 2
            }
            val buttons = LinearLayout(this).apply {
                orientation = LinearLayout.HORIZONTAL
            }
            val viewButton = Button(this).apply {
                text = "View"
                setOnClickListener {
                    lifecycleScope.launch {
                        val config = prefs.load()
                        if (config == null) {
                            updateStatus("Save the pairing code first.")
                            return@launch
                        }
                        updateStatus("Loading log detail...")
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().fetchTrainingLogDetail(config, log.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                renderTrainingLogs(feed, result.data)
                                updateStatus("Training log loaded.")
                            }
                            is ApiResult.Failure -> updateStatus("Could not load log: ${result.message}")
                        }
                    }
                }
            }
            val editButton = Button(this).apply {
                text = "Edit"
                setOnClickListener {
                    populateTrainingLogEditor(log)
                    trainingLogDetailText.text = "Editing log #${log.id}. Save to update it."
                }
            }
            buttons.addView(viewButton)
            buttons.addView(editButton)
            row.addView(title)
            row.addView(meta)
            row.addView(note)
            row.addView(buttons)
            trainingLogsListContainer.addView(row)
        }
    }

    private fun renderTrainingLogDetail(log: com.guidepaw.bridge.model.TrainingLogEntry, suggestions: List<String>) {
        trainingLogDetailText.text = buildString {
            append("Log #")
            append(log.id)
            append('\n')
            append(formatTrainingLogDateForDisplay(log.logDate))
            append('\n')
            append(log.locationName)
            if (log.locationCityState.isNotBlank()) {
                append(" • ")
                append(log.locationCityState)
            }
            if (log.locationType.isNotBlank()) {
                append(" • ")
                append(log.locationType)
            }
            append("\nFocus ")
            append(log.focusLevel)
            append("/5")
            if (log.skillsPracticed.isNotEmpty()) {
                append("\nSkills: ")
                append(log.skillsPracticed.joinToString(", "))
            }
            if (log.handlerNotes.isNotBlank()) {
                append("\n\n")
                append(log.handlerNotes)
            }
            if (suggestions.isNotEmpty()) {
                append("\n\nNext steps:")
                suggestions.take(3).forEach { suggestion ->
                    append("\n• ")
                    append(suggestion)
                }
            }
        }
    }

    private fun refreshTrainingLogs(explicitDogId: Long? = null) {
        val config = prefs.load()
        if (config == null) {
            renderTrainingLogs(null, null)
            return
        }
        val dogId = explicitDogId ?: config.dogId
        if (dogId <= 0L) {
            renderTrainingLogs(null, null)
            return
        }

        lifecycleScope.launch {
            updateStatus("Loading training logs...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchTrainingLogs(config, dogId)
            }
            when (result) {
                is ApiResult.Success -> {
                    renderTrainingLogs(result.data)
                    updateStatus("Training logs loaded.")
                }
                is ApiResult.Failure -> {
                    trainingSuggestionsText.text = "Training suggestions: could not load."
                    trainingLogsSummaryText.text = "Training logs: ${result.message}"
                    trainingLogDetailText.text = "Select a log to view detail."
                    trainingLogsListContainer.removeAllViews()
                }
            }
        }
    }

    private fun saveTrainingLogEntry() {
        val config = prefs.load()
        if (config == null) {
            updateStatus("Save the pairing code first.")
            return
        }

        val dogId = config.dogId
        if (dogId <= 0L) {
            updateStatus("Choose a dog before saving logs.")
            return
        }

        val locationName = trainingLogLocationInput.text.toString().trim()
        val cityState = trainingLogCityStateInput.text.toString().trim()
        val dateValue = trainingLogDateInput.text.toString().trim()
        val locationType = trainingLogTypeSpinner.selectedItem?.toString().orEmpty().ifBlank { "Other" }
        val focusLevel = trainingLogFocusSeek.progress + 1
        val notes = trainingLogNotesInput.text.toString().trim()
        if (locationName.isBlank()) {
            updateStatus("Enter a location name first.")
            return
        }

        lifecycleScope.launch {
            updateStatus(if (selectedTrainingLogId == null) "Saving training log..." else "Updating training log...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().saveTrainingLog(
                    config = config,
                    logId = selectedTrainingLogId,
                    dogId = dogId,
                    logDate = dateValue.ifBlank { null },
                    locationName = locationName,
                    locationCityState = cityState,
                    locationType = locationType,
                    focusLevel = focusLevel,
                    skills = selectedTrainingSkills.toList(),
                    handlerNotes = notes,
                )
            }
            when (result) {
                is ApiResult.Success -> {
                    updateStatus(result.data.message)
                    clearTrainingLogEditor()
                    refreshTrainingLogs(dogId)
                }
                is ApiResult.Failure -> updateStatus("Could not save training log: ${result.message}")
            }
        }
    }

    private fun formatTrainingLogDateForEditor(value: String): String {
        if (value.isBlank()) return ""
        return runCatching {
            val parsed = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).parse(value)
            if (parsed != null) SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).format(parsed) else value
        }.getOrDefault(value)
    }

    private fun formatTrainingLogDateForDisplay(value: String): String {
        if (value.isBlank()) return "Unknown time"
        return runCatching {
            val parsed = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).parse(value)
            if (parsed != null) DateFormat.getDateTimeInstance(DateFormat.MEDIUM, DateFormat.SHORT).format(parsed) else value
        }.getOrDefault(value)
    }

    private fun refreshPublicProfile(explicitDogId: Long? = null) {
        val config = prefs.load()
        if (config == null) {
            currentPublicProfile = null
            renderPublicProfile(null)
            return
        }
        val dogId = explicitDogId ?: config.dogId
        if (dogId <= 0L) {
            currentPublicProfile = null
            renderPublicProfile(null)
            return
        }

        lifecycleScope.launch {
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchPublicProfile(config, dogId)
            }
            when (result) {
                is ApiResult.Success -> {
                    currentPublicProfile = result.data
                    renderPublicProfile(result.data)
                }
                is ApiResult.Failure -> {
                    currentPublicProfile = null
                    renderPublicProfile(null)
                    publicProfileSummaryText.text = "Public profile: ${result.message}"
                }
            }
        }
    }

    private fun renderPublicProfile(profile: com.guidepaw.bridge.model.PublicProfileOverview?) {
        if (profile == null) {
            publicProfileSummaryText.text = "Public profile: sign in and choose a dog to see QR/share details."
            publicProfileDetailText.text = "Found-dog reporting is disabled until a public profile is loaded."
            publicProfileStatusText.text = "Status: waiting for profile"
            publicProfileQrImage.setImageDrawable(null)
            return
        }

        val dog = profile.dog
        publicProfileSummaryText.text = buildString {
            append(dog.name)
            if (dog.breed.isNotBlank()) {
                append(" • ")
                append(dog.breed)
            }
            if (dog.accessRole.isNotBlank()) {
                append(" • ")
                append(dog.accessRole)
            }
        }
        publicProfileStatusText.text = if (dog.supportBadge != null) {
            "Support badge: ${dog.supportBadge.label}"
        } else {
            "Support badge: none"
        }
        publicProfileDetailText.text = buildString {
            append("Public profile:\n")
            append(profile.publicUrl)
            append("\n\nQR opens tracked on the website. The QR below matches the live public link.")
            if (dog.handlerName.isNotBlank()) {
                append("\n\nHandler: ")
                append(dog.handlerName)
            }
            if (dog.handlerPhone.isNotBlank()) {
                append("\nPhone: ")
                append(dog.handlerPhone)
            }
            if (dog.handlerEmail.isNotBlank()) {
                append("\nEmail: ")
                append(dog.handlerEmail)
            }
            if (dog.backupContactPhone.isNotBlank()) {
                append("\nBackup: ")
                append(dog.backupContactName.ifBlank { "Backup contact" })
                append(" · ")
                append(dog.backupContactPhone)
            }
            if (dog.homeState.isNotBlank()) {
                append("\nHome state: ")
                append(dog.homeState)
            }
            if (dog.publicNotes.isNotBlank()) {
                append("\n\nPublic notes:\n")
                append(dog.publicNotes)
            }
            if (dog.foundDogInstructions.isNotBlank()) {
                append("\n\nIf found:\n")
                append(dog.foundDogInstructions)
            }
            if (dog.criticalAllergies.isNotBlank()) {
                append("\n\nMedical note:\n")
                append(dog.criticalAllergies)
            }
            if (dog.serviceTasks.isNotBlank()) {
                append("\n\nService tasks:\n")
                append(dog.serviceTasks)
            }
            if (dog.supportBadge != null) {
                append("\n\nSupport badge: ")
                append(dog.supportBadge.label)
                if (!dog.supportBadge.expiresAt.isNullOrBlank()) {
                    append(" until ")
                    append(dog.supportBadge.expiresAt)
                } else if (dog.supportBadge.lifetime) {
                    append(" for life")
                }
            }
        }
        loadQrBitmap(profile.qrUrl)
    }

    private fun loadQrBitmap(url: String) {
        if (url.isBlank()) {
            publicProfileQrImage.setImageDrawable(null)
            return
        }
        lifecycleScope.launch {
            val bitmap = withContext(Dispatchers.IO) {
                runCatching {
                    val connection = java.net.URL(url).openConnection() as java.net.HttpURLConnection
                    connection.connectTimeout = 12000
                    connection.readTimeout = 12000
                    connection.inputStream.use { input ->
                        BitmapFactory.decodeStream(input)
                    }
                }.getOrNull()
            }
            if (bitmap != null) {
                publicProfileQrImage.setImageBitmap(bitmap)
            } else {
                publicProfileQrImage.setImageDrawable(null)
            }
        }
    }

    private fun refreshBillingOverview(explicitDogId: Long? = null) {
        val config = prefs.load()
        if (config == null) {
            renderBillingOverview(null)
            return
        }

        val dogId = explicitDogId ?: config.dogId
        lifecycleScope.launch {
            updateStatus("Loading billing and add-ons...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchBillingOverview(config, dogId.takeIf { it > 0L })
            }
            when (result) {
                is ApiResult.Success -> renderBillingOverview(result.data)
                is ApiResult.Failure -> {
                    renderBillingOverview(null)
                    billingSummaryText.text = "Support, plans, and add-ons: ${result.message}"
                }
            }
        }
    }

    private fun renderBillingOverview(overview: com.guidepaw.bridge.model.BillingOverview?) {
        currentBillingOverview = overview
        billingPlanContainer.removeAllViews()
        billingServiceContainer.removeAllViews()
        billingSupportHistoryContainer.removeAllViews()
        billingPurchaseHistoryContainer.removeAllViews()

        if (overview == null) {
            billingSummaryText.text = "Support, plans, and add-ons: sign in and choose a dog to see billing state."
            billingStatusText.text = "Status: waiting for billing data"
            billingSupportOnceButton.text = "Support once"
            billingSupportOnceButton.isEnabled = false
            billingSupportMonthlyButton.text = "Support monthly"
            billingSupportMonthlyButton.isEnabled = false
            return
        }

        billingSummaryText.text = buildString {
            append("Current plan: ")
            append(overview.currentTierLabel)
            append(" • Dogs: ")
            append(overview.dogCount)
            append(" • ")
            append(if (overview.canCreateAnotherDog) "Can add another dog" else "Dog limit reached")
        }
        billingStatusText.text = buildString {
            append("Active dog ID: ")
            append(if (overview.activeDogId > 0L) overview.activeDogId else "none")
            if (overview.supportBadge != null) {
                append(" • Support badge: ")
                append(overview.supportBadge.label)
            }
        }

        overview.supportOptions.forEach { option ->
            if (option.supportType == "monthly") {
                billingSupportMonthlyButton.text = option.label
                billingSupportMonthlyButton.isEnabled = option.checkoutAvailable
            } else {
                billingSupportOnceButton.text = option.label
                billingSupportOnceButton.isEnabled = option.checkoutAvailable
            }
        }

        overview.planRows.forEach { plan ->
            val row = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(0, 0, 0, 18)
            }
            row.addView(TextView(this).apply {
                text = if (plan.isCurrent) "${plan.label} • Current" else plan.label
                textSize = 16f
                setTextColor(resources.getColor(android.R.color.black, theme))
            })
            row.addView(TextView(this).apply {
                text = plan.summary
            })
            if (plan.includedText.isNotEmpty()) {
                row.addView(TextView(this).apply {
                    text = "Included: ${plan.includedText.joinToString(", ")}"
                })
            }
            if (plan.lockedText.isNotEmpty()) {
                row.addView(TextView(this).apply {
                    text = "Locked below this plan: ${plan.lockedText.joinToString(", ")}"
                })
            }
            billingPlanContainer.addView(row)
        }

        overview.serviceRows.forEach { service ->
            val row = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(0, 0, 0, 20)
            }
            row.addView(TextView(this).apply {
                text = buildString {
                    append(service.label)
                    if (service.active) append(" • Active")
                }
                textSize = 16f
                setTextColor(resources.getColor(android.R.color.black, theme))
            })
            row.addView(TextView(this).apply {
                text = service.summary
            })
            row.addView(TextView(this).apply {
                text = buildString {
                    append("Scope: ")
                    append(service.scope)
                    append(" • Price: $")
                    append(String.format(Locale.US, "%.2f", service.priceCents / 100.0))
                    if (service.notes.isNotBlank()) {
                        append("\n")
                        append(service.notes)
                    }
                    if (service.includedText.isNotEmpty()) {
                        append("\nIncludes: ")
                        append(service.includedText.joinToString(", "))
                    }
                    if (service.lockedText.isNotEmpty()) {
                        append("\nLocked: ")
                        append(service.lockedText.joinToString(", "))
                    }
                }
            })
            val actionButton = Button(this).apply {
                text = when {
                    service.active -> "Already active"
                    service.checkoutAvailable -> service.actionLabel.ifBlank { "Buy now" }
                    service.requiresActiveDog -> "Choose a dog first"
                    else -> "Checkout unavailable"
                }
                isEnabled = service.checkoutAvailable && !service.active
                setOnClickListener {
                    startServiceCheckout(service)
                }
            }
            row.addView(actionButton)
            billingServiceContainer.addView(row)
        }

        if (overview.recentSupportEvents.isEmpty()) {
            billingSupportHistoryContainer.addView(TextView(this).apply {
                text = "No support receipts yet."
            })
        } else {
            overview.recentSupportEvents.forEach { event ->
                billingSupportHistoryContainer.addView(TextView(this).apply {
                    text = buildString {
                        append(event.title)
                        append(" • $")
                        append(String.format(Locale.US, "%.2f", event.amountCents / 100.0))
                        append(" • ")
                        append(event.status.ifBlank { "paid" })
                        if (event.createdAt.isNotBlank()) {
                            append("\n")
                            append(event.createdAt)
                        }
                    }
                })
            }
        }

        if (overview.recentPurchaseEvents.isEmpty()) {
            billingPurchaseHistoryContainer.addView(TextView(this).apply {
                text = "No service purchases yet."
            })
        } else {
            overview.recentPurchaseEvents.forEach { event ->
                billingPurchaseHistoryContainer.addView(TextView(this).apply {
                    text = buildString {
                        append(event.title)
                        append(" • $")
                        append(String.format(Locale.US, "%.2f", event.amountCents / 100.0))
                        append(" • ")
                        append(event.status.ifBlank { "paid" })
                        if (event.createdAt.isNotBlank()) {
                            append("\n")
                            append(event.createdAt)
                        }
                    }
                })
            }
        }
    }

    private fun openCheckoutUrl(url: String, successMessage: String) {
        if (url.isBlank()) {
            updateStatus("Checkout link is missing.")
            return
        }
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            updateStatus(successMessage)
        }.onFailure {
            updateStatus("Could not open checkout.")
        }
    }

    private fun startSupportCheckout(supportType: String) {
        val config = prefs.load() ?: run {
            updateStatus("Save the pairing code first.")
            return
        }
        lifecycleScope.launch {
            updateStatus("Opening support checkout...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().startBillingCheckout(config, "support", supportType = supportType)
            }
            when (result) {
                is ApiResult.Success -> openCheckoutUrl(result.data.checkoutUrl, result.data.message)
                is ApiResult.Failure -> updateStatus("Could not start support checkout: ${result.message}")
            }
        }
    }

    private fun startServiceCheckout(service: com.guidepaw.bridge.model.BillingServiceRow) {
        val config = prefs.load() ?: run {
            updateStatus("Save the pairing code first.")
            return
        }
        if (service.active) {
            updateStatus("${service.label} is already active.")
            return
        }
        val dogId = when {
            service.scope == "dog" -> config.dogId.takeIf { it > 0L }
            else -> null
        }
        if (service.scope == "dog" && dogId == null) {
            updateStatus("Choose a dog before buying this add-on.")
            return
        }

        lifecycleScope.launch {
            updateStatus("Opening ${service.label} checkout...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().startBillingCheckout(
                    config = config,
                    kind = "service",
                    serviceSlug = service.slug,
                    dogId = dogId,
                )
            }
            when (result) {
                is ApiResult.Success -> openCheckoutUrl(result.data.checkoutUrl, result.data.message)
                is ApiResult.Failure -> updateStatus("Could not start checkout: ${result.message}")
            }
        }
    }

    private fun sharePublicProfileLink() {
        val profile = currentPublicProfile ?: run {
            updateStatus("Load a public profile first.")
            return
        }
        val share = Intent(Intent.ACTION_SEND).apply {
            type = "text/plain"
            putExtra(Intent.EXTRA_SUBJECT, "GuidePaw public profile")
            putExtra(Intent.EXTRA_TEXT, profile.publicUrl)
        }
        startActivity(Intent.createChooser(share, "Share public profile"))
    }

    private fun openPublicProfile() {
        val profile = currentPublicProfile ?: run {
            updateStatus("Load a public profile first.")
            return
        }
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(profile.publicUrl)))
        }.onFailure {
            updateStatus("Could not open the public profile link.")
        }
    }

    private fun openFoundDogReportPage() {
        val profile = currentPublicProfile ?: run {
            updateStatus("Load a public profile first.")
            return
        }
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(profile.reportUrl)))
        }.onFailure {
            updateStatus("Could not open the found-dog report link.")
        }
    }

    private fun submitFoundDogReport() {
        val profile = currentPublicProfile ?: run {
            updateStatus("Load a public profile first.")
            return
        }
        val location = foundLocationInput.text.toString().trim()
        val name = foundNameInput.text.toString().trim()
        val phone = foundPhoneInput.text.toString().trim()
        val message = foundMessageInput.text.toString().trim()
        if (location.isBlank() && phone.isBlank()) {
            updateStatus("Enter a location and phone number before sending the report.")
            return
        }
        if (phone.isBlank()) {
            updateStatus("Enter a phone number before sending the report.")
            return
        }

        lifecycleScope.launch {
            updateStatus("Sending found-dog report...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().submitFoundDogReport(
                    reportApiUrl = profile.reportApiUrl,
                    dogId = profile.dogId,
                    token = profile.reportToken,
                    finderLocation = location,
                    finderName = name,
                    finderPhone = phone,
                    finderMessage = message,
                )
            }
            when (result) {
                is ApiResult.Success -> {
                    updateStatus(result.data.message)
                    foundLocationInput.setText("")
                    foundNameInput.setText("")
                    foundPhoneInput.setText("")
                    foundMessageInput.setText("")
                }
                is ApiResult.Failure -> updateStatus("Could not send found-dog report: ${result.message}")
            }
        }
    }

    private fun refreshLastSync() {
        val lastSync = prefs.getLastSyncAt()
        lastSyncText.text = if (lastSync > 0L) {
            "Last sync: " + DateFormat.getDateTimeInstance(DateFormat.MEDIUM, DateFormat.SHORT).format(Date(lastSync))
        } else {
            "Last sync: never"
        }
    }

    private fun updateStatus(message: String) {
        statusText.text = message
    }
}
