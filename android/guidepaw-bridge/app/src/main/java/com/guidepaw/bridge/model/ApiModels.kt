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

data class BillingPlanRow(
    val slug: String,
    val label: String,
    val summary: String,
    val includedText: List<String>,
    val lockedText: List<String>,
    val requiredTier: String,
    val isCurrent: Boolean,
)

data class BillingSupportOption(
    val supportType: String,
    val label: String,
    val summary: String,
    val emoji: String,
    val mode: String,
    val priceIdConfigured: Boolean,
    val checkoutAvailable: Boolean,
)

data class BillingServiceRow(
    val slug: String,
    val label: String,
    val summary: String,
    val includedText: List<String>,
    val lockedText: List<String>,
    val billingModel: String,
    val requiredTier: String,
    val scope: String,
    val priceCents: Int,
    val currency: String,
    val stripePriceId: String,
    val notes: String,
    val active: Boolean,
    val checkoutAvailable: Boolean,
    val requiresActiveDog: Boolean,
    val actionLabel: String,
)

data class BillingEventRow(
    val source: String,
    val title: String,
    val amountCents: Int,
    val currency: String,
    val status: String,
    val createdAt: String,
    val details: String,
)

data class BillingOverview(
    val userId: Long,
    val username: String,
    val activeDogId: Long,
    val currentTier: String,
    val currentTierLabel: String,
    val dogCount: Int,
    val canCreateAnotherDog: Boolean,
    val supportBadge: PublicProfileSupportBadge?,
    val planRows: List<BillingPlanRow>,
    val supportOptions: List<BillingSupportOption>,
    val serviceRows: List<BillingServiceRow>,
    val recentSupportEvents: List<BillingEventRow>,
    val recentPurchaseEvents: List<BillingEventRow>,
)

data class BillingCheckoutResult(
    val kind: String,
    val supportType: String,
    val serviceSlug: String,
    val dogId: Long,
    val checkoutUrl: String,
    val message: String,
)

data class LoginSession(
    val token: String,
    val expiresAt: String,
    val userId: Long,
    val username: String,
)
