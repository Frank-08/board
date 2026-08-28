package org.togetherincouncil.mobile.data.auth

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Secure, encrypted storage for the user's X-API-Key. API-key auth bypasses
 * 2FA entirely (see config/auth.php's currentApiKeyUser()), so the key is
 * the sole secret protecting the account — it must never sit in plain
 * SharedPreferences, logs, or backups (see AndroidManifest's
 * android:allowBackup="false" and data_extraction_rules.xml).
 */
class ApiKeyStore(context: Context) {

    private val prefs: SharedPreferences by lazy {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()

        EncryptedSharedPreferences.create(
            context,
            PREFS_FILE_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    fun getKey(): String? = prefs.getString(KEY_API_KEY, null)

    fun saveKey(key: String) {
        prefs.edit().putString(KEY_API_KEY, key).apply()
    }

    fun clear() {
        prefs.edit().remove(KEY_API_KEY).apply()
    }

    companion object {
        // Keep this filename in sync with data_extraction_rules.xml's backup/transfer exclusions.
        private const val PREFS_FILE_NAME = "tic_secure_prefs"
        private const val KEY_API_KEY = "api_key"
    }
}
