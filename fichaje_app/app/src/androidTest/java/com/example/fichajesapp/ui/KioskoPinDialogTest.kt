package com.example.fichajesapp.ui

import androidx.activity.ComponentActivity
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import com.example.fichajesapp.api.EmpleadoStatus
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

class KioskoPinDialogTest {
    @get:Rule
    val compose = createAndroidComposeRule<ComponentActivity>()

    @Test
    fun pinMustHaveEightDigitsAndAdditionalDigitsAreIgnored() {
        val submitted = mutableListOf<String>()
        showDialog(onSubmit = { submitted += it })
        compose.onNodeWithText("OK").performClick()
        compose.runOnIdle { assertTrue(submitted.isEmpty()) }

        "123456789".forEach { compose.onNodeWithText(it.toString()).performClick() }
        compose.onNodeWithText("OK").performClick()
        compose.runOnIdle { assertEquals(listOf("12345678"), submitted) }
    }

    @Test
    fun deleteAllowsCorrectingPinBeforeSubmitting() {
        val submitted = mutableListOf<String>()
        showDialog(onSubmit = { submitted += it })
        "12345678".forEach { compose.onNodeWithText(it.toString()).performClick() }
        compose.onNodeWithText("C").performClick()
        compose.onNodeWithText("OK").performClick()
        compose.runOnIdle { assertTrue(submitted.isEmpty()) }

        compose.onNodeWithText("9").performClick()
        compose.onNodeWithText("OK").performClick()
        compose.runOnIdle { assertEquals(listOf("12345679"), submitted) }
    }

    @Test
    fun cancelDismissesWithoutSubmitting() {
        val submitted = mutableListOf<String>()
        var dismissed = false
        showDialog(onDismiss = { dismissed = true }, onSubmit = { submitted += it })
        compose.onNodeWithText("1").performClick()
        compose.onNodeWithText("Cancelar").performClick()
        compose.runOnIdle {
            assertTrue(dismissed)
            assertTrue(submitted.isEmpty())
        }
    }

    private fun showDialog(onDismiss: () -> Unit = {}, onSubmit: (String) -> Unit) {
        compose.setContent {
            PinDialog(
                empleado = EmpleadoStatus(7, "Empleado QA", null, false),
                onDismiss = onDismiss,
                onSubmit = onSubmit
            )
        }
    }
}
