package com.guidepaw.companion

import android.content.Context
import android.content.Intent
import android.net.Uri

object GuidePawNavigation {
    const val EXTRA_OPEN_SECTION = "guidepaw_open_section"
    const val OPEN_SECTION_NOTIFICATIONS = "notifications"

    fun openUrl(context: Context, url: String, title: String = "GuidePaw", accessToken: String? = null) {
        val uri = Uri.parse(url)
        if (isNotificationCenterUrl(uri)) {
            openNativeSection(context, OPEN_SECTION_NOTIFICATIONS, accessToken)
            return
        }
        val host = uri.host.orEmpty().lowercase()
        if (host == "guidepaw.app" || host.endsWith(".guidepaw.app")) {
            context.startActivity(Intent(context, GuidePawWebActivity::class.java).apply {
                putExtra(GuidePawWebActivity.EXTRA_URL, uri.toString())
                putExtra(GuidePawWebActivity.EXTRA_TITLE, title)
                if (!accessToken.isNullOrBlank()) {
                    putExtra(GuidePawWebActivity.EXTRA_ACCESS_TOKEN, accessToken)
                }
            })
            return
        }
        context.startActivity(Intent(Intent.ACTION_VIEW, uri))
    }

    fun openNativeSection(context: Context, section: String, accessToken: String? = null) {
        context.startActivity(Intent(context, MainActivity::class.java).apply {
            putExtra(EXTRA_OPEN_SECTION, section)
            if (!accessToken.isNullOrBlank()) {
                putExtra(GuidePawWebActivity.EXTRA_ACCESS_TOKEN, accessToken)
            }
            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        })
    }

    fun isNotificationCenterUrl(uri: Uri): Boolean {
        val host = uri.host.orEmpty().lowercase()
        if (!(host == "guidepaw.app" || host.endsWith(".guidepaw.app"))) return false
        return uri.lastPathSegment.equals("notificationcenter.php", ignoreCase = true)
    }
}
