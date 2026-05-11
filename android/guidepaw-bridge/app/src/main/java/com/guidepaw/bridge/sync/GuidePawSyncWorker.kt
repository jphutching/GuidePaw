package com.guidepaw.bridge.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.guidepaw.bridge.BridgePreferences
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class GuidePawSyncWorker(
    appContext: Context,
    params: WorkerParameters,
) : CoroutineWorker(appContext, params) {
    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = BridgePreferences(applicationContext)
        val config = prefs.load() ?: return@withContext Result.success()

        runCatching {
            val snapshot = HealthConnectRepository(applicationContext).buildTodaySnapshot()
            when (val upload = GuidePawApiClient().postSnapshot(config, snapshot)) {
                is UploadResult.Success -> {
                    prefs.setLastSyncAt(System.currentTimeMillis())
                    Result.success()
                }
                is UploadResult.Failure -> Result.retry()
            }
        }.getOrElse { Result.retry() }
    }
}

object GuidePawSyncScheduler {
    private const val WORK_NAME = "guidepaw_wearable_sync"

    fun schedule(context: Context, enabled: Boolean) {
        val workManager = androidx.work.WorkManager.getInstance(context)
        if (!enabled) {
            workManager.cancelUniqueWork(WORK_NAME)
            return
        }

        val request = androidx.work.PeriodicWorkRequestBuilder<GuidePawSyncWorker>(6, java.util.concurrent.TimeUnit.HOURS).build()
        workManager.enqueueUniquePeriodicWork(WORK_NAME, androidx.work.ExistingPeriodicWorkPolicy.UPDATE, request)
    }

    fun runNow(context: Context) {
        val request = androidx.work.OneTimeWorkRequestBuilder<GuidePawSyncWorker>().build()
        androidx.work.WorkManager.getInstance(context).enqueueUniqueWork(
            "${WORK_NAME}_now",
            androidx.work.ExistingWorkPolicy.REPLACE,
            request,
        )
    }
}
