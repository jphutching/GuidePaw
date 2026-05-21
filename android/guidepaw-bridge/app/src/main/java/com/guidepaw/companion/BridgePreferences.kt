package com.guidepaw.companion

import android.content.Context
import com.guidepaw.companion.model.BridgeConfig

class BridgePreferences(context: Context) {
    private val prefs = context.getSharedPreferences("guidepaw_bridge", Context.MODE_PRIVATE)

    fun save(config: BridgeConfig) {
        prefs.edit()
            .putString(KEY_ENDPOINT, config.endpoint)
            .putString(KEY_TOKEN, config.token)
            .putLong(KEY_DOG_ID, config.dogId)
            .putString(KEY_DOG_NAME, config.dogName)
            .putString(KEY_SOURCE, config.source)
            .apply()
    }

    fun load(): BridgeConfig? {
        val endpoint = prefs.getString(KEY_ENDPOINT, "")?.trim().orEmpty()
        val token = prefs.getString(KEY_TOKEN, "")?.trim().orEmpty()
        val dogId = prefs.getLong(KEY_DOG_ID, 0L)
        val dogName = prefs.getString(KEY_DOG_NAME, "")?.trim().orEmpty()
        val source = prefs.getString(KEY_SOURCE, "health_connect")?.trim().orEmpty()
        if (endpoint.isBlank() || token.isBlank()) return null
        return BridgeConfig(endpoint = endpoint, token = token, dogId = dogId, dogName = dogName, source = if (source.isBlank()) "health_connect" else source)
    }

    fun setAutoSyncEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_AUTO_SYNC, enabled).apply()
    }

    fun isAutoSyncEnabled(): Boolean = prefs.getBoolean(KEY_AUTO_SYNC, false)

    fun setLastSyncAt(epochMillis: Long) {
        prefs.edit().putLong(KEY_LAST_SYNC, epochMillis).apply()
    }

    fun getLastSyncAt(): Long = prefs.getLong(KEY_LAST_SYNC, 0L)

    companion object {
        private const val KEY_ENDPOINT = "endpoint"
        private const val KEY_TOKEN = "token"
        private const val KEY_DOG_ID = "dog_id"
        private const val KEY_DOG_NAME = "dog_name"
        private const val KEY_SOURCE = "source"
        private const val KEY_AUTO_SYNC = "auto_sync"
        private const val KEY_LAST_SYNC = "last_sync"
    }
}
