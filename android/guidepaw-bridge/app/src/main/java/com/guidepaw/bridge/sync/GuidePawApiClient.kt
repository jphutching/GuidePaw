package com.guidepaw.bridge.sync

import com.guidepaw.bridge.model.BridgeConfig
import com.guidepaw.bridge.model.HealthSnapshot
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class GuidePawApiClient {
    fun postSnapshot(config: BridgeConfig, snapshot: HealthSnapshot): UploadResult {
        val connection = (URL(config.endpoint).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            doOutput = true
            setRequestProperty("Authorization", "Bearer ${config.token}")
            setRequestProperty("X-API-Token", config.token)
            setRequestProperty("Content-Type", "application/json; charset=utf-8")
            connectTimeout = 15000
            readTimeout = 15000
        }

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
}

sealed class UploadResult {
    data class Success(val status: Int, val body: String) : UploadResult()
    data class Failure(val status: Int, val message: String) : UploadResult()
}
