package com.guidepaw.companion

import android.content.SharedPreferences
import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedCard
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import java.util.concurrent.Executors

private val GpNotificationsColorScheme = lightColorScheme(
    primary          = Color(0xFF0D6EFD),
    onPrimary        = Color.White,
    surface          = Color.White,
    onSurface        = Color(0xFF0F172A),
    onSurfaceVariant = Color(0xFF64748B),
)

class NotificationCenterActivity : AppCompatActivity() {

    private val api    = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()

    private lateinit var prefs: SharedPreferences

    private var currentToken: String?                     by mutableStateOf(null)
    private var currentState: GuidePawNotificationsResult? by mutableStateOf(null)
    private var isLoading                                 by mutableStateOf(false)
    private var statusMessage                             by mutableStateOf("Ready")
    private var prefAccess                                by mutableStateOf(true)
    private var prefCare                                  by mutableStateOf(true)
    private var prefAdmin                                 by mutableStateOf(true)
    private var prefGeneral                               by mutableStateOf(true)

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN  = "auth_token"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        loadToken()
        setContent {
            MaterialTheme(colorScheme = GpNotificationsColorScheme) {
                Surface(modifier = Modifier.fillMaxSize(), color = Color(0xFFEEF2F7)) {
                    NotificationsScreen()
                }
            }
        }
        refreshNotifications()
    }

    override fun onResume() {
        super.onResume()
        loadToken()
    }

    override fun onDestroy() {
        super.onDestroy()
        worker.shutdownNow()
    }

    @Composable
    private fun NotificationsScreen() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Brush.verticalGradient(listOf(Color(0xFF0D6EFD), Color(0xFF0E7490))))
                    .padding(vertical = 18.dp),
                contentAlignment = Alignment.Center,
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        "Notification Center",
                        color      = Color.White,
                        fontSize   = 25.sp,
                        fontWeight = FontWeight.Bold,
                    )
                    OutlinedButton(
                        onClick  = { finish() },
                        modifier = Modifier.padding(top = 10.dp),
                        colors   = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                        border   = BorderStroke(1.dp, Color.White),
                    ) { Text("Back") }
                }
            }

            Column(modifier = Modifier.padding(12.dp)) {
                Surface(
                    color    = Color(0xFFDBEAFE),
                    shape    = RoundedCornerShape(4.dp),
                    modifier = Modifier.padding(top = 8.dp),
                ) {
                    Text(
                        "v${CompanionAppVersion.VERSION_NAME}",
                        color      = Color(0xFF0F172A),
                        fontSize   = 12.sp,
                        fontWeight = FontWeight.Bold,
                        modifier   = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                    )
                }

                Text(
                    statusMessage,
                    color    = Color(0xFF334155),
                    fontSize = 12.sp,
                    modifier = Modifier.padding(top = 10.dp),
                )

                if (isLoading) {
                    LinearProgressIndicator(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 6.dp),
                    )
                }

                val token = currentToken
                val state = currentState

                if (token.isNullOrBlank()) {
                    SectionCard("Inbox") {
                        Text(
                            "Sign in first to view notifications.",
                            color    = Color(0xFF334155),
                            fontSize = 13.sp,
                            modifier = Modifier.padding(top = 8.dp),
                        )
                    }
                } else {
                    SectionCard("Inbox snapshot") {
                        if (state != null) {
                            Text(
                                buildString {
                                    append("Native inbox for ")
                                    append(if (state.username.isBlank()) "your account" else state.username)
                                    state.activeDogId?.let { append(" | active dog #$it") }
                                    if (state.hiddenCount > 0) append(" | ${state.hiddenCount} hidden")
                                },
                                color    = Color(0xFF334155),
                                fontSize = 13.sp,
                                modifier = Modifier.padding(top = 6.dp),
                            )
                            Text(
                                "Unread: ${state.unreadCount}  |  Visible unread: ${state.visibleUnreadCount}",
                                color    = Color(0xFF475569),
                                fontSize = 12.sp,
                                modifier = Modifier.padding(top = 6.dp),
                            )
                        } else {
                            Text(
                                "Loading notifications...",
                                color    = Color(0xFF334155),
                                fontSize = 13.sp,
                                modifier = Modifier.padding(top = 6.dp),
                            )
                        }
                    }

                    SectionCard("Notification preferences") {
                        Text(
                            if (state != null) "Categories save instantly and keep the inbox focused."
                            else "Tap refresh if the inbox does not load.",
                            color    = Color(0xFF475569),
                            fontSize = 12.sp,
                            modifier = Modifier.padding(top = 4.dp),
                        )
                        Row(
                            modifier             = Modifier.padding(top = 10.dp),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            FilterChip(selected = prefAccess,  onClick = { prefAccess  = !prefAccess  }, label = { Text("Access")  })
                            FilterChip(selected = prefCare,    onClick = { prefCare    = !prefCare    }, label = { Text("Care")    })
                            FilterChip(selected = prefAdmin,   onClick = { prefAdmin   = !prefAdmin   }, label = { Text("Admin")   })
                            FilterChip(selected = prefGeneral, onClick = { prefGeneral = !prefGeneral }, label = { Text("General") })
                        }
                        Row(
                            modifier              = Modifier
                                .fillMaxWidth()
                                .padding(top = 10.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            Button(
                                onClick  = { savePreferences() },
                                modifier = Modifier.weight(1f),
                            ) { Text("Save preferences") }
                            OutlinedButton(
                                onClick  = { markAllRead() },
                                modifier = Modifier.weight(1f),
                                colors   = ButtonDefaults.outlinedButtonColors(containerColor = Color(0xFFDBEAFE)),
                                border   = BorderStroke(1.dp, Color(0xFFBFDBFE)),
                            ) { Text("Mark all read") }
                        }
                        OutlinedButton(
                            onClick  = { refreshNotifications() },
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(top = 8.dp),
                            colors   = ButtonDefaults.outlinedButtonColors(containerColor = Color(0xFFE2E8F0)),
                            border   = BorderStroke(1.dp, Color(0xFFCBD5E1)),
                        ) { Text("Refresh") }
                        OutlinedButton(
                            onClick  = { openExternal("https://guidepaw.app/notifications.php") },
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(top = 8.dp),
                            colors   = ButtonDefaults.outlinedButtonColors(containerColor = Color(0xFFEEF2FF)),
                            border   = BorderStroke(1.dp, Color(0xFFC7D2FE)),
                        ) { Text("Open web notifications") }
                    }

                    SectionCard("Notifications") {
                        val notifications = state?.notifications ?: emptyList()
                        if (notifications.isEmpty()) {
                            Text("No notifications yet.", color = Color(0xFF334155), fontSize = 13.sp, modifier = Modifier.padding(top = 6.dp))
                        } else {
                            notifications.forEach { NotificationCard(it) }
                        }
                    }

                    SectionCard("Pending invites") {
                        val invites = state?.pendingInvites ?: emptyList()
                        if (invites.isEmpty()) {
                            Text("No pending dog access invites.", color = Color(0xFF334155), fontSize = 13.sp, modifier = Modifier.padding(top = 6.dp))
                        } else {
                            invites.forEach { InviteCard(it) }
                        }
                    }
                }
            }
        }
    }

    @Composable
    private fun SectionCard(title: String, content: @Composable () -> Unit) {
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 12.dp),
            shape    = RoundedCornerShape(18.dp),
            colors   = CardDefaults.outlinedCardColors(containerColor = Color.White),
            border   = BorderStroke(1.dp, Color(0xFFCBD5E1)),
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text(title, color = Color(0xFF0F172A), fontSize = 15.sp, fontWeight = FontWeight.Bold)
                content()
            }
        }
    }

    @Composable
    private fun NotificationCard(notification: GuidePawNotificationItem) {
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp),
            shape    = RoundedCornerShape(16.dp),
            colors   = CardDefaults.outlinedCardColors(containerColor = Color.White),
            border   = BorderStroke(1.dp, Color(0xFFCBD5E1)),
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text(notification.title, color = Color(0xFF0F172A), fontSize = 15.sp, fontWeight = FontWeight.Bold)
                val meta = buildString {
                    append(notification.category)
                    if (notification.dogName.isNotBlank()) { append(" • "); append(notification.dogName) }
                    append(" • ")
                    append(if (notification.isRead) "read" else "unread")
                    if (notification.priority.isNotBlank()) { append(" • "); append(notification.priority) }
                }
                Text(meta, color = Color(0xFF64748B), fontSize = 11.sp, modifier = Modifier.padding(top = 4.dp))
                if (notification.body.isNotBlank()) {
                    Text(notification.body, color = Color(0xFF334155), fontSize = 13.sp, modifier = Modifier.padding(top = 8.dp))
                }
                if (notification.createdAt.isNotBlank()) {
                    Text(
                        notification.createdAt.replace('T', ' ').take(19),
                        color    = Color(0xFF94A3B8),
                        fontSize = 11.sp,
                        modifier = Modifier.padding(top = 6.dp),
                    )
                }
                Row(
                    modifier              = Modifier.padding(top = 10.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (notification.actionUrl.isNotBlank()) {
                        Button(onClick = { openExternal(notification.actionUrl) }, modifier = Modifier.weight(1f)) { Text("Open") }
                    }
                    if (!notification.isRead) {
                        Button(onClick = { markSelectedRead(notification.id) }, modifier = Modifier.weight(1f)) { Text("Mark read") }
                    }
                    OutlinedButton(
                        onClick  = { deleteSelected(listOf(notification.id)) },
                        modifier = Modifier.weight(1f),
                        colors   = ButtonDefaults.outlinedButtonColors(containerColor = Color(0xFFE2E8F0)),
                    ) { Text("Delete") }
                }
            }
        }
    }

    @Composable
    private fun InviteCard(invite: GuidePawNotificationInviteItem) {
        OutlinedCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp),
            shape    = RoundedCornerShape(16.dp),
            colors   = CardDefaults.outlinedCardColors(containerColor = Color(0xFFF8FAFC)),
            border   = BorderStroke(1.dp, Color(0xFFCBD5E1)),
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text("Dog access invite", color = Color(0xFF0F172A), fontSize = 15.sp, fontWeight = FontWeight.Bold)
                Text(
                    buildString {
                        append(invite.dogName.ifBlank { "Dog" })
                        if (invite.role.isNotBlank()) { append(" • "); append(invite.role) }
                        if (invite.permissionLevel.isNotBlank()) { append(" • "); append(invite.permissionLevel) }
                        if (invite.accessEndsAt.isNotBlank()) { append("\nEnds: "); append(invite.accessEndsAt.take(10)) }
                    },
                    color    = Color(0xFF334155),
                    fontSize = 13.sp,
                    modifier = Modifier.padding(top = 6.dp),
                )
                Text(
                    listOf(invite.ownerDisplayName, invite.ownerUsername).filter { it.isNotBlank() }.joinToString(" • "),
                    color    = Color(0xFF64748B),
                    fontSize = 11.sp,
                    modifier = Modifier.padding(top = 6.dp),
                )
                Row(
                    modifier              = Modifier.padding(top = 10.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Button(onClick = { acceptInvite(invite.handlerId) }, modifier = Modifier.weight(1f)) { Text("Accept") }
                    OutlinedButton(
                        onClick  = { declineInvite(invite.handlerId) },
                        modifier = Modifier.weight(1f),
                        colors   = ButtonDefaults.outlinedButtonColors(containerColor = Color(0xFFE2E8F0)),
                    ) { Text("Decline") }
                }
            }
        }
    }

    private fun loadToken() {
        currentToken = prefs.getString(KEY_TOKEN, null)
        if (currentToken.isNullOrBlank()) statusMessage = "Sign in first to view notifications."
    }

    private fun syncPreferenceState(preferences: Map<String, Boolean>) {
        prefAccess  = preferences["access"]  ?: true
        prefCare    = preferences["care"]    ?: true
        prefAdmin   = preferences["admin"]   ?: true
        prefGeneral = preferences["general"] ?: true
    }

    private fun refreshNotifications() {
        val token = currentToken
        if (token.isNullOrBlank()) { currentState = null; return }
        isLoading = true
        statusMessage = "Loading notification center..."
        worker.execute {
            try {
                val result = api.notifications(token)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Ready"
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not load notifications."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not load notifications."); isLoading = false }
            }
        }
    }

    private fun savePreferences() {
        val token = currentToken ?: return
        val prefsMap = linkedMapOf(
            "access"  to prefAccess,
            "care"    to prefCare,
            "admin"   to prefAdmin,
            "general" to prefGeneral,
        )
        isLoading = true
        statusMessage = "Saving notification preferences..."
        worker.execute {
            try {
                val result = api.saveNotificationPreferences(token, prefsMap)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Notification preferences saved."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not save preferences."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not save preferences."); isLoading = false }
            }
        }
    }

    private fun markAllRead() {
        val token = currentToken ?: return
        isLoading = true
        statusMessage = "Marking notifications read..."
        worker.execute {
            try {
                val result = api.markNotificationsRead(token)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Notifications marked read."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not update notifications."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not update notifications."); isLoading = false }
            }
        }
    }

    private fun markSelectedRead(notificationId: Int) {
        val token = currentToken ?: return
        isLoading = true
        statusMessage = "Marking notification read..."
        worker.execute {
            try {
                val result = api.markNotificationsRead(token, listOf(notificationId))
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Notification marked read."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not update notification."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not update notification."); isLoading = false }
            }
        }
    }

    private fun deleteSelected(notificationIds: List<Int>) {
        val token = currentToken ?: return
        isLoading = true
        statusMessage = "Deleting notifications..."
        worker.execute {
            try {
                val result = api.deleteNotifications(token, notificationIds)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Notification deleted."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not delete notification."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not delete notification."); isLoading = false }
            }
        }
    }

    private fun acceptInvite(handlerId: Int) {
        val token = currentToken ?: return
        isLoading = true
        statusMessage = "Accepting invite..."
        worker.execute {
            try {
                val result = api.acceptDogAccessInvite(token, handlerId)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Invite accepted."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not accept invite."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not accept invite."); isLoading = false }
            }
        }
    }

    private fun declineInvite(handlerId: Int) {
        val token = currentToken ?: return
        isLoading = true
        statusMessage = "Declining invite..."
        worker.execute {
            try {
                val result = api.declineDogAccessInvite(token, handlerId)
                runOnUiThread {
                    currentState = result
                    syncPreferenceState(result.preferences)
                    statusMessage = "Invite declined."
                    isLoading = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not decline invite."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not decline invite."); isLoading = false }
            }
        }
    }

    private fun openExternal(url: String) {
        GuidePawNavigation.openUrl(this, url, "GuidePaw", currentToken)
    }

    private fun friendlyMessage(raw: String?, fallback: String): String {
        val text = raw?.trim().orEmpty()
        if (text.isBlank()) return fallback
        val suspicious = listOf("sqlstate", "pdo", "select ", "insert ", "update ", "delete ", "syntax error").any {
            text.contains(it, ignoreCase = true)
        }
        return if (suspicious) fallback else text
    }
}
