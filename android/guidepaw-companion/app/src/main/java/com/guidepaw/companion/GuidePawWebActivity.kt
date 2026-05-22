package com.guidepaw.companion

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import com.google.android.material.progressindicator.LinearProgressIndicator

class GuidePawWebActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var progressView: LinearProgressIndicator

    companion object {
        const val EXTRA_URL = "guidepaw_url"
        const val EXTRA_TITLE = "guidepaw_title"
        private const val DEFAULT_URL = "https://guidepaw.app/"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_guidepaw_web)

        val title = intent.getStringExtra(EXTRA_TITLE).orEmpty().ifBlank { "GuidePaw" }
        val url = intent.getStringExtra(EXTRA_URL).orEmpty().ifBlank { DEFAULT_URL }

        findViewById<TextView>(R.id.titleView).text = title
        findViewById<TextView>(R.id.versionView).text = "v${CompanionAppVersion.VERSION_NAME}"
        findViewById<MaterialButton>(R.id.btnClose).setOnClickListener { finish() }
        findViewById<MaterialButton>(R.id.btnBack).setOnClickListener {
            if (webView.canGoBack()) webView.goBack() else finish()
        }

        progressView = findViewById(R.id.progressView)
        webView = findViewById(R.id.webView)
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val target = request.url
                val host = target.host.orEmpty().lowercase()
                if (host == "guidepaw.app" || host.endsWith(".guidepaw.app")) {
                    return false
                }
                startActivity(Intent(Intent.ACTION_VIEW, target))
                return true
            }

            override fun onPageStarted(view: WebView?, url: String?, favicon: android.graphics.Bitmap?) {
                progressView.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                progressView.visibility = View.INVISIBLE
            }
        }
        webView.loadUrl(Uri.parse(url).toString())
    }

    @Deprecated("Deprecated in Java")
    override fun onBackPressed() {
        if (::webView.isInitialized && webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }
}
