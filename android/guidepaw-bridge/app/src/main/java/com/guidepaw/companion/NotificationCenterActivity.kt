package com.guidepaw.companion

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Button
import android.widget.CheckBox
import android.widget.LinearLayout
import android.widget.Switch
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.guidepaw.companion.model.BridgeConfig
import com.guidepaw.companion.model.NotificationOverview
import com.guidepaw.companion.model.NotificationPreferenceState
import com.guidepaw.companion.sync.ApiResult
import com.guidepaw.companion.sync.GuidePawApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class NotificationCenterActivity : AppCompatActivity() {
    private lateinit var prefs: BridgePreferences
    private lateinit var summaryText: TextView
    private lateinit var statusText: TextView
    private lateinit var refreshButton: Button
    private lateinit var markAllReadButton: Button
    private lateinit var deleteSelectedButton: Button
    private lateinit var savePrefsButton: Button
    private lateinit var accessSwitch: Switch
    private lateinit var careSwitch: Switch
    private lateinit var adminSwitch: Switch
    private lateinit var generalSwitch: Switch
    private lateinit var pendingInvitesContainer: LinearLayout
    private lateinit var inboxContainer: LinearLayout
    private var currentOverview: NotificationOverview? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_notification_center)

        prefs = BridgePreferences(this)
        bindViews()
        wireButtons()
        loadNotifications()
    }

    override fun onResume() {
        super.onResume()
        loadNotifications()
    }

    private fun bindViews() {
        summaryText = findViewById(R.id.notificationSummaryText)
        statusText = findViewById(R.id.notificationStatusText)
        refreshButton = findViewById(R.id.notificationRefreshButton)
        markAllReadButton = findViewById(R.id.notificationMarkAllReadButton)
        deleteSelectedButton = findViewById(R.id.notificationDeleteSelectedButton)
        savePrefsButton = findViewById(R.id.notificationSavePrefsButton)
        accessSwitch = findViewById(R.id.prefAccessSwitch)
        careSwitch = findViewById(R.id.prefCareSwitch)
        adminSwitch = findViewById(R.id.prefAdminSwitch)
        generalSwitch = findViewById(R.id.prefGeneralSwitch)
        pendingInvitesContainer = findViewById(R.id.pendingInvitesContainer)
        inboxContainer = findViewById(R.id.notificationInboxContainer)
    }

    private fun wireButtons() {
        refreshButton.setOnClickListener { loadNotifications() }
        markAllReadButton.setOnClickListener {
            val config = prefs.load() ?: run {
                updateStatus("Save the connection first.")
                return@setOnClickListener
            }
            lifecycleScope.launch {
                updateStatus("Marking notifications read...")
                val result = withContext(Dispatchers.IO) {
                    GuidePawApiClient().markAllNotificationsRead(config)
                }
                handleOverviewResult(result, "Notifications marked read.")
            }
        }
        deleteSelectedButton.setOnClickListener {
            val ids = selectedNotificationIds()
            if (ids.isEmpty()) {
                updateStatus("Select at least one notification to delete.")
                return@setOnClickListener
            }
            val config = prefs.load() ?: run {
                updateStatus("Save the connection first.")
                return@setOnClickListener
            }
            lifecycleScope.launch {
                updateStatus("Deleting selected notifications...")
                val result = withContext(Dispatchers.IO) {
                    GuidePawApiClient().deleteNotifications(config, ids)
                }
                handleOverviewResult(result, "Deleted ${ids.size} notification${if (ids.size == 1) "" else "s"}.")
            }
        }
        savePrefsButton.setOnClickListener {
            val config = prefs.load() ?: run {
                updateStatus("Save the connection first.")
                return@setOnClickListener
            }
            lifecycleScope.launch {
                updateStatus("Saving notification preferences...")
                val result = withContext(Dispatchers.IO) {
                    GuidePawApiClient().saveNotificationPreferences(
                        config,
                        NotificationPreferenceState(
                            access = accessSwitch.isChecked,
                            care = careSwitch.isChecked,
                            admin = adminSwitch.isChecked,
                            general = generalSwitch.isChecked,
                        ),
                    )
                }
                handleOverviewResult(result, "Notification preferences saved.")
            }
        }
    }

    private fun loadNotifications() {
        val config = prefs.load() ?: run {
            summaryText.text = "Notifications load once you save the connection."
            statusText.text = "Status: save the connection first."
            renderOverview(null)
            return
        }
        lifecycleScope.launch {
            updateStatus("Loading notifications...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().fetchNotificationOverview(config)
            }
            handleOverviewResult(result, "Notification center loaded.")
        }
    }

    private fun handleOverviewResult(result: ApiResult<NotificationOverview>, successMessage: String) {
        when (result) {
            is ApiResult.Success -> {
                currentOverview = result.data
                renderOverview(result.data)
                updateStatus(successMessage)
            }
            is ApiResult.Failure -> {
                updateStatus("Could not load notifications: ${result.message}")
            }
        }
    }

    private fun renderOverview(overview: NotificationOverview?) {
        pendingInvitesContainer.removeAllViews()
        inboxContainer.removeAllViews()

        if (overview == null) {
            summaryText.text = "Open the notification center after saving your connection."
            accessSwitch.isChecked = true
            careSwitch.isChecked = true
            adminSwitch.isChecked = true
            generalSwitch.isChecked = true
            pendingInvitesContainer.addView(TextView(this).apply {
                text = "Pending invites appear here after sign-in."
            })
            inboxContainer.addView(TextView(this).apply {
                text = "Inbox items appear here after sign-in."
            })
            return
        }

        summaryText.text = buildString {
            append("${overview.visibleUnreadCount} unread visible alert${if (overview.visibleUnreadCount == 1) "" else "s"}")
            if (overview.hiddenCount > 0) {
                append(" • ${overview.hiddenCount} hidden by preferences")
            }
            if (overview.pendingInvites.isNotEmpty()) {
                append(" • ${overview.pendingInvites.size} pending dog access invite${if (overview.pendingInvites.size == 1) "" else "s"}")
            }
        }

        accessSwitch.isChecked = overview.preferences.access
        careSwitch.isChecked = overview.preferences.care
        adminSwitch.isChecked = overview.preferences.admin
        generalSwitch.isChecked = overview.preferences.general

        renderPendingInvites(overview)
        renderInbox(overview)
    }

    private fun renderPendingInvites(overview: NotificationOverview) {
        if (overview.pendingInvites.isEmpty()) {
            pendingInvitesContainer.addView(TextView(this).apply {
                text = "No pending dog access invites."
            })
            return
        }

        overview.pendingInvites.forEach { invite ->
            pendingInvitesContainer.addView(createInviteCard(overview, invite))
        }
    }

    private fun createInviteCard(overview: NotificationOverview, invite: com.guidepaw.companion.model.DogAccessPendingInviteRow): LinearLayout {
        val card = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(20, 20, 20, 20)
            layoutParams = LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                bottomMargin = 16
            }
        }
        card.addView(TextView(this).apply {
            text = invite.dogName
            textSize = 16f
            setTextColor(resources.getColor(android.R.color.black, theme))
        })
        card.addView(TextView(this).apply {
            text = "From ${invite.ownerDisplayName.ifBlank { invite.ownerUsername }} • ${invite.role.ifBlank { "co-op handler" }} • ${invite.permissionLevel.ifBlank { "view" }}"
        })
        card.addView(TextView(this).apply {
            text = "End date: ${invite.accessEndsAt.ifBlank { "not set" }}"
        })

        val actions = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
        }
        actions.addView(Button(this).apply {
            text = "Accept"
            setOnClickListener { respondToInvite(overview, invite.id, true) }
        })
        actions.addView(Button(this).apply {
            text = "Decline"
            setOnClickListener { respondToInvite(overview, invite.id, false) }
        })
        card.addView(actions)
        return card
    }

    private fun respondToInvite(
        overview: NotificationOverview,
        handlerId: Long,
        accept: Boolean,
    ) {
        val config = prefs.load() ?: run {
            updateStatus("Save the connection first.")
            return
        }
        val invite = overview.pendingInvites.firstOrNull { it.id == handlerId } ?: return
        lifecycleScope.launch {
            updateStatus(if (accept) "Accepting invite..." else "Declining invite...")
            val result = withContext(Dispatchers.IO) {
                if (accept) {
                    GuidePawApiClient().acceptDogAccessInvite(config, invite.dogId, invite.id)
                } else {
                    GuidePawApiClient().declineDogAccessInvite(config, invite.dogId, invite.id)
                }
            }
            when (result) {
                is ApiResult.Success -> {
                    updateStatus(if (accept) "Dog access invite accepted." else "Dog access invite declined.")
                    loadNotifications()
                }
                is ApiResult.Failure -> updateStatus("Could not update invite: ${result.message}")
            }
        }
    }

    private fun renderInbox(overview: NotificationOverview) {
        if (overview.notifications.isEmpty()) {
            inboxContainer.addView(TextView(this).apply {
                text = "No visible notifications yet."
            })
            return
        }

        overview.notifications.forEach { notification ->
            inboxContainer.addView(createNotificationCard(notification))
        }
    }

    private fun createNotificationCard(notification: com.guidepaw.companion.model.NotificationRow): LinearLayout {
        val card = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(20, 20, 20, 20)
            layoutParams = LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                bottomMargin = 16
            }
            tag = notification.id
        }

        val selector = CheckBox(this).apply {
            text = "Select"
            isChecked = false
            tag = notification.id
        }
        card.addView(selector)
        card.addView(TextView(this).apply {
            text = notification.title
            textSize = 16f
            setTextColor(resources.getColor(android.R.color.black, theme))
        })
        card.addView(TextView(this).apply {
            text = buildString {
                append(notification.notificationType)
                append(" • ")
                append(notification.priority)
                if (notification.dogName.isNotBlank()) {
                    append(" • ")
                    append(notification.dogName)
                }
                if (notification.isRead) {
                    append(" • Read")
                } else {
                    append(" • Unread")
                }
                if (notification.createdAt.isNotBlank()) {
                    append("\n")
                    append(notification.createdAt)
                }
            }
        })
        if (notification.body.isNotBlank()) {
            card.addView(TextView(this).apply {
                text = notification.body
            })
        }

        val actions = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
        }
        if (notification.actionUrl.isNotBlank()) {
            actions.addView(Button(this@NotificationCenterActivity).apply {
                text = "Open"
                setOnClickListener { openNotificationAction(notification) }
            })
        }
        if (!notification.isRead) {
            actions.addView(Button(this@NotificationCenterActivity).apply {
                text = "Mark read"
                setOnClickListener { markNotificationRead(notification) }
            })
        }
        actions.addView(Button(this@NotificationCenterActivity).apply {
            text = "Delete"
            setOnClickListener { deleteNotification(notification.id) }
        })
        card.addView(actions)
        return card
    }

    private fun openNotificationAction(notification: com.guidepaw.companion.model.NotificationRow) {
        val config = prefs.load() ?: run {
            updateStatus("Save the connection first.")
            return
        }
        val actionUrl = resolveGuidepawUrl(config, notification.actionUrl)
        if (actionUrl.isBlank()) {
            updateStatus("No action URL is attached to this notification.")
            return
        }
        lifecycleScope.launch {
            updateStatus("Opening notification...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().markNotificationRead(config, notification.id)
            }
            when (result) {
                is ApiResult.Success -> {
                    currentOverview = result.data
                    renderOverview(result.data)
                    runCatching {
                        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(actionUrl)))
                    }.onFailure {
                        updateStatus("Marked read, but could not open the link.")
                    }
                }
                is ApiResult.Failure -> updateStatus("Could not mark read: ${result.message}")
            }
        }
    }

    private fun markNotificationRead(notification: com.guidepaw.companion.model.NotificationRow) {
        val config = prefs.load() ?: run {
            updateStatus("Save the connection first.")
            return
        }
        lifecycleScope.launch {
            updateStatus("Marking notification read...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().markNotificationRead(config, notification.id)
            }
            handleOverviewResult(result, "Notification marked read.")
        }
    }

    private fun deleteNotification(notificationId: Long) {
        val config = prefs.load() ?: run {
            updateStatus("Save the connection first.")
            return
        }
        lifecycleScope.launch {
            updateStatus("Deleting notification...")
            val result = withContext(Dispatchers.IO) {
                GuidePawApiClient().deleteNotifications(config, listOf(notificationId))
            }
            handleOverviewResult(result, "Notification deleted.")
        }
    }

    private fun selectedNotificationIds(): List<Long> {
        val ids = mutableListOf<Long>()
        for (i in 0 until inboxContainer.childCount) {
            val row = inboxContainer.getChildAt(i)
            if (row is LinearLayout) {
                val first = row.getChildAt(0)
                if (first is CheckBox && first.isChecked) {
                    val id = (first.tag as? Long) ?: (row.tag as? Long)
                    if (id != null && id > 0L) {
                        ids.add(id)
                    }
                }
            }
        }
        return ids
    }

    private fun resolveGuidepawUrl(config: BridgeConfig, actionUrl: String): String {
        val cleaned = actionUrl.trim()
        if (cleaned.isBlank()) {
            return ""
        }
        if (cleaned.startsWith("http://") || cleaned.startsWith("https://")) {
            return cleaned
        }
        val base = config.endpoint.substringBefore("/api/", config.endpoint).trimEnd('/')
        return if (cleaned.startsWith('/')) {
            base + cleaned
        } else {
            "$base/$cleaned"
        }
    }

    private fun updateStatus(message: String) {
        statusText.text = "Status: $message"
    }
}
