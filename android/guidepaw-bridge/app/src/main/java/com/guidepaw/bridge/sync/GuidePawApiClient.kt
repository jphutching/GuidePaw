package com.guidepaw.bridge.sync

import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.model.AccessibleDogSummary
import com.guidepaw.bridge.model.AccountOverview
import com.guidepaw.bridge.model.HealthSnapshot
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class GuidePawApiClient {
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
                )
            )
        }
    }

    fun fetchAccessibleDogs(config: BridgeConfig): ApiResult<List<AccessibleDogSummary>> {
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
            ApiResult.Success(dogs)
        }
    }

    fun postSnapshot(config: BridgeConfig, snapshot: HealthSnapshot): UploadResult {
        val connection = openEndpointConnection(config, "POST")

        val payload = JSONObject().apply {
            put("dog_id", config.dogId)
            put("source", config.source)
            put("device_name", "Samsung Health / Health Connect")
            put("recorded_for_date", snapshot.recordedForDate)
            put("steps", snapshot.steps)
            put("avg_heart_rate", snapshot.avgHeartRate)
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
}

sealed class UploadResult {
    data class Success(val status: Int, val body: String) : UploadResult()
    data class Failure(val status: Int, val message: String) : UploadResult()
}

sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Failure(val status: Int, val message: String) : ApiResult<Nothing>()
}
