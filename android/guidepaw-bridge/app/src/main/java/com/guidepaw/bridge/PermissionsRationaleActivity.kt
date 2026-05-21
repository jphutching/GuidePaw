package com.guidepaw.bridge

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class PermissionsRationaleActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val root = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
        }
        val message = TextView(this).apply {
            text = "GuidePaw Companion needs Health Connect permission to read steps, distance, calories burned, sleep, heart rate, and resting heart rate, then send a daily summary to the handler's chosen dog profile."
        }
        val button = Button(this).apply {
            text = "Back to app"
            setOnClickListener {
                startActivity(Intent(this@PermissionsRationaleActivity, MainActivity::class.java))
                finish()
            }
        }
        root.addView(message)
        root.addView(button)
        setContentView(root)
    }
}
