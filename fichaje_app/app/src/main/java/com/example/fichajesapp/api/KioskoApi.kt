package com.example.fichajesapp.api

import com.example.fichajesapp.BuildConfig
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType

@Serializable
data class StatusResponse(
    @SerialName("server_time") val serverTime: String,
    val empleados: List<EmpleadoStatus>,
    @SerialName("proximos_turnos") val proximosTurnos: List<ProximoTurno>
)

@Serializable
data class EmpleadoStatus(
    val id: Int,
    val nombre: String,
    val apellidos: String?,
    @SerialName("is_working") val isWorking: Boolean,
    @SerialName("entrada_actual") val entradaActual: String? = null
)

@Serializable
data class ProximoTurno(
    val tipo: String, // "entra" o "sale"
    val nombre: String,
    val hora: String
)

@Serializable
data class FicharRequest(
    @SerialName("user_id") val userId: Int,
    val pin: String
)

@Serializable
data class FicharResponse(
    val success: Boolean = false,
    val action: String = "",
    val message: String = "",
    val horas: Double = 0.0,
    val error: String? = null
)

interface KioskoApi {
    @GET("api/kiosko/status")
    suspend fun getStatus(): StatusResponse

    @POST("api/kiosko/fichar")
    suspend fun fichar(@Body request: FicharRequest): FicharResponse

    companion object {
        // Usa 10.0.2.2 para emuladores Android si el backend está en localhost
        private val BASE_URL = BuildConfig.DEMO_BASE_URL
        
        private val json = Json { ignoreUnknownKeys = true }

        fun create(): KioskoApi {
            val retrofit = Retrofit.Builder()
                .baseUrl(BASE_URL)
                .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
                .build()
            return retrofit.create(KioskoApi::class.java)
        }
    }
}
