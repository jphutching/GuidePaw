package com.guidepaw.companion

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import java.net.URLEncoder
import java.nio.charset.StandardCharsets

private val GpWebColorScheme = lightColorScheme(
    primary          = Color(0xFF0D6EFD),
    onPrimary        = Color.White,
    surface          = Color.White,
    onSurface        = Color(0xFF0F172A),
    onSurfaceVariant = Color(0xFF64748B),
)

class GuidePawWebActivity : AppCompatActivity() {

    private var webView: WebView? = null
    private var isPageLoading by mutableStateOf(false)

    companion object {
        const val EXTRA_URL          = "guidepaw_url"
        const val EXTRA_TITLE        = "guidepaw_title"
        const val EXTRA_ACCESS_TOKEN = "guidepaw_access_token"
        private const val DEFAULT_URL = "https://guidepaw.app/"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val title       = intent.getStringExtra(EXTRA_TITLE).orEmpty().ifBlank { "GuidePaw" }
        val url         = intent.getStringExtra(EXTRA_URL).orEmpty().ifBlank { DEFAULT_URL }
        val accessToken = intent.getStringExtra(EXTRA_ACCESS_TOKEN).orEmpty()

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                val wv = webView
                if (wv != null && wv.canGoBack()) {
                    wv.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        setContent {
            MaterialTheme(colorScheme = GpWebColorScheme) {
                WebScreen(
                    title       = title,
                    url         = url,
                    accessToken = accessToken,
                )
            }
        }
    }

    @Composable
    private fun WebScreen(title: String, url: String, accessToken: String) {
        Column(modifier = Modifier.fillMaxSize()) {
            // Toolbar
            Surface(shadowElevation = 2.dp) {
                Row(
                    modifier         = Modifier.fillMaxWidth().padding(10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    OutlinedButton(
                        onClick = {
                            val wv = webView
                            if (wv != null && wv.canGoBack()) wv.goBack() else finish()
                        },
                        colors = ButtonDefaults.outlinedButtonColors(
                            containerColor = Color(0xFFE2E8F0),
                            contentColor   = Color(0xFF0F172A),
                        ),
                    ) { Text("Back") }

                    Column(
                        modifier = Modifier
                            .weight(1f)
                            .padding(horizontal = 10.dp),
                    ) {
                        Text(title, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color(0xFF0F172A))
                        Text("v${CompanionAppVersion.VERSION_NAME}", fontSize = 11.sp, color = Color(0xFF64748B))
                    }

                    OutlinedButton(onClick = { finish() }) { Text("Close") }
                }
            }

            if (isPageLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

            AndroidView(
                factory = { ctx ->
                    WebView(ctx).also { wv ->
                        webView = wv
                        wv.settings.javaScriptEnabled = true
                        wv.settings.domStorageEnabled  = true
                        wv.webViewClient = object : WebViewClient() {
                            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                                val host = request.url.host.orEmpty().lowercase()
                                if (host == "guidepaw.app" || host.endsWith(".guidepaw.app")) return false
                                startActivity(Intent(Intent.ACTION_VIEW, request.url))
                                return true
                            }
                            override fun onPageStarted(view: WebView?, url: String?, favicon: android.graphics.Bitmap?) {
                                isPageLoading = true
                            }
                            override fun onPageFinished(view: WebView?, url: String?) {
                                isPageLoading = false
                            }
                        }
                        if (accessToken.isNotBlank()) {
                            val body = "access_token=${encode(accessToken)}&next=${encode(Uri.parse(url).toString())}"
                            wv.postUrl("https://guidepaw.app/companion_session.php", body.toByteArray(StandardCharsets.UTF_8))
                        } else {
                            wv.loadUrl(Uri.parse(url).toString())
                        }
                    }
                },
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
            )
        }
    }

    private fun encode(value: String): String =
        URLEncoder.encode(value, StandardCharsets.UTF_8.name())
}
