package com.guidepaw.bridge.model

data class HealthSnapshot(
    val recordedForDate: String,
    val steps: Long?,
    val avgHeartRate: Long?,
    val minHeartRate: Long?,
    val maxHeartRate: Long?,
    val summaryText: String,
)
