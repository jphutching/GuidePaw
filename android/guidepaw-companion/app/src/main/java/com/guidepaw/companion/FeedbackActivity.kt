package com.guidepaw.companion

import android.content.Intent
import android.content.SharedPreferences
import android.database.Cursor
import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import android.view.View
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import com.google.android.material.button.MaterialButtonToggleGroup
import com.google.android.material.card.MaterialCardView
import com.google.android.material.progressindicator.LinearProgressIndicator
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import java.util.concurrent.Executors

class FeedbackActivity : AppCompatActivity() {
    private val api = GuidePawApiClient()
    private val worker = Executors.newSingleThreadExecutor()
    private lateinit var prefs: SharedPreferences

    private lateinit var statusView: TextView
    private lateinit var versionView: TextView
    private lateinit var progressView: LinearProgressIndicator
    private lateinit var feedbackStateView: TextView
    private lateinit var categoryToggle: MaterialButtonToggleGroup
    private lateinit var pageWorkflowInput: TextInputEditText
    private lateinit var contactEmailInput: TextInputEditText
    private lateinit var detailsInput: TextInputEditText
    private lateinit var attachmentsView: TextView
    private lateinit var submitButton: MaterialButton
    private lateinit var addAttachmentsButton: MaterialButton
    private lateinit var clearAttachmentsButton: MaterialButton
    private lateinit var webFeedbackButton: MaterialButton
    private lateinit var tokenNoticeView: TextView

