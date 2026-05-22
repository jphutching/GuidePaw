package com.guidepaw.companion

import android.content.ContentResolver
import android.net.Uri
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedOutputStream
import java.io.BufferedReader
import java.io.DataOutputStream
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import java.util.UUID

data class GuidePawLoginResult(
    val success: Boolean,
    val token: String? = null,
    val requiresTwoFactor: Boolean = false,
    val message: String? = null,
)

data class GuidePawAppReleaseResult(
    val appName: String,
    val versionName: String,
    val versionCode: Int,
    val apkUrl: String,
    val apkFile: String,
    val releaseNotes: String?,
    val publishedAt: String?,
)

data class GuidePawMeResult(
    val username: String,
    val activeDogId: Int?,
)

data class GuidePawDogItem(
    val id: Int,
    val name: String,
    val breed: String?,
    val ownerUsername: String?,
    val accessRole: String?,
    val lifecycleStatus: String?,
)

data class GuidePawLogItem(
    val id: Int,
    val logDate: String,
    val locationName: String,
    val locationCityState: String?,
    val locationType: String?,
    val focusLevel: Int,
    val skillsPracticed: List<String>,
    val handlerNotes: String,
)

data class GuidePawLogsResult(
    val activeDogId: Int?,
    val dogId: Int?,
    val logs: List<GuidePawLogItem>,
    val trainingSuggestions: List<String>,
)

data class GuidePawSaveLogResult(
    val logId: Int?,
    val message: String?,
    val trainingSuggestions: List<String>,
)

data class GuidePawFeedbackAttachmentInput(
    val uri: Uri,
    val displayName: String,
    val mimeType: String,
)

data class GuidePawFeedbackResult(
    val feedbackId: Int?,
    val message: String?,
    val uploadDebug: List<String>,
)

data class GuidePawNotificationItem(
    val id: Int,
    val relatedDogId: Int,
    val dogName: String,
    val notificationType: String,
    val category: String,
    val priority: String,
    val title: String,
    val body: String,
    val actionUrl: String,
    val isRead: Boolean,
    val createdAt: String,
    val readAt: String,
)

data class GuidePawNotificationInviteItem(
    val handlerId: Int,
    val dogId: Int,
    val dogName: String,
    val role: String,
    val permissionLevel: String,
    val accessEndsAt: String,
    val ownerUsername: String,
    val ownerDisplayName: String,
)

data class GuidePawNotificationsResult(
    val username: String,
    val activeDogId: Int?,
    val unreadCount: Int,
    val visibleUnreadCount: Int,
    val hiddenCount: Int,
    val preferences: Map<String, Boolean>,
    val notifications: List<GuidePawNotificationItem>,
    val pendingInvites: List<GuidePawNotificationInviteItem>,
)

data class GuidePawWearableCatalogItem(
    val slug: String,
    val label: String,
    val vendor: String,
    val pairingMode: String,
    val dataFocus: String,
    val notes: String,
    val deviceType: String,
)

data class GuidePawWearableEvent(
    val id: Int,
    val dogId: Int?,
    val dogName: String,
    val source: String,
    val deviceName: String,
    val recordedForDate: String,
    val steps: Int?,
    val activeMinutes: Int?,
    val restMinutes: Int?,
    val playMinutes: Int?,
    val distanceMiles: Double?,
    val totalCaloriesBurned: Double?,
    val activityIntensityMinutes: Int?,
    val avgHeartRate: Int?,
    val restingHeartRate: Int?,
    val sleepHours: Double?,
    val batteryPercent: Int?,
    val summaryText: String,
    val notes: String,
    val createdAt: String,
)

data class GuidePawWearableSetup(
    val handlerWearableSlug: String?,
    val dogTrackerSlug: String?,
    val syncMode: String?,
    val notes: String?,
    val handlerWearableLabel: String?,
    val handlerWearableVendor: String?,
    val handlerWearablePairingMode: String?,
    val handlerWearableDataFocus: String?,
    val dogTrackerLabel: String?,
    val dogTrackerVendor: String?,
    val dogTrackerPairingMode: String?,
    val dogTrackerDataFocus: String?,
    val syncModeLabel: String?,
)

