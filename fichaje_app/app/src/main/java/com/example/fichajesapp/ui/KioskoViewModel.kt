package com.example.fichajesapp.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.fichajesapp.api.EmpleadoStatus
import com.example.fichajesapp.api.FicharRequest
import com.example.fichajesapp.api.KioskoApi
import com.example.fichajesapp.api.ProximoTurno
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import retrofit2.HttpException
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone

sealed class KioskoState {
    object Loading : KioskoState()
    data class Success(
        val empleados: List<EmpleadoStatus>,
        val proximosTurnos: List<ProximoTurno>,
        val serverTime: String,
        val localTime: String
    ) : KioskoState()
    data class Error(val message: String) : KioskoState()
}

class KioskoViewModel : ViewModel() {
    private val api = KioskoApi.create()

    private val _uiState = MutableStateFlow<KioskoState>(KioskoState.Loading)
    val uiState: StateFlow<KioskoState> = _uiState.asStateFlow()

    private val _fichajeMessage = MutableStateFlow<String?>(null)
    val fichajeMessage = _fichajeMessage.asStateFlow()
    private var fichajeEnCurso = false
    
    private val timeFormat = SimpleDateFormat("HH:mm:ss", Locale.forLanguageTag("es-ES")).apply {
        timeZone = TimeZone.getTimeZone("Europe/Madrid")
    }

    init {
        startPolling()
    }

    private fun startPolling() {
        viewModelScope.launch {
            while (true) {
                fetchStatus()
                // Polling cada 30 segundos, pero actualizamos el reloj local más rápido
                for (i in 0 until 30) {
                    updateLocalTime()
                    delay(1000)
                }
            }
        }
    }
    
    private fun updateLocalTime() {
        val currentState = _uiState.value
        if (currentState is KioskoState.Success) {
            _uiState.value = currentState.copy(localTime = timeFormat.format(Date()))
        }
    }

    private suspend fun fetchStatus() {
        try {
            val response = api.getStatus()
            _uiState.value = KioskoState.Success(
                empleados = response.empleados,
                proximosTurnos = response.proximosTurnos,
                serverTime = response.serverTime,
                localTime = timeFormat.format(Date())
            )
        } catch (e: Exception) {
            if (_uiState.value !is KioskoState.Success) {
                _uiState.value = KioskoState.Error(e.localizedMessage ?: "Error de conexión")
            }
        }
    }

    fun fichar(userId: Int, pin: String) {
        if (fichajeEnCurso) return
        fichajeEnCurso = true
        viewModelScope.launch {
            try {
                val response = api.fichar(FicharRequest(userId, pin))
                if (response.success) {
                    _fichajeMessage.value = response.message
                    fetchStatus() // Refrescar la UI inmediatamente
                } else {
                    _fichajeMessage.value = response.error ?: "Error al fichar"
                }
            } catch (e: HttpException) {
                 _fichajeMessage.value = "PIN incorrecto"
            } catch (e: Exception) {
                 _fichajeMessage.value = "Error de conexión"
            } finally {
                fichajeEnCurso = false
            }
            
            // Borrar el mensaje después de 3 segundos
            delay(3000)
            _fichajeMessage.value = null
        }
    }
}
