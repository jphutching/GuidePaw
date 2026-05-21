package com.guidepaw.companion

import android.content.Intent
import android.content.SharedPreferences
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.AutoCompleteTextView
import android.widget.LinearLayout
import android.widget.SeekBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.button.MaterialButton
import com.google.android.material.button.MaterialButtonToggleGroup
import com.google.android.material.chip.Chip
import com.google.android.material.chip.ChipGroup
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import com.google.android.material.card.MaterialCardView
import com.google.android.material.progressindicator.LinearProgressIndicator
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.Executors

class MainActivity : AppCompatActivity() {
    private val api = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()

    private lateinit var prefs: SharedPreferences
    private lateinit var statusView: TextView
    private lateinit var progressView: LinearProgressIndicator
    private lateinit var menuButton: MaterialButton
    private lateinit var versionBadgeView: TextView
    private lateinit var versionView: TextView

    private lateinit var loginCard: MaterialCardView
    private lateinit var dashboardCard: MaterialCardView
    private lateinit var sectionToggle: MaterialButtonToggleGroup
    private lateinit var loginMessageView: TextView
    private lateinit var twoFactorLayout: TextInputLayout
    private lateinit var recoveryKeyLayout: TextInputLayout
    private lateinit var usernameInput: TextInputEditText
    private lateinit var passwordInput: TextInputEditText
    private lateinit var twoFactorInput: TextInputEditText
    private lateinit var recoveryKeyInput: TextInputEditText

    private lateinit var dashboardSummaryView: TextView
    private lateinit var activeDogSummaryView: TextView
    private lateinit var accountSummaryView: TextView
    private lateinit var suggestionsContainer: LinearLayout
    private lateinit var dogsContainer: LinearLayout
    private lateinit var logsContainer: LinearLayout
    private lateinit var dogsMessageView: TextView
    private lateinit var trainMessageView: TextView
    private lateinit var wearablesBodyView: TextView

    private lateinit var logLocationInput: TextInputEditText
    private lateinit var logCityStateInput: TextInputEditText
    private lateinit var logTypeInput: AutoCompleteTextView
    private lateinit var logTypeLayout: TextInputLayout
    private lateinit var logNotesInput: TextInputEditText
    private lateinit var focusSeekBar: SeekBar
    private lateinit var focusValueView: TextView
    private lateinit var skillChipGroup: ChipGroup
    private lateinit var saveLogButton: MaterialButton

    private var currentToken: String? = null
    private var currentMe: GuidePawMeResult? = null
    private var currentDogs: List<GuidePawDogItem> = emptyList()
    private var currentLogs: List<GuidePawLogItem> = emptyList()
    private var currentSuggestions: List<String> = emptyList()
    private var currentActiveDogId: Int? = null
    private var currentEditingLogId: Int? = null
    private var currentSectionButtonId: Int = R.id.btnOverview