data class GuidePawSimpleLabelNotes(
    val label: String,
    val notes: String,
)

data class GuidePawWearableResult(
    val activeDogId: Int?,
    val dogId: Int?,
    val currentSetup: GuidePawWearableSetup?,
    val summary: Map<String, Any?>,
    val handlerWearables: List<GuidePawWearableCatalogItem>,
    val dogTrackers: List<GuidePawWearableCatalogItem>,
    val syncModes: Map<String, GuidePawSimpleLabelNotes>,
    val recentEvents: List<GuidePawWearableEvent>,
    val dogs: List<GuidePawDogItem>,
)

data class GuidePawWearableSaveResult(
    val message: String?,
    val eventId: Int? = null,
)

class GuidePawApiException(
    val statusCode: Int,
    message: String,
    val payload: JSONObject? = null,
) : RuntimeException(message)

class GuidePawApiClient(
    private val baseUrl: String = "https://guidepaw.app/",
) {
    fun login(
        username: String,
        password: String,
        tokenLabel: String,
        totpCode: String?,
        recoveryKey: String?,
    ): GuidePawLoginResult {
        val payload = JSONObject()
            .put("username", username)
            .put("password", password)
            .put("token_label", tokenLabel)
        if (!totpCode.isNullOrBlank()) {
            payload.put("totp_code", totpCode.trim())
        }
        if (!recoveryKey.isNullOrBlank()) {
            payload.put("recovery_key", recoveryKey.trim())
        }
        val response = requestJson("api/login.php", "POST", null, payload)
        val message = response.json.optText("message")
        val success = response.json.optBoolean("success", false)
        val requiresTwoFactor = response.json.optBoolean("requires_2fa", false)
        val token = response.json.optText("token")
        return GuidePawLoginResult(
            success = success,
            token = token,
            requiresTwoFactor = requiresTwoFactor,
            message = message,
        )
    }

    fun appRelease(): GuidePawAppReleaseResult {
        val response = requestJson("api/app_release.php", "GET", null, null)
        ensureSuccess(response)
        return GuidePawAppReleaseResult(
            appName = response.json.optString("app_name", "GuidePaw Companion"),
            versionName = response.json.optString("version_name", "0.000"),
            versionCode = response.json.optInt("version_code", 0),
            apkUrl = response.json.optString("apk_url", ""),
            apkFile = response.json.optString("apk_file", "GuidePaw_Companion.apk"),
            releaseNotes = response.json.optText("release_notes"),
            publishedAt = response.json.optText("published_at"),
        )
    }

    fun me(token: String): GuidePawMeResult {
        val response = requestJson("api/me.php", "GET", token, null)
        ensureSuccess(response)
        val user = response.json.optJSONObject("user") ?: JSONObject()
        return GuidePawMeResult(
            username = user.optString("username", ""),
            activeDogId = optNullableInt(response.json, "active_dog_id"),
        )
    }

    fun dogs(token: String): List<GuidePawDogItem> {
        val response = requestJson("api/dogs.php", "GET", token, null)
        ensureSuccess(response)
        val dogsArray = response.json.optJSONArray("dogs") ?: JSONArray()
        return dogsArray.toList().mapNotNull { element ->
            val obj = element as? JSONObject ?: return@mapNotNull null
            GuidePawDogItem(
                id = obj.optInt("id", 0),
                name = obj.optString("name", "Dog"),
                breed = obj.optText("breed") ?: obj.optText("breed_name"),
                ownerUsername = obj.optText("owner_username"),
                accessRole = obj.optText("access_role"),
                lifecycleStatus = obj.optText("lifecycle_status"),
            )
        }
    }

    fun setActiveDog(token: String, dogId: Int): Int? {
        val payload = JSONObject()
            .put("action", "set_active_dog")
            .put("dog_id", dogId)
        val response = requestJson("api/dogs.php", "POST", token, payload)
        ensureSuccess(response)
        return optNullableInt(response.json, "active_dog_id")
    }

    fun logs(token: String, dogId: Int?): GuidePawLogsResult {
        val query = if (dogId != null && dogId > 0) {
            "api/logs.php?dog_id=$dogId"
        } else {
            "api/logs.php"
        }
        val response = requestJson(query, "GET", token, null)
        ensureSuccess(response)
        val activeDogId = optNullableInt(response.json, "active_dog_id")
        val resolvedDogId = optNullableInt(response.json, "dog_id")
        val suggestions = response.json.optJSONArray("training_suggestions")?.toStringList().orEmpty()
        val logsArray = response.json.optJSONArray("logs") ?: JSONArray()
        val logs = logsArray.toList().mapNotNull { element ->
            val obj = element as? JSONObject ?: return@mapNotNull null
            GuidePawLogItem(
                id = obj.optInt("id", 0),
                logDate = obj.optString("log_date", ""),
                locationName = obj.optString("location_name", ""),
                locationCityState = obj.optText("location_city_state"),
                locationType = obj.optText("location_type"),
                focusLevel = obj.optInt("focus_level", 3),
                skillsPracticed = obj.optJSONArray("skills_practiced")?.toStringList().orEmpty(),
                handlerNotes = obj.optString("handler_notes", ""),
            )
        }
        return GuidePawLogsResult(
            activeDogId = activeDogId,
            dogId = resolvedDogId,
            logs = logs,
            trainingSuggestions = suggestions,
        )
    }

    fun saveLog(
        token: String,
        dogId: Int,
        logId: Int?,
        locationName: String,
        cityState: String,
        locationType: String,
        focusLevel: Int,
        skills: List<String>,
        notes: String,
    ): GuidePawSaveLogResult {
        val payload = JSONObject()
            .put("dog_id", dogId)
            .put("location_name", locationName)
            .put("location_city_state", cityState)
            .put("location_type", locationType)
            .put("focus_level", focusLevel)
            .put("skills", JSONArray(skills))
            .put("handler_notes", notes)
        if (logId != null && logId > 0) {
            payload.put("id", logId)
        }
        val response = requestJson("api/logs.php", "POST", token, payload)
        ensureSuccess(response)
        return GuidePawSaveLogResult(
            logId = optNullableInt(response.json, "log_id"),
            message = response.json.optText("message"),
            trainingSuggestions = response.json.optJSONArray("training_suggestions")?.toStringList().orEmpty(),
        )
    }

    fun submitFeedback(
        token: String,
        category: String,
        pageWorkflow: String,
        contactEmail: String,
        details: String,
        sourceVersion: String,
        sourceDevice: String,
        attachments: List<GuidePawFeedbackAttachmentInput>,
        contentResolver: ContentResolver,
    ): GuidePawFeedbackResult {
        val fields = linkedMapOf(
            "category" to category,
            "page_workflow" to pageWorkflow,
            "contact_email" to contactEmail,
            "details" to details,
            "source_version" to sourceVersion,
            "source_device" to sourceDevice,
        )
        val response = requestMultipartJson("api/feedback.php", token, fields, attachments, contentResolver)
        ensureSuccess(response)
        return GuidePawFeedbackResult(
            feedbackId = optNullableInt(response.json, "feedback_id"),
            message = response.json.optText("message"),
            uploadDebug = response.json.optJSONArray("upload_debug")?.toStringList().orEmpty(),
        )
    }

    fun notifications(token: String): GuidePawNotificationsResult {
        val response = requestJson("api/notifications.php", "GET", token, null)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun saveNotificationPreferences(token: String, categories: Map<String, Boolean>): GuidePawNotificationsResult {
        val payload = JSONObject()
            .put("action", "save_notification_preferences")
            .put("categories", JSONObject(categories))
        val response = requestJson("api/notifications.php", "POST", token, payload)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun markNotificationsRead(token: String, notificationIds: List<Int> = emptyList()): GuidePawNotificationsResult {
        val payload = JSONObject().put("action", "mark_all_read")
        if (notificationIds.isNotEmpty()) {
            payload.put("action", "mark_read")
            payload.put("notification_ids", JSONArray(notificationIds))
        }
        val response = requestJson("api/notifications.php", "POST", token, payload)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun deleteNotifications(token: String, notificationIds: List<Int>): GuidePawNotificationsResult {
        val payload = JSONObject()
            .put("action", "delete_selected_notifications")
            .put("notification_ids", JSONArray(notificationIds))
        val response = requestJson("api/notifications.php", "POST", token, payload)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun acceptDogAccessInvite(token: String, handlerId: Int): GuidePawNotificationsResult {
        val payload = JSONObject()
            .put("action", "accept_dog_access_invite")
            .put("handler_id", handlerId)
        val response = requestJson("api/notifications.php", "POST", token, payload)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun declineDogAccessInvite(token: String, handlerId: Int): GuidePawNotificationsResult {
        val payload = JSONObject()
            .put("action", "decline_dog_access_invite")
            .put("handler_id", handlerId)
        val response = requestJson("api/notifications.php", "POST", token, payload)
        ensureSuccess(response)
        return parseNotificationsResult(response.json)
    }

    fun wearables(token: String, dogId: Int? = null): GuidePawWearableResult {
        val query = if (dogId != null && dogId > 0) {
            "api/wearables.php?dog_id=$dogId"
        } else {
            "api/wearables.php"
        }
        val response = requestJson(query, "GET", token, null)
        ensureSuccess(response)
        return GuidePawWearableResult(
            activeDogId = optNullableInt(response.json, "active_dog_id"),
            dogId = optNullableInt(response.json, "dog_id"),
            currentSetup = response.json.optJSONObject("current_setup")?.let { setup ->
                GuidePawWearableSetup(
                    handlerWearableSlug = setup.optText("handler_wearable_slug"),
                    dogTrackerSlug = setup.optText("dog_tracker_slug"),
                    syncMode = setup.optText("sync_mode"),
                    notes = setup.optText("notes"),
                    handlerWearableLabel = setup.optText("handler_wearable_label"),
                    handlerWearableVendor = setup.optText("handler_wearable_vendor"),
                    handlerWearablePairingMode = setup.optText("handler_wearable_pairing_mode"),
                    handlerWearableDataFocus = setup.optText("handler_wearable_data_focus"),
                    dogTrackerLabel = setup.optText("dog_tracker_label"),
                    dogTrackerVendor = setup.optText("dog_tracker_vendor"),
                    dogTrackerPairingMode = setup.optText("dog_tracker_pairing_mode"),
                    dogTrackerDataFocus = setup.optText("dog_tracker_data_focus"),
                    syncModeLabel = setup.optText("sync_mode_label"),
                )
            },
            summary = response.json.optJSONObject("summary").toSimpleMap(),
            handlerWearables = response.json.optJSONArray("handler_wearables")?.toWearableCatalogList().orEmpty(),
            dogTrackers = response.json.optJSONArray("dog_trackers")?.toWearableCatalogList().orEmpty(),
            syncModes = response.json.optJSONObject("sync_modes")?.toSyncModeMap().orEmpty(),
            recentEvents = response.json.optJSONArray("recent_events")?.toWearableEventList().orEmpty(),
            dogs = response.json.optJSONArray("dogs")?.toDogList().orEmpty(),
        )
    }

    fun saveWearableSetup(
        token: String,
        dogId: Int,
        handlerWearableSlug: String,
        dogTrackerSlug: String,
        syncMode: String,
        notes: String,
    ): GuidePawWearableSaveResult {
        val payload = JSONObject()
            .put("action", "save_setup")
            .put("dog_id", dogId)
            .put("handler_wearable_slug", handlerWearableSlug)
            .put("dog_tracker_slug", dogTrackerSlug)
            .put("sync_mode", syncMode)
            .put("notes", notes)
        val response = requestJson("api/wearables.php", "POST", token, payload)
        ensureSuccess(response)
        return GuidePawWearableSaveResult(
            message = response.json.optText("message"),
        )
    }

    private fun requestJson(path: String, method: String, token: String?, body: JSONObject?): ApiResponse {
        val resolvedPath = if (token.isNullOrBlank()) {
            path
        } else {
            val separator = if (path.contains('?')) '&' else '?'
            path + separator + "access_token=" + URLEncoder.encode(token, StandardCharsets.UTF_8.name())
        }
        val endpoint = URL(baseUrl.trimEnd('/') + "/" + resolvedPath.trimStart('/'))
        val connection = (endpoint.openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15_000
            readTimeout = 20_000
            doInput = true
            setRequestProperty("Accept", "application/json")
            if (!token.isNullOrBlank()) {
                setRequestProperty("Authorization", "Bearer $token")
                setRequestProperty("X-API-TOKEN", token)
            }
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json; charset=utf-8")
                outputStream.use { os ->
                    os.write(body.toString().toByteArray(StandardCharsets.UTF_8))
                }
            }
        }

        val status = connection.responseCode
        val responseText = connection.responseText()
        val json = try {
            if (responseText.isBlank()) JSONObject() else JSONObject(responseText)
        } catch (_: Throwable) {
            JSONObject().put("raw", responseText)
        }
        connection.disconnect()
        return ApiResponse(status, json, responseText)
    }

    private fun requestMultipartJson(
        path: String,
        token: String?,
        fields: Map<String, String>,
        attachments: List<GuidePawFeedbackAttachmentInput>,
        contentResolver: ContentResolver,
    ): ApiResponse {
        val resolvedPath = if (token.isNullOrBlank()) {
            path
        } else {
            val separator = if (path.contains('?')) '&' else '?'
            path + separator + "access_token=" + URLEncoder.encode(token, StandardCharsets.UTF_8.name())
        }
        val endpoint = URL(baseUrl.trimEnd('/') + "/" + resolvedPath.trimStart('/'))
        val boundary = "----GuidePaw" + UUID.randomUUID().toString().replace("-", "")
        val connection = (endpoint.openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 15_000
            readTimeout = 20_000
            doInput = true
            doOutput = true
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
            if (!token.isNullOrBlank()) {
                setRequestProperty("Authorization", "Bearer $token")
                setRequestProperty("X-API-TOKEN", token)
            }
            setChunkedStreamingMode(0)
        }

        DataOutputStream(BufferedOutputStream(connection.outputStream)).use { out ->
            fields.forEach { (key, value) ->
                writeMultipartField(out, boundary, key, value)
            }
            attachments.forEachIndexed { index, attachment ->
                writeMultipartFile(out, boundary, "attachments[]", attachment, contentResolver)
            }
            out.writeBytes("--$boundary--\r\n")
            out.flush()
        }

        val status = connection.responseCode
        val responseText = connection.responseText()
        val json = try {
            if (responseText.isBlank()) JSONObject() else JSONObject(responseText)
        } catch (_: Throwable) {
            JSONObject().put("raw", responseText)
        }
        connection.disconnect()
        return ApiResponse(status, json, responseText)
    }

    private fun ensureSuccess(response: ApiResponse) {
        if (response.statusCode in 200..299 && response.json.optBoolean("success", true)) {
            return
        }
        throw GuidePawApiException(
            statusCode = response.statusCode,
            message = sanitizeMessage(response.json.optString("message", "Request failed"), "Request failed"),
            payload = response.json,
        )
    }

    private fun HttpURLConnection.responseText(): String {
        val stream = runCatching { if (responseCode >= 400) errorStream else inputStream }.getOrNull() ?: return ""
        return stream.use { input ->
            BufferedReader(InputStreamReader(input, StandardCharsets.UTF_8)).use { it.readText() }
        }
    }

    private fun writeMultipartField(out: DataOutputStream, boundary: String, name: String, value: String) {
        out.writeBytes("--$boundary\r\n")
        out.writeBytes("Content-Disposition: form-data; name=\"$name\"\r\n")
        out.writeBytes("Content-Type: text/plain; charset=utf-8\r\n\r\n")
        out.write(value.toByteArray(StandardCharsets.UTF_8))
        out.writeBytes("\r\n")
    }

    private fun writeMultipartFile(
        out: DataOutputStream,
        boundary: String,
        name: String,
        attachment: GuidePawFeedbackAttachmentInput,
        contentResolver: ContentResolver,
    ) {
        val mimeType = attachment.mimeType.ifBlank { "application/octet-stream" }
        out.writeBytes("--$boundary\r\n")
        out.writeBytes("Content-Disposition: form-data; name=\"$name\"; filename=\"${sanitizeFileName(attachment.displayName)}\"\r\n")
        out.writeBytes("Content-Type: $mimeType\r\n\r\n")
        val input = contentResolver.openInputStream(attachment.uri)
            ?: throw IllegalStateException("Could not open attachment ${attachment.displayName}")
        input.use { stream ->
            stream.copyTo(out, 8192)
        }
        out.writeBytes("\r\n")
    }

    private fun optNullableInt(json: JSONObject, key: String): Int? {
        return if (json.has(key) && !json.isNull(key)) {
            val value = json.optString(key, "").trim()
            if (value.isNotBlank()) value.toIntOrNull() ?: json.optInt(key, 0).takeIf { it > 0 } else null
        } else {
            null
        }
    }

    private fun optNullableDouble(json: JSONObject, key: String): Double? {
        return if (json.has(key) && !json.isNull(key)) {
            val value = json.optString(key, "").trim()
            if (value.isNotBlank()) value.toDoubleOrNull() else null
        } else {
            null
        }
    }

    private fun JSONObject.optText(key: String): String? {
        return optString(key, "").trim().takeIf { it.isNotBlank() }
    }

    private fun JSONObject.toSimpleMap(): Map<String, Any?> {
        val map = linkedMapOf<String, Any?>()
        val keys = keys()
        while (keys.hasNext()) {
            val key = keys.next()
            map[key] = when (val value = opt(key)) {
                is JSONObject -> value.toSimpleMap()
                is JSONArray -> value.toList().mapNotNull { element ->
                    when (element) {
                        is JSONObject -> element.toSimpleMap()
                        JSONObject.NULL -> null
                        else -> element
                    }
                }
                JSONObject.NULL -> null
                else -> value
            }
        }
        return map
    }

    private fun parseNotificationsResult(json: JSONObject): GuidePawNotificationsResult {
        val prefs = json.optJSONObject("preferences")?.toBooleanMap().orEmpty()
        val notifications = json.optJSONArray("notifications")?.toNotificationList().orEmpty()
        val invites = json.optJSONArray("pending_invites")?.toInviteList().orEmpty()
        return GuidePawNotificationsResult(
            username = json.optString("username", ""),
            activeDogId = optNullableInt(json, "active_dog_id"),
            unreadCount = json.optInt("unread_count", 0),
            visibleUnreadCount = json.optInt("visible_unread_count", 0),
            hiddenCount = json.optInt("hidden_count", 0),
            preferences = prefs,
            notifications = notifications,
            pendingInvites = invites,
        )
    }

    private fun JSONArray.toWearableCatalogList(): List<GuidePawWearableCatalogItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GuidePawWearableCatalogItem(
                slug = obj.optString("slug", ""),
                label = obj.optString("label", ""),
                vendor = obj.optString("vendor", ""),
                pairingMode = obj.optString("pairing_mode", ""),
                dataFocus = obj.optString("data_focus", ""),
                notes = obj.optString("notes", ""),
                deviceType = obj.optString("device_type", ""),
            )
        }
    }

    private fun JSONObject.toSyncModeMap(): Map<String, GuidePawSimpleLabelNotes> {
        val map = linkedMapOf<String, GuidePawSimpleLabelNotes>()
        val keys = keys()
        while (keys.hasNext()) {
            val key = keys.next()
            val obj = optJSONObject(key) ?: continue
            map[key] = GuidePawSimpleLabelNotes(
                label = obj.optString("label", key),
                notes = obj.optString("notes", ""),
            )
        }
        return map
    }

    private fun JSONArray.toWearableEventList(): List<GuidePawWearableEvent> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GuidePawWearableEvent(
                id = obj.optInt("id", 0),
                dogId = optNullableInt(obj, "dog_id"),
                dogName = obj.optString("dog_name", ""),
                source = obj.optString("source", ""),
                deviceName = obj.optString("device_name", ""),
                recordedForDate = obj.optString("recorded_for_date", ""),
                steps = optNullableInt(obj, "steps"),
                activeMinutes = optNullableInt(obj, "active_minutes"),
                restMinutes = optNullableInt(obj, "rest_minutes"),
                playMinutes = optNullableInt(obj, "play_minutes"),
                distanceMiles = optNullableDouble(obj, "distance_miles"),
                totalCaloriesBurned = optNullableDouble(obj, "total_calories_burned"),
                activityIntensityMinutes = optNullableInt(obj, "activity_intensity_minutes"),
                avgHeartRate = optNullableInt(obj, "avg_heart_rate"),
                restingHeartRate = optNullableInt(obj, "resting_heart_rate"),
                sleepHours = optNullableDouble(obj, "sleep_hours"),
                batteryPercent = optNullableInt(obj, "battery_percent"),
                summaryText = obj.optString("summary_text", ""),
                notes = obj.optString("notes", ""),
                createdAt = obj.optString("created_at", ""),
            )
        }
    }

    private fun JSONArray.toNotificationList(): List<GuidePawNotificationItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GuidePawNotificationItem(
                id = obj.optInt("id", 0),
                relatedDogId = obj.optInt("related_dog_id", 0),
                dogName = obj.optString("dog_name", ""),
                notificationType = obj.optString("notification_type", ""),
                category = obj.optString("category", "general"),
                priority = obj.optString("priority", "normal"),
                title = obj.optString("title", ""),
                body = obj.optString("body", ""),
                actionUrl = obj.optString("action_url", ""),
                isRead = obj.optBoolean("is_read", false),
                createdAt = obj.optString("created_at", ""),
                readAt = obj.optString("read_at", ""),
            )
        }
    }

    private fun JSONArray.toInviteList(): List<GuidePawNotificationInviteItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GuidePawNotificationInviteItem(
                handlerId = obj.optInt("id", 0),
                dogId = obj.optInt("dog_id", 0),
                dogName = obj.optString("dog_name", ""),
                role = obj.optText("role") ?: "",
                permissionLevel = obj.optText("permission_level") ?: "",
                accessEndsAt = obj.optText("access_ends_at") ?: "",
                ownerUsername = obj.optText("owner_username") ?: "",
                ownerDisplayName = obj.optText("owner_display_name") ?: "",
            )
        }
    }

    private fun JSONObject.toBooleanMap(): Map<String, Boolean> {
        val map = linkedMapOf<String, Boolean>()
        val keys = keys()
        while (keys.hasNext()) {
            val key = keys.next()
            map[key] = optBoolean(key, true)
        }
        return map
    }

    private fun JSONArray.toDogList(): List<GuidePawDogItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GuidePawDogItem(
                id = obj.optInt("id", 0),
                name = obj.optString("name", "Dog"),
                breed = obj.optText("breed") ?: obj.optText("breed_name"),
                ownerUsername = obj.optText("owner_username"),
                accessRole = obj.optText("access_role"),
                lifecycleStatus = obj.optText("lifecycle_status"),
            )
        }
    }

    private fun JSONArray.toStringList(): List<String> {
        return (0 until length()).mapNotNull { idx ->
            val value = opt(idx)
            when (value) {
                is String -> value.takeIf { it.isNotBlank() }
                is JSONObject -> value.optText("title")
                    ?: value.optText("name")
                    ?: value.optText("label")
                    ?: value.toString().takeIf { it.isNotBlank() }
                else -> value?.toString()?.takeIf { it.isNotBlank() }
            }
        }
    }

    private fun sanitizeMessage(message: String?, fallback: String): String {
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

    private fun JSONArray.toList(): List<Any?> = (0 until length()).map { opt(it) }

    private fun sanitizeFileName(name: String): String {
        return name.trim().ifBlank { "feedback_attachment" }.replace(Regex("[\\r\\n\"]"), "_")
    }

    private data class ApiResponse(
        val statusCode: Int,
        val json: JSONObject,
        val rawText: String,
    )
}