    private val selectedAttachments = mutableListOf<SelectedAttachment>()
    private var currentToken: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_feedback)
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        bindViews()
        setupUi()
        loadToken()
        updateAuthState()
    }

    override fun onDestroy() {
        super.onDestroy()
        worker.shutdownNow()
    }

    override fun onResume() {
        super.onResume()
        loadToken()
        updateAuthState()
    }

    @Deprecated("Deprecated in Java")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != REQ_ATTACHMENTS || resultCode != RESULT_OK || data == null) {
            return
        }
        val picked = mutableListOf<SelectedAttachment>()
        val clipData = data.clipData
        if (clipData != null) {
            for (index in 0 until clipData.itemCount) {
                val uri = clipData.getItemAt(index).uri ?: continue
                resolveAttachment(uri)?.let(picked::add)
            }
        } else {
            data.data?.let { uri ->
                resolveAttachment(uri)?.let(picked::add)
            }
        }
        if (picked.isNotEmpty()) {
            selectedAttachments.addAll(picked)
            refreshAttachmentList()
        }
    }

    private fun bindViews() {
        statusView = findViewById(R.id.statusView)
        versionView = findViewById(R.id.versionView)
        progressView = findViewById(R.id.progressView)
        feedbackStateView = findViewById(R.id.feedbackStateView)
        categoryToggle = findViewById(R.id.categoryToggle)
        pageWorkflowInput = findViewById(R.id.pageWorkflowInput)
        contactEmailInput = findViewById(R.id.contactEmailInput)
        detailsInput = findViewById(R.id.detailsInput)
        attachmentsView = findViewById(R.id.attachmentsView)
        submitButton = findViewById(R.id.btnSubmitFeedback)
        addAttachmentsButton = findViewById(R.id.btnAddAttachments)
        clearAttachmentsButton = findViewById(R.id.btnClearAttachments)
        webFeedbackButton = findViewById(R.id.btnOpenWebFeedback)
        tokenNoticeView = findViewById(R.id.tokenNoticeView)
    }

    private fun setupUi() {
        versionView.text = "v${CompanionAppVersion.VERSION_NAME}"
        feedbackStateView.text = "Submitted from GuidePaw Companion v${CompanionAppVersion.VERSION_NAME} on Android."
        categoryToggle.check(R.id.btnBug)

        findViewById<MaterialButton>(R.id.btnBack).setOnClickListener { finish() }
        addAttachmentsButton.setOnClickListener { openAttachmentPicker() }
        clearAttachmentsButton.setOnClickListener {
            selectedAttachments.clear()
            refreshAttachmentList()
        }
        submitButton.setOnClickListener { submitFeedback() }
        webFeedbackButton.setOnClickListener {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://guidepaw.app/feedback.php")))
        }
    }

    private fun loadToken() {
        currentToken = prefs.getString(KEY_TOKEN, null)
    }

    private fun updateAuthState() {
        val token = currentToken
        if (token.isNullOrBlank()) {
            tokenNoticeView.text = "Sign in first to send a feedback report from the Android app."
        } else {
            tokenNoticeView.text = "Your submission will be labeled as Android automatically."
        }
        setLoading(false, null)
        refreshAttachmentList()
    }

    private fun refreshAttachmentList() {
        attachmentsView.text = if (selectedAttachments.isEmpty()) {
            "No attachments selected yet."
        } else {
            buildString {
                selectedAttachments.forEachIndexed { index, attachment ->
                    append(index + 1)
                    append(". ")
                    append(attachment.displayName)
                    append(" (")
                    append(attachment.mimeType.ifBlank { "application/octet-stream" })
                    attachment.sizeBytes?.let {
                        append(", ")
                        append(formatBytes(it))
                    }
                    append(")\n")
                }
            }.trimEnd()
        }
    }

    private fun openAttachmentPicker() {
        val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "*/*"
            putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
            putExtra(
                Intent.EXTRA_MIME_TYPES,
                arrayOf(
                    "image/*",
                    "video/*",
                    "audio/*",
                    "application/pdf",
                    "text/plain",
                    "text/csv",
                    "application/json",
                    "application/msword",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    "application/rtf",
                    "application/vnd.oasis.opendocument.text",
                )
            )
        }
        startActivityForResult(Intent.createChooser(intent, "Select attachments"), REQ_ATTACHMENTS)
    }

    private fun submitFeedback() {
        val token = currentToken
        if (token.isNullOrBlank()) {
            statusView.text = "Sign in first to submit a report from the Android app."
            return
        }

        val category = when (categoryToggle.checkedButtonId) {
            R.id.btnFeature -> "feature"
            R.id.btnEnhancement -> "enhancement"
            else -> "bug"
        }
        val pageWorkflow = pageWorkflowInput.text?.toString()?.trim().orEmpty()
        val contactEmail = contactEmailInput.text?.toString()?.trim().orEmpty()
        val details = detailsInput.text?.toString()?.trim().orEmpty()
        if (details.isBlank()) {
            statusView.text = "Add details before sending."
            return
        }

        setLoading(true, "Sending feedback...")
        worker.execute {
            try {
                val attachmentInputs = selectedAttachments.map { attachment ->
                    GuidePawFeedbackAttachmentInput(
                        uri = attachment.uri,
                        displayName = attachment.displayName,
                        mimeType = attachment.mimeType,
                    )
                }
                val response = api.submitFeedback(
                    token = token,
                    category = category,
                    pageWorkflow = pageWorkflow,
                    contactEmail = contactEmail,
                    details = details,
                    sourceVersion = CompanionAppVersion.VERSION_NAME,
                    sourceDevice = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}".trim(),
                    attachments = attachmentInputs,
                    contentResolver = contentResolver,
                )
                runOnUiThread {
                    statusView.text = buildString {
                        append(response.message ?: "Feedback sent.")
                        if (response.uploadDebug.isNotEmpty()) {
                            append(" ")
                            append(response.uploadDebug.joinToString(" | "))
                        }
                    }
                    selectedAttachments.clear()
                    detailsInput.setText("")
                    pageWorkflowInput.setText("")
                    contactEmailInput.setText("")
                    categoryToggle.check(R.id.btnBug)
                    refreshAttachmentList()
                    setLoading(false, null)
                }
            } catch (e: GuidePawApiException) {
                runOnUiThread {
                    statusView.text = friendlyMessage(e.message, "Could not send feedback.")
                    setLoading(false, null)
                }
            } catch (t: Throwable) {
                runOnUiThread {
                    statusView.text = friendlyMessage(t.message, "Could not send feedback.")
                    setLoading(false, null)
                }
            }
        }
    }

    private fun resolveAttachment(uri: Uri): SelectedAttachment? {
        val projection = arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE)
        val name = runCatching {
            contentResolver.query(uri, projection, null, null, null)?.use { cursor ->
                cursor.readAttachmentName(uri)
            }
        }.getOrNull()
        val mime = contentResolver.getType(uri).orEmpty()
        val displayName = ensureAttachmentExtension(
            name ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() } ?: "attachment",
            mime
        )
        return SelectedAttachment(
            uri = uri,
            displayName = displayName,
            mimeType = mime.ifBlank { inferMimeFromName(displayName) },
            sizeBytes = queryAttachmentSize(uri),
        )
    }

    private fun queryAttachmentSize(uri: Uri): Long? {
        val projection = arrayOf(OpenableColumns.SIZE)
        return runCatching {
            contentResolver.query(uri, projection, null, null, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    val index = cursor.getColumnIndex(OpenableColumns.SIZE)
                    if (index >= 0 && !cursor.isNull(index)) {
                        cursor.getLong(index).takeIf { it > 0 }
                    } else {
                        null
                    }
                } else {
                    null
                }
            }
        }.getOrNull()
    }

    private fun Cursor.readAttachmentName(uri: Uri): String? {
        return if (moveToFirst()) {
            val index = getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (index >= 0 && !isNull(index)) {
                getString(index)?.trim()?.takeIf { it.isNotBlank() }
            } else {
                null
            }
        } else {
            null
        } ?: uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() }
    }

    private fun inferMimeFromName(name: String): String {
        val lower = name.lowercase()
        return when {
            lower.endsWith(".pdf") -> "application/pdf"
            lower.endsWith(".doc") -> "application/msword"
            lower.endsWith(".docx") -> "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            lower.endsWith(".rtf") -> "application/rtf"
            lower.endsWith(".odt") -> "application/vnd.oasis.opendocument.text"
            lower.endsWith(".csv") -> "text/csv"
            lower.endsWith(".json") -> "application/json"
            lower.endsWith(".txt") || lower.endsWith(".log") -> "text/plain"
            lower.endsWith(".mp3") -> "audio/mpeg"
            lower.endsWith(".m4a") -> "audio/mp4"
            lower.endsWith(".wav") -> "audio/wav"
            lower.endsWith(".aac") -> "audio/aac"
            lower.endsWith(".ogg") -> "audio/ogg"
            lower.endsWith(".mp4") -> "video/mp4"
            lower.endsWith(".mov") -> "video/quicktime"
            lower.endsWith(".webm") -> "video/webm"
            lower.endsWith(".m4v") -> "video/x-m4v"
            lower.endsWith(".3gp") -> "video/3gpp"
            lower.endsWith(".jpg") || lower.endsWith(".jpeg") -> "image/jpeg"
            lower.endsWith(".png") -> "image/png"
            lower.endsWith(".gif") -> "image/gif"
            lower.endsWith(".webp") -> "image/webp"
            lower.endsWith(".heic") -> "image/heic"
            lower.endsWith(".heif") -> "image/heif"
            else -> "application/octet-stream"
        }
    }

    private fun ensureAttachmentExtension(displayName: String, mimeType: String): String {
        val current = displayName.trim().ifBlank { "attachment" }
        if (current.contains('.')) {
            return current
        }
        val ext = when (mimeType.lowercase()) {
            "application/pdf" -> "pdf"
            "application/msword" -> "doc"
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" -> "docx"
            "application/rtf" -> "rtf"
            "application/vnd.oasis.opendocument.text" -> "odt"
            "text/csv" -> "csv"
            "application/json" -> "json"
            "text/plain" -> "txt"
            "audio/mpeg" -> "mp3"
            "audio/mp4" -> "m4a"
            "audio/wav" -> "wav"
            "audio/aac" -> "aac"
            "audio/ogg" -> "ogg"
            "video/mp4" -> "mp4"
            "video/quicktime" -> "mov"
            "video/webm" -> "webm"
            "video/x-m4v" -> "m4v"
            "video/3gpp" -> "3gp"
            "image/jpeg" -> "jpg"
            "image/png" -> "png"
            "image/gif" -> "gif"
            "image/webp" -> "webp"
            "image/heic" -> "heic"
            "image/heif" -> "heif"
            else -> ""
        }
        return if (ext.isBlank()) current else "$current.$ext"
    }

    private fun formatBytes(bytes: Long): String {
        return when {
            bytes >= 1024 * 1024 -> String.format("%.1f MB", bytes / 1024.0 / 1024.0)
            bytes >= 1024 -> String.format("%.1f KB", bytes / 1024.0)
            else -> "$bytes B"
        }
    }

    private fun setLoading(isLoading: Boolean, message: String?) {
        progressView.visibility = if (isLoading) View.VISIBLE else View.INVISIBLE
        val signedIn = !currentToken.isNullOrBlank()
        submitButton.isEnabled = signedIn && !isLoading
        addAttachmentsButton.isEnabled = signedIn && !isLoading
        clearAttachmentsButton.isEnabled = signedIn && !isLoading
        webFeedbackButton.isEnabled = !isLoading
        if (message != null) {
            statusView.text = message
        }
    }

    private fun friendlyMessage(message: String?, fallback: String): String {
        val raw = message?.trim().orEmpty()
        if (raw.isBlank()) {
            return fallback
        }
        val suspicious = listOf(
            "sqlstate",
            "select ",
            "insert ",
            "update ",
            "delete ",
            "pdo",
            "syntax error",
            "near \"",
            "column ",
            "table ",
        ).any { raw.contains(it, ignoreCase = true) }
        return if (suspicious) fallback else raw
    }

    private data class SelectedAttachment(
        val uri: Uri,
        val displayName: String,
        val mimeType: String,
        val sizeBytes: Long?,
    )

    companion object {
        private const val PREFS_NAME = "guidepaw_companion"
        private const val KEY_TOKEN = "auth_token"
        private const val REQ_ATTACHMENTS = 1042
    }
}
