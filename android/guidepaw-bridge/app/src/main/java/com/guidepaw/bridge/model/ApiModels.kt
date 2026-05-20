package com.guidepaw.bridge.model

data class AccountOverview(
    val userId: Long,
    val username: String,
    val dbDriver: String,
    val schemaVersion: Int,
)

data class AccessibleDogSummary(
    val id: Long,
    val name: String,
    val breed: String,
    val accessRole: String,
    val lifecycleStatus: String,
)

