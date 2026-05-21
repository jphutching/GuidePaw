package com.guidepaw.companion.model

data class BridgeConfig(
    val endpoint: String,
    val token: String,
    val dogId: Long,
    val dogName: String,
    val source: String = "health_connect",
)
