package com.guidepaw.bridge.sync

import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.model.AccessibleDogSummary
import com.guidepaw.bridge.model.AccountOverview
import com.guidepaw.bridge.model.HandlerProfileOverview
import com.guidepaw.bridge.model.HandlerProfileSaveResult
import com.guidepaw.bridge.model.DogAccessDogSummary
import com.guidepaw.bridge.model.DogAccessHandlerRow
import com.guidepaw.bridge.model.DogAccessOverview
import com.guidepaw.bridge.model.DogAccessPendingInviteRow
import com.guidepaw.bridge.model.DogAccessTransferRow
import com.guidepaw.bridge.model.BillingCheckoutResult
import com.guidepaw.bridge.model.BillingEventRow
import com.guidepaw.bridge.model.BillingOverview
import com.guidepaw.bridge.model.BillingPlanRow
import com.guidepaw.bridge.model.BillingServiceRow
import com.guidepaw.bridge.model.BillingSupportOption
import com.guidepaw.bridge.model.HealthSnapshot
import com.guidepaw.bridge.model.LoginSession
import com.guidepaw.bridge.model.DogsOverview
import com.guidepaw.bridge.model.FoundDogReportResult
import com.guidepaw.bridge.model.TrainingLogEntry
import com.guidepaw.bridge.model.TrainingLogFeed
import com.guidepaw.bridge.model.TrainingLogSaveResult
import com.guidepaw.bridge.model.PublicDogProfile
import com.guidepaw.bridge.model.PublicProfileOverview
import com.guidepaw.bridge.model.PublicProfileSupportBadge
import com.guidepaw.bridge.model.WearableDeviceSetup
import com.guidepaw.bridge.model.WearableOverview
import com.guidepaw.bridge.model.WearableSyncEvent
import com.guidepaw.bridge.model.WearableTrendSummary
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class GuidePawApiClient {
    fun login(
        apiBaseUrl: String,
        username: String,
        password: String,
        tokenLabel: String,
        totpCode: String = "",
        recoveryKey: String = "",
    ): ApiResult<LoginSession> {
        val connection = openBareConnection(apiBaseUrl, "POST", "/api/login.php")
        val payload = JSONObject().apply {
            put("username", username)
            put("password", password)
            put("token_label", tokenLabel)
            if (totpCode.isNotBlank()) put("totp_code", totpCode)
            if (recoveryKey.isNotBlank()) put("recovery_key", recoveryKey)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            val user = json.optJSONObject("user") ?: JSONObject()
            val token = json.optString("token", "")
            val expiresAt = json.optString("expires_at", "")
            if (token.isBlank()) {
                ApiResult.Failure(connection.responseCode, "Login succeeded but token was missing.")
            } else {
                ApiResult.Success(
                    LoginSession(
                        token = token,
                        expiresAt = expiresAt,
                        userId = user.optLong("id", 0L),
                        username = user.optString("username", username),
                    )
                )
            }
        }
    }

    fun fetchAccountOverview(config: BridgeConfig): ApiResult<AccountOverview> {
        val connection = openApiConnection(config, "GET", "/api/me.php")
        return decodeJson(connection) { json ->
            val user = json.optJSONObject("user") ?: JSONObject()
            ApiResult.Success(
                AccountOverview(
                    userId = user.optLong("id", 0L),
                    username = user.optString("username", ""),
                    dbDriver = json.optString("db_driver", ""),
                    schemaVersion = json.optInt("schema_version", 0),
                    activeDogId = json.optLong("active_dog_id", 0L),
                )
            )
        }
    }

    fun fetchHandlerProfile(config: BridgeConfig): ApiResult<HandlerProfileOverview> {
        val connection = openApiConnection(config, "GET", "/api/profile.php")
        return decodeJson(connection) { json ->
            val user = json.optJSONObject("user") ?: JSONObject()
            ApiResult.Success(
                HandlerProfileOverview(
                    userId = user.optLong("id", 0L),
                    username = user.optString("username", ""),
                    displayName = user.optString("display_name", ""),
                    homeStreet = user.optString("home_street", ""),
                    homeApt = user.optString("home_apt", ""),
                    homeCity = user.optString("home_city", ""),
                    homeState = user.optString("home_state", ""),
                    homeZip = user.optString("home_zip", ""),
                    phone = user.optString("phone", ""),
                    publicEmail = user.optString("public_email", ""),
                    facebookUrl = user.optString("facebook_url", ""),
                    profilePhotoUrl = user.optString("profile_photo_url", ""),
                    backupContactName = user.optString("backup_contact_name", ""),
                    backupContactPhone = user.optString("backup_contact_phone", ""),
                    publicNotes = user.optString("public_notes", ""),
                    smsPhone = user.optString("sms_phone", ""),
                    smsNotificationsEnabled = user.optBoolean("sms_notifications_enabled", false),
                    homeAddress = user.optString("home_address", ""),
                )
            )
        }
    }

    fun saveHandlerProfile(config: BridgeConfig, profile: HandlerProfileOverview, profilePhotoUrl: String = ""): ApiResult<HandlerProfileSaveResult> {
        val connection = openApiConnection(config, "POST", "/api/profile.php")
        val payload = JSONObject().apply {
            put("display_name", profile.displayName)
            put("home_street", profile.homeStreet)
            put("home_apt", profile.homeApt)
            put("home_city", profile.homeCity)
            put("home_state", profile.homeState)
            put("home_zip", profile.homeZip)
            put("phone", profile.phone)
            put("public_email", profile.publicEmail)
            if (profile.facebookUrl.isNotBlank()) put("facebook_url", profile.facebookUrl)
            if (profilePhotoUrl.isNotBlank()) put("profile_photo_url", profilePhotoUrl)
            if (profile.backupContactName.isNotBlank()) put("backup_contact_name", profile.backupContactName)
            if (profile.backupContactPhone.isNotBlank()) put("backup_contact_phone", profile.backupContactPhone)
            if (profile.publicNotes.isNotBlank()) put("public_notes", profile.publicNotes)
            if (profile.smsPhone.isNotBlank()) put("sms_phone", profile.smsPhone)
            put("sms_notifications_enabled", profile.smsNotificationsEnabled)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            val user = json.optJSONObject("user") ?: JSONObject()
            ApiResult.Success(
                HandlerProfileSaveResult(
                    message = json.optString("message", "Saved."),
                    displayName = user.optString("display_name", profile.displayName),
                    publicEmail = user.optString("public_email", profile.publicEmail),
                    phone = user.optString("phone", profile.phone),
                    profilePhotoUrl = user.optString("profile_photo_url", profilePhotoUrl),
                )
            )
        }
    }

    fun fetchDogAccessOverview(config: BridgeConfig, dogId: Long? = null): ApiResult<DogAccessOverview> {
        val path = buildString {
            append("/api/dog_access.php")
            val query = mutableListOf<String>()
            dogId?.takeIf { it > 0L }?.let { query.add("dog_id=$it") }
            if (query.isNotEmpty()) {
                append("?")
                append(query.joinToString("&"))
            }
        }
        val connection = openApiConnection(config, "GET", path)
        return decodeJson(connection) { json ->
            val dogJson = json.optJSONObject("dog")
            val handlersJson = json.optJSONArray("handlers") ?: JSONArray()
            val pendingInvitesJson = json.optJSONArray("pending_invites") ?: JSONArray()
            val transfersJson = json.optJSONArray("incoming_transfers") ?: JSONArray()
            ApiResult.Success(
                DogAccessOverview(
                    activeDogId = json.optLong("active_dog_id", 0L),
                    selectedDogId = json.optLong("selected_dog_id", dogId ?: 0L),
                    isOwner = json.optBoolean("is_owner", false),
                    canEdit = json.optBoolean("can_edit", false),
                    dog = dogJson?.let {
                        DogAccessDogSummary(
                            id = it.optLong("id", 0L),
                            name = it.optString("name", ""),
                            ownerUsername = it.optString("owner_username", ""),
                            ownerDisplayName = it.optString("owner_display_name", ""),
                            lifecycleStatus = it.optString("lifecycle_status", "active"),
                            lifecycleNote = it.optString("lifecycle_note", ""),
                        )
                    },
                    handlers = buildList {
                        for (i in 0 until handlersJson.length()) {
                            val row = handlersJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessHandlerRow(
                                    id = row.optLong("id", 0L),
                                    userId = row.optLong("user_id", 0L),
                                    username = row.optString("username", ""),
                                    email = row.optString("email", ""),
                                    displayName = row.optString("display_name", ""),
                                    role = row.optString("role", ""),
                                    permissionLevel = row.optString("permission_level", ""),
                                    accessEndsAt = row.optString("access_ends_at", ""),
                                    accessStatus = row.optString("access_status", ""),
                                )
                            )
                        }
                    },
                    pendingInvites = buildList {
                        for (i in 0 until pendingInvitesJson.length()) {
                            val row = pendingInvitesJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessPendingInviteRow(
                                    id = row.optLong("id", 0L),
                                    dogId = row.optLong("dog_id", 0L),
                                    dogName = row.optString("dog_name", ""),
                                    ownerUsername = row.optString("owner_username", ""),
                                    ownerDisplayName = row.optString("owner_display_name", ""),
                                    role = row.optString("role", ""),
                                    permissionLevel = row.optString("permission_level", ""),
                                    accessEndsAt = row.optString("access_ends_at", ""),
                                    accessStatus = row.optString("access_status", ""),
                                )
                            )
                        }
                    },
                    incomingTransfers = buildList {
                        for (i in 0 until transfersJson.length()) {
                            val row = transfersJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessTransferRow(
                                    id = row.optLong("id", 0L),
                                    dogId = row.optLong("dog_id", 0L),
                                    dogName = row.optString("dog_name", ""),
                                    fromUsername = row.optString("from_username", ""),
                                    fromDisplayName = row.optString("from_display_name", ""),
                                    keepPreviousOwnerAccess = row.optBoolean("keep_previous_owner_access", false),
                                    note = row.optString("note", ""),
                                    requestedAt = row.optString("requested_at", ""),
                                    status = row.optString("status", ""),
                                )
                            )
                        }
                    },
                )
            )
        }
    }

    fun sendDogAccessInvite(
        config: BridgeConfig,
        dogId: Long,
        handlerIdentity: String,
        role: String,
        permissionLevel: String,
        accessEndsAt: String,
    ): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "grant_access",
            dogId = dogId,
            extras = mapOf(
                "handler_identity" to handlerIdentity,
                "role" to role,
                "permission_level" to permissionLevel,
                "access_ends_at" to accessEndsAt,
            ),
        )
    }

    fun revokeDogAccess(config: BridgeConfig, dogId: Long, handlerId: Long): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "revoke_access",
            dogId = dogId,
            extras = mapOf("handler_id" to handlerId.toString()),
        )
    }

    fun acceptDogAccessInvite(config: BridgeConfig, dogId: Long, handlerId: Long): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "accept_dog_access_invite",
            dogId = dogId,
            extras = mapOf("handler_id" to handlerId.toString()),
        )
    }

    fun declineDogAccessInvite(config: BridgeConfig, dogId: Long, handlerId: Long): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "decline_dog_access_invite",
            dogId = dogId,
            extras = mapOf("handler_id" to handlerId.toString()),
        )
    }

    fun requestDogTransfer(
        config: BridgeConfig,
        dogId: Long,
        transferIdentity: String,
        keepPreviousOwnerAccess: Boolean,
        transferNote: String,
    ): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "request_transfer",
            dogId = dogId,
            extras = mapOf(
                "transfer_identity" to transferIdentity,
                "keep_previous_owner_access" to if (keepPreviousOwnerAccess) "1" else "0",
                "transfer_note" to transferNote,
            ),
        )
    }

    fun acceptDogTransfer(config: BridgeConfig, requestId: Long): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "accept_transfer",
            extras = mapOf("request_id" to requestId.toString()),
        )
    }

    fun declineDogTransfer(config: BridgeConfig, requestId: Long): ApiResult<DogAccessOverview> {
        return postDogAccessAction(
            config = config,
            action = "decline_transfer",
            extras = mapOf("request_id" to requestId.toString()),
        )
    }

    private fun postDogAccessAction(
        config: BridgeConfig,
        action: String,
        dogId: Long? = null,
        extras: Map<String, String> = emptyMap(),
    ): ApiResult<DogAccessOverview> {
        val connection = openApiConnection(config, "POST", "/api/dog_access.php")
        val payload = JSONObject().apply {
            put("action", action)
            if (dogId != null && dogId > 0L) put("dog_id", dogId)
            extras.forEach { (key, value) ->
                if (value.isNotBlank()) put(key, value)
            }
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            val dogJson = json.optJSONObject("dog")
            val handlersJson = json.optJSONArray("handlers") ?: JSONArray()
            val pendingInvitesJson = json.optJSONArray("pending_invites") ?: JSONArray()
            val transfersJson = json.optJSONArray("incoming_transfers") ?: JSONArray()
            ApiResult.Success(
                DogAccessOverview(
                    activeDogId = json.optLong("active_dog_id", 0L),
                    selectedDogId = json.optLong("selected_dog_id", dogId ?: 0L),
                    isOwner = json.optBoolean("is_owner", false),
                    canEdit = json.optBoolean("can_edit", false),
                    dog = dogJson?.let {
                        DogAccessDogSummary(
                            id = it.optLong("id", 0L),
                            name = it.optString("name", ""),
                            ownerUsername = it.optString("owner_username", ""),
                            ownerDisplayName = it.optString("owner_display_name", ""),
                            lifecycleStatus = it.optString("lifecycle_status", "active"),
                            lifecycleNote = it.optString("lifecycle_note", ""),
                        )
                    },
                    handlers = buildList {
                        for (i in 0 until handlersJson.length()) {
                            val row = handlersJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessHandlerRow(
                                    id = row.optLong("id", 0L),
                                    userId = row.optLong("user_id", 0L),
                                    username = row.optString("username", ""),
                                    email = row.optString("email", ""),
                                    displayName = row.optString("display_name", ""),
                                    role = row.optString("role", ""),
                                    permissionLevel = row.optString("permission_level", ""),
                                    accessEndsAt = row.optString("access_ends_at", ""),
                                    accessStatus = row.optString("access_status", ""),
                                )
                            )
                        }
                    },
                    pendingInvites = buildList {
                        for (i in 0 until pendingInvitesJson.length()) {
                            val row = pendingInvitesJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessPendingInviteRow(
                                    id = row.optLong("id", 0L),
                                    dogId = row.optLong("dog_id", 0L),
                                    dogName = row.optString("dog_name", ""),
                                    ownerUsername = row.optString("owner_username", ""),
                                    ownerDisplayName = row.optString("owner_display_name", ""),
                                    role = row.optString("role", ""),
                                    permissionLevel = row.optString("permission_level", ""),
                                    accessEndsAt = row.optString("access_ends_at", ""),
                                    accessStatus = row.optString("access_status", ""),
                                )
                            )
                        }
                    },
                    incomingTransfers = buildList {
                        for (i in 0 until transfersJson.length()) {
                            val row = transfersJson.optJSONObject(i) ?: continue
                            add(
                                DogAccessTransferRow(
                                    id = row.optLong("id", 0L),
                                    dogId = row.optLong("dog_id", 0L),
                                    dogName = row.optString("dog_name", ""),
                                    fromUsername = row.optString("from_username", ""),
                                    fromDisplayName = row.optString("from_display_name", ""),
                                    keepPreviousOwnerAccess = row.optBoolean("keep_previous_owner_access", false),
                                    note = row.optString("note", ""),
                                    requestedAt = row.optString("requested_at", ""),
                                    status = row.optString("status", ""),
                                )
                            )
                        }
                    },
                )
            )
        }
    }

    fun fetchAccessibleDogs(config: BridgeConfig): ApiResult<DogsOverview> {
        val connection = openApiConnection(config, "GET", "/api/dogs.php")
        return decodeJson(connection) { json ->
            val dogsJson = json.optJSONArray("dogs") ?: JSONArray()
            val dogs = buildList {
                for (i in 0 until dogsJson.length()) {
                    val row = dogsJson.optJSONObject(i) ?: continue
                    add(
                        AccessibleDogSummary(
                            id = row.optLong("id", 0L),
                            name = row.optString("name", "Dog"),
                            breed = row.optString("breed", ""),
                            accessRole = row.optString("access_role", "viewer"),
                            lifecycleStatus = row.optString("lifecycle_status", "active"),
                        )
                    )
                }
            }
            ApiResult.Success(
                DogsOverview(
                    activeDogId = json.optLong("active_dog_id", 0L),
                    dogs = dogs,
                )
            )
        }
    }

    fun fetchTrainingLogs(config: BridgeConfig, dogId: Long? = null): ApiResult<TrainingLogFeed> {
        val path = buildString {
            append("/api/logs.php")
            val query = mutableListOf<String>()
            dogId?.takeIf { it > 0L }?.let { query.add("dog_id=$it") }
            if (query.isNotEmpty()) {
                append("?")
                append(query.joinToString("&"))
            }
        }
        val connection = openApiConnection(config, "GET", path)
        return decodeJson(connection) { json ->
            val logsJson = json.optJSONArray("logs") ?: JSONArray()
            val logs = buildList {
                for (i in 0 until logsJson.length()) {
                    val row = logsJson.optJSONObject(i) ?: continue
                    add(parseTrainingLogEntry(row))
                }
            }
            val suggestions = parseStringArray(json, "training_suggestions")
            ApiResult.Success(
                TrainingLogFeed(
                    activeDogId = json.optLong("active_dog_id", 0L),
                    dogId = json.optLong("dog_id", dogId ?: 0L),
                    logs = logs,
                    trainingSuggestions = suggestions,
                )
            )
        }
    }

    fun fetchPublicProfile(config: BridgeConfig, dogId: Long? = null): ApiResult<PublicProfileOverview> {
        val path = buildString {
            append("/api/public_profile.php")
            val query = mutableListOf<String>()
            dogId?.takeIf { it > 0L }?.let { query.add("dog_id=$it") }
            if (query.isNotEmpty()) {
                append("?")
                append(query.joinToString("&"))
            }
        }
        val connection = openApiConnection(config, "GET", path)
        return decodeJson(connection) { json ->
            val dogJson = json.optJSONObject("dog") ?: JSONObject()
            val badgeJson = dogJson.optJSONObject("support_badge")
            ApiResult.Success(
                PublicProfileOverview(
                    dogId = json.optLong("dog_id", dogId ?: 0L),
                    publicUrl = json.optString("public_url", ""),
                    qrUrl = json.optString("qr_url", ""),
                    reportUrl = json.optString("report_url", ""),
                    reportApiUrl = json.optString("report_api_url", ""),
                    reportToken = json.optString("report_token", ""),
                    dog = PublicDogProfile(
                        name = dogJson.optString("name", ""),
                        breed = dogJson.optString("breed", ""),
                        accessRole = dogJson.optString("access_role", ""),
                        supportBadge = badgeJson?.let {
                            PublicProfileSupportBadge(
                                tier = it.optString("tier", ""),
                                label = it.optString("label", ""),
                                image = it.optString("image", ""),
                                lifetime = it.optBoolean("lifetime", false),
                                expiresAt = it.optString("expires_at", "").takeIf { value -> value.isNotBlank() },
                            )
                        },
                        handlerName = dogJson.optString("handler_name", ""),
                        handlerPhone = dogJson.optString("handler_phone", ""),
                        handlerEmail = dogJson.optString("handler_email", ""),
                        backupContactName = dogJson.optString("backup_contact_name", ""),
                        backupContactPhone = dogJson.optString("backup_contact_phone", ""),
                        homeState = dogJson.optString("home_state", ""),
                        publicNotes = dogJson.optString("public_notes", ""),
                        foundDogInstructions = dogJson.optString("found_dog_instructions", ""),
                        criticalAllergies = dogJson.optString("critical_allergies", ""),
                        serviceTasks = dogJson.optString("service_tasks", ""),
                        profilePhotoUrl = dogJson.optString("profile_photo_url", ""),
                    ),
                )
            )
        }
    }

    fun fetchBillingOverview(config: BridgeConfig, dogId: Long? = null): ApiResult<BillingOverview> {
        val path = buildString {
            append("/api/billing.php")
            val query = mutableListOf<String>()
            dogId?.takeIf { it > 0L }?.let { query.add("dog_id=$it") }
            if (query.isNotEmpty()) {
                append("?")
                append(query.joinToString("&"))
            }
        }
        val connection = openApiConnection(config, "GET", path)
        return decodeJson(connection) { json ->
            val planRowsJson = json.optJSONArray("plan_rows") ?: JSONArray()
            val supportOptionsJson = json.optJSONArray("support_options") ?: JSONArray()
            val serviceRowsJson = json.optJSONArray("service_rows") ?: JSONArray()
            val recentSupportEventsJson = json.optJSONArray("recent_support_events") ?: JSONArray()
            val recentPurchaseEventsJson = json.optJSONArray("recent_purchase_events") ?: JSONArray()

            ApiResult.Success(
                BillingOverview(
                    userId = json.optLong("user_id", 0L),
                    username = json.optString("username", ""),
                    activeDogId = json.optLong("active_dog_id", 0L),
                    currentTier = json.optString("current_tier", "free"),
                    currentTierLabel = json.optString("current_tier_label", "Free"),
                    dogCount = json.optInt("dog_count", 0),
                    canCreateAnotherDog = json.optBoolean("can_create_another_dog", false),
                    supportBadge = json.optJSONObject("support_badge")?.let {
                        PublicProfileSupportBadge(
                            tier = it.optString("tier", ""),
                            label = it.optString("label", ""),
                            image = it.optString("image", ""),
                            lifetime = it.optBoolean("lifetime", false),
                            expiresAt = it.optString("expires_at", "").takeIf { value -> value.isNotBlank() },
                        )
                    },
                    planRows = buildList {
                        for (i in 0 until planRowsJson.length()) {
                            val row = planRowsJson.optJSONObject(i) ?: continue
                            add(
                                BillingPlanRow(
                                    slug = row.optString("slug", ""),
                                    label = row.optString("label", ""),
                                    summary = row.optString("summary", ""),
                                    includedText = parseStringArray(row, "included_text"),
                                    lockedText = parseStringArray(row, "locked_text"),
                                    requiredTier = row.optString("required_tier", "free"),
                                    isCurrent = row.optBoolean("is_current", false),
                                )
                            )
                        }
                    },
                    supportOptions = buildList {
                        for (i in 0 until supportOptionsJson.length()) {
                            val row = supportOptionsJson.optJSONObject(i) ?: continue
                            add(
                                BillingSupportOption(
                                    supportType = row.optString("support_type", "one_time"),
                                    label = row.optString("label", ""),
                                    summary = row.optString("summary", ""),
                                    emoji = row.optString("emoji", ""),
                                    mode = row.optString("mode", "payment"),
                                    priceIdConfigured = row.optBoolean("price_id_configured", false),
                                    checkoutAvailable = row.optBoolean("checkout_available", false),
                                )
                            )
                        }
                    },
                    serviceRows = buildList {
                        for (i in 0 until serviceRowsJson.length()) {
                            val row = serviceRowsJson.optJSONObject(i) ?: continue
                            add(
                                BillingServiceRow(
                                    slug = row.optString("slug", ""),
                                    label = row.optString("label", ""),
                                    summary = row.optString("summary", ""),
                                    includedText = parseStringArray(row, "included_text"),
                                    lockedText = parseStringArray(row, "locked_text"),
                                    billingModel = row.optString("billing_model", "plan"),
                                    requiredTier = row.optString("required_tier", "free"),
                                    scope = row.optString("scope", "user"),
                                    priceCents = row.optInt("price_cents", 0),
                                    currency = row.optString("currency", "usd"),
                                    stripePriceId = row.optString("stripe_price_id", ""),
                                    notes = row.optString("notes", ""),
                                    active = row.optBoolean("active", false),
                                    checkoutAvailable = row.optBoolean("checkout_available", false),
                                    requiresActiveDog = row.optBoolean("requires_active_dog", false),
                                    actionLabel = row.optString("action_label", ""),
                                )
                            )
                        }
                    },
                    recentSupportEvents = buildList {
                        for (i in 0 until recentSupportEventsJson.length()) {
                            val row = recentSupportEventsJson.optJSONObject(i) ?: continue
                            add(
                                BillingEventRow(
                                    source = row.optString("source", "support"),
                                    title = row.optString("title", "Support"),
                                    amountCents = row.optInt("amount_cents", 0),
                                    currency = row.optString("currency", "usd"),
                                    status = row.optString("status", ""),
                                    createdAt = row.optString("created_at", ""),
                                    details = row.optString("details", ""),
                                )
                            )
                        }
                    },
                    recentPurchaseEvents = buildList {
                        for (i in 0 until recentPurchaseEventsJson.length()) {
                            val row = recentPurchaseEventsJson.optJSONObject(i) ?: continue
                            add(
                                BillingEventRow(
                                    source = row.optString("source", "purchase"),
                                    title = row.optString("title", "Service purchase"),
                                    amountCents = row.optInt("amount_cents", 0),
                                    currency = row.optString("currency", "usd"),
                                    status = row.optString("status", ""),
                                    createdAt = row.optString("created_at", ""),
                                    details = row.optString("details", ""),
                                )
                            )
                        }
                    },
                )
            )
        }
    }

    fun submitFoundDogReport(
        reportApiUrl: String,
        dogId: Long,
        token: String,
        finderLocation: String,
        finderName: String,
        finderPhone: String,
        finderMessage: String,
        finderLatitude: Double? = null,
        finderLongitude: Double? = null,
        finderAccuracyM: Int? = null,
    ): ApiResult<FoundDogReportResult> {
        val connection = openBareConnection(reportApiUrl, "POST", "")
        val payload = JSONObject().apply {
            put("dog_id", dogId)
            put("token", token)
            put("finder_location", finderLocation)
            put("finder_name", finderName)
            put("finder_phone", finderPhone)
            put("finder_message", finderMessage)
            if (finderLatitude != null) put("finder_latitude", finderLatitude)
            if (finderLongitude != null) put("finder_longitude", finderLongitude)
            if (finderAccuracyM != null) put("finder_accuracy_m", finderAccuracyM)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            ApiResult.Success(
                FoundDogReportResult(
                    reportId = json.optLong("report_id", 0L),
                    notified = json.optBoolean("notified", false),
                    message = json.optString("message", "Reported."),
                )
            )
        }
    }

    fun startBillingCheckout(
        config: BridgeConfig,
        kind: String,
        supportType: String = "",
        serviceSlug: String = "",
        dogId: Long? = null,
    ): ApiResult<BillingCheckoutResult> {
        val connection = openApiConnection(config, "POST", "/api/billing.php")
        val payload = JSONObject().apply {
            put("action", "start_checkout")
            put("kind", kind)
            if (supportType.isNotBlank()) put("support_type", supportType)
            if (serviceSlug.isNotBlank()) put("service_slug", serviceSlug)
            if (dogId != null && dogId > 0L) put("dog_id", dogId)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            ApiResult.Success(
                BillingCheckoutResult(
                    kind = json.optString("kind", kind),
                    supportType = json.optString("support_type", supportType),
                    serviceSlug = json.optString("service_slug", serviceSlug),
                    dogId = json.optLong("dog_id", dogId ?: 0L),
                    checkoutUrl = json.optString("checkout_url", ""),
                    message = json.optString("message", "Opened."),
                )
            )
        }
    }

    fun fetchTrainingLogDetail(config: BridgeConfig, logId: Long): ApiResult<TrainingLogEntry> {
        val connection = openApiConnection(config, "GET", "/api/logs.php?log_id=$logId")
        return decodeJson(connection) { json ->
            val row = json.optJSONObject("log") ?: JSONObject()
            ApiResult.Success(parseTrainingLogEntry(row))
        }
    }

    fun saveTrainingLog(
        config: BridgeConfig,
        logId: Long?,
        dogId: Long,
        logDate: String?,
        locationName: String,
        locationCityState: String,
        locationType: String,
        focusLevel: Int,
        skills: List<String>,
        handlerNotes: String,
        latitude: Double? = null,
        longitude: Double? = null,
    ): ApiResult<TrainingLogSaveResult> {
        val connection = openApiConnection(config, "POST", "/api/logs.php")
        val payload = JSONObject().apply {
            if (logId != null && logId > 0L) put("id", logId)
            put("dog_id", dogId)
            put("location_name", locationName)
            put("location_city_state", locationCityState)
            put("location_type", locationType)
            put("focus_level", focusLevel)
            put("skills", JSONArray(skills))
            put("handler_notes", handlerNotes)
            if (!logDate.isNullOrBlank()) put("log_date", logDate)
            if (latitude != null) put("latitude", latitude)
            if (longitude != null) put("longitude", longitude)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            ApiResult.Success(
                TrainingLogSaveResult(
                    logId = json.optLong("log_id", logId ?: 0L),
                    message = json.optString("message", "Saved."),
                    trainingSuggestions = parseStringArray(json, "training_suggestions"),
                )
            )
        }
    }

    fun setActiveDog(config: BridgeConfig, dogId: Long): ApiResult<Long> {
        val connection = openApiConnection(config, "POST", "/api/dogs.php")
        val payload = JSONObject().apply {
            put("action", "set_active_dog")
            put("dog_id", dogId)
        }
        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }
        return decodeJson(connection) { json ->
            ApiResult.Success(json.optLong("active_dog_id", dogId))
        }
    }

    fun postSnapshot(config: BridgeConfig, snapshot: HealthSnapshot): UploadResult {
        if (config.dogId <= 0L) {
            return UploadResult.Failure(422, "Choose a dog before syncing wearable data.")
        }
        val connection = openEndpointConnection(config, "POST")

        val payload = JSONObject().apply {
            put("dog_id", config.dogId)
            put("source", config.source)
            put("device_name", "Samsung Health / Health Connect")
            put("recorded_for_date", snapshot.recordedForDate)
            put("steps", snapshot.steps)
            put("distance_miles", snapshot.distanceMiles)
            put("total_calories_burned", snapshot.totalCaloriesBurned)
            put("activity_intensity_minutes", snapshot.activityIntensityMinutes)
            put("avg_heart_rate", snapshot.avgHeartRate)
            put("resting_heart_rate", snapshot.restingHeartRate)
            put("sleep_hours", snapshot.sleepHours)
            put("summary_text", snapshot.summaryText)
            put("notes", "Synced automatically from GuidePaw Bridge.")
        }

        connection.outputStream.use { stream ->
            stream.write(payload.toString().toByteArray(Charsets.UTF_8))
        }

        val status = connection.responseCode
        val body = runCatching { connection.inputStream.bufferedReader().use { it.readText() } }
            .getOrElse { runCatching { connection.errorStream?.bufferedReader()?.use { it.readText() }.orEmpty() }.getOrDefault("") }

        return if (status in 200..299) {
            UploadResult.Success(status, body)
        } else {
            UploadResult.Failure(status, body.ifBlank { "Upload failed." })
        }
    }

    fun fetchWearableOverview(config: BridgeConfig, dogId: Long? = null): ApiResult<WearableOverview> {
        val path = buildString {
            append("/api/wearables.php")
            if (dogId != null && dogId > 0L) {
                append("?dog_id=")
                append(dogId)
            }
        }
        val connection = openApiConnection(config, "GET", path)
        return decodeJson(connection) { json ->
            ApiResult.Success(
                WearableOverview(
                    userId = json.optLong("user_id", 0L),
                    dogId = json.optLong("dog_id", dogId ?: 0L),
                    setup = parseWearableSetup(json.optJSONObject("setup")),
                    summary = parseWearableSummary(json.optJSONObject("summary") ?: JSONObject()),
                    events = parseWearableEvents(json.optJSONArray("events")),
                )
            )
        }
    }

    private fun openEndpointConnection(config: BridgeConfig, method: String): HttpURLConnection {
        return (URL(config.endpoint.trim()).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            doOutput = method == "POST"
            setRequestProperty("Authorization", "Bearer ${config.token}")
            setRequestProperty("X-API-Token", config.token)
            setRequestProperty("Content-Type", "application/json; charset=utf-8")
            connectTimeout = 15000
            readTimeout = 15000
        }
    }

    private fun openBareConnection(apiBaseUrl: String, method: String, path: String): HttpURLConnection {
        val base = apiBaseUrl.trim().trimEnd('/')
        val suffix = when {
            path.isBlank() -> ""
            path.startsWith("/") -> path
            else -> "/$path"
        }
        return (URL(base + suffix).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            doOutput = method == "POST"
            setRequestProperty("Content-Type", "application/json; charset=utf-8")
            connectTimeout = 15000
            readTimeout = 15000
        }
    }

    private fun openApiConnection(config: BridgeConfig, method: String, path: String): HttpURLConnection {
        val endpoint = config.endpoint.trim()
        val apiRoot = endpoint.substringBefore("/api/", endpoint).trimEnd('/')
        val suffix = if (path.startsWith("/")) path else "/$path"
        return (URL(apiRoot + suffix).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            doOutput = method == "POST"
            setRequestProperty("Authorization", "Bearer ${config.token}")
            setRequestProperty("X-API-Token", config.token)
            setRequestProperty("Content-Type", "application/json; charset=utf-8")
            connectTimeout = 15000
            readTimeout = 15000
        }
    }

    private fun <T> decodeJson(connection: HttpURLConnection, parser: (JSONObject) -> ApiResult<T>): ApiResult<T> {
        val status = connection.responseCode
        val body = readBody(connection)
        return if (status in 200..299) {
            runCatching { parser(JSONObject(body.ifBlank { "{}" })) }
                .getOrElse { ApiResult.Failure(status, it.message ?: "Unable to parse response.") }
        } else {
            ApiResult.Failure(status, body.ifBlank { "Request failed." })
        }
    }

    private fun readBody(connection: HttpURLConnection): String {
        return runCatching { connection.inputStream.bufferedReader().use { it.readText() } }
            .getOrElse { runCatching { connection.errorStream?.bufferedReader()?.use { it.readText() }.orEmpty() }.getOrDefault("") }
    }

    private fun parseTrainingLogEntry(row: JSONObject): TrainingLogEntry {
        return TrainingLogEntry(
            id = row.optLong("id", 0L),
            dogId = row.optLong("dog_id", 0L),
            userId = row.optLong("user_id", 0L),
            handlerUsername = row.optString("handler_username", ""),
            logDate = row.optString("log_date", ""),
            locationName = row.optString("location_name", ""),
            locationCityState = row.optString("location_city_state", ""),
            locationType = row.optString("location_type", ""),
            focusLevel = row.optInt("focus_level", 3),
            skillsPracticed = parseStringArray(row, "skills_practiced"),
            handlerNotes = row.optString("handler_notes", ""),
            latitude = if (row.isNull("latitude")) null else row.optDouble("latitude"),
            longitude = if (row.isNull("longitude")) null else row.optDouble("longitude"),
        )
    }

    private fun parseWearableSummary(json: JSONObject): WearableTrendSummary {
        return WearableTrendSummary(
            eventCount = json.optInt("event_count", 0),
            totalSteps = json.optInt("total_steps", 0),
            totalActiveMinutes = json.optInt("total_active_minutes", 0),
            totalRestMinutes = json.optInt("total_rest_minutes", 0),
            totalPlayMinutes = json.optInt("total_play_minutes", 0),
            activityIntensityMinutes = when {
                !json.isNull("total_activity_intensity_minutes") -> json.optLong("total_activity_intensity_minutes")
                !json.isNull("avg_activity_intensity_minutes") -> json.optLong("avg_activity_intensity_minutes")
                !json.isNull("activity_intensity_minutes") -> json.optLong("activity_intensity_minutes")
                else -> null
            },
            avgBatteryPercent = if (json.isNull("avg_battery_percent")) null else json.optDouble("avg_battery_percent"),
            avgDistanceMiles = if (json.isNull("avg_distance_miles")) null else json.optDouble("avg_distance_miles"),
            totalCaloriesBurned = if (json.isNull("avg_total_calories_burned")) null else json.optDouble("avg_total_calories_burned"),
            avgHeartRate = if (json.isNull("avg_heart_rate")) null else json.optDouble("avg_heart_rate"),
            avgRestingHeartRate = if (json.isNull("avg_resting_heart_rate")) null else json.optDouble("avg_resting_heart_rate"),
            avgSleepHours = if (json.isNull("avg_sleep_hours")) null else json.optDouble("avg_sleep_hours"),
        )
    }

    private fun parseWearableSetup(json: JSONObject?): WearableDeviceSetup? {
        if (json == null || json.length() == 0) {
            return null
        }
        return WearableDeviceSetup(
            handlerWearableSlug = json.optString("handler_wearable_slug", ""),
            dogTrackerSlug = json.optString("dog_tracker_slug", ""),
            syncMode = json.optString("sync_mode", ""),
            notes = json.optString("notes", ""),
            handlerWearableLabel = json.optString("handler_wearable_label", ""),
            dogTrackerLabel = json.optString("dog_tracker_label", ""),
            syncModeLabel = json.optString("sync_mode_label", ""),
            handlerWearableVendor = json.optString("handler_wearable_vendor", ""),
            dogTrackerVendor = json.optString("dog_tracker_vendor", ""),
            handlerWearablePairingMode = json.optString("handler_wearable_pairing_mode", ""),
            dogTrackerPairingMode = json.optString("dog_tracker_pairing_mode", ""),
            handlerWearableDataFocus = json.optString("handler_wearable_data_focus", ""),
            dogTrackerDataFocus = json.optString("dog_tracker_data_focus", ""),
        )
    }

    private fun parseWearableEvent(row: JSONObject): WearableSyncEvent {
        return WearableSyncEvent(
            id = row.optLong("id", 0L),
            recordedForDate = row.optString("recorded_for_date", ""),
            createdAt = row.optString("created_at", ""),
            dogName = row.optString("dog_name", ""),
            source = row.optString("source", ""),
            deviceName = row.optString("device_name", ""),
            steps = if (row.isNull("steps")) null else row.optInt("steps"),
            activeMinutes = if (row.isNull("active_minutes")) null else row.optInt("active_minutes"),
            restMinutes = if (row.isNull("rest_minutes")) null else row.optInt("rest_minutes"),
            playMinutes = if (row.isNull("play_minutes")) null else row.optInt("play_minutes"),
            batteryPercent = if (row.isNull("battery_percent")) null else row.optInt("battery_percent"),
            distanceMiles = if (row.isNull("distance_miles")) null else row.optDouble("distance_miles"),
            totalCaloriesBurned = if (row.isNull("total_calories_burned")) null else row.optDouble("total_calories_burned"),
            activityIntensityMinutes = if (row.isNull("activity_intensity_minutes")) null else row.optLong("activity_intensity_minutes"),
            avgHeartRate = if (row.isNull("avg_heart_rate")) null else row.optDouble("avg_heart_rate"),
            restingHeartRate = if (row.isNull("resting_heart_rate")) null else row.optDouble("resting_heart_rate"),
            sleepHours = if (row.isNull("sleep_hours")) null else row.optDouble("sleep_hours"),
            summaryText = row.optString("summary_text", ""),
        )
    }

    private fun parseWearableEvents(array: JSONArray?): List<WearableSyncEvent> {
        if (array == null) {
            return emptyList()
        }
        return buildList {
            for (i in 0 until array.length()) {
                val row = array.optJSONObject(i) ?: continue
                add(parseWearableEvent(row))
            }
        }
    }

    private fun parseStringArray(json: JSONObject, key: String): List<String> {
        val raw = json.opt(key)
        return when (raw) {
            is JSONArray -> buildList {
                for (i in 0 until raw.length()) {
                    val value = raw.optString(i, "").trim()
                    if (value.isNotBlank()) add(value)
                }
            }
            is String -> runCatching {
                val parsed = JSONArray(raw)
                buildList {
                    for (i in 0 until parsed.length()) {
                        val value = parsed.optString(i, "").trim()
                        if (value.isNotBlank()) add(value)
                    }
                }
            }.getOrDefault(emptyList())
            else -> emptyList()
        }
    }
}

sealed class UploadResult {
    data class Success(val status: Int, val body: String) : UploadResult()
    data class Failure(val status: Int, val message: String) : UploadResult()
}

sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Failure(val status: Int, val message: String) : ApiResult<Nothing>()
}
