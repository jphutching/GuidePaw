package com.guidepaw.bridge.model

data class HealthSnapshot(
    val recordedForDate: String,
    val steps: Long?,
    val distanceMiles: Double?,
    val totalCaloriesBurned: Double?,
    val activityIntensityMinutes: Long?,
    val avgHeartRate: Long?,
    val minHeartRate: Long?,
    val maxHeartRate: Long?,
    val restingHeartRate: Long?,
    val sleepHours: Double?,
    val summaryText: String,
)
