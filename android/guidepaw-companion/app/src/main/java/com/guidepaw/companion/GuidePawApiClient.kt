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
    val nextAppointmentAt: String? = null,
    val nextAppointmentTitle: String? = null,
    val nextRefillDate: String? = null,
    val nextRefillMedName: String? = null,
    val activeMedCount: Int = 0,
)

data class GpDogAccessHandler(
    val id: Int,
    val userId: Int,
    val username: String,
    val displayName: String,
    val role: String,
    val permissionLevel: String,
    val accessEndsAt: String,
    val accessStatus: String,
)
data class GpDogAccessInvite(
    val id: Int,
    val dogId: Int,
    val dogName: String,
    val ownerUsername: String,
    val role: String,
    val accessStatus: String,
)
data class GpDogAccessTransfer(
    val id: Int,
    val dogId: Int,
    val dogName: String,
    val fromUsername: String,
    val note: String,
    val requestedAt: String,
)
data class GpDogAccessDog(
    val id: Int,
    val name: String,
    val ownerUsername: String,
    val lifecycleStatus: String,
)
data class GpDogAccessResult(
    val dog: GpDogAccessDog?,
    val isOwner: Boolean,
    val canEdit: Boolean,
    val handlers: List<GpDogAccessHandler>,
    val pendingInvites: List<GpDogAccessInvite>,
    val incomingTransfers: List<GpDogAccessTransfer>,
)

data class GpQrScanEvent(val viewedAt: String, val device: String, val referrer: String)
data class GpQrResult(
    val dogId: Int,
    val dogName: String,
    val publicUrl: String,
    val totalViews: Int,
    val lastViewed: String,
    val recentViews: List<GpQrScanEvent>,
)

data class GpAlert(
    val level: String,
    val title: String,
    val detail: String,
    val actionUrl: String,
    val actionLabel: String,
)

data class GpSkillStat(val skill: String, val count: Int)
data class GpEnvStat(val type: String, val count: Int)
data class GpTrendDay(val date: String, val logs: Int, val avgFocus: Double)
data class GpStats(
    val dogId: Int,
    val totalLogs: Int,
    val avgFocus: Double,
    val logsThisWeek: Int,
    val logsThisMonth: Int,
    val topSkills: List<GpSkillStat>,
    val locationBreakdown: List<GpEnvStat>,
    val trend14d: List<GpTrendDay>,
)

data class GpHandlerProfile(
    val id: Int,
    val username: String,
    val displayName: String,
    val homeStreet: String,
    val homeApt: String,
    val homeCity: String,
    val homeState: String,
    val homeZip: String,
    val phone: String,
    val publicEmail: String,
    val facebookUrl: String,
    val backupContactName: String,
    val backupContactPhone: String,
    val publicNotes: String,
    val smsPhone: String,
    val smsNotificationsEnabled: Boolean,
)

