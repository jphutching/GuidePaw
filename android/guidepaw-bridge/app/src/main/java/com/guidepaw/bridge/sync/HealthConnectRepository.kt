package com.guidepaw.bridge.sync

import android.content.Context
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.records.HeartRateRecord
import androidx.health.connect.client.records.StepsRecord
import androidx.health.connect.client.request.AggregateRequest
import androidx.health.connect.client.time.TimeRangeFilter
import com.guidepaw.bridge.model.HealthSnapshot
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

class HealthConnectRepository(context: Context) {
    private val client = HealthConnectClient.getOrCreate(context)

    suspend fun buildTodaySnapshot(): HealthSnapshot = withContext(Dispatchers.IO) {
        val zoneId = ZoneId.systemDefault()
        val start = LocalDate.now(zoneId).atStartOfDay(zoneId).toInstant()
        val end = Instant.now()
        val result = client.aggregate(
            AggregateRequest(
                metrics = setOf(
                    StepsRecord.COUNT_TOTAL,
                    HeartRateRecord.BPM_AVG,
                    HeartRateRecord.BPM_MIN,
                    HeartRateRecord.BPM_MAX,
                ),
                timeRangeFilter = TimeRangeFilter.between(start, end),
                dataOriginFilter = emptySet(),
            )
        )

        val steps = result[StepsRecord.COUNT_TOTAL]
        val avgHr = result[HeartRateRecord.BPM_AVG]
        val minHr = result[HeartRateRecord.BPM_MIN]
        val maxHr = result[HeartRateRecord.BPM_MAX]
        val summary = buildString {
            append("Synced from Samsung Health / Health Connect.")
            if (steps != null) append(" Steps today: $steps.")
            if (avgHr != null) append(" Avg heart rate: $avgHr bpm.")
            if (minHr != null && maxHr != null) append(" Range: $minHr-$maxHr bpm.")
        }

        HealthSnapshot(
            recordedForDate = LocalDate.now(zoneId).toString(),
            steps = steps,
            avgHeartRate = avgHr,
            minHeartRate = minHr,
            maxHeartRate = maxHr,
            summaryText = summary,
        )
    }
}
