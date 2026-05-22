package com.guidepaw.companion

import android.content.Intent
import android.content.SharedPreferences
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import com.google.android.material.button.MaterialButtonToggleGroup
import com.google.android.material.card.MaterialCardView
import com.google.android.material.progressindicator.LinearProgressIndicator
import java.util.concurrent.Executors

class NotificationCenterActivity : AppCompatActivity() {
    private val api = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()

    private lateinit var prefs: SharedPreferences
    private lateinit var statusView: TextView
    private lateinit var versionView: TextView
    private lateinit var summaryView: TextView
    private lateinit var unreadView: TextView
    private lateinit var progressView: LinearProgressIndicator
    private lateinit var categoryToggle: MaterialButtonToggleGroup
    private lateinit var notificationsContainer: LinearLayout
    private lateinit var invitesContainer: LinearLayout
    private lateinit var prefsStatusView: TextView
    private lateinit var btnSavePrefs: MaterialButton
    private lateinit var btnMarkAllRead: MaterialButton
    private lateinit var btnRefresh: MaterialButton
    private lateinit var btnOpenWeb: MaterialButton

    private var currentToken: String? = null
    private var currentState: GuidePawNotificationsResult? = null

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN = "auth_token"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_notifications)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        bindViews()
        setupUi()
        loadToken()
        renderState()
        refreshNotifications()
    }

    override fun onDestroy() {
        super.onDestroy()
        worker.shutdownNow()
    }

    override fun onResume() {
        super.onResume()
        loadToken()
        renderState()
    }

    private fun bindViews() {
        statusView = findViewById(R.id.statusView)
        versionView = findViewById(R.id.versionView)
        summaryView = findViewById(R.id.summaryView)
        unreadView = findViewById(R.id.unreadView)
        progressView = findViewById(R.id.progressView)
        categoryToggle = findViewById(R.id.categoryToggle)
        notificationsContainer = findViewById(R.id.notificationsContainer)
        invitesContainer = findViewById(R.id.invitesContainer)
        prefsStatusView = findViewById(R.id.prefsStatusView)
        btnSavePrefs = findViewById(R.id.btnSavePrefs)
        btnMarkAllRead = findViewById(R.id.btnMarkAllRead)
        btnRefresh = findViewById(R.id.btnRefresh)
        btnOpenWeb = findViewById(R.id.btnOpenWeb)
    }

    private fun setupUi() {
        versionView.text = "v${CompanionAppVersion.VERSION_NAME}"
        findViewById<MaterialButton>(R.id.btnBack).setOnClickListener { finish() }
        btnRefresh.setOnClickListener { refreshNotifications() }
        btnMarkAllRead.setOnClickListener { markAllRead() }
        btnSavePrefs.setOnClickListener { savePreferences() }
        btnOpenWeb.setOnClickListener { openExternal("https://guidepaw.app/notifications.php") }
    }

    private fun loadToken() {
        currentToken = prefs.getString(KEY_TOKEN, null)
    }

    private fun renderState() {
        val state = currentState
        val token = currentToken
        if (token.isNullOrBlank()) {
            statusView.text = "Sign in first to view notifications."
            btnSavePrefs.isEnabled = false
            btnMarkAllRead.isEnabled = false
            btnRefresh.isEnabled = false
            prefsStatusView.text = "Notification preferences are available after sign in."
            unreadView.text = "Unread: --"
            notificationsContainer.removeAllViews()
            invitesContainer.removeAllViews()
            categoryToggle.clearChecked()
            return
        }

        btnSavePrefs.isEnabled = true
        btnMarkAllRead.isEnabled = true
        btnRefresh.isEnabled = true

        if (state == null) {
            summaryView.text = "Loading notifications..."
            unreadView.text = "Unread: ..."
            prefsStatusView.text = "Tap refresh if the inbox does not load."
            return
        }

        summaryView.text = buildString {
            append("Native inbox for ")
            append(if (state.username.isBlank()) "your account" else state.username)
            state.activeDogId?.let {
                append(" | active dog #")
                append(it)
            }
            if (state.hiddenCount > 0) {
                append(" | ")
                append(state.hiddenCount)
                append(" hidden")
            }
        }
        unreadView.text = "Unread: ${state.unreadCount}  |  Visible unread: ${state.visibleUnreadCount}"
        prefsStatusView.text = "Categories save instantly and keep the inbox focused."
        syncPreferencesToggle(state.preferences)
        rebuildNotificationList(state.notifications)
        rebuildInvitesList(state.pendingInvites)
    }

    private fun syncPreferencesToggle(preferences: Map<String, Boolean>) {
        val access = preferences["access"] ?: true
        val care = preferences["care"] ?: true
        val admin = preferences["admin"] ?: true
        val general = preferences["general"] ?: true
        categoryToggle.clearChecked()
        if (access) categoryToggle.check(R.id.btnPrefAccess)
        if (care) categoryToggle.check(R.id.btnPrefCare)
        if (admin) categoryToggle.check(R.id.btnPrefAdmin)
        if (general) categoryToggle.check(R.id.btnPrefGeneral)
    }

    private fun rebuildNotificationList(notifications: List<GuidePawNotificationItem>) {
        notificationsContainer.removeAllViews()
        if (notifications.isEmpty()) {
            notificationsContainer.addView(makePlainText("No notifications yet."))
            return
        }
        notifications.forEach { notification ->
            notificationsContainer.addView(buildNotificationCard(notification))
        }
    }

    private fun rebuildInvitesList(invites: List<GuidePawNotificationInviteItem>) {
        invitesContainer.removeAllViews()
        if (invites.isEmpty()) {
            invitesContainer.addView(makePlainText("No pending dog access invites."))
            return
        }
        invites.forEach { invite ->
            invitesContainer.addView(buildInviteCard(invite))
        }
    }

    private fun buildNotificationCard(notification: GuidePawNotificationItem): MaterialCardView {
        val card = MaterialCardView(this).apply {
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            ).apply { topMargin = dp(8) }
            radius = dp(16).toFloat()
            cardElevation = 0f
            setCardBackgroundColor(0xFFFFFFFF.toInt())
            strokeColor = 0xFFCBD5E1.toInt()
            strokeWidth = dp(1)
        }
        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(14), dp(14), dp(14), dp(14))
        }
        content.addView(TextView(this).apply {
            text = notification.title
            setTextColor(0xFF0F172A.toInt())
            textSize = 15f
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        })
        val meta = buildString {
            append(notification.category)
            if (notification.dogName.isNotBlank()) {
                append(" • ")
                append(notification.dogName)
            }
            append(" • ")
            append(if (notification.isRead) "read" else "unread")
            if (notification.priority.isNotBlank()) {
                append(" • ")
                append(notification.priority)
            }
        }
        content.addView(TextView(this).apply {
            text = meta
            setTextColor(0xFF64748B.toInt())
            textSize = 11f
            setPadding(0, dp(4), 0, 0)
        })
        if (notification.body.isNotBlank()) {
            content.addView(TextView(this).apply {
                text = notification.body
                setTextColor(0xFF334155.toInt())
                textSize = 13f
                setPadding(0, dp(8), 0, 0)
            })
        }
        if (notification.createdAt.isNotBlank()) {
            content.addView(TextView(this).apply {
                text = notification.createdAt.replace('T', ' ').take(19)
                setTextColor(0xFF94A3B8.toInt())
                textSize = 11f
                setPadding(0, dp(6), 0, 0)
            })
        }
        val actions = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            setPadding(0, dp(10), 0, 0)
        }
        if (notification.actionUrl.isNotBlank()) {
            actions.addView(MaterialButton(this).apply {
                text = "Open"
                layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
                setOnClickListener { openExternal(notification.actionUrl) }
                cornerRadius = dp(14)
            })
        }
        if (!notification.isRead) {
            actions.addView(MaterialButton(this).apply {
                text = "Mark read"
                layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f).apply {
                    if (actions.childCount > 0) marginStart = dp(8)
                }
                setOnClickListener { markSelectedRead(notification.id) }
                cornerRadius = dp(14)
            })
        }
        actions.addView(MaterialButton(this).apply {
            text = "Delete"
            layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f).apply {
                if (actions.childCount > 0) marginStart = dp(8)
            }
            setOnClickListener { deleteSelected(listOf(notification.id)) }
            cornerRadius = dp(14)
            setBackgroundColor(0xFFE2E8F0.toInt())
        })
        content.addView(actions)
        card.addView(content)
        return card
    }

    private fun buildInviteCard(invite: GuidePawNotificationInviteItem): MaterialCardView {
        val card = MaterialCardView(this).apply {
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            ).apply { topMargin = dp(8) }
            radius = dp(16).toFloat()
            cardElevation = 0f
            setCardBackgroundColor(0xFFF8FAFC.toInt())
            strokeColor = 0xFFCBD5E1.toInt()
            strokeWidth = dp(1)
        }
        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(14), dp(14), dp(14), dp(14))
        }
        content.addView(TextView(this).apply {
            text = "Dog access invite"
            setTextColor(0xFF0F172A.toInt())
            textSize = 15f
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        })
        content.addView(TextView(this).apply {
            text = buildString {
                append(invite.dogName.ifBlank { "Dog" })
                if (invite.role.isNotBlank()) {
                    append(" • ")
                    append(invite.role)
                }
                if (invite.permissionLevel.isNotBlank()) {
                    append(" • ")
                    append(invite.permissionLevel)
                }
                if (invite.accessEndsAt.isNotBlank()) {
                    append("\nEnds: ")
                    append(invite.accessEndsAt.take(10))
                }
            }
            setTextColor(0xFF334155.toInt())
            textSize = 13f
            setPadding(0, dp(6), 0, 0)
        })
        content.addView(TextView(this).apply {
            text = listOf(invite.ownerDisplayName, invite.ownerUsername).filter { it.isNotBlank() }.joinToString(" • ")
            setTextColor(0xFF64748B.toInt())
            textSize = 11f
            setPadding(0, dp(6), 0, 0)
        })
        val actions = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            setPadding(0, dp(10), 0, 0)
        }
        actions.addView(MaterialButton(this).apply {
            text = "Accept"
            layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
            setOnClickListener { acceptInvite(invite.handlerId) }
            cornerRadius = dp(14)
        })
        actions.addView(MaterialButton(this).apply {
            text = "Decline"
            layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f).apply {
                marginStart = dp(8)
            }
            setOnClickListener { declineInvite(invite.handlerId) }
            cornerRadius = dp(14)
            setBackgroundColor(0xFFE2E8F0.toInt())
        })
        content.addView(actions)
        card.addView(content)
        return card
    }

    private fun refreshNotifications() {
        val token = currentToken
        if (token.isNullOrBlank()) {
            currentState = null
            renderState()
            return
        }
        setLoading(true, "Loading notification center...")
        worker.execute {
            try {
                currentState = api.notifications(token)
                runOnUiThread {
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not load notifications.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not load notifications.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun savePreferences() {
        val token = currentToken ?: return
        val prefsMap = linkedMapOf(
            "access" to categoryToggle.checkedButtonIds.contains(R.id.btnPrefAccess),
            "care" to categoryToggle.checkedButtonIds.contains(R.id.btnPrefCare),
            "admin" to categoryToggle.checkedButtonIds.contains(R.id.btnPrefAdmin),
            "general" to categoryToggle.checkedButtonIds.contains(R.id.btnPrefGeneral),
        )
        setLoading(true, "Saving notification preferences...")
        worker.execute {
            try {
                currentState = api.saveNotificationPreferences(token, prefsMap)
                runOnUiThread {
                    statusView.text = "Notification preferences saved."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not save preferences.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not save preferences.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun markAllRead() {
        val token = currentToken ?: return
        setLoading(true, "Marking notifications read...")
        worker.execute {
            try {
                currentState = api.markNotificationsRead(token)
                runOnUiThread {
                    statusView.text = "Notifications marked read."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not update notifications.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not update notifications.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun markSelectedRead(notificationId: Int) {
        val token = currentToken ?: return
        setLoading(true, "Marking notification read...")
        worker.execute {
            try {
                currentState = api.markNotificationsRead(token, listOf(notificationId))
                runOnUiThread {
                    statusView.text = "Notification marked read."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not update notification.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not update notification.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun deleteSelected(notificationIds: List<Int>) {
        val token = currentToken ?: return
        setLoading(true, "Deleting notifications...")
        worker.execute {
            try {
                currentState = api.deleteNotifications(token, notificationIds)
                runOnUiThread {
                    statusView.text = "Notification deleted."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not delete notification.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not delete notification.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun acceptInvite(handlerId: Int) {
        val token = currentToken ?: return
        setLoading(true, "Accepting invite...")
        worker.execute {
            try {
                currentState = api.acceptDogAccessInvite(token, handlerId)
                runOnUiThread {
                    statusView.text = "Invite accepted."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not accept invite.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not accept invite.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun declineInvite(handlerId: Int) {
        val token = currentToken ?: return
        setLoading(true, "Declining invite...")
        worker.execute {
            try {
                currentState = api.declineDogAccessInvite(token, handlerId)
                runOnUiThread {
                    statusView.text = "Invite declined."
                    setLoading(false, null)
                    renderState()
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not decline invite.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not decline invite.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun openExternal(url: String) {
        GuidePawNavigation.openUrl(this, url, "GuidePaw")
    }

    private fun setLoading(isLoading: Boolean, message: String?) {
        progressView.visibility = if (isLoading) View.VISIBLE else View.INVISIBLE
        if (message != null) {
            statusView.text = message
        }
    }

    private fun makePlainText(message: String): TextView {
        return TextView(this).apply {
            text = message
            setTextColor(0xFF334155.toInt())
            textSize = 13f
            setPadding(0, dp(6), 0, 0)
        }
    }

    private fun dp(value: Int): Int = (resources.displayMetrics.density * value).toInt()

    private fun friendlyMessage(raw: String?, fallback: String): String {
        val text = raw?.trim().orEmpty()
        if (text.isBlank()) {
            return fallback
        }
        val suspicious = listOf("sqlstate", "pdo", "select ", "insert ", "update ", "delete ", "syntax error").any {
            text.contains(it, ignoreCase = true)
        }
        return if (suspicious) fallback else text
    }
}
