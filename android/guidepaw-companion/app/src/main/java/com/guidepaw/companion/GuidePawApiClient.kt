package com.guidepaw.companion

import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.nio.charset.StandardCharsets

data class GuidePawLoginResult(
    val success: Boolean,
    val token: String? = null,
    val requiresTwoFactor: Boolean = false,
    val message: String? = null,
)

data class GuidePawMeResult(
    val username: String,
    val activeDogId: Int?,
    val dbDriver: String?,
    val schemaVersion: String?,
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

    fun me(token: String): GuidePawMeResult {
        val response = requestJson("api/me.php", "GET", token, null)
        ensureSuccess(response)
        val user = response.json.optJSONObject("user") ?: JSONObject()
        return GuidePawMeResult(
            username = user.optString("username", ""),
            activeDogId = optNullableInt(response.json, "active_dog_id"),
            dbDriver = response.json.optText("db_driver"),
            schemaVersion = response.json.optText("schema_version"),
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

    private fun requestJson(path: String, method: String, token: String?, body: JSONObject?): ApiResponse {
        val endpoint = URL(baseUrl.trimEnd('/') + "/" + path.trimStart('/'))
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

    private fun ensureSuccess(response: ApiResponse) {
        if (response.statusCode in 200..299 && response.json.optBoolean("success", true)) {
            return
        }
        throw GuidePawApiException(
            statusCode = response.statusCode,
            message = response.json.optString("message", "Request failed"),
            payload = response.json,
        )
    }

    private fun HttpURLConnection.responseText(): String {
        val stream = runCatching { if (responseCode >= 400) errorStream else inputStream }.getOrNull() ?: return ""
        return stream.use { input ->
            BufferedReader(InputStreamReader(input, StandardCharsets.UTF_8)).use { it.readText() }
        }
    }

    private fun optNullableInt(json: JSONObject, key: String): Int? {
        return if (json.has(key) && !json.isNull(key)) {
            val value = json.optString(key, "").trim()
            if (value.isNotBlank()) value.toIntOrNull() ?: json.optInt(key, 0).takeIf { it > 0 } else null
        } else {
            null
        }
    }

    private fun JSONObject.optText(key: String): String? {
        return optString(key, "").trim().takeIf { it.isNotBlank() }
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

    private fun JSONArray.toList(): List<Any?> = (0 until length()).map { opt(it) }

    private data class ApiResponse(
        val statusCode: Int,
        val json: JSONObject,
        val rawText: String,
    )
}
