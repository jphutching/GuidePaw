package com.guidepaw.bridge

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class PublicPageActivity : AppCompatActivity() {
    companion object {
        const val EXTRA_TITLE = "title"
        const val EXTRA_URL = "url"
    }

    private lateinit var titleView: TextView
    private lateinit var urlView: TextView
    private lateinit var statusView: TextView
    private lateinit var webView: WebView
    private var allowedHost: String = ""

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_public_page)

        titleView = findViewById(R.id.publicPageTitle)
        urlView = findViewById(R.id.publicPageUrl)
        statusView = findViewById(R.id.publicPageStatus)
        webView = findViewById(R.id.publicPageWebView)
        findViewById<Button>(R.id.publicPageBackButton).setOnClickListener {
            if (webView.canGoBack()) {
                webView.goBack()
            } else {
                finish()
            }
        }

        val title = intent.getStringExtra(EXTRA_TITLE).orEmpty().ifBlank { "GuidePaw Public Page" }
        val url = intent.getStringExtra(EXTRA_URL).orEmpty()
        allowedHost = runCatching { Uri.parse(url).host.orEmpty() }.getOrDefault("")

        titleView.text = title
        urlView.text = url.ifBlank { "No URL provided." }
        statusView.text = if (url.isBlank()) "Unable to load the public page." else "Loading public page..."

        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: android.webkit.WebResourceRequest?): Boolean {
                val target = request?.url ?: return false
                val host = target.host.orEmpty()
                return if (host.isBlank() || allowedHost.isBlank() || host.endsWith(allowedHost)) {
                    false
                } else {
                    runCatching {
                        startActivity(Intent(Intent.ACTION_VIEW, target))
                    }
                    true
                }
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                statusView.text = "Public page loaded."
            }
        }

        if (url.isNotBlank()) {
            webView.loadUrl(url)
        }
    }
}
