package com.example.fichajesapp.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.fichajesapp.api.EmpleadoStatus
import kotlinx.coroutines.delay
import java.text.Normalizer
import java.util.Locale

private fun normalizeSearch(value: String): String =
    Normalizer.normalize(value, Normalizer.Form.NFD)
        .replace(Regex("\\p{M}+"), "")
        .lowercase(Locale.ROOT)

@Composable
fun KioskoScreen(viewModel: KioskoViewModel = viewModel()) {
    val state by viewModel.uiState.collectAsState()
    val message by viewModel.fichajeMessage.collectAsState()
    var selectedEmpleado by remember { mutableStateOf<EmpleadoStatus?>(null) }
    var showPinDialog by remember { mutableStateOf(false) }
    var searchQuery by rememberSaveable { mutableStateOf("") }
    val focusManager = LocalFocusManager.current

    Scaffold(
        topBar = {
            if (state is KioskoState.Success) {
                TopBarKiosko(state as KioskoState.Success)
            }
        }
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when (state) {
                is KioskoState.Loading -> {
                    CircularProgressIndicator(modifier = Modifier.align(Alignment.Center))
                }
                is KioskoState.Error -> {
                    Text(
                        text = (state as KioskoState.Error).message,
                        color = MaterialTheme.colorScheme.error,
                        modifier = Modifier.align(Alignment.Center)
                    )
                }
                is KioskoState.Success -> {
                    val successState = state as KioskoState.Success
                    val searchTerms = remember(searchQuery) {
                        normalizeSearch(searchQuery).trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
                    }
                    val empleados = remember(successState.empleados, searchTerms) {
                        successState.empleados.filter { empleado ->
                            val name = normalizeSearch("${empleado.nombre} ${empleado.apellidos.orEmpty()}")
                            searchTerms.all { name.contains(it) }
                        }
                    }
                    Column(modifier = Modifier.fillMaxSize().imePadding()) {
                        OutlinedTextField(
                            value = searchQuery,
                            onValueChange = { searchQuery = it },
                            label = { Text("Buscar empleado") },
                            placeholder = { Text("Nombre o apellidos") },
                            leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                            trailingIcon = {
                                if (searchQuery.isNotEmpty()) {
                                    IconButton(onClick = { searchQuery = "" }) {
                                        Icon(Icons.Default.Close, contentDescription = "Borrar búsqueda")
                                    }
                                }
                            },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                            keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() }),
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp)
                        )
                        if (empleados.isEmpty()) {
                            Text(
                                text = "No se encontraron empleados. Prueba con otro nombre o apellido.",
                                modifier = Modifier.fillMaxWidth().padding(24.dp),
                                textAlign = TextAlign.Center
                            )
                        } else {
                            LazyVerticalGrid(
                                columns = GridCells.Adaptive(minSize = 180.dp),
                                contentPadding = PaddingValues(16.dp),
                                horizontalArrangement = Arrangement.spacedBy(16.dp),
                                verticalArrangement = Arrangement.spacedBy(16.dp),
                                modifier = Modifier.fillMaxWidth().weight(1f)
                            ) {
                                items(empleados, key = { it.id }) { empleado ->
                                    EmpleadoCard(empleado = empleado) {
                                        focusManager.clearFocus()
                                        selectedEmpleado = empleado
                                        showPinDialog = true
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Snackbar de Mensajes (Feedback visual)
            message?.let { msg ->
                Snackbar(
                    modifier = Modifier.align(Alignment.BottomCenter).padding(16.dp),
                    containerColor = if (msg.contains("Error") || msg.contains("incorrecto")) Color(0xFFE53935) else Color(0xFF43A047)
                ) {
                    Text(msg, color = Color.White, fontWeight = FontWeight.Bold)
                }
            }

            // Dialogo de PIN
            if (showPinDialog && selectedEmpleado != null) {
                PinDialog(
                    empleado = selectedEmpleado!!,
                    onDismiss = {
                        showPinDialog = false
                        selectedEmpleado = null
                    },
                    onSubmit = { pin ->
                        viewModel.fichar(selectedEmpleado!!.id, pin)
                        showPinDialog = false
                        selectedEmpleado = null
                    }
                )
            }
        }
    }
}

@Composable
fun TopBarKiosko(state: KioskoState.Success) {
    Surface(
        color = MaterialTheme.colorScheme.primaryContainer,
        shadowElevation = 8.dp
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(horizontal = 24.dp, vertical = 16.dp)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = "Control de Presencia",
                    fontSize = 24.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onPrimaryContainer
                )
                Text(
                    text = state.localTime,
                    fontSize = 40.sp,
                    fontFamily = FontFamily.Monospace,
                    maxLines = 1,
                    softWrap = false,
                    fontWeight = FontWeight.Black,
                    color = MaterialTheme.colorScheme.onPrimaryContainer
                )
            }

            if (state.proximosTurnos.isNotEmpty()) {
                Spacer(modifier = Modifier.height(16.dp))
                Divider(color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.2f), thickness = 2.dp)
                Spacer(modifier = Modifier.height(16.dp))
                
                val entran = state.proximosTurnos.filter { it.tipo == "entra" }
                val salen = state.proximosTurnos.filter { it.tipo == "sale" }
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(32.dp)
                ) {
                    if (entran.isNotEmpty()) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "ENTRAN AHORA",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Black,
                                color = Color(0xFF2E7D32) // Verde oscuro
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            val entranText = entran.joinToString("  •  ") { "${it.nombre} (${it.hora})" }
                            Text(
                                text = entranText,
                                fontSize = 24.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onPrimaryContainer
                            )
                        }
                    }
                    
                    if (salen.isNotEmpty()) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "SALEN AHORA",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Black,
                                color = Color(0xFFC62828) // Rojo oscuro
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            val salenText = salen.joinToString("  •  ") { "${it.nombre} (${it.hora})" }
                            Text(
                                text = salenText,
                                fontSize = 24.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onPrimaryContainer
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun EmpleadoCard(empleado: EmpleadoStatus, onClick: () -> Unit) {
    val backgroundColor = if (empleado.isWorking) Color(0xFFE8F5E9) else MaterialTheme.colorScheme.surfaceVariant
    val borderColor = if (empleado.isWorking) Color(0xFF4CAF50) else Color.Transparent

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(140.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = backgroundColor)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            val initial = empleado.nombre.firstOrNull()?.toString() ?: "?"
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .clip(CircleShape)
                    .background(if (empleado.isWorking) Color(0xFF4CAF50) else MaterialTheme.colorScheme.primary),
                contentAlignment = Alignment.Center
            ) {
                Text(initial, color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.Bold)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = empleado.nombre,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                textAlign = TextAlign.Center
            )
            if (empleado.isWorking) {
                Text(
                    text = "Trabajando",
                    color = Color(0xFF2E7D32),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
fun PinDialog(empleado: EmpleadoStatus, onDismiss: () -> Unit, onSubmit: (String) -> Unit) {
    var pin by remember { mutableStateOf("") }
    
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(dismissOnBackPress = true, dismissOnClickOutside = true)
    ) {
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 8.dp
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = if (empleado.isWorking) "Fichar Salida" else "Fichar Entrada",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(text = "Introduce el PIN de ${empleado.nombre}")
                
                Spacer(modifier = Modifier.height(24.dp))
                
                // Pin dots
                Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                    for (i in 0 until 8) {
                        Box(
                            modifier = Modifier
                                .size(24.dp)
                                .clip(CircleShape)
                                .background(if (i < pin.length) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant)
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(32.dp))
                
                // Numpad
                val keys = listOf(
                    listOf("1", "2", "3"),
                    listOf("4", "5", "6"),
                    listOf("7", "8", "9"),
                    listOf("C", "0", "OK")
                )
                
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    for (row in keys) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            for (key in row) {
                                Button(
                                    onClick = {
                                        when (key) {
                                            "C" -> if (pin.isNotEmpty()) pin = pin.dropLast(1)
                                            "OK" -> if (pin.length == 8) onSubmit(pin)
                                            else -> if (pin.length < 8) pin += key
                                        }
                                    },
                                    modifier = Modifier.size(72.dp),
                                    shape = CircleShape,
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = if (key == "OK") Color(0xFF4CAF50) else if (key == "C") Color(0xFFE53935) else MaterialTheme.colorScheme.secondaryContainer,
                                        contentColor = if (key == "OK" || key == "C") Color.White else MaterialTheme.colorScheme.onSecondaryContainer
                                    )
                                ) {
                                    Text(key, fontSize = 24.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                TextButton(onClick = onDismiss) {
                    Text("Cancelar")
                }
            }
        }
    }
}