data class GuidePawDogItem(
    val id: Int,
    val name: String,
    val breed: String?,
    val ownerUsername: String?,
    val accessRole: String?,
    val lifecycleStatus: String?,
    val dateOfBirth: String? = null,
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

data class GpTrainingGoalItem(
    val id: Int,
    val dogId: Int,
    val dogName: String,
    val goalCategory: String,
    val currentProblem: String,
    val desiredBehavior: String,
    val contextEnvironment: String,
    val triggerDescription: String,
    val handlerTimeBudgetMinutes: Int,
    val reinforcerPreference: String,
    val safetyRisk: Boolean,
    val successCriteria: String,
    val maintenancePlan: String,
    val status: String,
    val createdAt: String,
)

data class GpTrainingGoalsResult(
    val statusFilter: String,
    val goals: List<GpTrainingGoalItem>,
)

data class GpHabitRepairProtocol(
    val key: String,
    val title: String,
    val time: String,
    val steps: List<String>,
)

data class GpBehaviorIncidentItem(
    val id: Int,
    val dogId: Int,
    val dogName: String,
    val incidentType: String,
    val contextEnvironment: String,
    val triggerDescription: String,
    val severity: Int,
    val notes: String,
    val status: String,
    val createdAt: String,
)

data class GpHabitRepairResult(
    val protocols: List<GpHabitRepairProtocol>,
    val incidents: List<GpBehaviorIncidentItem>,
)

data class GpCandidateSummary(
    val dogName: String,
    val focusLevelRecommended: Int,
    val recommendation: String,
    val safetyFlags: String,
)

data class GpBehaviorRiskResult(
    val dogId: Int?,
    val score: Int,
    val band: String,
    val openRegressions: Int,
    val reasons: List<String>,
    val recommendations: List<String>,
    val incidents: List<GpBehaviorIncidentItem>,
    val candidate: GpCandidateSummary?,
)

data class GpRegressionEventItem(
    val id: Int,
    val status: String,
    val detectedReason: String,
    val recommendedAction: String,
    val moduleTitle: String,
    val goalCategory: String,
    val createdAt: String,
)

data class GpRegressionResult(
    val dogId: Int,
    val dogName: String,
    val openCount: Int,
    val events: List<GpRegressionEventItem>,
)

data class GpCandidateDogItem(
    val id: Int,
    val name: String,
)

data class GpCandidateAssessmentItem(
    val id: Int,
    val dogId: Int,
    val dogName: String,
    val focusLevelRecommended: Int,
    val recommendation: String,
    val safetyFlags: String,
    val healthNotes: String,
    val averageScore: Float,
    val createdAt: String,
)

data class GpCandidateAssessmentsResult(
    val dogs: List<GpCandidateDogItem>,
    val assessments: List<GpCandidateAssessmentItem>,
    val scoreLabels: Map<String, String>,
)

data class GpCertItem(
    val id: Int,
    val category: String,
    val itemName: String,
    val description: String,
    val status: String,
    val notes: String,
)

data class GpCertAssessment(
    val assessmentDate: String,
    val publicAccessScore: Int?,
    val taskReliabilityScore: Int?,
    val obedienceScore: Int?,
    val environmentalScore: Int?,
    val notes: String,
)

data class GpCertResult(
    val dogName: String,
    val total: Int,
    val proficient: Int,
    val inTraining: Int,
    val readinessPct: Int,
    val items: List<GpCertItem>,
    val assessment: GpCertAssessment?,
)

data class GpTrainingItem(
    val id: Int,
    val category: String,
    val trackCode: String,
    val level: Int,
    val itemName: String,
    val description: String,
    val status: String,
    val lastWorkedAt: String,
    val notes: String,
)

data class GpTrainingProgramResult(
    val dogName: String,
    val total: Int,
    val mastered: Int,
    val inProgress: Int,
    val proofing: Int,
    val items: List<GpTrainingItem>,
)

data class GpVetItem(
    val id: Int,
    val clinicName: String,
    val vetName: String,
    val phone: String,
    val address: String = "",
    val notes: String = "",
    val isPrimary: Boolean = false,
)

data class GpHealthDocItem(
    val id: Int,
    val docType: String,
    val title: String,
    val providerName: String,
    val notes: String,
    val fileUrl: String,
    val createdAt: String,
)

data class GpHealthDocsResult(
    val dogName: String,
    val vets: List<GpVetItem>,
    val documents: List<GpHealthDocItem>,
)

data class GpAppointmentItem(
    val id: Int,
    val title: String,
    val status: String,
    val appointmentAt: String,
    val reminderAt: String,
    val locationText: String,
    val notes: String,
    val clinicName: String,
    val vetPhone: String,
)

data class GpAppointmentsResult(
    val dogName: String,
    val vets: List<GpVetItem>,
    val appointments: List<GpAppointmentItem>,
)

data class GpHealthRecord(
    val date: String,
    val type: String,
    val notes: String,
    val weightLbs: Float?,
)

data class GpMedicationItem(
    val id: Int,
    val medicationName: String,
    val dosage: String,
    val status: String,
    val scheduleText: String,
    val refillDate: String,
    val prescribingProvider: String,
    val instructions: String,
    val notes: String,
    val createdAt: String,
)

data class GpMedicationsResult(
    val dogName: String,
    val medications: List<GpMedicationItem>,
)

data class GpForgotPasswordResult(
    val success: Boolean,
    val message: String,
)

data class GpVetFinderResult(
    val placeId: String,
    val name: String,
    val address: String,
    val phone: String,
    val rating: Float?,
    val userRatingsTotal: Int,
    val lat: Double,
    val lng: Double,
    val distanceMiles: Float,
    val openNow: Boolean?,
    val hoursToday: String,
    val isEmergency: Boolean,
    val is24hr: Boolean,
    val legLabel: String,
    val website: String,
)

data class GpVetFinderResponse(
    val searchType: String,
    val routeDestination: String,
    val vets: List<GpVetFinderResult>,
)

data class GpVetAppointment(
    val id: Int,
    val title: String,
    val status: String,
    val appointmentAt: String,
    val locationText: String,
    val notes: String,
    val clinicName: String,
    val vetPhone: String,
)

data class GpHealthSummary(
    val dogId: Int,
    val dogName: String,
    val lastCheckupDate: String,
    val weightLbs: Float?,
    val activeMedicationCount: Int,
    val primaryVetClinic: String,
    val primaryVetName: String,
    val primaryVetPhone: String,
    val nextAppointmentAt: String,
    val nextAppointmentTitle: String,
    val activeMedications: List<GpMedicationItem>,
    val upcomingAppointments: List<GpVetAppointment>,
    val recentAppointments: List<GpVetAppointment>,
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
            username             = user.optString("username", ""),
            activeDogId          = optNullableInt(response.json, "active_dog_id"),
            nextAppointmentAt    = if (response.json.isNull("next_appointment_at"))    null else response.json.optString("next_appointment_at"),
            nextAppointmentTitle = if (response.json.isNull("next_appointment_title")) null else response.json.optString("next_appointment_title"),
            nextRefillDate       = if (response.json.isNull("next_refill_date"))       null else response.json.optString("next_refill_date"),
            nextRefillMedName    = if (response.json.isNull("next_refill_med_name"))   null else response.json.optString("next_refill_med_name"),
            activeMedCount       = response.json.optInt("active_med_count", 0),
        )
    }

    fun getDogAccess(token: String, dogId: Int? = null): GpDogAccessResult {
        val path = if (dogId != null) "api/dog_access.php?dog_id=$dogId" else "api/dog_access.php"
        val response = requestJson(path, "GET", token, null)
        ensureSuccess(response)
        return parseDogAccessResult(response.json)
    }

    fun grantDogAccess(token: String, dogId: Int, handlerIdentity: String, role: String, permissionLevel: String, endsAt: String): GpDogAccessResult {
        val payload = JSONObject()
            .put("action", "grant_access")
            .put("dog_id", dogId)
            .put("handler_identity", handlerIdentity)
            .put("role", role)
            .put("permission_level", permissionLevel)
            .put("access_ends_at", endsAt)
        val response = requestJson("api/dog_access.php", "POST", token, payload)
        ensureSuccess(response)
        return parseDogAccessResult(response.json)
    }

    fun revokeDogAccess(token: String, handlerId: Int): GpDogAccessResult {
        val payload = JSONObject().put("action", "revoke_access").put("handler_id", handlerId)
        val response = requestJson("api/dog_access.php", "POST", token, payload)
        ensureSuccess(response)
        return parseDogAccessResult(response.json)
    }

    fun respondDogAccessInvite(token: String, handlerId: Int, accept: Boolean): GpDogAccessResult {
        val action = if (accept) "accept_dog_access_invite" else "decline_dog_access_invite"
        val payload = JSONObject().put("action", action).put("handler_id", handlerId)
        val response = requestJson("api/dog_access.php", "POST", token, payload)
        ensureSuccess(response)
        return parseDogAccessResult(response.json)
    }

    fun respondTransfer(token: String, transferId: Int, accept: Boolean): GpDogAccessResult {
        val action = if (accept) "accept_transfer" else "decline_transfer"
        val payload = JSONObject().put("action", action).put("transfer_id", transferId)
        val response = requestJson("api/dog_access.php", "POST", token, payload)
        ensureSuccess(response)
        return parseDogAccessResult(response.json)
    }

    private fun parseDogAccessResult(json: JSONObject): GpDogAccessResult {
        val dogObj = json.optJSONObject("dog")
        val dog = dogObj?.let {
            GpDogAccessDog(
                id              = it.optInt("id"),
                name            = it.optString("name", ""),
                ownerUsername   = it.optString("owner_username", ""),
                lifecycleStatus = it.optString("lifecycle_status", "active"),
            )
        }
        val handlers = json.optJSONArray("handlers")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpDogAccessHandler(
                    id             = o.optInt("id"),
                    userId         = o.optInt("user_id"),
                    username       = o.optString("username", ""),
                    displayName    = o.optString("display_name", ""),
                    role           = o.optString("role", ""),
                    permissionLevel = o.optString("permission_level", "view"),
                    accessEndsAt   = o.optString("access_ends_at", ""),
                    accessStatus   = o.optString("access_status", ""),
                )
            }
        }.orEmpty()
        val invites = json.optJSONArray("pending_invites")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpDogAccessInvite(
                    id            = o.optInt("id"),
                    dogId         = o.optInt("dog_id"),
                    dogName       = o.optString("dog_name", ""),
                    ownerUsername = o.optString("owner_username", ""),
                    role          = o.optString("role", ""),
                    accessStatus  = o.optString("access_status", ""),
                )
            }
        }.orEmpty()
        val transfers = json.optJSONArray("incoming_transfers")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpDogAccessTransfer(
                    id           = o.optInt("id"),
                    dogId        = o.optInt("dog_id"),
                    dogName      = o.optString("dog_name", ""),
                    fromUsername = o.optString("from_username", ""),
                    note         = o.optString("note", ""),
                    requestedAt  = o.optString("requested_at", ""),
                )
            }
        }.orEmpty()
        return GpDogAccessResult(
            dog               = dog,
            isOwner           = json.optBoolean("is_owner"),
            canEdit           = json.optBoolean("can_edit"),
            handlers          = handlers,
            pendingInvites    = invites,
            incomingTransfers = transfers,
        )
    }

    fun getProfile(token: String): GpHandlerProfile {
        val response = requestJson("api/profile.php", "GET", token, null)
        ensureSuccess(response)
        val u = response.json.optJSONObject("user") ?: JSONObject()
        return GpHandlerProfile(
            id                      = u.optInt("id", 0),
            username                = u.optString("username", ""),
            displayName             = u.optString("display_name", ""),
            homeStreet              = u.optString("home_street", ""),
            homeApt                 = u.optString("home_apt", ""),
            homeCity                = u.optString("home_city", ""),
            homeState               = u.optString("home_state", ""),
            homeZip                 = u.optString("home_zip", ""),
            phone                   = u.optString("phone", ""),
            publicEmail             = u.optString("public_email", ""),
            facebookUrl             = u.optString("facebook_url", ""),
            backupContactName       = u.optString("backup_contact_name", ""),
            backupContactPhone      = u.optString("backup_contact_phone", ""),
            publicNotes             = u.optString("public_notes", ""),
            smsPhone                = u.optString("sms_phone", ""),
            smsNotificationsEnabled = u.optBoolean("sms_notifications_enabled", false),
        )
    }

    fun saveProfile(
        token: String,
        displayName: String,
        homeStreet: String,
        homeApt: String,
        homeCity: String,
        homeState: String,
        homeZip: String,
        phone: String,
        publicEmail: String,
        facebookUrl: String,
        backupContactName: String,
        backupContactPhone: String,
        publicNotes: String,
        smsPhone: String,
        smsNotificationsEnabled: Boolean,
    ): String {
        val payload = JSONObject()
            .put("display_name", displayName)
            .put("home_street", homeStreet)
            .put("home_apt", homeApt)
            .put("home_city", homeCity)
            .put("home_state", homeState)
            .put("home_zip", homeZip)
            .put("phone", phone)
            .put("public_email", publicEmail)
            .put("facebook_url", facebookUrl)
            .put("backup_contact_name", backupContactName)
            .put("backup_contact_phone", backupContactPhone)
            .put("public_notes", publicNotes)
            .put("sms_phone", smsPhone)
            .put("sms_notifications_enabled", if (smsNotificationsEnabled) 1 else 0)
        val response = requestJson("api/profile.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optString("message", "Profile saved.")
    }

    fun getStats(token: String): GpStats? {
        val response = requestJson("api/stats.php", "GET", token, null)
        ensureSuccess(response)
        val s = response.json.optJSONObject("stats") ?: return null
        val skills = s.optJSONArray("top_skills")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpSkillStat(o.optString("skill"), o.optInt("count"))
            }
        }.orEmpty()
        val envs = s.optJSONArray("location_breakdown")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpEnvStat(o.optString("type", "Other"), o.optInt("count"))
            }
        }.orEmpty()
        val trend = s.optJSONArray("trend_14d")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                GpTrendDay(o.optString("date"), o.optInt("logs"), o.optDouble("avg_focus"))
            }
        }.orEmpty()
        return GpStats(
            dogId            = response.json.optInt("dog_id"),
            totalLogs        = s.optInt("total_logs"),
            avgFocus         = s.optDouble("avg_focus"),
            logsThisWeek     = s.optInt("logs_this_week"),
            logsThisMonth    = s.optInt("logs_this_month"),
            topSkills        = skills,
            locationBreakdown = envs,
            trend14d         = trend,
        )
    }

    fun getQrTracking(token: String): GpQrResult? {
        val response = requestJson("api/qr_tracking.php", "GET", token, null)
        ensureSuccess(response)
        if (response.json.isNull("dog_id")) return null
        val views = response.json.optJSONArray("recent_views")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                GpQrScanEvent(
                    viewedAt = o.optString("viewed_at", ""),
                    device   = o.optString("device", "Unknown"),
                    referrer = o.optString("referrer", ""),
                )
            }
        }.orEmpty()
        return GpQrResult(
            dogId      = response.json.optInt("dog_id"),
            dogName    = response.json.optString("dog_name", ""),
            publicUrl  = response.json.optString("public_url", ""),
            totalViews = response.json.optInt("total_views"),
            lastViewed = response.json.optString("last_viewed", ""),
            recentViews = views,
        )
    }

    fun getAlerts(token: String): List<GpAlert> {
        val response = requestJson("api/alerts.php", "GET", token, null)
        ensureSuccess(response)
        return response.json.optJSONArray("alerts")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                GpAlert(
                    level       = o.optString("level", "info"),
                    title       = o.optString("title", ""),
                    detail      = o.optString("detail", ""),
                    actionUrl   = o.optString("action_url", ""),
                    actionLabel = o.optString("action_label", ""),
                )
            }
        }.orEmpty()
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
                dateOfBirth = obj.optText("date_of_birth"),
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
        token: String?,
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

    fun trainingGoals(token: String, status: String = "active"): GpTrainingGoalsResult {
        val response = requestJson(
            "api/training_goals.php?status=${URLEncoder.encode(status, StandardCharsets.UTF_8.name())}",
            "GET", token, null,
        )
        ensureSuccess(response)
        return GpTrainingGoalsResult(
            statusFilter = response.json.optString("status_filter", status),
            goals        = response.json.optJSONArray("goals")?.toTrainingGoalList().orEmpty(),
        )
    }

    fun createTrainingGoal(
        token: String,
        dogId: Int,
        goalCategory: String,
        currentProblem: String,
        desiredBehavior: String,
        contextEnvironment: String,
        triggerDescription: String,
        handlerTimeBudgetMinutes: Int,
        reinforcerPreference: String,
        safetyRisk: Boolean,
        successCriteria: String,
        maintenancePlan: String,
    ): Int {
        val payload = JSONObject()
            .put("action", "create")
            .put("dog_id", dogId)
            .put("goal_category", goalCategory)
            .put("current_problem", currentProblem)
            .put("desired_behavior", desiredBehavior)
            .put("context_environment", contextEnvironment)
            .put("trigger_description", triggerDescription)
            .put("handler_time_budget_minutes", handlerTimeBudgetMinutes)
            .put("reinforcer_preference", reinforcerPreference)
            .put("safety_risk", if (safetyRisk) 1 else 0)
            .put("success_criteria", successCriteria)
            .put("maintenance_plan", maintenancePlan)
        val response = requestJson("api/training_goals.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optInt("goal_id", 0)
    }

    fun archiveTrainingGoal(token: String, goalId: Int): Boolean {
        val payload  = JSONObject().put("action", "archive").put("goal_id", goalId)
        val response = requestJson("api/training_goals.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optBoolean("success", false)
    }

    fun habitRepair(token: String): GpHabitRepairResult {
        val response = requestJson("api/habit_repair.php", "GET", token, null)
        ensureSuccess(response)
        return GpHabitRepairResult(
            protocols = response.json.optJSONArray("protocols")?.toHabitRepairProtocolList().orEmpty(),
            incidents = response.json.optJSONArray("incidents")?.toBehaviorIncidentList().orEmpty(),
        )
    }

    fun createBehaviorIncident(
        token: String,
        dogId: Int,
        incidentType: String,
        contextEnvironment: String,
        triggerDescription: String,
        severity: Int,
        notes: String,
    ): Int {
        val payload = JSONObject()
            .put("action", "create")
            .put("dog_id", dogId)
            .put("incident_type", incidentType)
            .put("context_environment", contextEnvironment)
            .put("trigger_description", triggerDescription)
            .put("severity", severity)
            .put("notes", notes)
        val response = requestJson("api/habit_repair.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optInt("incident_id", 0)
    }

    fun archiveBehaviorIncident(token: String, incidentId: Int): Boolean {
        val payload  = JSONObject().put("action", "archive").put("incident_id", incidentId)
        val response = requestJson("api/habit_repair.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optBoolean("success", false)
    }

    fun behaviorRisk(token: String, dogId: Int? = null): GpBehaviorRiskResult {
        val query    = if (dogId != null && dogId > 0) "api/behavior_risk.php?dog_id=$dogId" else "api/behavior_risk.php"
        val response = requestJson(query, "GET", token, null)
        ensureSuccess(response)
        val candidate = response.json.optJSONObject("candidate")?.let { c ->
            GpCandidateSummary(
                dogName              = c.optString("dog_name", ""),
                focusLevelRecommended = c.optInt("focus_level_recommended", 0),
                recommendation       = c.optString("recommendation", ""),
                safetyFlags          = c.optString("safety_flags", ""),
            )
        }
        return GpBehaviorRiskResult(
            dogId           = optNullableInt(response.json, "dog_id"),
            score           = response.json.optInt("score", 0),
            band            = response.json.optString("band", "low"),
            openRegressions = response.json.optInt("open_regressions", 0),
            reasons         = response.json.optJSONArray("reasons")?.toStringList().orEmpty(),
            recommendations = response.json.optJSONArray("recommendations")?.toStringList().orEmpty(),
            incidents       = response.json.optJSONArray("incidents")?.toBehaviorIncidentList().orEmpty(),
            candidate       = candidate,
        )
    }

    fun regressionEvents(token: String): GpRegressionResult {
        val response = requestJson("api/regression_engine.php", "GET", token, null)
        ensureSuccess(response)
        return parseRegressionResult(response.json)
    }

    fun updateRegressionEvent(token: String, eventId: Int, status: String, recommendedAction: String): GpRegressionResult {
        val payload = JSONObject()
            .put("action", "update_event")
            .put("event_id", eventId)
            .put("status", status)
            .put("recommended_action", recommendedAction)
        val response = requestJson("api/regression_engine.php", "POST", token, payload)
        ensureSuccess(response)
        return parseRegressionResult(response.json)
    }

    fun candidateAssessments(token: String): GpCandidateAssessmentsResult {
        val response = requestJson("api/candidate_assessment.php", "GET", token, null)
        ensureSuccess(response)
        return parseCandidateAssessmentsResult(response.json)
    }

    fun createCandidateAssessment(
        token: String,
        dogId: Int,
        scores: Map<String, Int>,
        healthNotes: String,
        safetyFlags: String,
    ): String {
        val payload = JSONObject()
            .put("action", "create")
            .put("dog_id", dogId)
            .put("health_notes", healthNotes)
            .put("safety_flags", safetyFlags)
        scores.forEach { (key, value) -> payload.put(key, value) }
        val response = requestJson("api/candidate_assessment.php", "POST", token, payload)
        ensureSuccess(response)
        val avg = response.json.optDouble("average_score", 0.0)
        val fl  = response.json.optInt("focus_level", 0)
        val rec = response.json.optString("recommendation", "")
        return buildString {
            append("Average: %.1f".format(avg))
            append(" • Focus level: $fl")
            if (rec.isNotBlank()) append("\n$rec")
        }
    }

    fun archiveCandidateAssessment(token: String, assessmentId: Int): Boolean {
        val payload  = JSONObject().put("action", "archive").put("assessment_id", assessmentId)
        val response = requestJson("api/candidate_assessment.php", "POST", token, payload)
        ensureSuccess(response)
        return response.json.optBoolean("success", false)
    }

    fun medications(token: String): GpMedicationsResult {
        val response = requestJson("api/medications.php", "GET", token, null)
        ensureSuccess(response)
        val meds = response.json.optJSONArray("medications")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toMedicationItem() } }
            .orEmpty()
        return GpMedicationsResult(
            dogName     = response.json.optString("dog_name", ""),
            medications = meds,
        )
    }

    fun addMedication(
        token: String,
        medicationName: String,
        dosage: String,
        scheduleText: String,
        status: String,
        refillDate: String,
        prescribingProvider: String,
        instructions: String,
        notes: String,
    ) {
        val payload = JSONObject()
            .put("action", "add_medication")
            .put("medication_name", medicationName)
            .put("dosage", dosage)
            .put("schedule_text", scheduleText)
            .put("status", status)
            .put("refill_date", refillDate)
            .put("prescribing_provider", prescribingProvider)
            .put("instructions", instructions)
            .put("notes", notes)
        val response = requestJson("api/medications.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun setMedicationStatus(token: String, medId: Int, status: String) {
        val payload  = JSONObject().put("action", "set_status").put("med_id", medId).put("status", status)
        val response = requestJson("api/medications.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun appointments(token: String): GpAppointmentsResult {
        val response = requestJson("api/appointments.php", "GET", token, null)
        ensureSuccess(response)
        val vets = response.json.optJSONArray("vets")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toVetItem() } }
            .orEmpty()
        val appts = response.json.optJSONArray("appointments")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toAppointmentItem() } }
            .orEmpty()
        return GpAppointmentsResult(
            dogName      = response.json.optString("dog_name", ""),
            vets         = vets,
            appointments = appts,
        )
    }

    fun addAppointment(
        token: String,
        title: String,
        appointmentAt: String,
        reminderAt: String,
        locationText: String,
        notes: String,
        vetId: Int,
    ) {
        val payload = JSONObject()
            .put("action", "add_appointment")
            .put("title", title)
            .put("appointment_at", appointmentAt)
            .put("reminder_at", reminderAt)
            .put("location_text", locationText)
            .put("notes", notes)
            .put("dog_vet_id", if (vetId > 0) vetId else JSONObject.NULL)
        val response = requestJson("api/appointments.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun markAppointmentStatus(token: String, appointmentId: Int, newStatus: String) {
        val payload  = JSONObject().put("action", "mark_status").put("appointment_id", appointmentId).put("new_status", newStatus)
        val response = requestJson("api/appointments.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun healthDocs(token: String): GpHealthDocsResult {
        val response = requestJson("api/health_docs.php", "GET", token, null)
        ensureSuccess(response)
        val vets = response.json.optJSONArray("vets")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toVetItem() } }
            .orEmpty()
        val docs = response.json.optJSONArray("documents")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toHealthDocItem() } }
            .orEmpty()
        return GpHealthDocsResult(
            dogName   = response.json.optString("dog_name", ""),
            vets      = vets,
            documents = docs,
        )
    }

    fun getHealthSummary(token: String): GpHealthSummary? {
        val response = requestJson("api/dog_health_summary.php", "GET", token, null)
        ensureSuccess(response)

        fun parseAppts(key: String): List<GpVetAppointment> =
            response.json.optJSONArray(key)
                ?.let { arr -> (0 until arr.length()).mapNotNull { i ->
                    val o = arr.optJSONObject(i) ?: return@mapNotNull null
                    GpVetAppointment(
                        id            = o.optInt("id", 0),
                        title         = o.optString("title", ""),
                        status        = o.optString("status", ""),
                        appointmentAt = o.optString("appointment_at", ""),
                        locationText  = o.optString("location_text", ""),
                        notes         = o.optString("notes", ""),
                        clinicName    = o.optString("clinic_name", ""),
                        vetPhone      = o.optString("vet_phone", ""),
                    )
                } }
                .orEmpty()

        fun parseMeds(key: String): List<GpMedicationItem> =
            response.json.optJSONArray(key)
                ?.let { arr -> (0 until arr.length()).mapNotNull { i ->
                    val o = arr.optJSONObject(i) ?: return@mapNotNull null
                    GpMedicationItem(
                        id                   = o.optInt("id", 0),
                        medicationName       = o.optString("medication_name", ""),
                        dosage               = o.optString("dosage", ""),
                        status               = o.optString("status", ""),
                        scheduleText         = o.optString("schedule_text", ""),
                        refillDate           = o.optString("refill_date", ""),
                        prescribingProvider  = o.optString("prescribing_provider", ""),
                        instructions         = o.optString("instructions", ""),
                        notes                = o.optString("notes", ""),
                        createdAt            = o.optString("created_at", ""),
                    )
                } }
                .orEmpty()

        val weightRaw = if (response.json.isNull("weight_lbs")) null
                        else response.json.optDouble("weight_lbs").toFloat()
        return GpHealthSummary(
            dogId                 = response.json.optInt("dog_id", 0),
            dogName               = response.json.optString("dog_name", ""),
            lastCheckupDate       = response.json.optString("last_checkup_date", ""),
            weightLbs             = weightRaw,
            activeMedicationCount = response.json.optInt("active_medication_count", 0),
            primaryVetClinic      = response.json.optString("primary_vet_clinic", ""),
            primaryVetName        = response.json.optString("primary_vet_name", ""),
            primaryVetPhone       = response.json.optString("primary_vet_phone", ""),
            nextAppointmentAt     = response.json.optString("next_appointment_at", ""),
            nextAppointmentTitle  = response.json.optString("next_appointment_title", ""),
            activeMedications     = parseMeds("active_medications"),
            upcomingAppointments  = parseAppts("upcoming_appointments"),
            recentAppointments    = parseAppts("recent_appointments"),
        )
    }

    fun findVets(
        token: String,
        lat: Double,
        lng: Double,
        radiusMiles: Int = 50,
        destination: String = "",
        afterHoursOnly: Boolean = false,
    ): GpVetFinderResponse {
        val params = buildString {
            append("lat=").append(lat)
            append("&lng=").append(lng)
            append("&radius_miles=").append(radiusMiles)
            if (destination.isNotBlank()) append("&destination=").append(java.net.URLEncoder.encode(destination, "UTF-8"))
            if (afterHoursOnly) append("&after_hours=1")
        }
        val response = requestJson("api/vet_finder.php?$params", "GET", token, null)
        ensureSuccess(response)
        val vets = response.json.optJSONArray("vets")
            ?.let { arr -> (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                GpVetFinderResult(
                    placeId          = o.optString("place_id", ""),
                    name             = o.optString("name", ""),
                    address          = o.optString("address", ""),
                    phone            = o.optString("phone", ""),
                    rating           = if (o.isNull("rating")) null else o.optDouble("rating").toFloat(),
                    userRatingsTotal = o.optInt("user_ratings_total", 0),
                    lat              = o.optDouble("lat", 0.0),
                    lng              = o.optDouble("lng", 0.0),
                    distanceMiles    = o.optDouble("distance_miles", 0.0).toFloat(),
                    openNow          = if (o.isNull("open_now")) null else o.optBoolean("open_now"),
                    hoursToday       = o.optString("hours_today", ""),
                    isEmergency      = o.optBoolean("is_emergency", false),
                    is24hr           = o.optBoolean("is_24hr", false),
                    legLabel         = o.optString("leg_label", ""),
                    website          = o.optString("website", ""),
                )
            } }
            .orEmpty()
        return GpVetFinderResponse(
            searchType        = response.json.optString("search_type", "local"),
            routeDestination  = response.json.optString("route_destination", ""),
            vets              = vets,
        )
    }

    fun addVet(
        token: String,
        clinicName: String,
        vetName: String,
        phone: String,
        address: String,
        notes: String,
        isPrimary: Boolean,
    ) {
        val payload = JSONObject()
            .put("action", "add_vet")
            .put("clinic_name", clinicName)
            .put("vet_name", vetName)
            .put("phone", phone)
            .put("address", address)
            .put("notes", notes)
            .put("is_primary", isPrimary)
        val response = requestJson("api/health_docs.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun certification(token: String): GpCertResult {
        val response = requestJson("api/certification.php", "GET", token, null)
        ensureSuccess(response)
        val j = response.json
        val items = j.optJSONArray("items")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toCertItem() } }
            .orEmpty()
        val asmJson = j.optJSONObject("assessment")
        val assessment = asmJson?.let {
            GpCertAssessment(
                assessmentDate       = it.optString("assessment_date", ""),
                publicAccessScore    = if (!it.isNull("public_access_score")) it.optInt("public_access_score") else null,
                taskReliabilityScore = if (!it.isNull("task_reliability_score")) it.optInt("task_reliability_score") else null,
                obedienceScore       = if (!it.isNull("obedience_score")) it.optInt("obedience_score") else null,
                environmentalScore   = if (!it.isNull("environmental_score")) it.optInt("environmental_score") else null,
                notes                = it.optString("notes", ""),
            )
        }
        return GpCertResult(
            dogName     = j.optString("dog_name", ""),
            total       = j.optInt("total", 0),
            proficient  = j.optInt("proficient", 0),
            inTraining  = j.optInt("in_training", 0),
            readinessPct = j.optInt("readiness_pct", 0),
            items       = items,
            assessment  = assessment,
        )
    }

    fun seedCertTemplate(token: String) {
        val payload  = JSONObject().put("action", "seed_template")
        val response = requestJson("api/certification.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun updateCertItem(token: String, itemId: Int, status: String, notes: String) {
        val payload = JSONObject()
            .put("action", "update_item")
            .put("item_id", itemId)
            .put("status", status)
            .put("notes", notes)
        val response = requestJson("api/certification.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun forgotPassword(email: String): GpForgotPasswordResult {
        val payload = JSONObject().put("email", email)
        val response = requestJson("api/forgot_password.php", "POST", null, payload)
        return GpForgotPasswordResult(
            success = response.json.optBoolean("success", false),
            message = response.json.optString("message", "Check your email for a reset link."),
        )
    }

    fun addCertAssessment(
        token: String,
        assessmentDate: String,
        publicAccess: Int,
        taskReliability: Int,
        obedience: Int,
        environmental: Int,
        notes: String,
    ) {
        val payload = JSONObject()
            .put("action", "add_assessment")
            .put("assessment_date", assessmentDate)
            .put("public_access_score", publicAccess)
            .put("task_reliability_score", taskReliability)
            .put("obedience_score", obedience)
            .put("environmental_score", environmental)
            .put("notes", notes)
        val response = requestJson("api/certification.php", "POST", token, payload)
        ensureSuccess(response)
    }

    private fun JSONObject.toCertItem() = GpCertItem(
        id          = optInt("id", 0),
        category    = optString("category", ""),
        itemName    = optString("item_name", ""),
        description = optString("description", ""),
        status      = optString("status", "not_started"),
        notes       = optString("notes", ""),
    )

    private fun JSONObject.toTrainingItem() = GpTrainingItem(
        id           = optInt("id", 0),
        category     = optString("category", ""),
        trackCode    = optString("track_code", ""),
        level        = optInt("level", 1),
        itemName     = optString("item_name", ""),
        description  = optString("description", ""),
        status       = optString("status", "not_started"),
        lastWorkedAt = optString("last_worked_at", ""),
        notes        = optString("notes", ""),
    )

    fun trainingProgram(token: String): GpTrainingProgramResult {
        val response = requestJson("api/training_program.php", "GET", token, null)
        ensureSuccess(response)
        val j = response.json
        val items = j.optJSONArray("items")
            ?.let { arr -> (0 until arr.length()).map { arr.getJSONObject(it).toTrainingItem() } }
            .orEmpty()
        return GpTrainingProgramResult(
            dogName    = j.optString("dog_name", ""),
            total      = j.optInt("total", 0),
            mastered   = j.optInt("mastered", 0),
            inProgress = j.optInt("in_progress", 0),
            proofing   = j.optInt("proofing", 0),
            items      = items,
        )
    }

    fun seedTrainingProgram(token: String) {
        val payload  = JSONObject().put("action", "seed")
        val response = requestJson("api/training_program.php", "POST", token, payload)
        ensureSuccess(response)
    }

    fun updateTrainingItem(token: String, itemId: Int, status: String) {
        val payload = JSONObject()
            .put("action", "update_status")
            .put("item_id", itemId)
            .put("status", status)
        val response = requestJson("api/training_program.php", "POST", token, payload)
        ensureSuccess(response)
    }

    private fun JSONObject.toHealthDocItem() = GpHealthDocItem(
        id           = optInt("id", 0),
        docType      = optString("doc_type", "vet_record"),
        title        = optString("title", ""),
        providerName = optString("provider_name", ""),
        notes        = optString("notes", ""),
        fileUrl      = optString("file_url", ""),
        createdAt    = optString("created_at", ""),
    )

    private fun JSONObject.toVetItem() = GpVetItem(
        id          = optInt("id", 0),
        clinicName  = optString("clinic_name", ""),
        vetName     = optString("vet_name", ""),
        phone       = optString("phone", ""),
        address     = optString("address", ""),
        notes       = optString("notes", ""),
        isPrimary   = optBoolean("is_primary", false),
    )

    private fun JSONObject.toAppointmentItem() = GpAppointmentItem(
        id            = optInt("id", 0),
        title         = optString("title", ""),
        status        = optString("status", "scheduled"),
        appointmentAt = optString("appointment_at", ""),
        reminderAt    = optString("reminder_at", ""),
        locationText  = optString("location_text", ""),
        notes         = optString("notes", ""),
        clinicName    = optString("clinic_name", ""),
        vetPhone      = optString("vet_phone", ""),
    )

    private fun JSONObject.toMedicationItem() = GpMedicationItem(
        id                   = optInt("id", 0),
        medicationName       = optString("medication_name", ""),
        dosage               = optString("dosage", ""),
        status               = optString("status", "active"),
        scheduleText         = optString("schedule_text", ""),
        refillDate           = optString("refill_date", ""),
        prescribingProvider  = optString("prescribing_provider", ""),
        instructions         = optString("instructions", ""),
        notes                = optString("notes", ""),
        createdAt            = optString("created_at", ""),
    )

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
                dateOfBirth = obj.optText("date_of_birth"),
            )
        }
    }

    private fun JSONArray.toTrainingGoalList(): List<GpTrainingGoalItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GpTrainingGoalItem(
                id                        = obj.optInt("id", 0),
                dogId                     = obj.optInt("dog_id", 0),
                dogName                   = obj.optString("dog_name", ""),
                goalCategory              = obj.optString("goal_category", ""),
                currentProblem            = obj.optString("current_problem", ""),
                desiredBehavior           = obj.optString("desired_behavior", ""),
                contextEnvironment        = obj.optString("context_environment", ""),
                triggerDescription        = obj.optString("trigger_description", ""),
                handlerTimeBudgetMinutes  = obj.optInt("handler_time_budget_minutes", 3),
                reinforcerPreference      = obj.optString("reinforcer_preference", ""),
                safetyRisk                = obj.optBoolean("safety_risk", false),
                successCriteria           = obj.optString("success_criteria", ""),
                maintenancePlan           = obj.optString("maintenance_plan", ""),
                status                    = obj.optString("status", "active"),
                createdAt                 = obj.optString("created_at", ""),
            )
        }
    }

    private fun JSONArray.toHabitRepairProtocolList(): List<GpHabitRepairProtocol> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GpHabitRepairProtocol(
                key   = obj.optString("key", ""),
                title = obj.optString("title", ""),
                time  = obj.optString("time", ""),
                steps = obj.optJSONArray("steps")?.toStringList().orEmpty(),
            )
        }
    }

    private fun JSONArray.toBehaviorIncidentList(): List<GpBehaviorIncidentItem> {
        return (0 until length()).mapNotNull { idx ->
            val obj = optJSONObject(idx) ?: return@mapNotNull null
            GpBehaviorIncidentItem(
                id                  = obj.optInt("id", 0),
                dogId               = obj.optInt("dog_id", 0),
                dogName             = obj.optString("dog_name", ""),
                incidentType        = obj.optString("incident_type", ""),
                contextEnvironment  = obj.optString("context_environment", ""),
                triggerDescription  = obj.optString("trigger_description", ""),
                severity            = obj.optInt("severity", 2),
                notes               = obj.optString("notes", ""),
                status              = obj.optString("status", "active"),
                createdAt           = obj.optString("created_at", ""),
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

    private fun parseRegressionResult(json: JSONObject): GpRegressionResult =
        GpRegressionResult(
            dogId     = json.optInt("dog_id", 0),
            dogName   = json.optString("dog_name", ""),
            openCount = json.optInt("open_count", 0),
            events    = json.optJSONArray("events")?.toRegressionEventList().orEmpty(),
        )

    private fun JSONArray.toRegressionEventList(): List<GpRegressionEventItem> =
        (0 until length()).mapNotNull { i ->
            optJSONObject(i)?.let { o ->
                GpRegressionEventItem(
                    id                = o.optInt("id", 0),
                    status            = o.optString("status", "open"),
                    detectedReason    = o.optString("detected_reason", ""),
                    recommendedAction = o.optString("recommended_action", ""),
                    moduleTitle       = o.optString("module_title", ""),
                    goalCategory      = o.optString("goal_category", ""),
                    createdAt         = o.optString("created_at", ""),
                )
            }
        }

    private fun parseCandidateAssessmentsResult(json: JSONObject): GpCandidateAssessmentsResult {
        val dogsArr = json.optJSONArray("dogs")
        val dogs = (0 until (dogsArr?.length() ?: 0)).mapNotNull { i ->
            dogsArr?.optJSONObject(i)?.let { o ->
                GpCandidateDogItem(id = o.optInt("id", 0), name = o.optString("name", ""))
            }
        }
        val labelsObj = json.optJSONObject("score_labels")
        val scoreLabels = labelsObj?.keys()?.asSequence()?.associateWith { labelsObj.optString(it, it) } ?: emptyMap()
        return GpCandidateAssessmentsResult(
            dogs        = dogs,
            assessments = json.optJSONArray("assessments")?.toCandidateAssessmentList().orEmpty(),
            scoreLabels = scoreLabels,
        )
    }

    private fun JSONArray.toCandidateAssessmentList(): List<GpCandidateAssessmentItem> =
        (0 until length()).mapNotNull { i ->
            optJSONObject(i)?.let { o ->
                GpCandidateAssessmentItem(
                    id                    = o.optInt("id", 0),
                    dogId                 = o.optInt("dog_id", 0),
                    dogName               = o.optString("dog_name", ""),
                    focusLevelRecommended = o.optInt("focus_level_recommended", 0),
                    recommendation        = o.optString("recommendation", ""),
                    safetyFlags           = o.optString("safety_flags", ""),
                    healthNotes           = o.optString("health_notes", ""),
                    averageScore          = o.optDouble("average_score", 0.0).toFloat(),
                    createdAt             = o.optString("created_at", ""),
                )
            }
        }

    private fun sanitizeFileName(name: String): String {
        return name.trim().ifBlank { "feedback_attachment" }.replace(Regex("[\\r\\n\"]"), "_")
    }

    private data class ApiResponse(
        val statusCode: Int,
        val json: JSONObject,
        val rawText: String,
    )
}
