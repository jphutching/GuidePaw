package com.guidepaw.bridge

import android.os.Bundle
import android.widget.Button
import android.widget.CheckBox
import android.widget.EditText
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.model.HandlerProfileOverview
import com.guidepaw.bridge.sync.ApiResult
import com.guidepaw.bridge.sync.GuidePawApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class HandlerProfileActivity : AppCompatActivity() {
    private lateinit var prefs: BridgePreferences
    private lateinit var statusText: TextView
    private lateinit var summaryText: TextView
    private lateinit var displayNameInput: EditText
    private lateinit var homeStreetInput: EditText
    private lateinit var homeAptInput: EditText
    private lateinit var homeCityInput: EditText
    private lateinit var homeStateInput: EditText
    private lateinit var homeZipInput: EditText
    private lateinit var phoneInput: EditText
    private lateinit var publicEmailInput: EditText
    private lateinit var facebookUrlInput: EditText
    private lateinit var profilePhotoUrlInput: EditText
    private lateinit var backupNameInput: EditText
    private lateinit var backupPhoneInput: EditText
    private lateinit var publicNotesInput: EditText
    private lateinit var smsPhoneInput: EditText
    private lateinit var smsEnabledCheck: CheckBox
    private lateinit var refreshButton: Button
    private lateinit var saveButton: Button
    private var loadedProfile: HandlerProfileOverview? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_handler_profile)

        prefs = BridgePreferences(this)
        bindViews()
        wireButtons()
        loadProfile()
    }

    private fun bindViews() {
        statusText = findViewById(R.id.handlerProfileStatusText)
        summaryText = findViewById(R.id.handlerProfileSummaryText)
        displayNameInput = findViewById(R.id.handlerDisplayNameInput)
        homeStreetInput = findViewById(R.id.handlerHomeStreetInput)
        homeAptInput = findViewById(R.id.handlerHomeAptInput)
        homeCityInput = findViewById(R.id.handlerHomeCityInput)
        homeStateInput = findViewById(R.id.handlerHomeStateInput)
        homeZipInput = findViewById(R.id.handlerHomeZipInput)
        phoneInput = findViewById(R.id.handlerPhoneInput)
        publicEmailInput = findViewById(R.id.handlerPublicEmailInput)
        facebookUrlInput = findViewById(R.id.handlerFacebookUrlInput)
        profilePhotoUrlInput = findViewById(R.id.handlerProfilePhotoUrlInput)
        backupNameInput = findViewById(R.id.handlerBackupNameInput)
        backupPhoneInput = findViewById(R.id.handlerBackupPhoneInput)
        publicNotesInput = findViewById(R.id.handlerPublicNotesInput)
        smsPhoneInput = findViewById(R.id.handlerSmsPhoneInput)
        smsEnabledCheck = findViewById(R.id.handlerSmsEnabledCheck)
        refreshButton = findViewById(R.id.handlerProfileRefreshButton)
        saveButton = findViewById(R.id.handlerProfileSaveButton)
    }

    private fun wireButtons() {
        refreshButton.setOnClickListener { loadProfile() }
        saveButton.setOnClickListener { saveProfile() }
    }

    private fun loadProfile() {
        val config = prefs.load() ?: run {
            statusText.text = "Save pairing first."
            summaryText.text = "Handler profile: no pairing loaded yet."
            return
        }

        lifecycleScope.launch {
            statusText.text = "Loading handler profile..."
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchHandlerProfile(config)
            }
            when (result) {
                is ApiResult.Success -> {
                    loadedProfile = result.data
                    renderProfile(result.data)
                    statusText.text = "Handler profile loaded."
                }
                is ApiResult.Failure -> {
                    statusText.text = "Could not load handler profile: ${result.message}"
                }
            }
        }
    }

    private fun renderProfile(profile: HandlerProfileOverview) {
        summaryText.text = buildString {
            append(profile.displayName.ifBlank { profile.username })
            append(" • ")
            append(profile.publicEmail.ifBlank { "no public email" })
            if (profile.smsNotificationsEnabled) {
                append(" • SMS on")
            }
        }
        displayNameInput.setText(profile.displayName)
        homeStreetInput.setText(profile.homeStreet)
        homeAptInput.setText(profile.homeApt)
        homeCityInput.setText(profile.homeCity)
        homeStateInput.setText(profile.homeState)
        homeZipInput.setText(profile.homeZip)
        phoneInput.setText(profile.phone)
        publicEmailInput.setText(profile.publicEmail)
        facebookUrlInput.setText(profile.facebookUrl)
        profilePhotoUrlInput.setText(profile.profilePhotoUrl)
        backupNameInput.setText(profile.backupContactName)
        backupPhoneInput.setText(profile.backupContactPhone)
        publicNotesInput.setText(profile.publicNotes)
        smsPhoneInput.setText(profile.smsPhone)
        smsEnabledCheck.isChecked = profile.smsNotificationsEnabled
    }

    private fun saveProfile() {
        val config = prefs.load() ?: run {
            statusText.text = "Save pairing first."
            return
        }
        val baseProfile = loadedProfile ?: HandlerProfileOverview(
            userId = 0L,
            username = "",
            displayName = "",
            homeStreet = "",
            homeApt = "",
            homeCity = "",
            homeState = "",
            homeZip = "",
            phone = "",
            publicEmail = "",
            facebookUrl = "",
            profilePhotoUrl = "",
            backupContactName = "",
            backupContactPhone = "",
            publicNotes = "",
            smsPhone = "",
            smsNotificationsEnabled = false,
            homeAddress = "",
        )
        val profile = baseProfile.copy(
            displayName = displayNameInput.text.toString().trim(),
            homeStreet = homeStreetInput.text.toString().trim(),
            homeApt = homeAptInput.text.toString().trim(),
            homeCity = homeCityInput.text.toString().trim(),
            homeState = homeStateInput.text.toString().trim(),
            homeZip = homeZipInput.text.toString().trim(),
            phone = phoneInput.text.toString().trim(),
            publicEmail = publicEmailInput.text.toString().trim(),
            facebookUrl = facebookUrlInput.text.toString().trim(),
            backupContactName = backupNameInput.text.toString().trim(),
            backupContactPhone = backupPhoneInput.text.toString().trim(),
            publicNotes = publicNotesInput.text.toString().trim(),
            smsPhone = smsPhoneInput.text.toString().trim(),
            smsNotificationsEnabled = smsEnabledCheck.isChecked,
        )
        val profilePhotoUrl = profilePhotoUrlInput.text.toString().trim()

        lifecycleScope.launch {
            statusText.text = "Saving handler profile..."
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().saveHandlerProfile(config, profile, profilePhotoUrl)
            }
            when (result) {
                is ApiResult.Success -> {
                    loadedProfile = loadedProfile?.copy(
                        displayName = result.data.displayName,
                        publicEmail = result.data.publicEmail,
                        phone = result.data.phone,
                        profilePhotoUrl = result.data.profilePhotoUrl,
                    ) ?: profile.copy(
                        displayName = result.data.displayName,
                        publicEmail = result.data.publicEmail,
                        phone = result.data.phone,
                        profilePhotoUrl = result.data.profilePhotoUrl,
                    )
                    summaryText.text = buildString {
                        append(result.data.displayName.ifBlank { "Handler profile" })
                        append(" • ")
                        append(result.data.publicEmail.ifBlank { "no public email" })
                    }
                    statusText.text = result.data.message
                }
                is ApiResult.Failure -> {
                    statusText.text = "Could not save handler profile: ${result.message}"
                }
            }
        }
    }
}
