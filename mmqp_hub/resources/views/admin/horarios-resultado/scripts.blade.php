<script src="{{ asset('js/schedule-draft-session.js') }}"></script>
<script src="{{ asset('js/absence-dates.js') }}"></script>
<script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('resultadosHorarios', (data, isBorrador) => ({
                resultados: (data.resultados || []).flatMap(row => Array.isArray(row.turnos_requeridos)
                    ? row.turnos_requeridos.filter(t => t.empleado_id).map(t => ({ ...t, nombre: t.empleado_nombre || t.nombre || '' })) : [row]),
                plantilla: data.plantilla || [],
                empleados: data.empleados || [],
                esBorrador: isBorrador,
                esAdmin: {{ auth()->user()->es_admin ? 'true' : 'false' }} && data.estado === 'borrador',
                draftSession: null,
                saveState: data.estado === 'empty' ? 'Sin horario publicado para esta semana'
                    : data.estado === 'borrador' ? ''
                    : data.estado === 'historico' ? 'Histórico importado · solo lectura'
                    : data.estado === 'legacy' ? 'Registro antiguo · estado ambiguo'
                    : data.vigente === false ? 'Publicación anterior · solo lectura'
                    : `Publicado · versión ${data.published_draft_version || data.version}`,
                draftError: '',
                saveTimeout: null,
                pendingSaveCount: 0,
                saveFeedback: '',
                saveFeedbackTimeout: null,
                get isSaving() { return this.pendingSaveCount > 0; },
                isPublishing: false,
                ocultarHoras: false,
                dias: [],
                turnosUnidos: [],
                rolesDisponibles: [],
                turnosNoCubiertos: [],
                
                // Configuración de Impresión
                modoImpresion: false,
                numHojas: 2,
                configImpresion: {}, // { rol: { hoja: 1, escala: 100 } }
                
                // Drag and drop state
                draggedTurnoId: null,
                dragOverDia: null,
                dragOverEmp: null,
                
                // Modal de turnos
                showTurnoModal: false,
                turnoForm: { id: null, empleado_id: '', dia: '', hora_inicio: '', hora_fin: '', rol: '' },
                turnoFormError: '',
                
                // Publicar modal state
                showPublicarModal: false,
                rolesSeleccionados: (data.roles_publicados || []).filter(rol => rol !== 'admin'),
                get rolesPublicables() { return this.rolesDisponibles.filter(rol => rol !== 'admin'); },
                seleccionarTodosRoles(checked) {
                    if (checked) {
                        this.rolesSeleccionados = [...this.rolesPublicables];
                    } else {
                        this.rolesSeleccionados = [];
                    }
                },
                
                init() {
                    // Normalize legacy shapes for viewing/editing without rewriting the source record.
                    if (!Array.isArray(this.plantilla)) {
                        this.plantilla = Object.entries(this.plantilla).map(([day, shifts]) => ({
                            dia: day, notas: shifts.notas || '',
                            turnos_requeridos: (Array.isArray(shifts) ? shifts : shifts.turnos_requeridos || []).map(t => ({
                                ...t, turno_id: t.turno_id || t.id || t._idLocal, rol_necesario: t.rol_necesario || t.rol,
                            })),
                        }));
                    }
                    // Extraer dias
                    this.dias = this.plantilla.map(d => ({
                        nombre: d.dia,
                        fecha: d.fecha
                    }));

                    // Unir resultados con la plantilla
                    let rolesSet = new Set();
                    
                    this.plantilla.forEach(diaInfo => {
                        const diaNombre = diaInfo.dia;
                        if (diaInfo.turnos_requeridos) {
                            diaInfo.turnos_requeridos.forEach(tReq => {
                                const rol = (tReq.rol_necesario || '').toLowerCase();
                                if (!rol) return;
                                
                                rolesSet.add(rol);
                                
                                const res = this.resultados.find(r => r.turno_id === tReq.turno_id)
                                    || (tReq.empleado_id ? { ...tReq, nombre: tReq.empleado_nombre || tReq.nombre || '' } : null);
                                if (res) {
                                    this.turnosUnidos.push({
                                        ...tReq,
                                        hora_inicio: res.hora_inicio || tReq.hora_inicio,
                                        hora_fin: res.hora_fin || tReq.hora_fin,
                                        duracion_horas: res.duracion_horas ?? tReq.duracion_horas,
                                        dia: diaNombre,
                                        rol: rol,
                                        empleado_id: res.empleado_id,
                                        empleado_nombre: res.nombre
                                    });
                                } else {
                                    this.turnosNoCubiertos.push({
                                        ...tReq,
                                        dia: diaNombre,
                                        rol: rol
                                    });
                                }
                            });
                        }
                    });

                    this.resultados.forEach(res => {
                        const rol = (res.rol || '').toLowerCase();
                        if (rol) rolesSet.add(rol);
                    });
                    
                    this.empleados.forEach(emp => {
                        const rol = (emp.rol || '').toLowerCase();
                        if (rol) rolesSet.add(rol);
                    });

                    // Ordenar roles (Encargado primero, etc)
                    const orden = ['encargado', 'repartidor', 'auxiliar', 'limpiadora'];
                    this.rolesDisponibles = Array.from(rolesSet).filter(rol => rol.trim() !== 'admin').sort((a, b) => {
                        let idxA = orden.indexOf(a);
                        let idxB = orden.indexOf(b);
                        if (idxA === -1) idxA = 99;
                        if (idxB === -1) idxB = 99;
                        return idxA - idxB;
                    });
                    
                    // Inicializar configImpresion
                    this.rolesDisponibles.forEach((rol, idx) => {
                        this.configImpresion[rol] = {
                            hoja: (idx < 2) ? 1 : 2,
                            escala: 70
                        };
                    });
                    
                    this.setupAutoScroll();
                    if (this.esAdmin) {
                        const session = new ScheduleDraftSession({ id: data.horario_id, semana_id: data.metadatos.semana_id,
                            version: data.version, estado: data.estado }, (state, error) => {
                                this.draftError = error ? state : '';
                                this.saveState = !error && state !== 'Guardando borrador…' && session.dirty(this.draftContent())
                                    ? 'Cambios pendientes de guardar' : state;
                            });
                        this.draftSession = session;
                        session.markLoaded(this.draftContent());
                        this.$watch('turnosUnidos', () => this.queueSave());
                        this.$watch('turnosNoCubiertos', () => this.queueSave());
                        window.addEventListener('beforeunload', event => {
                            if (!session.closed && session.dirty(this.draftContent())) {
                                event.preventDefault(); event.returnValue = '';
                            }
                        });
                    }
                },

                draftContent() {
                    const days = this.plantilla.map(day => ({ ...day, turnos_requeridos: [] }));
                    const results = [];
                    // Persist the complete edited layout: moved, added, deleted and resized shifts.
                    for (const shift of [...this.turnosUnidos, ...this.turnosNoCubiertos]) {
                        let day = days.find(d => d.dia.toLowerCase() === shift.dia.toLowerCase());
                        if (!day) { day = { dia: shift.dia, turnos_requeridos: [] }; days.push(day); }
                        const stored = { ...shift, rol_necesario: shift.rol, empleado_nombre: shift.empleado_nombre || shift.nombre || '' };
                        day.turnos_requeridos.push(stored);
                        if (shift.empleado_id) results.push({ turno_id: shift.turno_id, empleado_id: shift.empleado_id,
                            nombre: stored.empleado_nombre, hora_inicio: shift.hora_inicio, hora_fin: shift.hora_fin,
                            duracion_horas: shift.duracion_horas });
                    }
                    return { fecha_inicio: data.metadatos.fecha_inicio, fecha_fin: data.metadatos.fecha_fin,
                        plantilla_diseno: days, resultados: results, empleados_snapshot: this.empleados };
                },

                queueSave() {
                    if (!this.draftSession || this.draftSession.blocked || this.isPublishing) return;
                    if (!this.draftSession.dirty(this.draftContent())) return;
                    clearTimeout(this.saveFeedbackTimeout);
                    this.saveFeedback = '';
                    clearTimeout(this.saveTimeout);
                    this.saveState = 'Cambios pendientes de guardar';
                    this.saveTimeout = setTimeout(() => this.guardarResultado(), 1000);
                },

                async guardarResultado(manual = false) {
                    if (!this.draftSession || this.isPublishing || (manual && this.isSaving)) return;
                    clearTimeout(this.saveTimeout);
                    clearTimeout(this.saveFeedbackTimeout);
                    this.saveFeedback = '';
                    this.pendingSaveCount++;
                    const content = this.draftContent();
                    const hadChanges = this.draftSession.dirty(content) || !!this.draftSession.pending;
                    if (manual) this.saveState = 'Guardando borrador…';
                    try {
                        await this.draftSession.save(content);
                        this.draftError = '';
                        if (manual && !this.draftSession.dirty(this.draftContent())) {
                            this.saveFeedback = hadChanges ? 'saved' : 'unchanged';
                            this.saveState = hadChanges
                                ? 'Borrador guardado · pendiente de publicar'
                                : 'Sin cambios nuevos · el borrador ya está guardado';
                            this.saveFeedbackTimeout = setTimeout(() => { this.saveFeedback = ''; }, 4000);
                        }
                    } catch (error) {
                        // Keep local changes available for retry/download, including rejected sessions.
                        this.saveState = 'No se pudo guardar el borrador';
                        this.draftError = error.status || this.draftSession.blocked || this.draftSession.closed
                            ? error.message : 'No se pudo completar el guardado. Revisa la conexión y pulsa «Reintentar».';
                    } finally {
                        this.pendingSaveCount--;
                    }
                },

                async publicarResultado() {
                    if (!this.draftSession || this.isPublishing) return;
                    clearTimeout(this.saveTimeout);
                    this.isPublishing = true;
                    this.esAdmin = false;
                    try {
                        const result = await this.draftSession.publish(this.draftContent(), this.rolesSeleccionados);
                        window.location.href = result.redirect_url;
                    } catch (error) {
                        this.draftError = error.message;
                        this.showPublicarModal = false;
                        this.esAdmin = !this.draftSession.blocked;
                        this.isPublishing = false;
                    }
                },

                descargarCambios() {
                    const url = URL.createObjectURL(new Blob([JSON.stringify(this.draftContent(), null, 2)], { type: 'application/json' }));
                    const link = document.createElement('a'); link.href = url;
                    link.download = `borrador-${data.metadatos.semana_id}.json`; link.click(); URL.revokeObjectURL(url);
                },

                iniciarArrastre(turnoId) {
                    this.draggedTurnoId = turnoId;
                    
                    // Asegurar que al terminar el arrastre en cualquier lugar se limpie y pare el scroll
                    const stopDrag = () => {
                        this.draggedTurnoId = null;
                        document.removeEventListener('dragend', stopDrag);
                    };
                    document.addEventListener('dragend', stopDrag);
                },

                autoScrollDir: 0,
                autoScrollInterval: null,
                
                setupAutoScroll() {
                    document.addEventListener('dragover', (e) => {
                        if (!this.draggedTurnoId) {
                            if (this.autoScrollInterval) {
                                clearInterval(this.autoScrollInterval);
                                this.autoScrollInterval = null;
                                this.autoScrollDir = 0;
                            }
                            return;
                        }
                        
                        const buffer = 100;
                        let dir = 0;
                        
                        if (e.clientY < buffer) {
                            dir = -1;
                        } else if (window.innerHeight - e.clientY < buffer) {
                            dir = 1;
                        }
                        
                        if (dir !== this.autoScrollDir) {
                            this.autoScrollDir = dir;
                            if (this.autoScrollInterval) {
                                clearInterval(this.autoScrollInterval);
                                this.autoScrollInterval = null;
                            }
                            if (dir !== 0) {
                                this.autoScrollInterval = setInterval(() => {
                                    window.scrollBy(0, dir * 25);
                                }, 20);
                            }
                        }
                    });
                    
                    const stopScroll = () => {
                        this.autoScrollDir = 0;
                        if (this.autoScrollInterval) {
                            clearInterval(this.autoScrollInterval);
                            this.autoScrollInterval = null;
                        }
                    };
                    
                    document.addEventListener('dragend', stopScroll);
                    document.addEventListener('drop', stopScroll);
                },

                eliminarTurno(turnoId) {
                    if (confirm("¿Seguro que quieres eliminar este turno? Esta acción no se puede deshacer.")) {
                        this.turnosUnidos = this.turnosUnidos.filter(t => t.turno_id !== turnoId);
                        this.resultados = this.resultados.filter(r => r.turno_id !== turnoId);
                    }
                },

                abrirModalNuevoTurno(empleadoId, dia, rol) {
                    this.turnoForm = {
                        id: null,
                        empleado_id: empleadoId,
                        dia: dia,
                        hora_inicio: '12:00',
                        hora_fin: '16:00',
                        rol: rol
                    };
                    this.turnoFormError = '';
                    this.showTurnoModal = true;
                },

                abrirModalEditarTurno(turno) {
                    this.turnoForm = {
                        id: turno.turno_id,
                        empleado_id: turno.empleado_id,
                        dia: turno.dia,
                        hora_inicio: turno.hora_inicio,
                        hora_fin: turno.hora_fin,
                        rol: turno.rol
                    };
                    this.turnoFormError = '';
                    this.showTurnoModal = true;
                },

                guardarTurnoModal() {
                    const selectedEmployee = this.empleados.find(e => String(e.id) === String(this.turnoForm.empleado_id));
                    if (selectedEmployee && this.isFestivoNoDisponible(selectedEmployee, null, this.turnoForm.dia)) {
                        this.turnoFormError = 'Este empleado no está disponible para trabajar en festivos.';
                        return;
                    }
                    if(!this.turnoForm.hora_inicio || !this.turnoForm.hora_fin) {
                        this.turnoFormError = 'Las horas de inicio y fin son obligatorias.';
                        return;
                    }

                    // Calcular duracion_horas
                    let timeToMins = (tStr) => {
                        let [h, m] = tStr.split(':').map(Number);
                        if (h < 5) h += 24;
                        if (tStr === "00:00") h = 24;
                        return h * 60 + m;
                    };
                    let iniMins = timeToMins(this.turnoForm.hora_inicio);
                    let finMins = timeToMins(this.turnoForm.hora_fin);
                    if (finMins < iniMins) finMins += 24 * 60;
                    let duracion = parseFloat(((finMins - iniMins) / 60).toFixed(2));

                    if (this.turnoForm.id) {
                        // Editar turno existente
                        let tUnido = this.turnosUnidos.find(t => t.turno_id === this.turnoForm.id);
                        if(tUnido) {
                            tUnido.hora_inicio = this.turnoForm.hora_inicio;
                            tUnido.hora_fin = this.turnoForm.hora_fin;
                            tUnido.duracion_horas = duracion;
                        }
                        let res = this.resultados.find(r => r.turno_id === this.turnoForm.id);
                        if (res) {
                            res.hora_inicio = this.turnoForm.hora_inicio;
                            res.hora_fin = this.turnoForm.hora_fin;
                            res.duracion_horas = duracion;
                        }
                    } else {
                        // Crear nuevo turno
                        let nuevoId = 'manual_' + Date.now();
                        let empInfo = this.empleados.find(e => e.id == this.turnoForm.empleado_id);
                        let empNombre = empInfo ? empInfo.nombre : '';

                        let nuevoTurno = {
                            turno_id: nuevoId,
                            empleado_id: this.turnoForm.empleado_id,
                            nombre: empNombre,
                            hora_inicio: this.turnoForm.hora_inicio,
                            hora_fin: this.turnoForm.hora_fin,
                            duracion_horas: duracion,
                            dia: this.turnoForm.dia,
                            rol: this.turnoForm.rol,
                            rol_necesario: this.turnoForm.rol
                        };

                        this.resultados.push({
                            turno_id: nuevoId,
                            empleado_id: this.turnoForm.empleado_id,
                            nombre: empNombre,
                            hora_inicio: this.turnoForm.hora_inicio,
                            hora_fin: this.turnoForm.hora_fin,
                            duracion_horas: duracion
                        });

                        this.turnosUnidos.push(nuevoTurno);
                    }
                    this.showTurnoModal = false;
                },

                soltarTurno(nuevoEmpleadoId, nuevoDia, rol, nuevoEmpleadoNombre) {
                    if (!this.draggedTurnoId) return;
                    
                    // Buscar el turno en turnosUnidos
                    let turnoUnido = this.turnosUnidos.find(t => t.turno_id === this.draggedTurnoId);
                    
                    // Solo permitir soltar si es del mismo rol
                    if (turnoUnido && turnoUnido.rol === rol) {
                        const destination = this.empleados.find(e => String(e.id) === String(nuevoEmpleadoId));
                        if (destination && this.isFestivoNoDisponible(destination, null, nuevoDia)) {
                            alert('Este empleado no está disponible para trabajar en festivos.');
                            return;
                        }
                        
                        let empleadoOrigenId = turnoUnido.empleado_id;
                        let empleadoOrigenNombre = turnoUnido.empleado_nombre;
                        let diaOrigen = turnoUnido.dia;

                        // Función auxiliar para calcular minutos (soporta madrugada)
                        let timeToMins = (tStr) => {
                            let [h, m] = tStr.split(':').map(Number);
                            if (h < 5) h += 24;
                            if (tStr === "00:00") h = 24;
                            return h * 60 + m;
                        };

                        let ini1 = timeToMins(turnoUnido.hora_inicio);
                        let fin1 = timeToMins(turnoUnido.hora_fin);

                        // Comprobar si el empleado destino ya tiene un turno en ese día que solape
                        let turnosDestinoEnDia = this.turnosUnidos.filter(t => 
                            t.empleado_id === nuevoEmpleadoId && 
                            t.dia.toLowerCase() === nuevoDia.toLowerCase() && 
                            t.rol === rol &&
                            t.turno_id !== turnoUnido.turno_id
                        );

                        let turnoSolapado = turnosDestinoEnDia.find(t => {
                            let ini2 = timeToMins(t.hora_inicio);
                            let fin2 = timeToMins(t.hora_fin);
                            return ini1 < fin2 && ini2 < fin1; // Hay solapamiento
                        });

                        if (turnoSolapado) {
                            const origin = this.empleados.find(e => String(e.id) === String(empleadoOrigenId));
                            if (origin && this.isFestivoNoDisponible(origin, null, diaOrigen)) {
                                alert('El intercambio asignaría un festivo a un empleado no disponible.');
                                return;
                            }
                            // INTERCAMBIAR TURNOS
                            // El arrastrado va al nuevo empleado
                            turnoUnido.empleado_id = nuevoEmpleadoId;
                            turnoUnido.empleado_nombre = nuevoEmpleadoNombre;
                            turnoUnido.dia = nuevoDia;

                            // El solapado va al empleado original
                            turnoSolapado.empleado_id = empleadoOrigenId;
                            turnoSolapado.empleado_nombre = empleadoOrigenNombre;
                            turnoSolapado.dia = diaOrigen;

                            // Actualizar ambos en resultados enviados al servidor
                            let res1 = this.resultados.find(r => r.turno_id === turnoUnido.turno_id);
                            if (res1) {
                                res1.empleado_id = nuevoEmpleadoId;
                                res1.nombre = nuevoEmpleadoNombre;
                            }
                            let res2 = this.resultados.find(r => r.turno_id === turnoSolapado.turno_id);
                            if (res2) {
                                res2.empleado_id = empleadoOrigenId;
                                res2.nombre = empleadoOrigenNombre;
                            }
                        } else {
                            // MOVIMIENTO NORMAL SIN SOLAPAMIENTO
                            turnoUnido.empleado_id = nuevoEmpleadoId;
                            turnoUnido.empleado_nombre = nuevoEmpleadoNombre;
                            
                            let res = this.resultados.find(r => r.turno_id === this.draggedTurnoId);
                            if (res) {
                                res.empleado_id = nuevoEmpleadoId;
                                res.nombre = nuevoEmpleadoNombre;
                            }

                            if (turnoUnido.dia.toLowerCase() !== nuevoDia.toLowerCase()) {
                                turnoUnido.dia = nuevoDia;
                            }
                        }
                    }
                    
                    this.draggedTurnoId = null;
                    this.dragOverDia = null;
                    this.dragOverEmp = null;
                },

                getEmpleadosConTurno(rol) {
                    const rolBuscado = (rol || '').toLowerCase();
                    let emps = this.empleados.filter(e => (e.rol || '').toLowerCase() === rolBuscado
                        || (data.is_importado && this.turnosUnidos.some(t => String(t.empleado_id) === String(e.id) && t.rol === rolBuscado)));
                    return emps.sort((a, b) => {
                        let aVisual = a.orden_visual || 0;
                        let bVisual = b.orden_visual || 0;
                        if (aVisual !== bVisual) {
                            return aVisual - bVisual;
                        }
                        return a.nombre.localeCompare(b.nombre);
                    });
                },

                moverArriba(empleado_id, rol) {
                    let lista = this.getEmpleadosConTurno(rol);
                    let index = lista.findIndex(e => e.id === empleado_id);
                    if (index > 0) {
                        // Intercambiar en el array
                        let temp = lista[index];
                        lista[index] = lista[index - 1];
                        lista[index - 1] = temp;
                        
                        this.recalcularOrden(lista);
                    }
                },

                moverAbajo(empleado_id, rol) {
                    let lista = this.getEmpleadosConTurno(rol);
                    let index = lista.findIndex(e => e.id === empleado_id);
                    if (index >= 0 && index < lista.length - 1) {
                        // Intercambiar en el array
                        let temp = lista[index];
                        lista[index] = lista[index + 1];
                        lista[index + 1] = temp;

                        this.recalcularOrden(lista);
                    }
                },

                recalcularOrden(lista) {
                    let payload = [];
                    lista.forEach((empItem, newIndex) => {
                        let objMain = this.empleados.find(e => e.id === empItem.id);
                        if (objMain) {
                            objMain.orden_visual = newIndex;
                            payload.push({id: objMain.id, orden_visual: newIndex});
                        }
                    });
                    this.guardarOrden(payload);
                },

                guardarOrden(datos) {
                    let csrfToken = document.querySelector('meta[name="csrf-token"]');
                    fetch('/admin/empleados/orden', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({orden: datos})
                    }).catch(console.error);
                },

                isFechaLibre(emp, diaFechaTexto, diaNombre) {
                    return this.isFestivoNoDisponible(emp, diaFechaTexto, diaNombre)
                        || AbsenceDates.contains(emp.fechas_libres_solicitadas, diaFechaTexto, data.metadatos?.semana_id, diaNombre);
                },

                isFestivoNoDisponible(emp, dateText, dayName) {
                    return emp.disponibilidad_festivos === 'no_disponible'
                        && AbsenceDates.contains(data.festivos || [], dateText, data.metadatos?.semana_id, dayName);
                },

                isVacaciones(emp, diaFechaTexto, diaNombre) {
                    return AbsenceDates.contains(emp.vacaciones, diaFechaTexto, data.metadatos?.semana_id, diaNombre);
                },

                getTurnosEmpleadoDia(empleado_id, diaNombre, rol) {
                    return this.turnosUnidos.filter(t => 
                        String(t.empleado_id) === String(empleado_id) &&
                        t.dia.toLowerCase() === diaNombre.toLowerCase() &&
                        t.rol === rol
                    ).sort((a, b) => {
                        return a.hora_inicio.localeCompare(b.hora_inicio);
                    });
                },

                getTotalHorasDia(empleado_id, diaNombre, rol) {
                    const turnos = this.getTurnosEmpleadoDia(empleado_id, diaNombre, rol);
                    let total = 0;
                    turnos.forEach(t => total += Number.isInteger(t.duracion_minutos)
                        ? t.duracion_minutos / 60 : (parseFloat(t.duracion_horas) || 0));
                    return Number(total.toFixed(2));
                },

                getTotalHorasSemana(empleado_id, rol) {
                    const turnos = this.turnosUnidos.filter(t => String(t.empleado_id) === String(empleado_id) && t.rol === rol);
                    let total = 0;
                    turnos.forEach(t => total += Number.isInteger(t.duracion_minutos)
                        ? t.duracion_minutos / 60 : (parseFloat(t.duracion_horas) || 0));
                    return Number(total.toFixed(2));
                },

                getDiasTrabajados(empleado_id, rol) {
                    let cont = 0;
                    this.dias.forEach(d => {
                        if (this.getTotalHorasDia(empleado_id, d.nombre, rol) > 0) cont++;
                    });
                    return cont;
                },

                tieneDosDiasLibresConsecutivos(empleado_id, rol) {
                    let libres = [];
                    this.dias.forEach((d, i) => {
                        if (this.getTotalHorasDia(empleado_id, d.nombre, rol) === 0) {
                            libres.push(i);
                        }
                    });
                    for (let i = 0; i < libres.length - 1; i++) {
                        if (libres[i+1] === libres[i] + 1) return true;
                    }
                    return false;
                },

                getHorasSemanalesObjetivo(emp) {
                    if (emp.horas_semanales_override) return parseFloat(emp.horas_semanales_override);
                    if (emp.horas_mensuales) return parseFloat(emp.horas_mensuales) / 4;
                    return 0;
                },

                getInfraccionesDescanso(empleado_id, rol) {
                    let infracciones = [];
                    let turnos = this.turnosUnidos.filter(t => String(t.empleado_id) === String(empleado_id) && t.rol === rol);
                    if (turnos.length < 2) return infracciones;
                    
                    let getMinutosTotales = (diaNombre, tStr) => {
                        if (!tStr) return 0;
                        let [h, m] = tStr.split(':').map(Number);
                        if (h < 5) h += 24; 
                        if (tStr === "00:00") h = 24;
                        let indexDia = this.dias.findIndex(d => d.nombre === diaNombre);
                        if (indexDia === -1) return 0;
                        return (indexDia * 24 * 60) + (h * 60) + m;
                    };

                    let turnosMapeados = turnos.map(t => {
                        let indexDia = this.dias.findIndex(d => d.nombre === t.dia);
                        return {
                            ...t,
                            indexDia: indexDia,
                            minInicio: getMinutosTotales(t.dia, t.hora_inicio),
                            minFin: getMinutosTotales(t.dia, t.hora_fin)
                        };
                    }).sort((a, b) => a.minInicio - b.minInicio);

                    let vioSameDay = false;
                    let vioNextDay = false;

                    for (let i = 0; i < turnosMapeados.length - 1; i++) {
                        let t1 = turnosMapeados[i];
                        let t2 = turnosMapeados[i+1];
                        let descanso = t2.minInicio - t1.minFin;

                        if (t1.indexDia === t2.indexDia) {
                            if (descanso < 150) vioSameDay = true;
                        } else {
                            if (descanso < 720) vioNextDay = true;
                        }
                    }

                    if (vioSameDay) infracciones.push("No tiene 2 horas y media de descanso entre turnos");
                    if (vioNextDay) infracciones.push("No tiene 12 horas de descanso");

                    return infracciones;
                },

                getTurnoInfraccion(empleado_id, rol, turno_id) {
                    let turnos = this.turnosUnidos.filter(t => String(t.empleado_id) === String(empleado_id) && t.rol === rol);
                    if (turnos.length < 2) return null;
                    
                    let getMinutosTotales = (diaNombre, tStr) => {
                        if (!tStr) return 0;
                        let [h, m] = tStr.split(':').map(Number);
                        if (h < 5) h += 24; 
                        if (tStr === "00:00") h = 24;
                        let indexDia = this.dias.findIndex(d => d.nombre === diaNombre);
                        if (indexDia === -1) return 0;
                        return (indexDia * 24 * 60) + (h * 60) + m;
                    };

                    let turnosMapeados = turnos.map(t => {
                        let indexDia = this.dias.findIndex(d => d.nombre === t.dia);
                        return {
                            ...t,
                            indexDia: indexDia,
                            minInicio: getMinutosTotales(t.dia, t.hora_inicio),
                            minFin: getMinutosTotales(t.dia, t.hora_fin)
                        };
                    }).sort((a, b) => a.minInicio - b.minInicio);

                    let idx = turnosMapeados.findIndex(t => t.turno_id === turno_id);
                    if (idx === -1) return null;

                    let t = turnosMapeados[idx];
                    let prev = idx > 0 ? turnosMapeados[idx - 1] : null;
                    let next = idx < turnosMapeados.length - 1 ? turnosMapeados[idx + 1] : null;

                    if (prev) {
                        let descansoPrev = t.minInicio - prev.minFin;
                        if (t.indexDia === prev.indexDia && descansoPrev >= 0 && descansoPrev < 150) return 'same_day';
                        if (t.indexDia !== prev.indexDia && descansoPrev >= 0 && descansoPrev < 720) return 'next_day';
                        // overlaps
                        if (descansoPrev < 0) return t.indexDia === prev.indexDia ? 'same_day' : 'next_day';
                    }

                    if (next) {
                        let descansoNext = next.minInicio - t.minFin;
                        if (t.indexDia === next.indexDia && descansoNext >= 0 && descansoNext < 150) return 'same_day';
                        if (t.indexDia !== next.indexDia && descansoNext >= 0 && descansoNext < 720) return 'next_day';
                        // overlaps
                        if (descansoNext < 0) return t.indexDia === next.indexDia ? 'same_day' : 'next_day';
                    }

                    return null;
                },

                getRolPlural(rol) {
                    if(rol === 'encargado') return 'ENCARGADOS';
                    if(rol === 'repartidor') return 'REPARTIDORES';
                    if(rol === 'auxiliar') return 'AUXILIARES';
                    if(rol === 'limpiadora') return 'LIMPIADORAS';
                    if(rol === 'sin_puesto') return 'SIN PUESTO INDICADO';
                    return rol.toUpperCase() + 'S';
                }
            }));
        });

    </script>
