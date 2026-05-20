package com.guidepaw.bridge.model

data class AccountOverview(
    val userId: Long,
    val username: String,
    val dbDriver: String,
    val schemaVersion: Int,
    val activeDogId: Long,
)

data class AccessibleDogSummary(
    val id: Long,
    val name: String,
    val breed: String,
    val accessRole: String,
    val lifecycleStatus: String,
)

data class DogsOverview(
    val activeDogId: Long,
    val dogs: List<AccessibleDogSummary>,
)

data class LoginSession(
    val token: String,
    val expiresAt: String,
    val userId: Long,
    val username: String,
)
