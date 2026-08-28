package org.togetherincouncil.mobile

import android.app.Application
import org.togetherincouncil.mobile.di.AppContainer

class TicApplication : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
    }
}