    private val locationTypes = listOf("In-Cab", "Truck Stop", "Shipper/Receiver", "Public Store", "Rest Area", "Other")
    private val skillOptions = listOf(
        "Focus / Watch me",
        "Loose leash",
        "Settle",
        "Recall",
        "Task work",
        "Sit/Stay",
        "Heel",
        "Leave It",
        "Under Tuck",
        "DPT Task",
        "PA Focus",
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        bindViews()
        setupUi()

        val storedToken = prefs.getString(KEY_TOKEN, null)
        if (!storedToken.isNullOrBlank()) {
            currentToken = storedToken
            showSignedInShell("Restoring saved session...")
            restoreCachedDashboard()
            refreshDashboard(storedToken, null)
        } else {
            showLoggedOut("Sign in to load your dogs, logs, and training dashboard.")
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        worker.shutdownNow()
    }

    private fun bindViews() {
        statusView = findViewById(R.id.statusView)
        progressView = findViewById(R.id.progressView)
        menuButton = findViewById(R.id.btnMenu)
        versionBadgeView = findViewById(R.id.versionBadgeView)
        versionView = findViewById(R.id.versionView)

        loginCard = findViewById(R.id.loginCard)
        dashboardCard = findViewById(R.id.dashboardCard)
        sectionToggle = findViewById(R.id.sectionToggle)
        loginMessageView = findViewById(R.id.loginMessageView)

        twoFactorLayout = findViewById(R.id.twoFactorLayout)
        recoveryKeyLayout = findViewById(R.id.recoveryKeyLayout)
        usernameInput = findViewById(R.id.usernameInput)
        passwordInput = findViewById(R.id.passwordInput)
        twoFactorInput = findViewById(R.id.twoFactorInput)
        recoveryKeyInput = findViewById(R.id.recoveryKeyInput)

        dashboardSummaryView = findViewById(R.id.dashboardSummaryView)
        activeDogSummaryView = findViewById(R.id.activeDogSummaryView)
        accountSummaryView = findViewById(R.id.accountSummaryView)
        suggestionsContainer = findViewById(R.id.suggestionsContainer)
        dogsContainer = findViewById(R.id.dogsContainer)
        logsContainer = findViewById(R.id.recentLogsContainer)
        dogsMessageView = findViewById(R.id.dogsMessageView)
        trainMessageView = findViewById(R.id.trainMessageView)
        wearablesBodyView = findViewById(R.id.wearablesBodyView)

        logLocationInput = findViewById(R.id.logLocationInput)
        logCityStateInput = findViewById(R.id.logCityStateInput)
        logTypeInput = findViewById<AutoCompleteTextView>(R.id.logTypeInput)
        logTypeLayout = findViewById(R.id.logTypeLayout)
        logNotesInput = findViewById(R.id.logNotesInput)
        focusSeekBar = findViewById(R.id.focusSeekBar)
        focusValueView = findViewById(R.id.focusValueView)
        skillChipGroup = findViewById(R.id.skillChipGroup)
        saveLogButton = findViewById(R.id.btnSaveLog)
    }

    private fun setupUi() {
        versionView.text = "v0.007"
        versionBadgeView.text = "v0.007"
        val typeAdapter = ArrayAdapter(this, android.R.layout.simple_dropdown_item_1line, locationTypes)
        logTypeInput.setAdapter(typeAdapter)
        logTypeInput.setText(locationTypes.first(), false)

        focusSeekBar.progress = 2
        updateFocusLabel(3)
        focusSeekBar.setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
            override fun onProgressChanged(seekBar: SeekBar?, progress: Int, fromUser: Boolean) {
                updateFocusLabel(progress + 1)
            }

            override fun onStartTrackingTouch(seekBar: SeekBar?) = Unit
            override fun onStopTrackingTouch(seekBar: SeekBar?) = Unit
        })

        skillOptions.forEach { label ->
            skillChipGroup.addView(
                Chip(this).apply {
                    text = label
                    isCheckable = true
                    isClickable = true
                    isChipIconVisible = false
                }
            )
        }

        findViewById<MaterialButton>(R.id.btnSignIn).setOnClickListener { attemptLogin() }
        findViewById<MaterialButton>(R.id.btnRefresh).setOnClickListener { refreshCurrent() }
        findViewById<MaterialButton>(R.id.btnSignOut).setOnClickListener { signOut("Signed out.") }
        saveLogButton.setOnClickListener { submitTrainingLog() }
        menuButton.setOnClickListener { showMenuDialog() }

