package org.togetherincouncil.mobile.navigation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import kotlinx.coroutines.flow.collectLatest
import org.togetherincouncil.mobile.data.auth.AuthState
import org.togetherincouncil.mobile.data.remote.AuthEventBus
import org.togetherincouncil.mobile.di.AppContainer
import org.togetherincouncil.mobile.permissions.Permission
import org.togetherincouncil.mobile.ui.common.rememberViewModel
import org.togetherincouncil.mobile.ui.dashboard.DashboardScreen
import org.togetherincouncil.mobile.ui.dashboard.DashboardViewModel
import org.togetherincouncil.mobile.ui.meetings.MeetingListScreen
import org.togetherincouncil.mobile.ui.meetings.MeetingListViewModel
import org.togetherincouncil.mobile.ui.meetings.detail.MeetingDetailScreen
import org.togetherincouncil.mobile.ui.meetings.detail.MeetingDetailViewModel
import org.togetherincouncil.mobile.ui.more.MoreScreen
import org.togetherincouncil.mobile.ui.onboarding.OnboardingScreen
import org.togetherincouncil.mobile.ui.onboarding.OnboardingViewModel

private data class BottomTab(val route: String, val label: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)
private val BOTTOM_TABS = listOf(
    BottomTab(Destinations.DASHBOARD, "Dashboard", Icons.Filled.Dashboard),
    BottomTab(Destinations.MEETING_LIST, "Meetings", Icons.Filled.List),
    BottomTab(Destinations.MORE, "More", Icons.Filled.MoreHoriz)
)

@Composable
fun TicNavGraph(container: AppContainer) {
    val navController = rememberNavController()
    val authState by container.sessionManager.authState.collectAsStateWithLifecycle()

    // SessionManager is an in-memory singleton, empty on every fresh process — but ApiKeyStore's
    // key survives app restarts. Re-validate a stored key against api/whoami.php before deciding
    // where to land, so a returning user doesn't get bounced back to onboarding every cold start.
    var bootstrapped by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        val storedKey = container.apiKeyStore.getKey()
        if (storedKey != null) {
            container.whoAmIRepository.whoAmI().fold(
                onSuccess = { dto -> container.sessionManager.setAuthenticated(dto.user, dto.permissions) },
                onFailure = { error ->
                    // Only drop a key that's actually invalid — leave it stored on a transient
                    // network hiccup or a 404 (whoami.php not deployed) so the user isn't forced
                    // to re-paste a still-good key; they'll just land back on Onboarding.
                    if (error is org.togetherincouncil.mobile.data.error.ApiException.AuthRequired) {
                        container.apiKeyStore.clear()
                    }
                }
            )
        }
        bootstrapped = true
    }

    // Centralized 401 handling: any endpoint returning AUTH_REQUIRED clears the session and
    // bounces the whole app back to Onboarding, from wherever the user currently is.
    LaunchedEffect(Unit) {
        AuthEventBus.unauthorized.collectLatest {
            container.sessionManager.clear()
            navController.navigate(Destinations.ONBOARDING) {
                popUpTo(0) { inclusive = true }
            }
        }
    }

    if (!bootstrapped) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
        return
    }

    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route
    val showBottomBar = currentRoute in listOf(Destinations.DASHBOARD, Destinations.MEETING_LIST, Destinations.MORE)

    Scaffold(
        bottomBar = {
            if (showBottomBar && authState is AuthState.Authenticated) {
                NavigationBar {
                    BOTTOM_TABS.forEach { tab ->
                        NavigationBarItem(
                            selected = currentRoute == tab.route,
                            onClick = {
                                navController.navigate(tab.route) {
                                    popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            icon = { Icon(tab.icon, contentDescription = tab.label) },
                            label = { Text(tab.label) }
                        )
                    }
                }
            }
        }
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = if (authState is AuthState.Authenticated) Destinations.DASHBOARD else Destinations.ONBOARDING,
            modifier = Modifier.padding(padding)
        ) {
            composable(Destinations.ONBOARDING) {
                val vm = rememberViewModel { c ->
                    OnboardingViewModel(c.whoAmIRepository, c.dashboardRepository, c.apiKeyStore, c.sessionManager)
                }
                OnboardingScreen(vm) {
                    navController.navigate(Destinations.DASHBOARD) { popUpTo(0) { inclusive = true } }
                }
            }
            composable(Destinations.DASHBOARD) {
                val vm = rememberViewModel { c -> DashboardViewModel(c.dashboardRepository, c.meetingTypeRepository) }
                DashboardScreen(vm, onMeetingClick = { id -> navController.navigate(Destinations.meetingDetail(id)) })
            }
            composable(Destinations.MEETING_LIST) {
                val vm = rememberViewModel { c -> MeetingListViewModel(c.meetingRepository, c.meetingTypeRepository) }
                MeetingListScreen(vm, onMeetingClick = { id -> navController.navigate(Destinations.meetingDetail(id)) })
            }
            composable(
                route = Destinations.MEETING_DETAIL,
                arguments = listOf(navArgument(Destinations.ARG_MEETING_ID) { type = NavType.IntType })
            ) { backStackEntry ->
                val meetingId = backStackEntry.arguments?.getInt(Destinations.ARG_MEETING_ID) ?: return@composable
                val vm = rememberViewModel { c ->
                    MeetingDetailViewModel(
                        meetingId = meetingId,
                        meetingRepository = c.meetingRepository,
                        agendaRepository = c.agendaRepository,
                        attendeeRepository = c.attendeeRepository,
                        minutesRepository = c.minutesRepository,
                        resolutionRepository = c.resolutionRepository,
                        proceduralProposalRepository = c.proceduralProposalRepository,
                        departureRepository = c.departureRepository,
                        memberRepository = c.memberRepository
                    )
                }
                val session = container.sessionManager
                MeetingDetailScreen(
                    viewModel = vm,
                    onBack = { navController.popBackStack() },
                    canManageAgenda = session.can(Permission.MANAGE_AGENDA),
                    canManageAttendees = session.can(Permission.MANAGE_ATTENDEES),
                    canEditOwnAttendance = session.can(Permission.EDIT_OWN_ATTENDANCE),
                    canManageMinutes = session.can(Permission.MANAGE_MINUTES),
                    canManageResolutions = session.can(Permission.CREATE_RESOLUTION),
                    canManageProceduralProposals = session.can(Permission.MANAGE_PROCEDURAL_PROPOSALS),
                    currentBoardMemberId = session.currentBoardMemberId,
                    isAdmin = session.can(Permission.MANAGE_USERS)
                )
            }
            composable(Destinations.MORE) {
                val session = container.sessionManager
                MoreScreen(
                    canSee = { permission -> permission == null || session.can(permission) },
                    onLogout = {
                        container.apiKeyStore.clear()
                        container.sessionManager.clear()
                        navController.navigate(Destinations.ONBOARDING) { popUpTo(0) { inclusive = true } }
                    }
                )
            }
        }
    }
}
