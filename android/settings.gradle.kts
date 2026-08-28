pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

plugins {
    // Lets Gradle auto-download a matching JDK (e.g. the JDK 17 app/build.gradle.kts's
    // jvmToolchain(17) requires) via the foojay Disco API when no local install matches,
    // instead of failing with "Toolchain download repositories have not been configured."
    id("org.gradle.toolchains.foojay-resolver-convention") version "0.8.0"
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = "together-in-council"
include(":app")
