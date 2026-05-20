package com.guidepaw.bridge

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
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
    private lateinit var tokenInput: EditText
    private lateinit var dogIdInput: EditText
    private lateinit var dogNameInput: EditText
    private lateinit var sourceInput: EditText
    private lateinit var statusText: TextView
    private lateinit var lastSyncText: TextView
    private lateinit var accountText: TextView
    private lateinit var dogsText: TextView
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
        tokenInput = findViewById(R.id.tokenInput)
        dogIdInput = findViewById(R.id.dogIdInput)
        dogNameInput = findViewById(R.id.dogNameInput)
        sourceInput = findViewById(R.id.sourceInput)
        statusText = findViewById(R.id.statusText)
        lastSyncText = findViewById(R.id.lastSyncText)
        accountText = findViewById(R.id.accountText)
        dogsText = findViewById(R.id.dogsText)
        autoSyncSwitch = findViewById(R.id.autoSyncSwitch)
    }

    private fun wireButtons() {
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
                    val dogs = dogsResult.data
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
                }
                is ApiResult.Failure -> {
                    dogsText.text = getString(R.string.dogs_summary_error, dogsResult.message)
                }
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
