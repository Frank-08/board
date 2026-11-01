package org.togetherincouncil.mobile.documents

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.browser.customtabs.CustomTabsIntent

/**
 * Every document/export link this app touches is handed off to an external
 * surface rather than fetched as bytes:
 *  - SharePoint document links need the user's own Microsoft 365 session,
 *    which this app doesn't and shouldn't try to proxy.
 *  - export/agenda_pdf.php / notice_pdf.php return real application/pdf
 *    bytes, but every export/ page requires a logged-in session (or an
 *    X-API-Key header, which ACTION_VIEW can't send) and 302s to
 *    login.php otherwise — so the browser that handles ACTION_VIEW shows
 *    the site's login page first, then lands on the PDF after sign-in.
 *  - export/agenda.php / notice.php / minutes.php are print-styled HTML
 *    relying on the browser's own window.print() — Custom Tabs gives a
 *    nicer in-app-feeling browser chrome for those than a bare ACTION_VIEW.
 */
object DocumentLauncher {

    fun openExternal(context: Context, url: String) {
        try {
            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        } catch (e: ActivityNotFoundException) {
            Toast.makeText(context, "No app found to open this link.", Toast.LENGTH_SHORT).show()
        }
    }

    fun openInCustomTab(context: Context, url: String) {
        try {
            CustomTabsIntent.Builder()
                .build()
                .launchUrl(context, Uri.parse(url))
        } catch (e: ActivityNotFoundException) {
            openExternal(context, url)
        }
    }
}