        findViewById<MaterialButton>(R.id.btnOpenWearablesWeb).setOnClickListener {
            openExternal("https://guidepaw.app/wearable_integrations.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenNotificationsWeb).setOnClickListener {
            openExternal("https://guidepaw.app/notifications.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenAppPage).setOnClickListener {
            openExternal("https://guidepaw.app/app.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenBreedQuestionnaire).setOnClickListener {
            openExternal("https://guidepaw.app/breed_questionnaire.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenFaq).setOnClickListener {
            openExternal("https://guidepaw.app/faq.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenComparisonHub).setOnClickListener {
            openExternal("https://guidepaw.app/breed_comparison_hub.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenFamilyGuide).setOnClickListener {
            openExternal("https://guidepaw.app/breed_family_guide.php")
        }
        findViewById<MaterialButton>(R.id.btnOpenLegalInfo).setOnClickListener {
            openExternal("https://guidepaw.app/service_dog_esa_legal_info.php")
        }

        sectionToggle.addOnButtonCheckedListener { _, checkedId, isChecked ->
            if (isChecked) {
                currentSectionButtonId = checkedId
                showSection(checkedId)
            }
        }

        currentSectionButtonId = R.id.btnOverview
        sectionToggle.check(R.id.btnOverview)
        showSection(R.id.btnOverview)
    }

    private fun attemptLogin() {
        val username = usernameInput.text?.toString()?.trim().orEmpty()
        val password = passwordInput.text?.toString().orEmpty()
        val totpCode = twoFactorInput.text?.toString()?.trim().orEmpty()
        val recoveryKey = recoveryKeyInput.text?.toString()?.trim().orEmpty()

        if (username.isBlank() || password.isBlank()) {
            loginMessageView.text = "Username and password are required."
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
                        showLoggedOut(friendlyMessage(result.message, "Sign in failed."))
                        setLoading(false, null)
                        return@runOnUiThread
                    }
                    saveToken(result.token)
                    showSignedInShell("Signed in. Loading dashboard...")
                    refreshDashboard(result.token, null)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    val payload = e.payload
                    if (e.statusCode == 401 && payload?.optBoolean("requires_2fa", false) == true) {
                        showTwoFactorPrompt(payload.optString("message") ?: "Two-factor authentication is required.")
                    } else {
                        loginMessageView.text = friendlyMessage(e.message, "Sign in failed.")
                        setLoading(false, null)
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    loginMessageView.text = friendlyMessage(t.message, "Sign in failed.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun refreshCurrent() {
        val token = currentToken ?: prefs.getString(KEY_TOKEN, null)
        if (token.isNullOrBlank()) {
            showLoggedOut("Sign in to load the dashboard.")
            return
        }
        refreshDashboard(token, currentActiveDogId, keepSignedInOnFailure = currentMe != null)
    }

    private fun refreshDashboard(token: String, preferDogId: Int?, keepSignedInOnFailure: Boolean = false) {
        setLoading(true, "Refreshing dashboard...")
        worker.execute {
            try {
                val me = api.me(token)
                val dogs = api.dogs(token)
                var activeDogId = preferDogId ?: me.activeDogId ?: dogs.firstOrNull()?.id
                if (activeDogId != null && activeDogId > 0 && activeDogId != me.activeDogId) {
                    activeDogId = api.setActiveDog(token, activeDogId) ?: activeDogId
                }
                val logsResult = api.logs(token, activeDogId)
                runOnUiThread {
                    currentToken = token
                    currentMe = me
                    currentDogs = dogs
                    currentActiveDogId = activeDogId ?: logsResult.activeDogId
                    currentLogs = logsResult.logs
                    currentSuggestions = logsResult.trainingSuggestions
                    saveCachedDashboard()
                    renderDashboard()
                    setLoading(false, "Dashboard updated.")
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    if (keepSignedInOnFailure) {
                        showSignedInShell(friendlyMessage(e.message, "Could not load the full dashboard yet."))
                    } else {
                        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
                        showLoggedOut(friendlyMessage(e.message, "Session expired. Please sign in again."))
                    }
                    val message = friendlyMessage(e.message, "Could not refresh dashboard.")
                    setLoading(false, message)
                    statusView.text = message
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    if (keepSignedInOnFailure) {
                        showSignedInShell(friendlyMessage(t.message, "Could not load the full dashboard yet."))
                    } else {
                        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
                        showLoggedOut(friendlyMessage(t.message, "Could not refresh dashboard."))
                    }
                    val message = friendlyMessage(t.message, "Could not refresh dashboard.")
                    setLoading(false, message)
                    statusView.text = message
                }
            }
        }
    }

    private fun renderDashboard() {
        val me = currentMe ?: return
        val activeDog = currentDogs.firstOrNull { it.id == currentActiveDogId }

        loginCard.visibility = View.GONE
        dashboardCard.visibility = View.VISIBLE
        dashboardSummaryView.text = "Signed in as ${me.username}"
        activeDogSummaryView.text = buildString {
            append("Active dog: ")
            append(activeDog?.name ?: "None selected")
            activeDog?.breed?.let { append(" • ").append(it) }
            if (currentDogs.isNotEmpty()) {
                append(" • ")
                append(currentDogs.size)
                append(" dogs")
            }
        }
        accountSummaryView.text = listOfNotNull(
            me.dbDriver?.let { "DB $it" },
        ).joinToString(" • ").ifBlank { "Account synced." }

        dogsMessageView.text = if (currentDogs.isEmpty()) {
            "No dogs are accessible on this account."
        } else {
            "Tap a dog to make it active. The app uses the active dog for logs and daily tracking."
        }
        wearablesBodyView.text = if (activeDog == null) {
            "Wearable data will feed the active dog timeline once a dog is selected."
        } else {
            "Wearable data is tied to ${activeDog.name} and will flow into the same handler timeline."
        }

        renderSuggestions()
        renderDogs()
        renderLogs()
        updateTrainingForm(activeDog)
        showSection(currentSectionButtonId)
        statusView.text = "Signed in as ${me.username}"
        loginMessageView.text = ""
    }

    private fun renderSuggestions() {
        suggestionsContainer.removeAllViews()
        if (currentSuggestions.isEmpty()) {
            suggestionsContainer.addView(makePlainText("No training suggestions right now."))
            return
        }
        currentSuggestions.forEach { suggestion ->
            suggestionsContainer.addView(makeBulletText(suggestion))
        }
    }

    private fun renderDogs() {
        dogsContainer.removeAllViews()
        if (currentDogs.isEmpty()) {
            dogsContainer.addView(makePlainText("No dogs available on this account."))
            return
        }
        currentDogs.forEach { dog ->
            val active = dog.id == currentActiveDogId
            val card = MaterialCardView(this).apply {
                layoutParams = LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
                ).apply { topMargin = 12 }
                radius = 18f
                cardElevation = 0f
                strokeWidth = 1
                strokeColor = 0xFFE2E8F0.toInt()
            }
            val inner = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(dp(14), dp(14), dp(14), dp(14))
            }
            inner.addView(TextView(this).apply {
                text = dog.name
                setTextColor(0xFF0F172A.toInt())
                textSize = 15f
                setTypeface(typeface, android.graphics.Typeface.BOLD)
            })
            inner.addView(TextView(this).apply {
                text = listOfNotNull(
                    dog.breed,
                    dog.accessRole?.replaceFirstChar { it.uppercase() },
                    dog.lifecycleStatus?.replace('_', ' ')
                ).joinToString(" • ").ifBlank { "Dog record" }
                setTextColor(0xFF334155.toInt())
                textSize = 12f
                setPadding(0, dp(4), 0, 0)
            })
            inner.addView(MaterialButton(this).apply {
                text = if (active) "Active dog" else "Use this dog"
                isEnabled = !active
                layoutParams = LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
                ).apply { topMargin = dp(10) }
                setOnClickListener {
                    setLoading(true, "Switching active dog...")
                    worker.execute {
                        try {
                            val activeDogId = api.setActiveDog(currentToken ?: return@execute, dog.id)
                            runOnUiThread {
                                currentActiveDogId = activeDogId ?: dog.id
                                refreshDashboard(currentToken ?: "", currentActiveDogId)
                            }
                        } catch (e: Throwable) {
                            runOnUiThread {
                                setLoading(false, friendlyMessage(e.message, "Could not switch dog."))
                            }
                        }
                    }
                }
            })
            card.addView(inner)
            dogsContainer.addView(card)
        }
    }

    private fun renderLogs() {
        logsContainer.removeAllViews()
        if (currentLogs.isEmpty()) {
            logsContainer.addView(makePlainText("No training logs yet for the active dog."))
            return
        }
        currentLogs.take(8).forEach { log ->
            val card = MaterialCardView(this).apply {
                layoutParams = LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.MATCH_PARENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
                ).apply { topMargin = dp(12) }
                radius = 18f
                cardElevation = 0f
                strokeWidth = 1
                strokeColor = 0xFFE2E8F0.toInt()
            }
            val inner = LinearLayout(this).apply {
                orientation = LinearLayout.VERTICAL
                setPadding(dp(14), dp(14), dp(14), dp(14))
            }
            inner.addView(TextView(this).apply {
                text = log.locationName.ifBlank { "Training session" }
                setTextColor(0xFF0F172A.toInt())
                textSize = 15f
                setTypeface(typeface, android.graphics.Typeface.BOLD)
            })
            inner.addView(TextView(this).apply {
                text = listOfNotNull(
                    log.logDate.takeIf { it.isNotBlank() },
                    log.locationCityState,
                    log.locationType,
                    "Focus ${log.focusLevel}"
                ).joinToString(" • ")
                setTextColor(0xFF334155.toInt())
                textSize = 12f
                setPadding(0, dp(4), 0, 0)
            })
            if (log.skillsPracticed.isNotEmpty()) {
                inner.addView(TextView(this).apply {
                    text = log.skillsPracticed.joinToString(" · ")
                    setTextColor(0xFF0F172A.toInt())
                    textSize = 13f
                    setPadding(0, dp(8), 0, 0)
                })
            }
            if (log.handlerNotes.isNotBlank()) {
                inner.addView(TextView(this).apply {
                    text = log.handlerNotes.take(220)
                    setTextColor(0xFF334155.toInt())
                    textSize = 12f
                    setPadding(0, dp(8), 0, 0)
                })
            }
            inner.addView(MaterialButton(this).apply {
                text = "Edit"
                layoutParams = LinearLayout.LayoutParams(
                    LinearLayout.LayoutParams.WRAP_CONTENT,
                    LinearLayout.LayoutParams.WRAP_CONTENT
                ).apply { topMargin = dp(10) }
                setOnClickListener {
                    beginEditLog(log)
                }
            })
            card.addView(inner)
            logsContainer.addView(card)
        }
    }

    private fun updateTrainingForm(activeDog: GuidePawDogItem?) {
        trainMessageView.text = if (activeDog == null) {
            "Pick an active dog before saving a training log."
        } else {
            "Training log will save to ${activeDog.name}."
        }
        findViewById<MaterialButton>(R.id.btnSaveLog).isEnabled = activeDog != null
    }

    private fun submitTrainingLog() {
        val token = currentToken ?: return
        val dogId = currentActiveDogId ?: return
        val locationName = logLocationInput.text?.toString()?.trim().orEmpty()
        if (locationName.isBlank()) {
            trainMessageView.text = "Location name is required."
            return
        }
        val cityState = logCityStateInput.text?.toString()?.trim().orEmpty()
        val locationType = logTypeInput.text?.toString()?.trim().orEmpty().ifBlank { locationTypes.first() }
        val notes = logNotesInput.text?.toString()?.trim().orEmpty()
        val focus = focusSeekBar.progress + 1
        val skills = skillChipGroup.checkedChipIds.mapNotNull { id ->
            findViewById<Chip>(id)?.text?.toString()?.trim()?.takeIf { it.isNotBlank() }
        }

        setLoading(true, "Saving training log...")
        worker.execute {
            try {
                val response = api.saveLog(
                    token = token,
                    dogId = dogId,
                    logId = currentEditingLogId,
                    locationName = locationName,
                    cityState = cityState,
                    locationType = locationType,
                    focusLevel = focus,
                    skills = skills,
                    notes = notes,
                )
                runOnUiThread {
                    trainMessageView.text = response.message ?: "Training log saved."
                    logNotesInput.setText("")
                    skillChipGroup.clearCheck()
                    clearEditLog()
                    refreshDashboard(token, dogId)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    trainMessageView.text = friendlyMessage(e.message, "Could not save training log.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    trainMessageView.text = friendlyMessage(t.message, "Could not save training log.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun showSection(sectionButtonId: Int) {
        val overview = findViewById<View>(R.id.sectionOverview)
        val training = findViewById<View>(R.id.sectionTraining)
        val dogs = findViewById<View>(R.id.sectionDogs)
        val wearables = findViewById<View>(R.id.sectionWearables)
        val publicPages = findViewById<View>(R.id.sectionPublic)

        overview.visibility = if (sectionButtonId == R.id.btnOverview) View.VISIBLE else View.GONE
        training.visibility = if (sectionButtonId == R.id.btnTraining) View.VISIBLE else View.GONE
        dogs.visibility = if (sectionButtonId == R.id.btnDogs) View.VISIBLE else View.GONE
        wearables.visibility = if (sectionButtonId == R.id.btnWearables) View.VISIBLE else View.GONE
        publicPages.visibility = if (sectionButtonId == R.id.btnPublic) View.VISIBLE else View.GONE
    }

    private fun showTwoFactorPrompt(message: String) {
        loginCard.visibility = View.VISIBLE
        dashboardCard.visibility = View.GONE
        twoFactorLayout.visibility = View.VISIBLE
        recoveryKeyLayout.visibility = View.VISIBLE
        loginMessageView.text = message
        setLoading(false, null)
    }

    private fun showSignedInShell(message: String) {
        loginCard.visibility = View.GONE
        dashboardCard.visibility = View.VISIBLE
        dashboardSummaryView.text = "Signed in"
        activeDogSummaryView.text = "Loading account details..."
        accountSummaryView.text = "Loading..."
        dogsMessageView.text = "Loading dogs..."
        wearablesBodyView.text = "Loading wearable data..."
        suggestionsContainer.removeAllViews()
        suggestionsContainer.addView(makePlainText("Loading training suggestions..."))
        dogsContainer.removeAllViews()
        logsContainer.removeAllViews()
        statusView.text = message
        loginMessageView.text = ""
    }

    private fun showLoggedOut(message: String) {
        currentToken = null
        currentMe = null
        currentDogs = emptyList()
        currentLogs = emptyList()
        currentSuggestions = emptyList()
        currentActiveDogId = null
        currentEditingLogId = null
        prefs.edit().remove(KEY_CACHE).commit()
        loginCard.visibility = View.VISIBLE
        dashboardCard.visibility = View.GONE
        loginMessageView.text = message
        statusView.text = "Signed out"
        setLoading(false, null)
    }

    private fun signOut(message: String) {
        prefs.edit().remove(KEY_TOKEN).remove(KEY_CACHE).commit()
        twoFactorInput.setText("")
        recoveryKeyInput.setText("")
        showLoggedOut(message)
    }

    private fun saveToken(token: String) {
        currentToken = token
        prefs.edit().putString(KEY_TOKEN, token).commit()
    }

    private fun beginEditLog(log: GuidePawLogItem) {
        currentEditingLogId = log.id
        logLocationInput.setText(log.locationName)
        logCityStateInput.setText(log.locationCityState ?: "")
        logTypeInput.setText(log.locationType ?: locationTypes.first(), false)
        focusSeekBar.progress = (log.focusLevel.coerceIn(1, 6) - 1)
        updateFocusLabel(log.focusLevel.coerceIn(1, 6))
        logNotesInput.setText(log.handlerNotes)
        skillChipGroup.clearCheck()
        log.skillsPracticed.forEach { skill ->
            val chip = findSkillChip(skill)
            if (chip != null) {
                chip.isChecked = true
            }
        }
        saveLogButton.text = "Update training log"
        sectionToggle.check(R.id.btnTraining)
        showSection(R.id.btnTraining)
        trainMessageView.text = "Editing log from ${log.logDate.takeIf { it.isNotBlank() } ?: "recent session"}."
    }

    private fun clearEditLog() {
        currentEditingLogId = null
        saveLogButton.text = "Save training log"
    }

    private fun findSkillChip(label: String): Chip? {
        for (idx in 0 until skillChipGroup.childCount) {
            val child = skillChipGroup.getChildAt(idx)
            if (child is Chip && child.text.toString().equals(label, ignoreCase = true)) {
                return child
            }
        }
        return null
    }

    private fun openExternal(url: String) {
        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
    }

    private fun showMenuDialog() {
        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(18), dp(18), dp(18), dp(8))
        }
        content.addView(makeMenuSection("Dog", listOf(
            MenuAction("Dogs", R.id.btnDogs),
        )))
        content.addView(makeMenuSection("Logs", listOf(
            MenuAction("Overview", R.id.btnOverview),
            MenuAction("Training logs", R.id.btnTraining),
        )))
        content.addView(makeMenuSection("Training", listOf(
            MenuAction("Training", R.id.btnTraining),
        )))
        content.addView(makeMenuSection("Care", listOf(
            MenuAction("Wearables", R.id.btnWearables),
        )))
        content.addView(makeMenuSection("More", listOf(
            MenuAction("Notification Center") { openExternal("https://guidepaw.app/notifications.php") },
            MenuAction("Public guides", R.id.btnPublic),
            MenuAction("GuidePaw app page") { openExternal("https://guidepaw.app/app.php") },
            MenuAction("Breed questionnaire") { openExternal("https://guidepaw.app/breed_questionnaire.php") },
            MenuAction("FAQ") { openExternal("https://guidepaw.app/faq.php") },
            MenuAction("Breed comparisons") { openExternal("https://guidepaw.app/breed_comparison_hub.php") },
            MenuAction("Breed family guide") { openExternal("https://guidepaw.app/breed_family_guide.php") },
            MenuAction("Legal info") { openExternal("https://guidepaw.app/service_dog_esa_legal_info.php") },
        )))

        MaterialAlertDialogBuilder(this)
            .setTitle("GuidePaw")
            .setView(content)
            .setNegativeButton("Close", null)
            .show()
    }

    private fun makeMenuSection(title: String, actions: List<MenuAction>): LinearLayout {
        return LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(0, dp(10), 0, dp(2))

            addView(TextView(this@MainActivity).apply {
                text = title
                setTextColor(0xFF0F172A.toInt())
                textSize = 14f
                setTypeface(typeface, android.graphics.Typeface.BOLD)
                setPadding(0, 0, 0, dp(6))
            })

            actions.forEach { action ->
                addView(MaterialButton(this@MainActivity).apply {
                    text = action.label
                    layoutParams = LinearLayout.LayoutParams(
                        LinearLayout.LayoutParams.MATCH_PARENT,
                        LinearLayout.LayoutParams.WRAP_CONTENT
                    ).apply { topMargin = dp(6) }
                    setOnClickListener {
                        action.onClick?.invoke()
                            ?: sectionToggle.check(action.sectionButtonId ?: R.id.btnOverview)
                    }
                })
            }
        }
    }

    private fun setLoading(isLoading: Boolean, message: String?) {
        progressView.visibility = if (isLoading) View.VISIBLE else View.INVISIBLE
        if (message != null) {
            statusView.text = message
        }
    }

    private fun updateFocusLabel(level: Int) {
        focusValueView.text = "Focus level: $level"
    }

    private fun makePlainText(message: String): TextView {
        return TextView(this).apply {
            text = message
            setTextColor(0xFF334155.toInt())
            textSize = 12f
        }
    }

    private fun makeBulletText(message: String): TextView {
        return TextView(this).apply {
            text = "• $message"
            setTextColor(0xFF1E293B.toInt())
            textSize = 13f
            setPadding(0, dp(4), 0, dp(4))
        }
    }

    private fun friendlyMessage(message: String?, fallback: String): String {
        val raw = message?.trim().orEmpty()
        if (raw.isBlank()) {
            return fallback
        }
        val suspicious = listOf(
            "sqlstate",
            "select ",
            "insert ",
            "update ",
            "delete ",
            "pdo",
            "syntax error",
            "near \"",
            "column ",
            "table ",
        ).any { raw.contains(it, ignoreCase = true) }
        return if (suspicious) fallback else raw
    }

    private fun restoreCachedDashboard() {
        val raw = prefs.getString(KEY_CACHE, null).orEmpty()
        if (raw.isBlank()) {
            return
        }
        runCatching {
            val cache = JSONObject(raw)
            currentMe = cache.optJSONObject("me")?.let {
                GuidePawMeResult(
                    username = it.optString("username", ""),
                    activeDogId = optNullableInt(it, "activeDogId"),
                    dbDriver = it.optText("dbDriver"),
                    schemaVersion = null,
                )
            }
            currentDogs = cache.optJSONArray("dogs")?.let { array ->
                (0 until array.length()).mapNotNull { idx ->
                    val obj = array.optJSONObject(idx) ?: return@mapNotNull null
                    GuidePawDogItem(
                        id = obj.optInt("id", 0),
                        name = obj.optString("name", "Dog"),
                        breed = obj.optText("breed"),
                        ownerUsername = obj.optText("ownerUsername"),
                        accessRole = obj.optText("accessRole"),
                        lifecycleStatus = obj.optText("lifecycleStatus"),
                    )
                }
            }.orEmpty()
            currentLogs = cache.optJSONArray("logs")?.let { array ->
                (0 until array.length()).mapNotNull { idx ->
                    val obj = array.optJSONObject(idx) ?: return@mapNotNull null
                    GuidePawLogItem(
                        id = obj.optInt("id", 0),
                        logDate = obj.optString("logDate", ""),
                        locationName = obj.optString("locationName", ""),
                        locationCityState = obj.optText("locationCityState"),
                        locationType = obj.optText("locationType"),
                        focusLevel = obj.optInt("focusLevel", 3),
                        skillsPracticed = obj.optJSONArray("skillsPracticed")?.toStringList().orEmpty(),
                        handlerNotes = obj.optString("handlerNotes", ""),
                    )
                }
            }.orEmpty()
            currentSuggestions = cache.optJSONArray("suggestions")?.toStringList().orEmpty()
            currentActiveDogId = optNullableInt(cache, "activeDogId")
            if (currentMe != null || currentDogs.isNotEmpty() || currentLogs.isNotEmpty()) {
                loginCard.visibility = View.GONE
                dashboardCard.visibility = View.VISIBLE
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
                .put("activeDogId", me.activeDogId ?: JSONObject.NULL)
                .put("dbDriver", me.dbDriver ?: JSONObject.NULL)
                .put("schemaVersion", me.schemaVersion ?: JSONObject.NULL))
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

    private fun optNullableInt(json: JSONObject, key: String): Int? {
        return if (json.has(key) && !json.isNull(key)) {
            val value = json.optString(key, "").trim()
            if (value.isNotBlank()) value.toIntOrNull() ?: json.optInt(key, 0).takeIf { it > 0 } else null
        } else {
            null
        }
    }

    private fun JSONObject.optText(key: String): String? {
        return optString(key, "").trim().takeIf { it.isNotBlank() }
    }

    private fun JSONArray.toStringList(): List<String> {
        return (0 until length()).mapNotNull { idx ->
            optString(idx, "").trim().takeIf { it.isNotBlank() }
        }
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    private data class MenuAction(
        val label: String,
        val sectionButtonId: Int? = null,
        val onClick: (() -> Unit)? = null,
    )

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN = "auth_token"
        private const val KEY_CACHE = "dashboard_cache"
    }
}
