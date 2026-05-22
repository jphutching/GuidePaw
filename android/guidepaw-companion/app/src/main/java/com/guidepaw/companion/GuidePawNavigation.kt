package com.guidepaw.companion

import android.content.Context
import android.content.Intent
import android.net.Uri

object GuidePawNavigation {
    fun openUrl(context: Context, url: String, title: String = "GuidePaw") {
        val uri = Uri.parse(url)
        val host = uri.host.orEmpty().lowercase()
        if (host == "guidepaw.app" || host.endsWith(".guidepaw.app")) {
            context.startActivity(Intent(context, GuidePawWebActivity::class.java).apply {
                putExtra(GuidePawWebActivity.EXTRA_URL, uri.toString())
                putExtra(GuidePawWebActivity.EXTRA_TITLE, title)
            })
            return
        }
        context.startActivity(Intent(Intent.ACTION_VIEW, uri))
    }
}
