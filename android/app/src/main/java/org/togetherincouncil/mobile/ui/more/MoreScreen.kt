package org.togetherincouncil.mobile.ui.more

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import org.togetherincouncil.mobile.data.remote.ApiConfig
import org.togetherincouncil.mobile.documents.DocumentLauncher
import org.togetherincouncil.mobile.permissions.Permission

private data class MoreRow(val label: String, val description: String, val path: String, val requires: Permission?)

/**
 * Admin screens out of native v1 scope, reached via the live PHP pages
 * (opened in Chrome Custom Tabs — see DocumentLauncher.openInCustomTab).
 * First visit hits that page's own requireLogin() and shows a normal
 * username/password(+2FA) form in the browser tab; this is a separate
 * credential entry from the app's own API key, by design (a WebView/browser
 * can't attach the X-API-Key header the way OkHttp does).
 */
private val ROWS = listOf(
    MoreRow("Members", "Browse and manage board members", "members.php", Permission.VIEW_MEMBERS),
    MoreRow("Resolutions", "Cross-meeting resolutions browser", "resolutions.php", Permission.VIEW_RESOLUTIONS),
    MoreRow("Documents", "Document link library", "documents.php", Permission.VIEW_DOCUMENTS),
    MoreRow("Users", "Manage user accounts", "users.php", Permission.MANAGE_USERS),
    MoreRow("Two-factor setup", "Configure 2FA for your account", "setup_2fa.php", null),
)

@Composable
fun MoreScreen(canSee: (Permission?) -> Boolean, onLogout: () -> Unit) {
    val context = LocalContext.current
    val siteRoot = remember(ApiConfig.BASE_URL) { ApiConfig.BASE_URL.removeSuffix("api/") }
    val visibleRows = ROWS.filter { canSee(it.requires) }

    Scaffold(topBar = { TopAppBar(title = { Text("More") }) }) { padding ->
        LazyColumn(modifier = Modifier.padding(padding).fillMaxSize()) {
            items(visibleRows) { row ->
                ListItem(
                    headlineContent = { Text(row.label) },
                    supportingContent = { Text(row.description) },
                    trailingContent = { Icon(Icons.AutoMirrored.Filled.OpenInNew, contentDescription = null) },
                    modifier = Modifier.clickable {
                        DocumentLauncher.openInCustomTab(context, siteRoot + row.path)
                    }
                )
                HorizontalDivider()
            }
            item {
                Spacer(Modifier.height(16.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable(onClick = onLogout)
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("Sign out", color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }
}
