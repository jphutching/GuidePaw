package com.guidepaw.companion

import android.annotation.SuppressLint
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.webkit.CookieManager
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.webkit.WebSettingsCompat
import androidx.webkit.WebViewFeature
import com.google.android.material.progressindicator.LinearProgressIndicator

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var statusView: TextView
    private lateinit var progressView: LinearProgressIndicator

    private val homeUrl = "https://guidepaw.app/app.php"
    private val quickTargets = linkedMapOf(
        "Home" to "https://guidepaw.app/",
        "App" to "https://guidepaw.app/app.php",
        "Training" to "https://guidepaw.app/training_program.php",
        "Logs" to "https://guidepaw.app/training_session_log.php",
        "Goals" to "https://guidepaw.app/goal_builder.php",
        "Dogs" to "https://guidepaw.app/dogs.php",
        "Wearables" to "https://guidepaw.app/wearable_integrations.php",
        "Public" to "https://guidepaw.app/faq.php",
        "Login" to "https://guidepaw.app/login.php",
    )

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        statusView = findViewById(R.id.statusView)
        progressView = findViewById(R.id.progressView)
        webView = findViewById(R.id.webView)

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.settings.loadsImagesAutomatically = true
        webView.settings.useWideViewPort = true
        webView.settings.loadWithOverviewMode = true
        webView.settings.mediaPlaybackRequiresUserGesture = false
        if (WebViewFeature.isFeatureSupported(WebViewFeature.FORCE_DARK)) {
            WebSettingsCompat.setForceDark(webView.settings, WebSettingsCompat.FORCE_DARK_OFF)
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val uri = request?.url ?: return false
                if (isGuidePawUrl(uri)) {
                    return false
                }
                startActivity(Intent(Intent.ACTION_VIEW, uri))
                return true
            }

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                progressView.visibility = View.VISIBLE
                statusView.text = url?.let { "Loading ${shortLabel(it)}" } ?: "Loading..."
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                progressView.visibility = View.INVISIBLE
                statusView.text = url?.let { shortLabel(it) } ?: "GuidePaw"
            }
        }
        webView.webChromeClient = WebChromeClient()

        quickTargets.forEach { (label, url) ->
            val buttonId = resources.getIdentifier("btn${label.replace(" ", "")}", "id", packageName)
            if (buttonId != 0) {
                findViewById<Button>(buttonId).setOnClickListener { loadGuidePaw(url) }
            }
        }
        findViewById<Button>(R.id.btnBack).setOnClickListener { onBackPressedDispatcher.onBackPressed() }
        findViewById<Button>(R.id.btnRefresh).setOnClickListener { webView.reload() }

        loadGuidePaw(savedInstanceState?.getString(STATE_URL) ?: homeUrl)
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putString(STATE_URL, webView.url ?: homeUrl)
        webView.saveState(outState)
    }

    override fun onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }

    private fun loadGuidePaw(url: String) {
        webView.loadUrl(url)
    }

    private fun isGuidePawUrl(uri: Uri): Boolean {
        return uri.host == "guidepaw.app" || uri.host == "www.guidepaw.app" || uri.host == null && uri.scheme == "about"
    }

    private fun shortLabel(url: String): String {
        return try {
            val uri = Uri.parse(url)
            val path = uri.path?.trim('/') ?: ""
            when {
                path.isEmpty() -> "GuidePaw"
                path.endsWith(".php") -> path.substringAfterLast('/')
                else -> path
            }
        } catch (_: Throwable) {
            "GuidePaw"
        }
    }

    private companion object {
        const val STATE_URL = "state_url"
    }
}
