package org.togetherincouncil.mobile.data.remote

import org.togetherincouncil.mobile.BuildConfig

object ApiConfig {
    /**
     * Base URL for every api/*.php endpoint, e.g. "https://togetherincouncil.com/api/".
     * Defaults from BuildConfig.API_BASE_URL (release: production, debug: override in
     * app/build.gradle.kts's debug buildType, or via the emulator-host debug network
     * security config exception already wired up for 10.0.2.2).
     */
    val BASE_URL: String = BuildConfig.API_BASE_URL
}
