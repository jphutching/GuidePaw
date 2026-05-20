package com.guidepaw.bridge

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.Switch
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
import java.util.Date

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
    private lateinit var autoSyncSwitch: Switch

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
        autoSyncSwitch = findViewById(R.id.autoSyncSwitch)
    }

    private fun wireButtons() {
        findViewById<Button>(R.id.loginButton).setOnClickListener { loginAndSaveToken() }
        findViewById<Button>(R.id.saveButton).setOnClickListener { saveConfigFromForm() }
        findViewById<Button>(R.id.refreshAccountButton).setOnClickListener { refreshAccountSummary() }
        findViewById<Button>(R.id.requestAccessButton).setOnClickListener { requestHealthConnectAccess() }
        findViewById<Button>(R.id.syncNowButton).setOnClickListener { syncNow() }
        findViewById<Button>(R.id.openBridgeButton).setOnClickListener { openBridgeLink() }
        findViewById<Button>(R.id.manageAccessButton).setOnClickListener { openHealthConnectSettings() }
        autoSyncSwitch.setOnCheckedChangeListener { _, enabled ->
            prefs.setAutoSyncEnabled(enabled)
            GuidePawSyncScheduler.schedule(this, enabled)
            updateStatus(if (enabled) "Automatic sync enabled every 6 hours." else "Automatic sync disabled.")
        }
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
                }
            }

            if ((prefs.load()?.dogId ?: 0L) <= 0L && dogsResult is ApiResult.Success && dogsResult.data.dogs.isNotEmpty()) {
                val firstDog = dogsResult.data.dogs.first()
                dogIdInput.setText(firstDog.id.toString())
                dogNameInput.setText(firstDog.name)
                saveConfigFromForm(showMessage = false)
            }

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
