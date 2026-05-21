package com.guidepaw.bridge

import android.os.Bundle
import android.widget.Button
import android.widget.CheckBox
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.model.DogAccessOverview
import com.guidepaw.bridge.sync.ApiResult
import com.guidepaw.bridge.sync.GuidePawApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class DogAccessActivity : AppCompatActivity() {
    private lateinit var prefs: BridgePreferences
    private lateinit var statusText: TextView
    private lateinit var summaryText: TextView
    private lateinit var dogText: TextView
    private lateinit var refreshButton: Button
    private lateinit var handlersContainer: LinearLayout
    private lateinit var pendingInvitesContainer: LinearLayout
    private lateinit var transferRequestsContainer: LinearLayout
    private lateinit var inviteIdentityInput: EditText
    private lateinit var inviteRoleInput: EditText
    private lateinit var invitePermissionInput: EditText
    private lateinit var inviteEndsAtInput: EditText
    private lateinit var inviteButton: Button
    private lateinit var transferIdentityInput: EditText
    private lateinit var transferNoteInput: EditText
    private lateinit var transferKeepAccessCheck: CheckBox
    private lateinit var transferButton: Button
    private var currentOverview: DogAccessOverview? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_dog_access)

        prefs = BridgePreferences(this)
        bindViews()
        wireButtons()
        refreshDogAccess()
    }

    private fun bindViews() {
        statusText = findViewById(R.id.dogAccessStatusText)
        summaryText = findViewById(R.id.dogAccessSummaryText)
        dogText = findViewById(R.id.dogAccessDogText)
        refreshButton = findViewById(R.id.dogAccessRefreshButton)
        handlersContainer = findViewById(R.id.dogAccessHandlersContainer)
        pendingInvitesContainer = findViewById(R.id.dogAccessPendingInvitesContainer)
        transferRequestsContainer = findViewById(R.id.dogAccessTransferRequestsContainer)
        inviteIdentityInput = findViewById(R.id.dogAccessInviteIdentityInput)
        inviteRoleInput = findViewById(R.id.dogAccessInviteRoleInput)
        invitePermissionInput = findViewById(R.id.dogAccessInvitePermissionInput)
        inviteEndsAtInput = findViewById(R.id.dogAccessInviteEndsAtInput)
        inviteButton = findViewById(R.id.dogAccessInviteButton)
        transferIdentityInput = findViewById(R.id.dogAccessTransferIdentityInput)
        transferNoteInput = findViewById(R.id.dogAccessTransferNoteInput)
        transferKeepAccessCheck = findViewById(R.id.dogAccessTransferKeepAccessCheck)
        transferButton = findViewById(R.id.dogAccessTransferButton)
    }

    private fun wireButtons() {
        refreshButton.setOnClickListener { refreshDogAccess() }
        inviteButton.setOnClickListener { sendInvite() }
        transferButton.setOnClickListener { requestTransfer() }
    }

    private fun refreshDogAccess() {
        val config = prefs.load() ?: run {
            statusText.text = "Save pairing first."
            summaryText.text = "Dog access: no pairing loaded yet."
            return
        }
        lifecycleScope.launch {
            statusText.text = "Loading dog access..."
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchDogAccessOverview(config)
            }
            when (result) {
                is ApiResult.Success -> {
                    currentOverview = result.data
                    renderOverview(result.data)
                    statusText.text = "Dog access loaded."
                }
                is ApiResult.Failure -> {
                    statusText.text = "Could not load dog access: ${result.message}"
                }
            }
        }
    }

    private fun renderOverview(overview: DogAccessOverview) {
        summaryText.text = buildString {
            append("Active dog ID: ")
            append(overview.activeDogId.takeIf { it > 0L } ?: overview.selectedDogId)
            append(" • ")
            append(if (overview.isOwner) "Owner" else if (overview.canEdit) "Editor" else "Viewer")
        }
        val dog = overview.dog
        dogText.text = if (dog == null) {
            "Dog access: no dog loaded."
        } else {
            buildString {
                append(dog.name)
                if (dog.ownerUsername.isNotBlank()) {
                    append("\nOwner: ")
                    append(dog.ownerDisplayName.ifBlank { dog.ownerUsername })
                }
                append("\nStatus: ")
                append(dog.lifecycleStatus)
                if (dog.lifecycleNote.isNotBlank()) {
                    append("\n")
                    append(dog.lifecycleNote)
                }
            }
        }

        renderHandlers(overview)
        renderPendingInvites(overview)
        renderTransfers(overview)

        val canOwnerInvite = overview.isOwner && dog != null
        inviteIdentityInput.isEnabled = canOwnerInvite
        inviteRoleInput.isEnabled = canOwnerInvite
        invitePermissionInput.isEnabled = canOwnerInvite
        inviteEndsAtInput.isEnabled = canOwnerInvite
        inviteButton.isEnabled = canOwnerInvite
        transferIdentityInput.isEnabled = canOwnerInvite
        transferNoteInput.isEnabled = canOwnerInvite
        transferKeepAccessCheck.isEnabled = canOwnerInvite
        transferButton.isEnabled = canOwnerInvite
    }

    private fun renderHandlers(overview: DogAccessOverview) {
        handlersContainer.removeAllViews()
        if (overview.handlers.isEmpty()) {
            handlersContainer.addView(TextView(this).apply {
                text = "No shared handlers yet."
            })
            return
        }
        overview.handlers.forEach { handler ->
            handlersContainer.addView(TextView(this).apply {
                text = buildString {
                    append(handler.displayName.ifBlank { handler.username })
                    if (handler.email.isNotBlank()) {
                        append("\n")
                        append(handler.email)
                    }
                    append("\n")
                    append(handler.role.ifBlank { "Co-op handler" })
                    append(" • ")
                    append(handler.permissionLevel.ifBlank { "view" })
                    if (handler.accessEndsAt.isNotBlank()) {
                        append(" • Ends ")
                        append(handler.accessEndsAt)
                    }
                    if (handler.accessStatus.isNotBlank()) {
                        append("\nStatus: ")
                        append(handler.accessStatus)
                    }
                }
            })
            if (overview.isOwner && handler.accessStatus != "revoked") {
                handlersContainer.addView(Button(this).apply {
                    text = "Revoke access"
                    setOnClickListener {
                        val config = prefs.load() ?: return@setOnClickListener
                        lifecycleScope.launch {
                            statusText.text = "Revoking access..."
                            val result = withContext(Dispatchers.IO) {
                                GuidePawApiClient().revokeDogAccess(config, overview.dog?.id ?: overview.selectedDogId, handler.id)
                            }
                            when (result) {
                                is ApiResult.Success -> {
                                    currentOverview = result.data
                                    renderOverview(result.data)
                                    statusText.text = result.data.dog?.name?.let { "Access revoked for $it." } ?: "Access revoked."
                                }
                                is ApiResult.Failure -> statusText.text = "Could not revoke access: ${result.message}"
                            }
                        }
                    }
                })
            }
            handlersContainer.addView(TextView(this).apply {
                text = ""
            })
        }
    }

    private fun renderPendingInvites(overview: DogAccessOverview) {
        pendingInvitesContainer.removeAllViews()
        if (overview.pendingInvites.isEmpty()) {
            pendingInvitesContainer.addView(TextView(this).apply {
                text = "No pending dog access invites."
            })
            return
        }
        overview.pendingInvites.forEach { invite ->
            pendingInvitesContainer.addView(TextView(this).apply {
                text = buildString {
                    append(invite.dogName)
                    append("\nFrom ")
                    append(invite.ownerDisplayName.ifBlank { invite.ownerUsername })
                    append("\n")
                    append(invite.role.ifBlank { "Co-op handler" })
                    append(" • ")
                    append(invite.permissionLevel.ifBlank { "view" })
                    if (invite.accessEndsAt.isNotBlank()) {
                        append(" • Ends ")
                        append(invite.accessEndsAt)
                    }
                }
            })
            val actions = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL }
            actions.addView(Button(this).apply {
                text = "Accept"
                setOnClickListener {
                    val config = prefs.load() ?: return@setOnClickListener
                    lifecycleScope.launch {
                        statusText.text = "Accepting invite..."
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().acceptDogAccessInvite(config, invite.dogId, invite.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                currentOverview = result.data
                                renderOverview(result.data)
                                statusText.text = "Invite accepted."
                            }
                            is ApiResult.Failure -> statusText.text = "Could not accept invite: ${result.message}"
                        }
                    }
                }
            })
            actions.addView(Button(this).apply {
                text = "Decline"
                setOnClickListener {
                    val config = prefs.load() ?: return@setOnClickListener
                    lifecycleScope.launch {
                        statusText.text = "Declining invite..."
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().declineDogAccessInvite(config, invite.dogId, invite.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                currentOverview = result.data
                                renderOverview(result.data)
                                statusText.text = "Invite declined."
                            }
                            is ApiResult.Failure -> statusText.text = "Could not decline invite: ${result.message}"
                        }
                    }
                }
            })
            pendingInvitesContainer.addView(actions)
        }
    }

    private fun renderTransfers(overview: DogAccessOverview) {
        transferRequestsContainer.removeAllViews()
        if (overview.incomingTransfers.isEmpty()) {
            transferRequestsContainer.addView(TextView(this).apply {
                text = "No incoming transfer requests."
            })
            return
        }
        overview.incomingTransfers.forEach { transfer ->
            transferRequestsContainer.addView(TextView(this).apply {
                text = buildString {
                    append(transfer.dogName)
                    append("\nFrom ")
                    append(transfer.fromDisplayName.ifBlank { transfer.fromUsername })
                    if (transfer.requestedAt.isNotBlank()) {
                        append("\nRequested ")
                        append(transfer.requestedAt)
                    }
                    if (transfer.note.isNotBlank()) {
                        append("\n")
                        append(transfer.note)
                    }
                }
            })
            val actions = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL }
            actions.addView(Button(this).apply {
                text = "Accept"
                setOnClickListener {
                    val config = prefs.load() ?: return@setOnClickListener
                    lifecycleScope.launch {
                        statusText.text = "Accepting transfer..."
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().acceptDogTransfer(config, transfer.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                currentOverview = result.data
                                renderOverview(result.data)
                                statusText.text = "Transfer accepted."
                            }
                            is ApiResult.Failure -> statusText.text = "Could not accept transfer: ${result.message}"
                        }
                    }
                }
            })
            actions.addView(Button(this).apply {
                text = "Decline"
                setOnClickListener {
                    val config = prefs.load() ?: return@setOnClickListener
                    lifecycleScope.launch {
                        statusText.text = "Declining transfer..."
                        val result = withContext(Dispatchers.IO) {
                            GuidePawApiClient().declineDogTransfer(config, transfer.id)
                        }
                        when (result) {
                            is ApiResult.Success -> {
                                currentOverview = result.data
                                renderOverview(result.data)
                                statusText.text = "Transfer declined."
                            }
                            is ApiResult.Failure -> statusText.text = "Could not decline transfer: ${result.message}"
                        }
                    }
                }
            })
            transferRequestsContainer.addView(actions)
        }
    }

    private fun sendInvite() {
        val config = prefs.load() ?: run {
            statusText.text = "Save pairing first."
            return
        }
        val overview = currentOverview ?: run {
            statusText.text = "Load a dog first."
            return
        }
        if (!overview.isOwner || overview.dog == null) {
            statusText.text = "Only the owner can send invites."
            return
        }
        val identity = inviteIdentityInput.text.toString().trim()
        if (identity.isBlank()) {
            statusText.text = "Enter a username or email."
            return
        }
        val role = inviteRoleInput.text.toString().trim().ifBlank { "co-op handler" }
        val permission = invitePermissionInput.text.toString().trim().ifBlank { "view" }
        val endsAt = inviteEndsAtInput.text.toString().trim()

        lifecycleScope.launch {
            statusText.text = "Sending invite..."
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().sendDogAccessInvite(
                    config = config,
                    dogId = overview.dog.id,
                    handlerIdentity = identity,
                    role = role,
                    permissionLevel = permission,
                    accessEndsAt = endsAt,
                )
            }
            when (result) {
                is ApiResult.Success -> {
                    currentOverview = result.data
                    renderOverview(result.data)
                    inviteIdentityInput.setText("")
                    inviteRoleInput.setText("co-op handler")
                    invitePermissionInput.setText("view")
                    inviteEndsAtInput.setText("")
                    statusText.text = "Invite sent."
                }
                is ApiResult.Failure -> statusText.text = "Could not send invite: ${result.message}"
            }
        }
    }

    private fun requestTransfer() {
        val config = prefs.load() ?: run {
            statusText.text = "Save pairing first."
            return
        }
        val overview = currentOverview ?: run {
            statusText.text = "Load a dog first."
            return
        }
        if (!overview.isOwner || overview.dog == null) {
            statusText.text = "Only the owner can request a transfer."
            return
        }
        val identity = transferIdentityInput.text.toString().trim()
        if (identity.isBlank()) {
            statusText.text = "Enter a receiving username or email."
            return
        }
        val note = transferNoteInput.text.toString().trim()
        val keepAccess = transferKeepAccessCheck.isChecked

        lifecycleScope.launch {
            statusText.text = "Sending transfer request..."
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().requestDogTransfer(
                    config = config,
                    dogId = overview.dog.id,
                    transferIdentity = identity,
                    keepPreviousOwnerAccess = keepAccess,
                    transferNote = note,
                )
            }
            when (result) {
                is ApiResult.Success -> {
                    currentOverview = result.data
                    renderOverview(result.data)
                    transferIdentityInput.setText("")
                    transferNoteInput.setText("")
                    transferKeepAccessCheck.isChecked = true
                    statusText.text = "Transfer request sent."
                }
                is ApiResult.Failure -> statusText.text = "Could not send transfer request: ${result.message}"
            }
        }
    }
}
