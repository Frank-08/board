package org.togetherincouncil.mobile.ui.common

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import org.togetherincouncil.mobile.TicApplication
import org.togetherincouncil.mobile.di.AppContainer

/** Every screen reaches the hand-written AppContainer (see di/AppContainer.kt) through this,
 * instead of a Hilt/Dagger-generated graph. */
@Composable
fun appContainer(): AppContainer = (LocalContext.current.applicationContext as TicApplication).container

/** Builds a screen's ViewModel from AppContainer, e.g.:
 * `val vm = rememberViewModel { c -> DashboardViewModel(c.dashboardRepository, c.meetingTypeRepository) }` */
@Composable
inline fun <reified VM : ViewModel> rememberViewModel(crossinline create: (AppContainer) -> VM): VM {
    val container = appContainer()
    val factory = remember(container) {
        viewModelFactory { initializer { create(container) } }
    }
    return viewModel(factory = factory)
}
