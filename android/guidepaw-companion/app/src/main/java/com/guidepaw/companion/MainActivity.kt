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
import com.google.android.material.button.MaterialButton
import com.google.android.material.button.MaterialButtonToggleGroup
import com.google.android.material.chip.Chip
import com.google.android.material.chip.ChipGroup
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import com.google.android.material.card.MaterialCardView
import com.google.android.material.progressindicator.LinearProgressIndicator
import java.util.concurrent.Executors

class MainActivity : AppCompatActivity() {
    private val api = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()

    private lateinit var prefs: SharedPreferences
    private lateinit var statusView: TextView
    private lateinit var progressView: LinearProgressIndicator

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
    private lateinit var tokenLabelInput: TextInputEditText

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
        tokenLabelInput = findViewById(R.id.tokenLabelInput)

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

        findViewById<MaterialButton>(R.id.btnOpenWearablesWeb).setOnClickListener {
            openExternal("https://guidepaw.app/wearable_integrations.php")
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
        val tokenLabel = tokenLabelInput.text?.toString()?.trim().orEmpty().ifBlank { "GuidePaw Companion" }
        val totpCode = twoFactorInput.text?.toString()?.trim().orEmpty()
        val recoveryKey = recoveryKeyInput.text?.toString()?.trim().orEmpty()

        if (username.isBlank() || password.isBlank()) {
            loginMessageView.text = "Username and password are required."
            return
        }

        setLoading(true, "Signing in...")
        worker.execute {
            try {
                val result = api.login(username, password, tokenLabel, totpCode, recoveryKey)
                runOnUiThread {
                    if (result.requiresTwoFactor && result.token.isNullOrBlank()) {
                        showTwoFactorPrompt(result.message ?: "Two-factor authentication is required.")
                        setLoading(false, "Two-factor required.")
                        return@runOnUiThread
                    }
                    if (!result.success || result.token.isNullOrBlank()) {
                        showLoggedOut(result.message ?: "Sign in failed.")
                        setLoading(false, null)
                        return@runOnUiThread
                    }
                    saveToken(result.token)
                    refreshDashboard(result.token, null)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    val payload = e.payload
                    if (e.statusCode == 401 && payload?.optBoolean("requires_2fa", false) == true) {
                        showTwoFactorPrompt(payload.optString("message") ?: "Two-factor authentication is required.")
                    } else {
                        loginMessageView.text = e.message ?: "Sign in failed."
                        setLoading(false, null)
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    loginMessageView.text = t.message ?: "Sign in failed."
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
        refreshDashboard(token, currentActiveDogId)
    }

    private fun refreshDashboard(token: String, preferDogId: Int?) {
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
                    renderDashboard()
                    setLoading(false, "Dashboard updated.")
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    if (e.statusCode == 401 || e.statusCode == 403) {
                        signOut("Session expired. Please sign in again.")
                    } else {
                        setLoading(false, e.message ?: "Could not refresh dashboard.")
                        statusView.text = e.message ?: "Could not refresh dashboard."
                    }
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    setLoading(false, t.message ?: "Could not refresh dashboard.")
                    statusView.text = t.message ?: "Could not refresh dashboard."
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
            me.schemaVersion?.let { "Schema $it" },
            me.dbDriver?.let { "DB $it" },
        ).joinToString(" • ").ifBlank { "Account details loaded." }

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
                setTextColor(0xFF64748B.toInt())
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
                                setLoading(false, e.message ?: "Could not switch dog.")
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
                setTextColor(0xFF64748B.toInt())
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
                    setTextColor(0xFF475569.toInt())
                    textSize = 12f
                    setPadding(0, dp(8), 0, 0)
                })
            }
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
                    logId = null,
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
                    refreshDashboard(token, dogId)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    trainMessageView.text = e.message ?: "Could not save training log."
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    trainMessageView.text = t.message ?: "Could not save training log."
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

    private fun showLoggedOut(message: String) {
        currentToken = null
        currentMe = null
        currentDogs = emptyList()
        currentLogs = emptyList()
        currentSuggestions = emptyList()
        currentActiveDogId = null
        loginCard.visibility = View.VISIBLE
        dashboardCard.visibility = View.GONE
        loginMessageView.text = message
        statusView.text = "Signed out"
        setLoading(false, null)
    }

    private fun signOut(message: String) {
        prefs.edit().remove(KEY_TOKEN).apply()
        twoFactorInput.setText("")
        recoveryKeyInput.setText("")
        showLoggedOut(message)
    }

    private fun saveToken(token: String) {
        currentToken = token
        prefs.edit().putString(KEY_TOKEN, token).apply()
    }

    private fun openExternal(url: String) {
        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
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
            setTextColor(0xFF64748B.toInt())
            textSize = 12f
        }
    }

    private fun makeBulletText(message: String): TextView {
        return TextView(this).apply {
            text = "• $message"
            setTextColor(0xFF0F172A.toInt())
            textSize = 13f
            setPadding(0, dp(4), 0, dp(4))
        }
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN = "auth_token"
    }
}
