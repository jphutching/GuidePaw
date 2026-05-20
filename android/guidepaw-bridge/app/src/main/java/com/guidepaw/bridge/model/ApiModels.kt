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

data class TrainingLogEntry(
    val id: Long,
    val dogId: Long,
    val userId: Long,
    val handlerUsername: String,
    val logDate: String,
    val locationName: String,
    val locationCityState: String,
    val locationType: String,
    val focusLevel: Int,
    val skillsPracticed: List<String>,
    val handlerNotes: String,
    val latitude: Double?,
    val longitude: Double?,
)

data class TrainingLogFeed(
    val activeDogId: Long,
    val dogId: Long,
    val logs: List<TrainingLogEntry>,
    val trainingSuggestions: List<String>,
)

data class TrainingLogSaveResult(
    val logId: Long,
    val message: String,
    val trainingSuggestions: List<String>,
)

data class PublicProfileSupportBadge(
    val tier: String,
    val label: String,
    val image: String,
    val lifetime: Boolean,
    val expiresAt: String?,
)

data class PublicDogProfile(
    val name: String,
    val breed: String,
    val accessRole: String,
    val supportBadge: PublicProfileSupportBadge?,
    val handlerName: String,
    val handlerPhone: String,
    val handlerEmail: String,
    val backupContactName: String,
    val backupContactPhone: String,
    val homeState: String,
    val publicNotes: String,
    val foundDogInstructions: String,
    val criticalAllergies: String,
    val serviceTasks: String,
    val profilePhotoUrl: String,
)

data class PublicProfileOverview(
    val dogId: Long,
    val publicUrl: String,
    val qrUrl: String,
    val reportUrl: String,
    val reportApiUrl: String,
    val reportToken: String,
    val dog: PublicDogProfile,
)

data class FoundDogReportResult(
    val reportId: Long,
    val notified: Boolean,
    val message: String,
)

data class LoginSession(
    val token: String,
    val expiresAt: String,
    val userId: Long,
    val username: String,
)
