package com.guidepaw.bridge.sync

import android.content.Context
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.records.DistanceRecord
import androidx.health.connect.client.records.HeartRateRecord
import androidx.health.connect.client.records.RestingHeartRateRecord
import androidx.health.connect.client.records.SleepSessionRecord
import androidx.health.connect.client.records.StepsRecord
import androidx.health.connect.client.records.TotalCaloriesBurnedRecord
import androidx.health.connect.client.request.AggregateRequest
import androidx.health.connect.client.time.TimeRangeFilter
import com.guidepaw.bridge.model.HealthSnapshot
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.util.Locale

class HealthConnectRepository(context: Context) {
    private val client = HealthConnectClient.getOrCreate(context)

    suspend fun buildTodaySnapshot(): HealthSnapshot = withContext(Dispatchers.IO) {
        val zoneId = ZoneId.systemDefault()
        val activityStart = LocalDate.now(zoneId).atStartOfDay(zoneId).toInstant()
        val sleepStart = activityStart.minusSeconds(24 * 60 * 60)
        val end = Instant.now()
        val activityResult = client.aggregate(
            AggregateRequest(
                metrics = setOf(
                    StepsRecord.COUNT_TOTAL,
                    DistanceRecord.DISTANCE_TOTAL,
                    TotalCaloriesBurnedRecord.ENERGY_TOTAL,
                    HeartRateRecord.BPM_AVG,
                    HeartRateRecord.BPM_MIN,
                    HeartRateRecord.BPM_MAX,
                    RestingHeartRateRecord.BPM_AVG,
                ),
                timeRangeFilter = TimeRangeFilter.between(activityStart, end),
                dataOriginFilter = emptySet(),
            )
        )
        val sleepResult = client.aggregate(
            AggregateRequest(
                metrics = setOf(SleepSessionRecord.SLEEP_DURATION_TOTAL),
                timeRangeFilter = TimeRangeFilter.between(sleepStart, end),
                dataOriginFilter = emptySet(),
            )
        )

        val steps = activityResult[StepsRecord.COUNT_TOTAL]
        val distance = activityResult[DistanceRecord.DISTANCE_TOTAL]
        val calories = activityResult[TotalCaloriesBurnedRecord.ENERGY_TOTAL]
        val avgHr = activityResult[HeartRateRecord.BPM_AVG]
        val minHr = activityResult[HeartRateRecord.BPM_MIN]
        val maxHr = activityResult[HeartRateRecord.BPM_MAX]
        val restingHr = activityResult[RestingHeartRateRecord.BPM_AVG]
        val sleepDuration = sleepResult[SleepSessionRecord.SLEEP_DURATION_TOTAL]
        val sleepHours = sleepDuration?.seconds?.div(3600.0)
        val summary = buildString {
            append("Synced from Samsung Health / Health Connect.")
            if (steps != null) append(" Steps today: $steps.")
            if (distance != null) append(" Distance today: ${String.format(Locale.US, "%.2f", distance.inMiles)} mi.")
            if (calories != null) append(" Calories burned: ${String.format(Locale.US, "%.0f", calories.inKilocalories)} kcal.")
            if (sleepHours != null) append(" Sleep last 24h: ${String.format(Locale.US, "%.1f", sleepHours)} h.")
            if (avgHr != null) append(" Avg heart rate: $avgHr bpm.")
            if (minHr != null && maxHr != null) append(" Range: $minHr-$maxHr bpm.")
            if (restingHr != null) append(" Resting heart rate: ${String.format(Locale.US, "%.0f", restingHr)} bpm.")
        }

        HealthSnapshot(
            recordedForDate = LocalDate.now(zoneId).toString(),
            steps = steps,
            distanceMiles = distance?.inMiles,
            totalCaloriesBurned = calories?.inKilocalories,
            avgHeartRate = avgHr,
            minHeartRate = minHr,
            maxHeartRate = maxHr,
            restingHeartRate = restingHr,
            sleepHours = sleepHours,
            summaryText = summary,
        )
    }
}
