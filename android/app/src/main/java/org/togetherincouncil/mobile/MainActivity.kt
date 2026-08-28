package org.togetherincouncil.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import org.togetherincouncil.mobile.navigation.TicNavGraph
import org.togetherincouncil.mobile.ui.theme.TogetherInCouncilTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val container = (application as TicApplication).container

        setContent {
            TogetherInCouncilTheme {
                TicNavGraph(container)
            }
        }
    }
}
