package com.guidepaw.companion

import android.content.Intent
import android.content.SharedPreferences
import android.database.Cursor
import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import java.util.concurrent.Executors

private val GpFeedbackColorScheme = lightColorScheme(
    primary          = Color(0xFF0D6EFD),
    onPrimary        = Color.White,
    primaryContainer = Color(0xFFE8F1FF),
    surface          = Color.White,
    onSurface        = Color(0xFF0F172A),
    onSurfaceVariant = Color(0xFF334155),
)

class FeedbackActivity : AppCompatActivity() {

    private val api    = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()
    private lateinit var prefs: SharedPreferences

    // ── Compose state ───────────────────────────────────────────────────────
    private var isLoading        by mutableStateOf(false)
    private var statusMessage    by mutableStateOf("")
    private var selectedCategory by mutableStateOf("bug")
    private var pageWorkflow     by mutableStateOf("")
    private var contactEmail     by mutableStateOf("")
    private var details          by mutableStateOf("")
    private var attachments      by mutableStateOf<List<SelectedAttachment>>(emptyList())
    private var currentToken: String? = null

    // ── File picker (replaces deprecated onActivityResult) ──────────────────
    private val attachmentLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode != RESULT_OK) return@registerForActivityResult
        val data = result.data ?: return@registerForActivityResult
        val picked = mutableListOf<SelectedAttachment>()
        val clip = data.clipData
        if (clip != null) {
            for (i in 0 until clip.itemCount) {
                resolveAttachment(clip.getItemAt(i).uri ?: continue)?.let(picked::add)
            }
        } else {
            data.data?.let { resolveAttachment(it)?.let(picked::add) }
        }
        if (picked.isNotEmpty()) attachments = attachments + picked
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        currentToken = prefs.getString(KEY_TOKEN, null)
        setContent {
            MaterialTheme(colorScheme = GpFeedbackColorScheme) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color    = Color(0xFFF1F5F9),
                ) { FeedbackScreen() }
            }
        }
    }

    override fun onResume() {
        super.onResume()
        currentToken = prefs.getString(KEY_TOKEN, null)
    }

    override fun onDestroy() {
        super.onDestroy()
        worker.shutdownNow()
    }

    // ── UI ──────────────────────────────────────────────────────────────────
    @Composable
    private fun FeedbackScreen() {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            // Header
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        Brush.linearGradient(listOf(Color(0xFF0D6EFD), Color(0xFF0F766E))),
                        RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp),
                    )
                    .padding(24.dp),
                contentAlignment = Alignment.Center,
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text(
                        "Feedback / Bug Report",
                        color      = Color.White,
                        fontSize   = 22.sp,
                        fontWeight = FontWeight.Bold,
                    )
                    Surface(
                        color = Color(0xFFDBEAFE),
                        shape = RoundedCornerShape(999.dp),
                    ) {
                        Text(
                            "v${CompanionAppVersion.VERSION_NAME}",
                            modifier   = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                            fontSize   = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color      = Color(0xFF0F172A),
                        )
                    }
                    OutlinedButton(
                        onClick = { finish() },
                        colors  = ButtonDefaults.outlinedButtonColors(contentColor = Color.White),
                        border  = BorderStroke(1.dp, Color.White.copy(alpha = 0.5f)),
                    ) { Text("Back") }
                }
            }

            Column(
                modifier            = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (statusMessage.isNotBlank()) {
                    Text(
                        statusMessage,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (isLoading) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

                Card(
                    modifier  = Modifier.fillMaxWidth(),
                    shape     = RoundedCornerShape(20.dp),
                    colors    = CardDefaults.cardColors(containerColor = Color.White),
                    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                ) {
                    Column(
                        modifier            = Modifier.padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        Text("Submitted from the Android companion app.", fontWeight = FontWeight.Bold)
                        Text(
                            "Submitted from GuidePaw Companion v${CompanionAppVersion.VERSION_NAME} on Android.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Text(
                            "Your submission will be labeled as Android automatically.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )

                        // Category
                        Text("Category", fontWeight = FontWeight.Medium, fontSize = 13.sp)
                        Row(
                            modifier              = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            listOf(
                                "bug"         to "Bug",
                                "feature"     to "Feature",
                                "enhancement" to "Enhancement",
                            ).forEach { (value, label) ->
                                FilterChip(
                                    selected = selectedCategory == value,
                                    onClick  = { selectedCategory = value },
                                    label    = { Text(label, fontSize = 12.sp) },
                                    modifier = Modifier.weight(1f),
                                )
                            }
                        }

                        OutlinedTextField(
                            value           = pageWorkflow,
                            onValueChange   = { pageWorkflow = it },
                            label           = { Text("Page or workflow") },
                            placeholder     = { Text("dogs.php, login, training log", fontSize = 12.sp, color = Color(0xFF94A3B8)) },
                            singleLine      = true,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                            modifier        = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value           = contactEmail,
                            onValueChange   = { contactEmail = it },
                            label           = { Text("Contact email (optional)") },
                            singleLine      = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, autoCorrect = false),
                            modifier        = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value           = details,
                            onValueChange   = { details = it },
                            label           = { Text("Details") },
                            placeholder     = { Text("What happened, what you expected, and steps to reproduce.", fontSize = 12.sp, color = Color(0xFF94A3B8)) },
                            minLines        = 7,
                            keyboardOptions = KeyboardOptions(
                                keyboardType   = KeyboardType.Text,
                                capitalization = KeyboardCapitalization.Sentences,
                            ),
                            modifier        = Modifier.fillMaxWidth(),
                        )

                        // Attachments
                        Text("Attachments", fontWeight = FontWeight.Bold)
                        if (attachments.isEmpty()) {
                            Text(
                                "No attachments selected yet.",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        } else {
                            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                attachments.forEachIndexed { i, a ->
                                    Text(
                                        "${i + 1}. ${a.displayName} (${a.mimeType.ifBlank { "application/octet-stream" }}${a.sizeBytes?.let { ", ${formatBytes(it)}" } ?: ""})",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }
                        Text(
                            "Documents, photos, videos, and audio are supported.",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B),
                        )
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(
                                onClick  = { openAttachmentPicker() },
                                enabled  = !isLoading,
                                modifier = Modifier.weight(1f),
                            ) { Text("Attach screenshot / file", fontSize = 13.sp) }
                            OutlinedButton(
                                onClick  = { attachments = emptyList() },
                                enabled  = !isLoading && attachments.isNotEmpty(),
                            ) { Text("Clear") }
                        }

                        Spacer(Modifier.height(4.dp))
                        Button(
                            onClick  = { submitFeedback() },
                            enabled  = !isLoading,
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text("Send feedback") }
                        OutlinedButton(
                            onClick  = {
                                GuidePawNavigation.openUrl(
                                    this@FeedbackActivity,
                                    "https://guidepaw.app/feedback.php",
                                    "Feedback",
                                    currentToken,
                                )
                            },
                            enabled  = !isLoading,
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text("Open web feedback") }
                    }
                }
            }
        }
    }

    // ── Business logic ──────────────────────────────────────────────────────
    private fun openAttachmentPicker() {
        val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "*/*"
            putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
            putExtra(
                Intent.EXTRA_MIME_TYPES,
                arrayOf(
                    "image/*", "video/*", "audio/*", "application/pdf",
                    "text/plain", "text/csv", "application/json",
                    "application/msword",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    "application/rtf", "application/vnd.oasis.opendocument.text",
                )
            )
        }
        attachmentLauncher.launch(Intent.createChooser(intent, "Select attachments"))
    }

    private fun submitFeedback() {
        val detailsVal = details.trim()
        if (detailsVal.isBlank()) {
            statusMessage = "Add details before sending."
            return
        }
        val token           = currentToken
        val category        = selectedCategory
        val pageWorkflowVal = pageWorkflow.trim()
        val contactEmailVal = contactEmail.trim()

        isLoading     = true
        statusMessage = "Sending feedback..."
        worker.execute {
            try {
                val attachmentInputs = attachments.map { a ->
                    GuidePawFeedbackAttachmentInput(uri = a.uri, displayName = a.displayName, mimeType = a.mimeType)
                }
                val response = api.submitFeedback(
                    token           = token,
                    category        = category,
                    pageWorkflow    = pageWorkflowVal,
                    contactEmail    = contactEmailVal,
                    details         = detailsVal,
                    sourceVersion   = CompanionAppVersion.VERSION_NAME,
                    sourceDevice    = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}".trim(),
                    attachments     = attachmentInputs,
                    contentResolver = contentResolver,
                )
                runOnUiThread {
                    statusMessage = buildString {
                        append(response.message ?: "Feedback sent.")
                        if (response.uploadDebug.isNotEmpty()) append(" ${response.uploadDebug.joinToString(" | ")}")
                    }
                    attachments      = emptyList()
                    details          = ""
                    pageWorkflow     = ""
                    contactEmail     = ""
                    selectedCategory = "bug"
                    isLoading        = false
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread { statusMessage = friendlyMessage(e.message, "Could not send feedback."); isLoading = false }
            } catch (t: Throwable) {
                runOnUiThread { statusMessage = friendlyMessage(t.message, "Could not send feedback."); isLoading = false }
            }
        }
    }

    private fun resolveAttachment(uri: Uri): SelectedAttachment? {
        val projection  = arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE)
        val name = runCatching {
            contentResolver.query(uri, projection, null, null, null)?.use { it.readAttachmentName(uri) }
        }.getOrNull()
        val mime        = contentResolver.getType(uri).orEmpty()
        val displayName = ensureAttachmentExtension(
            name ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() } ?: "attachment",
            mime,
        )
        return SelectedAttachment(
            uri         = uri,
            displayName = displayName,
            mimeType    = mime.ifBlank { inferMimeFromName(displayName) },
            sizeBytes   = queryAttachmentSize(uri),
        )
    }

    private fun queryAttachmentSize(uri: Uri): Long? = runCatching {
        contentResolver.query(uri, arrayOf(OpenableColumns.SIZE), null, null, null)?.use { cursor ->
            if (!cursor.moveToFirst()) return@use null
            val idx = cursor.getColumnIndex(OpenableColumns.SIZE)
            if (idx >= 0 && !cursor.isNull(idx)) cursor.getLong(idx).takeIf { it > 0 } else null
        }
    }.getOrNull()

    private fun Cursor.readAttachmentName(uri: Uri): String? {
        if (!moveToFirst()) return null
        val idx = getColumnIndex(OpenableColumns.DISPLAY_NAME)
        return if (idx >= 0 && !isNull(idx)) getString(idx)?.trim()?.takeIf { it.isNotBlank() } else null
            ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() }
    }

    private fun inferMimeFromName(name: String): String {
        val lower = name.lowercase()
        return when {
            lower.endsWith(".pdf")  -> "application/pdf"
            lower.endsWith(".doc")  -> "application/msword"
            lower.endsWith(".docx") -> "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            lower.endsWith(".rtf")  -> "application/rtf"
            lower.endsWith(".odt")  -> "application/vnd.oasis.opendocument.text"
            lower.endsWith(".csv")  -> "text/csv"
            lower.endsWith(".json") -> "application/json"
            lower.endsWith(".txt") || lower.endsWith(".log") -> "text/plain"
            lower.endsWith(".mp3")  -> "audio/mpeg"
            lower.endsWith(".m4a")  -> "audio/mp4"
            lower.endsWith(".wav")  -> "audio/wav"
            lower.endsWith(".aac")  -> "audio/aac"
            lower.endsWith(".ogg")  -> "audio/ogg"
            lower.endsWith(".mp4")  -> "video/mp4"
            lower.endsWith(".mov")  -> "video/quicktime"
            lower.endsWith(".webm") -> "video/webm"
            lower.endsWith(".m4v")  -> "video/x-m4v"
            lower.endsWith(".3gp")  -> "video/3gpp"
            lower.endsWith(".jpg") || lower.endsWith(".jpeg") -> "image/jpeg"
            lower.endsWith(".png")  -> "image/png"
            lower.endsWith(".gif")  -> "image/gif"
            lower.endsWith(".webp") -> "image/webp"
            lower.endsWith(".heic") -> "image/heic"
            lower.endsWith(".heif") -> "image/heif"
            else                    -> "application/octet-stream"
        }
    }

    private fun ensureAttachmentExtension(displayName: String, mimeType: String): String {
        val current = displayName.trim().ifBlank { "attachment" }
        if (current.contains('.')) return current
        val ext = when (mimeType.lowercase()) {
            "application/pdf"   -> "pdf";  "application/msword" -> "doc"
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" -> "docx"
            "application/rtf"   -> "rtf";  "application/vnd.oasis.opendocument.text" -> "odt"
            "text/csv"          -> "csv";  "application/json"  -> "json"; "text/plain" -> "txt"
            "audio/mpeg"        -> "mp3";  "audio/mp4"         -> "m4a";  "audio/wav"  -> "wav"
            "audio/aac"         -> "aac";  "audio/ogg"         -> "ogg"
            "video/mp4"         -> "mp4";  "video/quicktime"   -> "mov";  "video/webm" -> "webm"
            "video/x-m4v"       -> "m4v";  "video/3gpp"        -> "3gp"
            "image/jpeg"        -> "jpg";  "image/png"         -> "png";  "image/gif"  -> "gif"
            "image/webp"        -> "webp"; "image/heic"        -> "heic"; "image/heif" -> "heif"
            else                -> ""
        }
        return if (ext.isBlank()) current else "$current.$ext"
    }

    private fun formatBytes(bytes: Long): String = when {
        bytes >= 1024 * 1024 -> String.format("%.1f MB", bytes / 1024.0 / 1024.0)
        bytes >= 1024        -> String.format("%.1f KB", bytes / 1024.0)
        else                 -> "$bytes B"
    }

    private fun friendlyMessage(message: String?, fallback: String): String {
        val raw = message?.trim().orEmpty()
        if (raw.isBlank()) return fallback
        val suspicious = listOf("sqlstate", "select ", "insert ", "update ", "delete ",
            "pdo", "syntax error", "near \"", "column ", "table ")
            .any { raw.contains(it, ignoreCase = true) }
        return if (suspicious) fallback else raw
    }

    private data class SelectedAttachment(
        val uri         : Uri,
        val displayName : String,
        val mimeType    : String,
        val sizeBytes   : Long?,
    )

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN  = "auth_token"
    }
}
