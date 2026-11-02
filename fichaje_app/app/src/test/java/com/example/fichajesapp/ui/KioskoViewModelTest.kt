package com.example.fichajesapp.ui

import androidx.lifecycle.ViewModelStore
import com.example.fichajesapp.api.FicharRequest
import com.example.fichajesapp.api.FicharResponse
import com.example.fichajesapp.api.KioskoApi
import com.example.fichajesapp.api.StatusResponse
import java.io.IOException
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceTimeBy
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class KioskoViewModelTest {
    @Test
    fun initialConnectionFailureRecoversOnNextPoll() = runTest {
        Dispatchers.setMain(StandardTestDispatcher(testScheduler))
        val api = FakeKioskoApi { FicharResponse(success = true) }
        api.statusRespond = { throw IOException("Offline test fixture") }
        val store = ViewModelStore()
        try {
            val model = createModel(api, store)
            assertTrue(model.uiState.value is KioskoState.Loading)
            runCurrent()
            assertTrue(model.uiState.value is KioskoState.Error)

            api.statusRespond = { FakeKioskoApi.emptyStatus() }
            advanceTimeBy(30_000)
            runCurrent()
            assertTrue(model.uiState.value is KioskoState.Success)
        } finally {
            store.clear()
            Dispatchers.resetMain()
        }
    }

    @Test
    fun duplicateSubmissionsBeforeAndDuringRequestOnlySendOnce() = runTest {
        Dispatchers.setMain(StandardTestDispatcher(testScheduler))
        val pending = CompletableDeferred<FicharResponse>()
        val api = FakeKioskoApi { pending.await() }
        val store = ViewModelStore()
        try {
            val model = createModel(api, store)
            model.fichar(7, "12345678")
            model.fichar(7, "12345678")
            runCurrent()
            assertEquals(listOf(FicharRequest(7, "12345678")), api.requests)

            model.fichar(7, "12345678")
            runCurrent()
            assertEquals(1, api.requests.size)

            pending.complete(FicharResponse(success = true, message = "Entrada registrada"))
            runCurrent()
            assertEquals("Entrada registrada", model.fichajeMessage.value)
            assertTrue(model.uiState.value is KioskoState.Success)

            // A deliberate next operation is accepted after the first has completed.
            model.fichar(7, "12345678")
            runCurrent()
            assertEquals(2, api.requests.size)
        } finally {
            store.clear()
            Dispatchers.resetMain()
        }
    }

    @Test
    fun connectionFailureReleasesGuardAndAllowsRetry() = runTest {
        Dispatchers.setMain(StandardTestDispatcher(testScheduler))
        val api = FakeKioskoApi { throw IOException("Offline test fixture") }
        val store = ViewModelStore()
        try {
            val model = createModel(api, store)
            model.fichar(7, "12345678")
            runCurrent()
            assertEquals("Error de conexión", model.fichajeMessage.value)
            assertEquals(1, api.requests.size)

            api.respond = { FicharResponse(success = true, message = "Reintento registrado") }
            model.fichar(7, "12345678")
            runCurrent()
            assertEquals(2, api.requests.size)
            assertEquals("Reintento registrado", model.fichajeMessage.value)
        } finally {
            store.clear()
            Dispatchers.resetMain()
        }
    }

    @Test
    fun rejectedOperationReleasesGuardAndAllowsCorrectedPin() = runTest {
        Dispatchers.setMain(StandardTestDispatcher(testScheduler))
        val api = FakeKioskoApi { FicharResponse(success = false, error = "PIN incorrecto") }
        val store = ViewModelStore()
        try {
            val model = createModel(api, store)
            model.fichar(7, "00000000")
            runCurrent()
            assertEquals("PIN incorrecto", model.fichajeMessage.value)

            api.respond = { FicharResponse(success = true, message = "Entrada registrada") }
            model.fichar(7, "12345678")
            runCurrent()
            assertEquals(listOf("00000000", "12345678"), api.requests.map { it.pin })
            assertEquals("Entrada registrada", model.fichajeMessage.value)
        } finally {
            store.clear()
            Dispatchers.resetMain()
        }
    }

    private fun createModel(api: KioskoApi, store: ViewModelStore): KioskoViewModel {
        val model = KioskoViewModel()
        // Main is paused, so neither polling nor a real network call can start.
        // Keep production construction unchanged and inject only in this fixture.
        KioskoViewModel::class.java.getDeclaredField("api").apply {
            isAccessible = true
            set(model, api)
        }
        store.put("test-kiosko", model)
        return model
    }
}

private class FakeKioskoApi(var respond: suspend () -> FicharResponse) : KioskoApi {
    val requests = mutableListOf<FicharRequest>()
    var statusRespond: suspend () -> StatusResponse = { emptyStatus() }

    override suspend fun getStatus() = statusRespond()

    override suspend fun fichar(request: FicharRequest): FicharResponse {
        requests += request
        return respond()
    }

    companion object {
        fun emptyStatus() = StatusResponse(
            serverTime = "2026-10-23T12:00:00+02:00",
            empleados = emptyList(),
            proximosTurnos = emptyList()
        )
    }
}
