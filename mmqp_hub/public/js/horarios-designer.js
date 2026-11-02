document.addEventListener('alpine:init', () => {
              Alpine.data('horariosDesigner', (empleadosData, ausenciasData = [], festivosData = []) => ({
                  empleados: empleadosData || [],
                  ausencias: ausenciasData || [],
                  festivos: festivosData || [],
                  semana: '',
                  dias: ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                  
                  // IA Eventos Locales
                  direccionPostal: 'Ciudad Demo',
                  geminiApiKey: '',
                  geminiModelo: 'demo',
                  eventosLocalesHTML: '',
                  isCargandoEventos: false,
                  showEventosModal: false,
                  showSettingsModal: false,
                  turnos: {
                      lunes: [],
                      martes: [],
                      miercoles: [],
                      jueves: [],
                      viernes: [],
                      sabado: [],
                      domingo: []
                  },
                  previewRol: 'encargado',
                  fechasDias: {
                      'lunes': '',
                      'martes': '',
                      'miercoles': '',
                      'jueves': '',
                      'viernes': '',
                      'sabado': '',
                      'domingo': ''
                  },
                  fechasISO: {},
                  horas: ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '00:00', '01:00', '02:00', '03:00'],
                  isModalOpen: false,
                  isGenerating: false,
                  isGeneratingTemplate: false,
                  autoTemplateHorario: Object.fromEntries(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'].map(dia => [dia, {
                      abierto: true, franjas: [{activa: true, inicio: '12:00', fin: '16:30'}, {activa: true, inicio: '19:00', fin: '00:30'}],
                  }])),
                  autoTemplateAvisos: [],
                  autoTemplateHuecos: [],
                  autoTemplateResumen: [],
                  autoTemplateModelo: null,
                  autoTemplateError: '',
                  autoTemplateApplied: false,
                  generationTimeElapsed: 0,
                  generationTimer: null,
                  showConfirmModal: false,
                  confirmData: { message: '', deficit: {}, exceso: {}, requeridas: {}, disponibles: {} },
                  diaSeleccionado: '',
                  roles: ['encargado', 'repartidor', 'auxiliar', 'limpiadora'],
                  activeRole: 'encargado',
                  debugPayload: '',
                  bloques: [],
                  autoSaveTimeout: null,
                  autoSaveState: 'Cargando borrador…',
                  draftSession: null,
                  isLoadingDraft: true,
                  draftLoadSequence: 0,
                  draftError: '',
                  
                  notas: {
                      lunes: '', martes: '', miercoles: '', jueves: '', viernes: '', sabado: '', domingo: ''
                  },
                  isNotesModalOpen: false,
                  isSaveModalOpen: false,
                  isLoadModalOpen: false,
                  isImportModalOpen: false,
                  jsonImportText: '',
                  plantillaName: '',
                  isConfirmLoadOpen: false,
                  pendingLoadTurnos: null,
                  plantillasGuardadas: [],
                  horariosPasados: [],
                  expandedYears: {},
                  expandedMonths: {},
                  get horariosPasadosAgrupados() {
                      const agrupados = {};
                      const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                      
                      this.horariosPasados.forEach(semana => {
                          let anyo = "Otros";
                          let mes = "Desconocido";
                          
                          if (semana.fecha_inicio && semana.fecha_inicio.includes('-')) {
                              const parts = semana.fecha_inicio.split('-');
                              if (parts.length === 3) {
                                  anyo = parts[0];
                                  const mesIndex = parseInt(parts[1], 10) - 1;
                                  if (mesIndex >= 0 && mesIndex < 12) {
                                      mes = meses[mesIndex];
                                  }
                              }
                          }
                          
                          if (!agrupados[anyo]) agrupados[anyo] = {};
                          if (!agrupados[anyo][mes]) agrupados[anyo][mes] = [];
                          agrupados[anyo][mes].push(semana);
                      });
                      
                      return agrupados;
                  },
                  toggleYear(year) {
                      this.expandedYears[year] = !this.expandedYears[year];
                  },
                  toggleMonth(year, month) {
                      const key = year + '-' + month;
                      this.expandedMonths[key] = !this.expandedMonths[key];
                  },
                  isPreviewing: false,
                  previewTurnos: null,
                  previewPlantillaName: "",
                  isDragging: false,
                  dragRole: null,
                  dragStartMin: null,
                  dragCurrentMin: null,
                  isLinking: false,
                  linkingSourceTurnoId: null,
                  linkMouseX: 0,
                  linkMouseY: 0,
                  gridHeightReactive: 0,
                  
                  isAjusteManualOpen: false,
                  ajusteRole: 'encargado',
                  ajustesManuales: {},
                  empleadosAjuste: null,
                  ajustesBloqueados: {},
                  ajusteEmpleadoActivo: null,
                  tiempoGeneracion: 2,
                  colWidth: 300,
                  
                  updateColWidth() {
                      const availableWidth = window.innerWidth - 80;
                      this.colWidth = Math.max(200, availableWidth / 4);
                  },
                  
                  getCardMetrics(dia, rol) {
                      const maxCols = Math.max(1, this.getMaxColumnsForRol(dia, rol));
                      let cardW = 220; // Ancho visual siempre igual (fijo)
                      return {
                          width: cardW,
                          // Sumamos un 'cardW' extra para que SIEMPRE haya un hueco libre a la derecha para poder añadir nuevos turnos
                          scrollWidth: (maxCols + 1) * cardW + 16
                      };
                  },
                  
                  toggleBloqueo(empId) {
                      this.ajustesBloqueados[empId] = !this.ajustesBloqueados[empId];
                  },
                  
                  // Drag & Drop de turnos existentes
                  activeDragId: null,
                  dragAction: null,
                  dragStartY: 0,
                  dragOrigStartMin: 0,
                  dragOrigEndMin: 0,
                  dragPixelsPerMin: 0,
                  
                  // AsignaciÃ³n manual
                  isAssignModalOpen: false,
                  assignTurnoId: null,
                  assignTurnoRol: '',
                  assignTurnoPuesto: 1,
                  assignDia: '',
                  
                  abrirImportModal() {
                      this.jsonImportText = '';
                      this.isImportModalOpen = true;
                  },
                  cerrarImportModal() {
                      this.isImportModalOpen = false;
                  },
                  ejecutarImportarJSON() {
                      try {
                          const data = JSON.parse(this.jsonImportText);
                          let plantilla = data;
                          if (data.plantilla_diseno) {
                              plantilla = data.plantilla_diseno;
                          }
                          
                          if (!Array.isArray(plantilla)) {
                              alert("El JSON debe contener un array o una propiedad 'plantilla_diseno'.");
                              return;
                          }
                          
                          for (let d of this.dias) {
                              this.turnos[d] = [];
                          }
                          
                          plantilla.forEach(diaInfo => {
                              let diaStr = (diaInfo.dia || '').toLowerCase();
                              if (this.dias.includes(diaStr)) {
                                  let reqs = diaInfo.turnos_requeridos || [];
                                  reqs.forEach((t, i) => {
                                      this.turnos[diaStr].push({
                                          id: t._idLocal || Math.random().toString(36).substring(2, 9),
                                          rol: t.rol_necesario || 'encargado',
                                          puesto_num: t.puesto_num || i + 1,
                                          hora_inicio: t.hora_inicio || '09:00',
                                          hora_fin: t.hora_fin || '17:00',
                                          empleado_id: t.empleado_id || null,
                                          empleado_nombre: t.empleado_nombre || null,
                                          reserva_menor: t.reserva_menor === true,
                                          turno_id: t.turno_id || t._idLocal
                                      });
                                  });
                                  if (diaInfo.notas) {
                                      this.notas[diaStr] = diaInfo.notas;
                                  }
                              }
                          });
                          
                          this.cerrarImportModal();
                      } catch (e) {
                          alert('Error al importar JSON: ' + e.message);
                      }
                  },

                  draftContent() {
                      return {
                          fecha_inicio: this.fechasISO.lunes,
                          fecha_fin: this.fechasISO.domingo,
                          empleados_snapshot: this.empleados,
                          plantilla_diseno: this.dias.map(dia => ({
                              dia, fecha: this.fechasISO[dia], notas: this.notas[dia] || '',
                              turnos_requeridos: this.turnos[dia].map(t => {
                                  let hours = (this.timeToMinutes(t.hora_fin) - this.timeToMinutes(t.hora_inicio)) / 60;
                                  if (hours < 0) hours += 24;
                                  return { turno_id: t.turno_id || t.id, _idLocal: t.id, puesto_num: t.puesto_num,
                                      rol_necesario: t.rol, hora_inicio: t.hora_inicio, hora_fin: t.hora_fin,
                                      duracion_horas: Number(hours.toFixed(2)), empleado_id: t.empleado_id || null,
                                      empleado_nombre: t.empleado_nombre || null, reserva_menor: t.reserva_menor === true };
                              }),
                          })),
                      };
                  },

                  queueAutoSave() {
                      const session = this.draftSession;
                      if (this.isLoadingDraft || !session || session.blocked || session.closed || this.isGenerating) return;
                      if (!session.dirty(this.draftContent())) return;
                      clearTimeout(this.autoSaveTimeout);
                      this.autoSaveState = 'Cambios pendientes de guardar';
                      this.autoSaveTimeout = setTimeout(() => this.triggerAutoSave(), 1500);
                  },

                  async triggerAutoSave() {
                      const session = this.draftSession;
                      if (this.isLoadingDraft || !session || session.closed || this.isGenerating) return;
                      clearTimeout(this.autoSaveTimeout);
                      try { await session.save(this.draftContent()); } catch (error) { /* Status explains recovery. */ }
                  },

                  async cargarBorrador(week) {
                      if (this.draftSession?.week === week) return;
                      const previous = this.draftSession;
                      const previousContent = previous ? this.draftContent() : null;
                      const sequence = ++this.draftLoadSequence;
                      this.isLoadingDraft = true;
                      clearTimeout(this.autoSaveTimeout);
                      this.autoSaveState = 'Cargando borrador…';
                      try {
                          if (previous && previous.dirty(previousContent)) await previous.save(previousContent);
                          const draft = await ScheduleDraftSession.open(week);
                          if (sequence !== this.draftLoadSequence) return;
                          this.actualizarFechas(week);
                          this.empleados = draft.empleados_snapshot;
                          this.empleadosAjuste = null;
                          this.ajustesManuales = {};
                          this.ajustesBloqueados = {};
                          for (const day of this.dias) {
                              this.turnos[day] = this.extraerTurnosDeSemana(draft, day);
                              this.notas[day] = (Array.isArray(draft.plantilla_diseno)
                                  ? draft.plantilla_diseno.find(d => d.dia?.toLowerCase() === day)?.notas
                                  : draft.plantilla_diseno[day]?.notas) || '';
                          }
                          const session = new ScheduleDraftSession(draft, (state, error) => {
                              if (this.draftSession?.id !== session.id) return;
                              this.draftError = error ? state : '';
                              this.autoSaveState = !error && session.dirty(this.draftContent())
                                  ? 'Cambios pendientes de guardar' : state;
                          });
                          this.draftSession = session;
                          session.markLoaded(this.draftContent());
                          this.draftError = '';
                          this.autoSaveState = 'Borrador recuperado · pendiente de publicar';
                          await this.$nextTick();
                      } catch (error) {
                          this.draftError = error.message;
                          this.autoSaveState = error.message;
                          if (previous) this.semana = previous.week;
                      } finally {
                          if (sequence === this.draftLoadSequence) this.isLoadingDraft = false;
                      }
                  },

                  descargarCambios() {
                      const url = URL.createObjectURL(new Blob([JSON.stringify(this.draftContent(), null, 2)], { type: 'application/json' }));
                      const link = document.createElement('a');
                      link.href = url; link.download = `borrador-${this.draftSession?.week || this.semana}.json`;
                      link.click(); URL.revokeObjectURL(url);
                  },

                  async verBorrador() {
                      await this.triggerAutoSave();
                      if (!this.draftError && this.draftSession && !this.draftSession.dirty(this.draftContent())) {
                          window.location.href = `/admin/horarios/resultado/${this.draftSession.id}`;
                      }
                  },

                  normalizarHoraPlantilla(value) {
                      const text = String(value ?? '').trim();
                      let parts = text.match(/^(\d{1,2})[:.,](\d{2})$/);
                      if (!parts && /^\d{1,4}$/.test(text)) {
                          const digits = text.length <= 2 ? text.padStart(2, '0') + '00' : text.padStart(4, '0');
                          parts = [text, digits.slice(0, 2), digits.slice(2)];
                      }
                      if (!parts || Number(parts[1]) > 23 || Number(parts[2]) > 59) return text;
                      return parts[1].padStart(2, '0') + ':' + parts[2];
                  },

                  abrirAutoPlantilla() {
                      let saved;
                      try { saved = JSON.parse(localStorage.getItem('mmqp_auto_template_horario_v1')); } catch (_) { /* Usar propuesta inicial. */ }
                      for (const dia of this.dias) {
                          const previous = saved?.[dia] || this.autoTemplateHorario[dia];
                          this.autoTemplateHorario[dia] = previous?.franjas?.length === 2 ? previous : {
                              abierto: true,
                              franjas: [
                                  {activa: true, inicio: '12:00', fin: '16:30'},
                                  {activa: true, inicio: '19:00', fin: '00:30'},
                              ],
                          };
                          this.autoTemplateHorario[dia].franjas.forEach(franja => { franja.activa = true; });
                      }
                      this.autoTemplateError = '';
                      this.$refs.autoTemplateDialog.showModal();
                  },

                  async generarPlantillaIA() {
                      if (this.isGeneratingTemplate || this.isLoadingDraft || this.isGenerating) return;
                      const templateSession = this.draftSession;
                      const templateWeek = this.semana;
                      const horario = Object.fromEntries(this.dias.map(dia => [dia,
                          this.autoTemplateHorario[dia]?.abierto
                              ? this.autoTemplateHorario[dia].franjas.filter(f => f.activa).map(({inicio, fin}) => ({
                                  inicio: this.normalizarHoraPlantilla(inicio), fin: this.normalizarHoraPlantilla(fin),
                              })) : []]));
                      this.autoTemplateError = '';
                      this.isGeneratingTemplate = true;
                      this.$refs.autoTemplateDialog.close();
                      this.$refs.autoTemplateLoadingDialog.showModal();
                      try {
                              const response = await fetch('/api/generar-plantilla-ia', {
                                  method: 'POST',
                                  headers: {'Content-Type': 'application/json', Accept: 'application/json',
                                      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                                  body: JSON.stringify({semana: templateWeek, horario}),
                              });
                              const data = await response.json();
                              if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo generar la plantilla.');
                              this.isGeneratingTemplate = false;
                              this.$refs.autoTemplateLoadingDialog.close();
                              if (this.draftSession?.id !== templateSession?.id || this.semana !== templateWeek) return;
                              this.autoTemplateAvisos = data.avisos || [];
                              this.autoTemplateHuecos = data.huecos || [];
                              this.autoTemplateResumen = data.resumen || [];
                              this.autoTemplateModelo = data.modelo_historico || null;
                              this.autoTemplateApplied = Object.values(data.plantilla || {}).some(turnos => turnos.length);
                              try { localStorage.setItem('mmqp_auto_template_horario_v1', JSON.stringify(this.autoTemplateHorario)); } catch (_) { /* El cálculo sigue disponible sin almacenamiento local. */ }
                              if (!this.autoTemplateApplied) {
                                  this.$refs.autoTemplateSuccessDialog.showModal();
                                  return;
                              }
                              if (data.success && data.plantilla) {
                                  // Limpiar estado
                                  for (let d of this.dias) {
                                      this.turnos[d] = [];
                                  }
                                  
                                  // Los menores llegan reservados; Generar Horario asigna los demás huecos.
                                  Object.keys(data.plantilla).forEach(diaStr => {
                                      if (this.dias.includes(diaStr)) {
                                          let reqs = data.plantilla[diaStr];
                                          
                                          // Agrupar por rol para calcular puestos sin solapamiento
                                          let byRole = {};
                                          reqs.forEach(t => {
                                              let r = t.rol || 'encargado';
                                              if(!byRole[r]) byRole[r] = [];
                                              byRole[r].push(t);
                                          });
                                          
                                          Object.keys(byRole).forEach(rol => {
                                              let roleTurnos = byRole[rol];
                                              
                                              // Ordenar por hora de inicio
                                              roleTurnos.sort((a,b) => this.timeToMinutes(a.hora_inicio || '09:00') - this.timeToMinutes(b.hora_inicio || '09:00'));
                                              
                                              let endTimesByPuesto = {};
                                              let empleadosByPuesto = {};
                                              
                                              roleTurnos.forEach(t => {
                                                  let startMin = this.timeToMinutes(t.hora_inicio || '09:00');
                                                  let endMin = this.timeToMinutes(t.hora_fin || '17:00');
                                                  if (endMin <= startMin) endMin += 24 * 60; // cruce de medianoche
                                                  
                                                  const empleadoId = t.empleado_id ?? null;
                                                  let assignedPuesto = 1;
                                                  // Buscar el primer puesto libre con al menos 60 min de descanso
                                                  // para evitar "horarios partidos" continuos (ej. 16:00-16:00)
                                                  while (Object.hasOwn(endTimesByPuesto, assignedPuesto) && (
                                                      startMin < endTimesByPuesto[assignedPuesto] + 60
                                                      || empleadosByPuesto[assignedPuesto] !== empleadoId
                                                  )) {
                                                      assignedPuesto++;
                                                  }
                                                  
                                                  endTimesByPuesto[assignedPuesto] = endMin;
                                                  empleadosByPuesto[assignedPuesto] = empleadoId;
                                                  
                                                  this.turnos[diaStr].push({
                                                      id: 'ai-' + Math.random().toString(36).substring(2, 9),
                                                      rol: rol,
                                                      puesto_num: assignedPuesto,
                                                      hora_inicio: t.hora_inicio || '09:00',
                                                      hora_fin: t.hora_fin || '17:00',
                                                      empleado_id: empleadoId,
                                                      reserva_menor: t.reserva_menor === true
                                                  });
                                              });
                                          });
                                      }
                                  });
                                  
                                  // Forzar reactividad
                                  this.dias.forEach(d => {
                                      this.turnos[d] = [...this.turnos[d]];
                                  });
                                  
                                  this.$refs.autoTemplateSuccessDialog.showModal();
                              }
                      } catch (e) {
                              this.isGeneratingTemplate = false;
                              this.$refs.autoTemplateLoadingDialog.close();
                              this.autoTemplateError = e.message || 'Error de conexión. Vuelve a intentarlo.';
                              this.$refs.autoTemplateDialog.showModal();
                      }
                  },

                  guardarAjustesIA() {
                      this.showSettingsModal = false;
                  },

                  async cargarEventosLocales() {
                      if (!this.semana) {
                          alert('Por favor, selecciona una semana primero.');
                          return;
                      }
                      
                      this.isCargandoEventos = true;
                      this.eventosLocalesHTML = '';
                      this.showEventosModal = true;
                      
                      try {
                          const isoLunes = this.fechasISO['lunes'];
                          const isoDomingo = this.fechasISO['domingo'];
                          
                          const response = await fetch('/api/eventos-locales', {
                              method: 'POST',
                              headers: {
                                  'Content-Type': 'application/json',
                                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                              },
                              body: JSON.stringify({
                                  localidad: this.direccionPostal,
                                  fecha_inicio: isoLunes,
                                  fecha_fin: isoDomingo
                              })
                          });
                          
                          const result = await response.json();
                          if (result.success) {
                              this.eventosLocalesHTML = result.html;
                          } else {
                              this.eventosLocalesHTML = '<li>Error: ' + (result.message || 'Desconocido') + '</li>';
                          }
                      } catch (error) {
                          console.error('Error al cargar eventos locales:', error);
                          this.eventosLocalesHTML = '<li>Error de conexión.</li>';
                      } finally {
                          this.isCargandoEventos = false;
                      }
                  },

                  init() {
                      for (let m = 540; m < 1620; m += 30) {
                          this.bloques.push({
                              minuto: m,
                              label: (m % 60 === 0) ? this.minToTime(m) : ''
                          });
                      }
                      
                      const now = new Date();
                      const year = now.getFullYear();
                      const d = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
                      const dayNum = d.getUTCDay() || 7;
                      d.setUTCDate(d.getUTCDate() + 4 - dayNum);
                      const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
                      const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1)/7);
                      
                      this.semana = new URLSearchParams(window.location.search).get('semana') || `${d.getUTCFullYear()}-W${weekNo.toString().padStart(2, '0')}`;
                      
                      this.$watch('semana', value => this.cargarBorrador(value));
                      this.$watch('turnos', () => this.queueAutoSave());
                      this.$watch('notas', () => this.queueAutoSave());
                      window.addEventListener('beforeunload', event => {
                          if (this.draftSession?.dirty(this.draftContent())) {
                              event.preventDefault(); event.returnValue = '';
                          }
                      });

                      this.gridHeightReactive = window.innerHeight; 
                      this.updateColWidth();
                      window.addEventListener('resize', () => { 
                          this.gridHeightReactive = window.innerHeight; 
                          this.updateColWidth();
                      }); 
                      this.loadHorariosSemanales();
                      
                      this.$nextTick(() => {
                          if (this.$refs.gridInner) {
                              this.gridHeightReactive = this.$refs.gridInner.clientHeight;
                              const ro = new ResizeObserver(entries => {
                                  this.gridHeightReactive = entries[0].target.clientHeight;
                              });
                              ro.observe(this.$refs.gridInner);
                          }
                      });
                      
                      this.actualizarFechas(this.semana);
                      this.cargarBorrador(this.semana);
                  },
                  
                  getRenderTurnos(dia) {
                      if (this.isPreviewing && dia === this.diaSeleccionado && this.previewTurnos) {
                          return this.previewTurnos;
                      }
                      return this.turnos[dia] || [];
                  },
                  
                  loadHorariosSemanales() {
                      fetch('/api/horarios-semanales')
                          .then(res => res.json())
                          .then(data => {
                              this.horariosPasados = data;
                          })
                          .catch(err => console.error("Error loading horarios semanales", err));
                  },
                  
                  extraerTurnosDeSemana(semana, diaNombre) {
                      if (!semana.plantilla_diseno) return [];
                      
                      let diaObj = null;
                      if (Array.isArray(semana.plantilla_diseno)) {
                          diaObj = semana.plantilla_diseno.find(d => d.dia && d.dia.toLowerCase() === diaNombre.toLowerCase());
                      } else {
                          diaObj = semana.plantilla_diseno[diaNombre]; // Formato antiguo
                      }
                      
                      if (!diaObj) return [];
                      
                      let turnosRaw = diaObj.turnos_requeridos || diaObj || [];
                      
                      let extracted = turnosRaw.map((t, idx) => ({
                          id: t._idLocal || t.turno_id || t.id || ('imported-' + idx),
                          turno_id: t.turno_id || t._idLocal || t.id || ('imported-' + idx),
                          rol: (t.rol_necesario || t.rol || '').toLowerCase(),
                          hora_inicio: t.hora_inicio,
                          hora_fin: t.hora_fin,
                          empleado_id: t.empleado_id || null,
                          empleado_nombre: t.empleado_nombre || null,
                          reserva_menor: t.reserva_menor === true,
                          _puestoOriginal: t.puesto_num,
                          _turno_id: t.turno_id
                      }));
                      
                      extracted.forEach(t => {
                          const assignment = (semana.resultados || []).find(r => r.turno_id === t._turno_id);
                          if (assignment) {
                              t.empleado_id = assignment.empleado_id;
                              t.empleado_nombre = assignment.nombre || assignment.empleado_nombre;
                              t.hora_inicio = assignment.hora_inicio || t.hora_inicio;
                              t.hora_fin = assignment.hora_fin || t.hora_fin;
                          }
                      });
                      const porRol = {};
                      extracted.forEach(t => {
                          if(!porRol[t.rol]) porRol[t.rol] = [];
                          porRol[t.rol].push(t);
                      });
                      
                      for (const rol in porRol) {
                          const turnos = porRol[rol];
                          turnos.sort((a,b) => this.timeToMinutes(a.hora_inicio) - this.timeToMinutes(b.hora_inicio));
                          
                          const resultados = semana.resultados || [];
                          const employeeToPuesto = {};
                          const endTimesByPuesto = {};
                          
                          turnos.forEach((t) => {
                              let startMin = this.timeToMinutes(t.hora_inicio || '09:00');
                              let endMin = this.timeToMinutes(t.hora_fin || '17:00');
                              if (endMin <= startMin) endMin += 24 * 60;
                              
                              if (t._puestoOriginal) {
                                  t.puesto_num = t._puestoOriginal;
                                  endTimesByPuesto[t.puesto_num] = Math.max(endTimesByPuesto[t.puesto_num] || 0, endMin);
                              } else {
                                  const res = resultados.find(r => r.turno_id === t._turno_id);
                                  if (res && res.empleado_id) {
                                      if (!employeeToPuesto[res.empleado_id]) {
                                          let p = 1;
                                          while(endTimesByPuesto[p]) p++;
                                          employeeToPuesto[res.empleado_id] = p;
                                      }
                                      t.puesto_num = employeeToPuesto[res.empleado_id];
                                      t.empleado_id = res.empleado_id;
                                      endTimesByPuesto[t.puesto_num] = Math.max(endTimesByPuesto[t.puesto_num] || 0, endMin);
                                  } else {
                                      let p = 1;
                                      // Buscar hueco con 60 mins de margen
                                      while(endTimesByPuesto[p] && startMin < endTimesByPuesto[p] + 60) {
                                          p++;
                                      }
                                      t.puesto_num = p;
                                      endTimesByPuesto[p] = endMin;
                                  }
                              }
                          });
                      }
                      
                      extracted.forEach(t => {
                          delete t._puestoOriginal;
                          delete t._turno_id;
                      });
                      
                      return extracted;
                  },
                  
                  formatearFechaSemana(inicio, fin) {
                      if (!inicio || !fin) return "Semana (Manual)";
                      
                      // Si la fecha ya viene en formato "3 ago" o similar, la usamos directamente
                      if (!inicio.includes('-')) {
                          return `${inicio} al ${fin}`;
                      }
                      
                      const [y1,m1,d1] = inicio.split('-');
                      const [y2,m2,d2] = fin.split('-');
                      return `${d1}/${m1} al ${d2}/${m2}/${y2}`;
                  },
                  
                  previsualizarPlantilla(turnosArray, nombre) {
                      this.previewTurnos = JSON.parse(JSON.stringify(turnosArray));
                      this.previewPlantillaName = nombre;
                      this.isPreviewing = true;
                      this.isLoadModalOpen = false; // Ocultamos modal para ver el main grid
                  },
                  
                  applyPreview() {
                      if (this.previewTurnos) {
                          this.pendingLoadTurnos = JSON.parse(JSON.stringify(this.previewTurnos));
                          this.isConfirmLoadOpen = true;
                      }
                  },
                  
                  cancelPreview() {
                      this.isPreviewing = false;
                      this.previewTurnos = null;
                      this.isLoadModalOpen = true; // Reabrir el gestor al cancelar
                  },
  
                  actualizarFechas(semanaStr) {
                      if (!semanaStr) return;
                      const parts = semanaStr.split('-W');
                      const y = parseInt(parts[0], 10);
                      const w = parseInt(parts[1], 10);
                      
                      const simple = new Date(y, 0, 1 + (w - 1) * 7);
                      const dow = simple.getDay();
                      const ISOweekStart = simple;
                      if (dow <= 4) {
                          ISOweekStart.setDate(simple.getDate() - simple.getDay() + 1);
                      } else {
                          ISOweekStart.setDate(simple.getDate() + 8 - simple.getDay());
                      }
                      
                      const formatter = new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'short' });
                      const obj = {};
                      const isoObj = {};
                      
                      for (let i = 0; i < 7; i++) {
                          const currentDate = new Date(ISOweekStart);
                          currentDate.setDate(ISOweekStart.getDate() + i);
                          obj[this.dias[i]] = formatter.format(currentDate);
                          // ISO format: YYYY-MM-DD
                          const yyyy = currentDate.getFullYear();
                          const mm = String(currentDate.getMonth() + 1).padStart(2, '0');
                          const dd = String(currentDate.getDate()).padStart(2, '0');
                          isoObj[this.dias[i]] = `${yyyy}-${mm}-${dd}`;
                      }
                      this.fechasDias = obj;
                      this.fechasISO = isoObj;
                  },
  
                  openModal(dia) {
                      this.diaSeleccionado = dia;
                      this.nuevoTurno = { hora_inicio: '', hora_fin: '', rol: '', empleado_id: null };
                      this.isModalOpen = true;
                  },
  
                  closeModal() {
                      this.isModalOpen = false;
                      this.diaSeleccionado = '';
                  },
                  
                  cambiarDia(delta) {
                      let idx = this.dias.indexOf(this.diaSeleccionado);
                      if (idx !== -1) {
                          let newIdx = (idx + delta + 7) % 7;
                          this.diaSeleccionado = this.dias[newIdx];
                      }
                  },
  
                  minToTime(m) {
                      let h = Math.floor(m / 60);
                      let min = m % 60;
                      if (h >= 24) h -= 24;
                      return h.toString().padStart(2, '0') + ':' + min.toString().padStart(2, '0');
                  },
  
                  startDrag(rol, minuto) {
                      this.isDragging = true;
                      this.dragRole = rol;
                      this.dragStartMin = minuto;
                      this.dragCurrentMin = minuto;
                  },
                  
                  onDrag(rol, minuto) {
                      if (this.isDragging && this.dragRole === rol) {
                          this.dragCurrentMin = minuto;
                      }
                  },
  
                  endDrag() {
                      if (!this.isDragging) return;
                      this.isDragging = false;
                      
                      let start = Math.min(this.dragStartMin, this.dragCurrentMin);
                      let end = Math.max(this.dragStartMin, this.dragCurrentMin) + 30; // +30 mins por bloque

                       // Asegurar que el turno creado sea de al menos 1h y media (90 minutos)
                       if ((end - start) < 90) {
                           return;
                       }
                      
                      if (!this.turnos[this.diaSeleccionado]) {
                          this.turnos[this.diaSeleccionado] = [];
                      }
                      
                      // Encontrar un puesto_num que NO esté usado por NINGÚN turno hoy (para no unir automáticamente)
                      let puestoNum = 1;
                      const turnosMismoRol = this.turnos[this.diaSeleccionado].filter(t => t.rol === this.dragRole);
                      
                      while (true) {
                          const usado = turnosMismoRol.some(t => (t.puesto_num || 1) === puestoNum);
                          if (!usado) break;
                          puestoNum++;
                      }
  
                      const turnoObj = {
                          id: Math.random().toString(36).substring(2, 9),
                          rol: this.dragRole,
                          puesto_num: puestoNum,
                          hora_inicio: this.minToTime(start),
                          hora_fin: this.minToTime(end),
                          empleado_id: null
                      };
                      
                      this.turnos[this.diaSeleccionado].push(turnoObj);
                      
                      // Forzar reactividad
                      this.turnos[this.diaSeleccionado] = [...this.turnos[this.diaSeleccionado]];
                      
                      this.dragRole = null;
                      this.dragStartMin = null;
                      this.dragCurrentMin = null;
                  },
                  
                  isBlockSelected(rol, minuto) {
                      if (!this.isDragging || this.dragRole !== rol) return false;
                      let start = Math.min(this.dragStartMin, this.dragCurrentMin);
                      let end = Math.max(this.dragStartMin, this.dragCurrentMin);
                      return minuto >= start && minuto <= end;
                  },
  
                  getTurnosPorRol(dia, rol) { 
                      const src = this.getRenderTurnos(dia);
                      return src.filter(t => t.rol === rol); 
                  },

                  getPreviewLayout(dia, rol) {
                      // Las columnas de la vista semanal dependen de los solapamientos,
                      // no del número de puesto (que conserva sus vínculos originales).
                      const entries = this.getTurnosPorRol(dia, rol).map(turno => {
                          const start = this.timeToMinutes(turno.hora_inicio);
                          const end = this.timeToMinutes(turno.hora_fin);
                          return { turno, start, end: end > start ? end : start + 60 };
                      }).sort((a, b) => a.start - b.start || a.end - b.end ||
                          (a.turno.puesto_num || 1) - (b.turno.puesto_num || 1));
                      let group = [];
                      let columnEnds = [];
                      let groupEnd = -Infinity;
                      const finishGroup = () => {
                          group.forEach(entry => { entry.columns = columnEnds.length; });
                      };

                      for (const entry of entries) {
                          if (entry.start >= groupEnd) {
                              finishGroup();
                              group = [];
                              columnEnds = [];
                          }
                          let column = columnEnds.findIndex(end => end <= entry.start);
                          if (column === -1) column = columnEnds.length;
                          columnEnds[column] = entry.end;
                          entry.column = column;
                          group.push(entry);
                          groupEnd = Math.max(groupEnd, entry.end);
                      }
                      finishGroup();
                      return entries;
                  },

                  getPreviewTurnoStyle(turno, dia) {
                      const entry = this.getPreviewLayout(dia, turno.rol)
                          .find(entry => entry.turno.id === turno.id);
                      const columns = entry?.columns || 1;
                      const column = entry?.column || 0;
                      const start = entry?.start ?? this.timeToMinutes(turno.hora_inicio);
                      const duration = entry ? entry.end - entry.start : 60;
                      // La escala compacta solo afecta a esta vista, no al editor del día.
                      return `top: calc(var(--preview-hour-height) * ${(start - 9 * 60) / 60} + 1px);` +
                          ` height: calc(var(--preview-hour-height) * ${duration / 60} - 2px);` +
                          ` width: calc(100% / ${columns} - 6px); left: calc(100% / ${columns} * ${column} + 3px);`;
                  },

                  getPreviewDayWidths() {
                      return this.dias.map(dia => {
                          const entries = this.getPreviewLayout(dia, this.previewRol);
                          const columns = Math.max(1, ...entries.map(entry => entry.columns));
                          // Los turnos cortos necesitan las dos horas en una sola línea.
                          const laneWidth = entries.some(entry => entry.end - entry.start < 90) ? 128 : 64;
                          return Math.max(112, columns * laneWidth);
                      });
                  },

                  getPreviewGridMinWidth() {
                      return this.getPreviewDayWidths().reduce((total, width) => total + width, 0);
                  },

                  getPreviewGridStyle() {
                      const widths = this.getPreviewDayWidths();
                      return `min-width: ${widths.reduce((total, width) => total + width, 0)}px;` +
                          ` grid-template-columns: ${widths.map(width => `minmax(${width}px, ${width}fr)`).join(' ')};`;
                  },
  
                  getColumnForTurno(turno, dia) {
                      return (turno.puesto_num || 1) - 1;
                  },
  
                  getMaxColumnsForRol(dia, rol) { 
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return 1; 
                      const turnosRol = src.filter(t => t.rol === rol);
                      if (turnosRol.length === 0) return 1;
                      
                      let max = 1;
                      for (const t of turnosRol) {
                          const p = t.puesto_num || 1;
                          if (p > max) max = p;
                      }
                      return max;
                  },

                  getPuestosUtilizadosCount(dia, rol) {
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return 0;
                      const turnosRol = src.filter(t => t.rol === rol);
                      if (turnosRol.length === 0) return 0;
                      
                      let max = 1;
                      for (const t of turnosRol) {
                          const p = t.puesto_num || 1;
                          if (p > max) max = p;
                      }
                      return max;
                  },
  
                  // --- Funciones de AsignaciÃ³n Manual ---
                  getEmpleadoNombre(id) {
                      if (!id) return '';
                      const emp = this.empleados.find(e => e.id === id);
                      return emp ? `${emp.nombre} ${emp.apellidos || ''}`.trim() : 'Desconocido';
                  },
                  
                  isEmpleadoAusente(empleadoId, dia) {
                      const isoDate = this.fechasISO[dia];
                      if (!isoDate) return false;
                      const employee = this.empleados.find(e => String(e.id) === String(empleadoId));
                      if (employee?.en_baja || (employee?.fechas_libres_solicitadas || []).includes(isoDate)) return true;
                      if (this.festivos.includes(isoDate) && employee?.disponibilidad_festivos === 'no_disponible') return true;
                      return this.ausencias.some(a => String(a.user_id) === String(empleadoId) && a.estado === 'aprobada' && a.fecha &&
                          a.fecha.substring(0, 10) <= isoDate && (a.fecha_fin || a.fecha).substring(0, 10) >= isoDate);
                  },
                  
                  getEmpleadosDisponibles(rol) {
                      return this.empleados.filter(e => e.rol === rol && this.esEmpleadoDisponible(e));
                  },

                  esEmpleadoDisponible(empleado) {
                      return ![false, 0, '0'].includes(empleado.activo)
                          && ![true, 1, '1'].includes(empleado.en_baja);
                  },
                  
                  getEmpleadosTotalCount(dia, rol) {
                      return this.getEmpleadosDisponibles(rol).filter(e => !this.isEmpleadoAusente(e.id, dia)).length;
                  },
                  
                  calcularMinutos(inicio, fin) {
                      if (!inicio || !fin) return 0;
                      const i = this.timeToMinutes(inicio);
                      let f = this.timeToMinutes(fin);
                      if (f < i) f += 24 * 60;
                      return f - i;
                  },

                  getHorasMes(empleadoId) {
                      if (!this.semana) return 0;
                      const isoMonday = this.fechasISO && this.fechasISO['lunes'];
                      if (!isoMonday) return 0;
                      const currentMonth = isoMonday.substring(0, 7); // "YYYY-MM"
                      
                      let totalMinutes = 0;
                      
                      // Horas de semanas guardadas del mismo mes (excluyendo la actual)
                      this.horariosPasados.forEach(hs => {
                          if (hs.estado === 'borrador' || hs.vigente === false) return;
                          const hsSemana = hs.semana_id || hs.semana;
                          if (hsSemana === this.semana) return; 
                          
                          if (hs.fecha_inicio && hs.fecha_inicio.substring(0, 7) === currentMonth) {
                              const plantilla = hs.plantilla_diseno;
                              if (Array.isArray(plantilla)) {
                                  plantilla.forEach(dia => {
                                      const turnos = dia.turnos_requeridos || dia.turnos || [];
                                      turnos.forEach(t => {
                                          if (t.empleado_id == empleadoId) {
                                              totalMinutes += this.calcularMinutos(t.hora_inicio, t.hora_fin);
                                          }
                                      });
                                  });
                              } else if (typeof plantilla === 'object' && plantilla !== null) {
                                  Object.values(plantilla).forEach(dia => {
                                      const turnos = dia.turnos_requeridos || dia || [];
                                      turnos.forEach(t => {
                                          if (t.empleado_id == empleadoId) {
                                              totalMinutes += this.calcularMinutos(t.hora_inicio, t.hora_fin);
                                          }
                                      });
                                  });
                              }
                          }
                      });
                      
                      // Horas de la semana actual (live)
                      this.dias.forEach(d => {
                          if (this.turnos[d]) {
                              this.turnos[d].forEach(t => {
                                  if (t.empleado_id == empleadoId) {
                                      totalMinutes += this.calcularMinutos(t.hora_inicio, t.hora_fin);
                                  }
                              });
                          }
                      });
                      
                      return Math.round((totalMinutes / 60) * 10) / 10;
                  },
                  
                  getEmpleadosPendientesCount(dia, rol) { 
                      const total = this.getEmpleadosTotalCount(dia, rol); 
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return total; 
                       
                      // Contamos los distintos Puestos que se han colocado en el calendario, estǟn asignados o no 
                      const turnosRol = src.filter(t => t.rol === rol);
                      const puestosCreados = new Set(turnosRol.map(t => t.puesto_num || 1));
                      
                      return Math.max(0, total - puestosCreados.size);
                  },
                  
                  isLinked(turno) {
                      if (!this.turnos[this.diaSeleccionado]) return false;
                      return this.turnos[this.diaSeleccionado].some(t => t.id !== turno.id && t.rol === turno.rol && (t.puesto_num || 1) === (turno.puesto_num || 1));
                  },
                  
                  romperEnlace(turno) {
                      let maxPuesto = 0;
                      if (this.turnos[this.diaSeleccionado]) {
                          for (const t of this.turnos[this.diaSeleccionado]) {
                              if (t.rol === turno.rol && (t.puesto_num || 1) > maxPuesto) {
                                  maxPuesto = t.puesto_num || 1;
                              }
                          }
                      }
                      turno.puesto_num = maxPuesto + 1;
                      
                      // Forzar reactividad
                      if (this.diaSeleccionado) {
                          this.turnos[this.diaSeleccionado] = [...this.turnos[this.diaSeleccionado]];
                      }
                  },
                  
                  startLinking(turno) {
                      this.isLinking = true;
                      this.linkingSourceTurnoId = turno.id;
                  },
                  updateLinkingMouse(e, el) {
                      if (!this.isLinking) return;
                      const rect = el.getBoundingClientRect();
                      this.linkMouseX = e.clientX - rect.left + el.scrollLeft;
                      this.linkMouseY = e.clientY - rect.top + el.scrollTop;
                  },
                  finishLinking(turno) {
                      if (!this.isLinking) return;
                      if (this.linkingSourceTurnoId !== turno.id) {
                          const source = this.turnos[this.diaSeleccionado].find(t => t.id === this.linkingSourceTurnoId);
                          if (source && source.rol === turno.rol) {
                              turno.puesto_num = source.puesto_num || 1;
                              
                              const empToSync = source.empleado_id || turno.empleado_id;
                              if (empToSync) {
                                  this.turnos[this.diaSeleccionado].forEach(t => {
                                      if (t.rol === turno.rol && (t.puesto_num || 1) === turno.puesto_num) {
                                          t.empleado_id = empToSync;
                                      }
                                  });
                              }
                          }
                      }
                      this.isLinking = false;
                      this.linkingSourceTurnoId = null;
                  },
                  getVisualLinksForRol(dia, rol) { 
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return []; 
                      const areaHeight = this.gridHeightReactive || 1080; 
                      const turnos = src.filter(t => t.rol === rol);
                      const links = [];
                      const byPuesto = {};
                      for (const t of turnos) {
                          const p = t.puesto_num || 1;
                          if (!byPuesto[p]) byPuesto[p] = [];
                          byPuesto[p].push(t);
                      }
                      
                      for (const p in byPuesto) {
                          const grupo = byPuesto[p];
                          if (grupo.length > 1) {
                              grupo.sort((a, b) => this.timeToMinutes(a.hora_inicio) - this.timeToMinutes(b.hora_inicio));
                              for (let i = 0; i < grupo.length - 1; i++) {
                                  const t1 = grupo[i];
                                  const t2 = grupo[i+1];
                                  
                                  const minTime = 9 * 60;
                                  const totalMinutes = 18 * 60;
                                  
                                  const tm1 = this.timeToMinutes(t1.hora_fin);
                                  const tm2 = this.timeToMinutes(t2.hora_inicio);
                                  
                                  const y1 = ((tm1 - minTime) / totalMinutes) * areaHeight;
                                  const y2 = ((tm2 - minTime) / totalMinutes) * areaHeight;
                                  
                                  const metrics = this.getCardMetrics(dia, rol);
                                  const x1 = 8 + this.getColumnForTurno(t1, dia) * metrics.width + (metrics.width / 2);
                                  const x2 = 8 + this.getColumnForTurno(t2, dia) * metrics.width + (metrics.width / 2);
                                  
                                  links.push({
                                      id: t1.id + '-' + t2.id,
                                      path: `M ${x1} ${y1} C ${x1} ${(y1+y2)/2}, ${x2} ${(y1+y2)/2}, ${x2} ${y2}`,
                                      puesto: p
                                  });
                              }
                          }
                      }
                      return links;
                  },
                  getVisualLinksHtml(dia, rol) {
                      const links = this.getVisualLinksForRol(dia, rol);
                      let html = '';
                      
                      for (const link of links) {
                          html += `<path d="${link.path}" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="8 8" fill="none" class="text-slate-400 dark:text-slate-500 drop-shadow-sm pointer-events-none opacity-80" />`;
                      }
                      
                      if (this.isLinking && this.getTurnoById(this.linkingSourceTurnoId)?.rol === rol) {
                          const coords = this.getLinkHandleCoords(this.linkingSourceTurnoId);
                          html += `<path d="M ${coords.x} ${coords.y} C ${coords.x} ${(coords.y + this.linkMouseY)/2}, ${this.linkMouseX} ${(coords.y + this.linkMouseY)/2}, ${this.linkMouseX} ${this.linkMouseY}" stroke="currentColor" stroke-width="4" stroke-dasharray="8 6" fill="none" class="text-indigo-400 dark:text-indigo-500 animate-pulse drop-shadow-sm pointer-events-none" stroke-linecap="round" />`;
                      }
                      
                      return html;
                  },
                  getLinkHandleCoords(turnoId) {
                      if (!this.turnos[this.diaSeleccionado]) return { x: 0, y: 0 };
                      const areaHeight = this.gridHeightReactive || 1080;
                      const t = this.turnos[this.diaSeleccionado].find(x => x.id === turnoId);
                      if (!t) return { x: 0, y: 0 };
                      const minTime = 9 * 60;
                      const totalMinutes = 18 * 60;
                      const yPercent = ((this.timeToMinutes(t.hora_fin) - minTime) / totalMinutes);
                      const metrics = this.getCardMetrics(this.diaSeleccionado, t.rol);
                      return {
                          x: 8 + this.getColumnForTurno(t, this.diaSeleccionado) * metrics.width + (metrics.width / 2),
                          y: yPercent * areaHeight
                      };
                  },
                  getTurnoById(id) {
                      if (!this.turnos[this.diaSeleccionado]) return null;
                      return this.turnos[this.diaSeleccionado].find(t => t.id === id);
                  },
                  
                  abrirAsignacion(turno) {
                      this.assignTurnoId = turno.id;
                      this.assignTurnoRol = turno.rol;
                      this.assignTurnoPuesto = turno.puesto_num || 1;
                      this.assignDia = this.diaSeleccionado;
                      this.isAssignModalOpen = true;
                  },
                  
                  confirmarAsignacion(empleadoId) {
                      if (this.assignTurnoId && this.assignDia) {
                          const targetTurno = this.turnos[this.assignDia].find(x => x.id === this.assignTurnoId);
                          
                          if (targetTurno) {
                              const targetRol = targetTurno.rol;
                              const targetPuesto = targetTurno.puesto_num || 1;
                              
                              // Asignar masivamente a todos los del mismo rol y puesto en el dÃ­a
                              this.turnos[this.assignDia].forEach(t => {
                                  if (t.rol === targetRol && (t.puesto_num || 1) === targetPuesto) {
                                      t.empleado_id = empleadoId;
                                  }
                              });
                              
                              // Forzar reactividad de Alpine
                              this.turnos[this.assignDia] = [...this.turnos[this.assignDia]];
                          }
                      }
                      this.isAssignModalOpen = false;
                      this.assignTurnoId = null;
                  },
                  
                  cerrarAsignacion() {
                      this.isAssignModalOpen = false;
                      this.assignTurnoId = null;
                  },
  
                  getOverlapOffsetVertical(turno, dia) { 
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return 0; 
                      const idx = src.indexOf(turno); 
                      let overlaps = 0; 
                      for (let i = 0; i < idx; i++) { 
                          const t2 = src[i];
                          const s1 = this.timeToMinutes(turno.hora_inicio);
                          const e1 = this.timeToMinutes(turno.hora_fin);
                          const s2 = this.timeToMinutes(t2.hora_inicio);
                          const e2 = this.timeToMinutes(t2.hora_fin);
                          if (Math.max(s1, s2) < Math.min(e1, e2)) {
                              overlaps++;
                          }
                      }
                      return overlaps;
                  },
                  
                  getOverlapOffsetHorizontal(turno, dia) { 
                      const src = this.getRenderTurnos(dia);
                      if (src.length === 0) return 0; 
                      const idx = src.indexOf(turno); 
                      let overlaps = 0; 
                      for (let i = 0; i < idx; i++) { 
                          const t2 = src[i];
                          if (t2.rol === turno.rol) {
                              const s1 = this.timeToMinutes(turno.hora_inicio);
                              const e1 = this.timeToMinutes(turno.hora_fin);
                              const s2 = this.timeToMinutes(t2.hora_inicio);
                              const e2 = this.timeToMinutes(t2.hora_fin);
                              if (Math.max(s1, s2) < Math.min(e1, e2)) {
                                  overlaps++;
                              }
                          }
                      }
                      return Math.min(overlaps, 3); // LÃ­mite de desplazamiento visual
                  },
  
                  getHorizontalStyle(inicio, fin) {
                      const startMin = this.timeToMinutes(inicio);
                      let endMin = this.timeToMinutes(fin);
                      
                      // Ajuste de solapamiento si por algÃºn motivo acaba a la misma hora o error
                      if (endMin <= startMin) endMin = startMin + 30; 
                      
                      const minTime = 540; // 09:00
                      const totalMins = 1080; // 18 horas
                      
                      const leftPercent = ((startMin - minTime) / totalMins) * 100;
                      const widthPercent = ((endMin - startMin) / totalMins) * 100;
                      
                      return `left: ${leftPercent}%; width: ${widthPercent}%;`;
                  },
  
                  eliminarTurno(dia, id) {
                      this.turnos[dia] = [...this.turnos[dia].filter(t => t.id !== id)];
                  },
  
                  // --- Funciones de Plantillas ---
                  loadPlantillas() {
                      fetch('/api/plantillas')
                          .then(res => res.json())
                          .then(data => {
                              this.plantillasGuardadas = data;
                              this.isLoadModalOpen = true;
                          });
                  },
  
                  savePlantilla() {
                      if (!this.plantillaName.trim() || !this.diaSeleccionado) return;
                      
                      const payload = {
                          nombre: this.plantillaName,
                          turnos: this.turnos[this.diaSeleccionado]
                      };
  
                      fetch('/api/plantillas', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                              'Accept': 'application/json',
                              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                          },
                          body: JSON.stringify(payload)
                      })
                      .then(res => res.json())
                      .then(data => {
                          this.isSaveModalOpen = false;
                          this.plantillaName = '';
                          // Opcional: mostrar un toast o mensaje de Ã©xito
                      })
                      .catch(error => {
                          console.error("Error guardando plantilla:", error);
                          alert("Hubo un error al guardar la plantilla. Verifica que el nombre no estÃ© duplicado.");
                      });
                  },
  
                  async generarHorario(forzarAmpliar = false) {
                      try {
                          console.log("Iniciando generarHorario con forzarAmpliar:", forzarAmpliar);
                          if (!this.semana) {
                              alert("Por favor, selecciona una semana (Semana del A\u00f1o) antes de generar el horario.");
                              return;
                          }
                          
                          if (this.isLoadingDraft || !this.draftSession || this.draftSession.blocked) return;
                          clearTimeout(this.autoSaveTimeout);
                          await this.draftSession.save(this.draftContent());
                          const generationSession = this.draftSession;
                          this.isGenerating = true;
                          this.generationTimeElapsed = 0;
                          if (this.generationTimer) clearInterval(this.generationTimer);
                          this.generationTimer = setInterval(() => {
                              this.generationTimeElapsed++;
                          }, 1000);
                          
                          // Formatear turnos al formato esperado por Python
                          const plantillaDiseno = [];
                          this.dias.forEach(dia => {
                              if (this.turnos[dia].length > 0) {
                                  const diaTurnos = this.turnos[dia].map((t, idx) => {
                                      if (!t.hora_inicio || !t.hora_fin) {
                                          throw new Error("Falta hora de inicio o fin en un turno del " + dia);
                                      }
                                      const hStart = parseFloat(t.hora_inicio.replace(':', '.'));
                                      const hEnd = parseFloat(t.hora_fin.replace(':', '.'));
                                      let duracion = hEnd - hStart;
                                      if (duracion < 0) duracion += 24; // cruzando medianoche
                                      
                                      // Correcci\u00f3n de minutos a decimal (ej 30 min -> 0.5 horas)
                                      let durReal = (this.timeToMinutes(t.hora_fin) - this.timeToMinutes(t.hora_inicio)) / 60;
                                      if (durReal < 0) durReal += 24;
                                      
                                      return {
                                          turno_id: t.turno_id || t.id,
                                          rol_necesario: t.rol,
                                          hora_inicio: t.hora_inicio,
                                          hora_fin: t.hora_fin,
                                          duracion_horas: parseFloat(durReal.toFixed(2)),
                                          empleado_id: t.empleado_id || null, // Permite generación parcial (preasignaciones)
                                          empleado_nombre: t.empleado_nombre || null,
                                          reserva_menor: t.reserva_menor === true,
                                          _idLocal: t.id // Para luego mapearlo
                                      };
                                  });
                                  
                                  plantillaDiseno.push({
                                      dia: dia.charAt(0).toUpperCase() + dia.slice(1),
                                      fecha: this.fechasISO[dia],
                                      notas: this.notas[dia] || "",
                                      turnos_requeridos: diaTurnos
                                  });
                              }
                          });
                          
                          if (plantillaDiseno.length === 0) {
                              alert("No hay turnos dise\u00f1ados para enviar al algoritmo.");
                              this.isGenerating = false;
                              if (this.generationTimer) clearInterval(this.generationTimer);
                              return;
                          }
  
                          const payload = {
                              draft_id: generationSession.id, version: generationSession.version,
                              request_id: crypto.randomUUID(),
                              metadatos: {
                                  semana_id: this.semana,
                                  fecha_inicio: this.fechasISO['lunes'],
                                  fecha_fin: this.fechasISO['domingo']
                              },
                              plantilla_diseno: plantillaDiseno,
                              ampliar_limites: forzarAmpliar,
                              ajustes_manuales: this.ajustesManuales,
                              tiempo_maximo: this.tiempoGeneracion
                          };
  
                          this.debugPayload = JSON.stringify(payload, null, 2);
                          console.log("Enviando payload:", payload);
  
                          fetch('/api/generar-horario', {
                              method: 'POST',
                              headers: {
                                  'Content-Type': 'application/json',
                                  'Accept': 'application/json',
                                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                              },
                              body: JSON.stringify(payload)
                          })
                          .then(res => {
                              if (!res.ok && res.status !== 422) {
                                  return res.text().then(text => {
                                      console.error("Respuesta HTTP fallida:", res.status, text);
                                      let parsed;
                                      try { parsed = JSON.parse(text); } catch(e) { throw new Error("HTTP " + res.status + " - " + text.substring(0, 100)); }
                                      throw new Error(parsed.message || "Error " + res.status);
                                  });
                              }
                              return res.json();
                          })
                          .then(data => {
                              console.log("Respuesta de la API:", data);
                              this.isGenerating = false;
                              if (this.generationTimer) clearInterval(this.generationTimer);
                              
                              if (data.requires_confirmation) {
                                  console.log("Abriendo modal de confirmacion. Data:", data);
                                  this.empleadosAjuste = Array.isArray(data.empleados_disponibles)
                                      ? data.empleados_disponibles : null;
                                  this.confirmData = {
                                      message: data.message || "Se han detectado desajustes importantes entre las horas necesarias en el horario y las horas de contrato de los empleados. Revisa los detalles:",
                                      deficit: data.deficit || {},
                                      exceso: data.exceso || {},
                                      requeridas: data.requeridas || {},
                                      disponibles: data.disponibles || {}
                                  };
                                  if (data.requires_hour_input) {
                                      this.abrirAjusteManual();
                                      this.ajusteRole = data.roles_sin_horas?.[0] || this.roles[0];
                                  } else {
                                      this.showConfirmModal = true;
                                  }
                              } else if (data.success && data.redirect_url && this.draftSession === generationSession) {
                                  generationSession.closed = true;
                                  // Keep generating true so the blur stays while redirecting
                                  this.isGenerating = true;
                                  window.location.href = data.redirect_url;
                              } else {
                                  alert("Error al generar: " + (data.message || "Error desconocido"));
                                  console.error(data.error);
                              }
                          })
                          .catch(error => {
                              this.isGenerating = false;
                              if (this.generationTimer) clearInterval(this.generationTimer);
                              console.error("Error llamando a la IA:", error);
                              alert("Hubo un error al comunicarse con el servidor o el algoritmo: " + error.message);
                          });
                      } catch (e) {
                          this.isGenerating = false;
                          if (this.generationTimer) clearInterval(this.generationTimer);
                          console.error("Error JS en generarHorario:", e);
                          alert("Error JS detectado: " + e.message);
                      }
                  },
  
                  applyPlantilla(turnosArray) { 
                      this.pendingLoadTurnos = JSON.parse(JSON.stringify(turnosArray));
                      this.isConfirmLoadOpen = true;
                  },

                  confirmarCargaPlantilla(conAsignaciones) {
                      if (!this.pendingLoadTurnos) return;
                      
                      let turnosACargar = this.pendingLoadTurnos;
                      
                      if (!conAsignaciones) {
                          turnosACargar.forEach(t => {
                              t.empleado_id = null;
                              t.reserva_menor = false;
                          });
                      }
                      
                      this.turnos[this.diaSeleccionado] = turnosACargar;
                      
                      this.isConfirmLoadOpen = false;
                      this.isLoadModalOpen = false;
                      this.isPreviewing = false;
                      this.previewTurnos = null;
                      this.pendingLoadTurnos = null;
                  },

                  cancelarCargaPlantilla() {
                      this.isConfirmLoadOpen = false;
                      this.pendingLoadTurnos = null;
                  },
  
                  deletePlantilla(id) {
                      if(!confirm("Â¿Seguro que quieres borrar esta plantilla?")) return;
  
                      fetch(`/api/plantillas/${id}`, {
                          method: 'DELETE',
                          headers: {
                              'X-CSRF-TOKEN': 
document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                          }
                      })
                      .then(() => {
                          this.plantillasGuardadas = this.plantillasGuardadas.filter(p => p.id !== id);
                      });
                  },
  
                  timeToMinutes(timeStr) {
                      if (!timeStr) return 0;
                      const parts = timeStr.split(':');
                      let h = parseInt(parts[0], 10);
                      let m = parseInt(parts[1], 10);
                      // Las horas menores de 09:00 las consideramos del dÃ­a siguiente
                      if (h < 9) h += 24;
                      return (h * 60) + m;
                  },
  
                  getTurnoStyle(turno) {
                      const minTime = 9 * 60; // 09:00 en minutos
                      const totalMinutes = 18 * 60; // 18 horas de grid
                      
                      const startMin = this.timeToMinutes(turno.hora_inicio);
                      let endMin = this.timeToMinutes(turno.hora_fin);
                      
                      // Asegurar que visualmente ocupe algo si se equivocan en la hora
                      if (endMin <= startMin) {
                          endMin = startMin + 60; 
                      }
                      
                      const topPercent = ((startMin - minTime) / totalMinutes) * 100;
                      const heightPercent = ((endMin - startMin) / totalMinutes) * 100;
                      
                      return `top: ${topPercent}%; height: ${heightPercent}%;`;
                  },
                  
                  abrirAjusteManual() {
                      this.ajusteRole = this.roles[0];
                      // Solo la jefa o Autocompletar pueden introducir los objetivos semanales.
                      this.isAjusteManualOpen = true;
                  },

                  getEmpleadosAjuste(rol) {
                      // Balance exactly the roster used by the generator, including changes since the draft opened.
                      return (this.empleadosAjuste ?? this.empleados)
                          .filter(e => e.rol === rol && this.esEmpleadoDisponible(e));
                  },

                  getHorasSemanalesActuales(emp) {
                      return Number(this.ajustesManuales[emp.id]) || 0;
                  },

                  sumarHorasSemanales(emp) {
                      let actual = this.getHorasSemanalesActuales(emp);
                      this.ajustesManuales[emp.id] = actual + 0.5;
                      this.recalcularDesajuste(emp.rol);
                  },

                  restarHorasSemanales(emp) {
                      let actual = this.getHorasSemanalesActuales(emp);
                      if (actual >= 0.5) {
                          this.ajustesManuales[emp.id] = actual - 0.5;
                          this.recalcularDesajuste(emp.rol);
                      }
                  },

                  setHorasSemanales(emp, value) {
                      if (String(value).trim() === '') {
                          const next = { ...this.ajustesManuales };
                          delete next[emp.id];
                          this.ajustesManuales = next;
                          this.recalcularDesajuste(emp.rol);
                          return;
                      }
                      let num = parseFloat(value);
                      if (Number.isFinite(num) && num >= 0 && num <= 168) {
                          this.ajustesManuales[emp.id] = num;
                          this.recalcularDesajuste(emp.rol);
                      }
                  },

                  autoBalancearRol(rol) {
                      let req = this.confirmData.requeridas[rol] || 0;
                      const reservas = new Map();
                      for (const turnos of Object.values(this.turnos)) {
                          for (const turno of turnos) {
                              if (!turno.reserva_menor || !turno.empleado_id || turno.rol !== rol) continue;
                              const minutes = hora => { const [h, m] = hora.split(':').map(Number); return h * 60 + m; };
                              let duracion = minutes(turno.hora_fin) - minutes(turno.hora_inicio);
                              if (duracion < 0) duracion += 1440;
                              const id = String(turno.empleado_id);
                              reservas.set(id, (reservas.get(id) || 0) + duracion / 60);
                          }
                      }
                      // Start empty inputs from their own contract. Existing manual values
                      // and locks stay authoritative; auto-template reservations stay fixed.
                      let empleadosRol = this.getEmpleadosAjuste(rol).map(e => {
                          const reservado = reservas.has(String(e.id));
                          const manual = this.ajustesManuales[e.id];
                          const contrato = Number(e.horas_mensuales) / 4;
                          const horas = reservado ? reservas.get(String(e.id))
                              : manual !== undefined && manual !== '' ? this.getHorasSemanalesActuales(e)
                              : this.ajustesBloqueados[e.id] ? 0
                              : Number.isFinite(contrato) && contrato > 0 ? contrato : 0;
                          return {id: e.id, horas, reservado, bloqueado: reservado || this.ajustesBloqueados[e.id]};
                      });
                      if (empleadosRol.length === 0) return;
                      
                      let disp = 0;
                      for(let e of empleadosRol) {
                          disp += e.horas;
                      }
                      
                      let deficit = req - disp;
                      
                      let empleadosNoBloqueados = empleadosRol.filter(e => !e.bloqueado);
                      if (empleadosNoBloqueados.length === 0 && Math.abs(deficit) > 0.01) {
                          alert("Todos los empleados están bloqueados. No se puede autobalancear.");
                          return;
                      }
                      
                      const cambios = Object.fromEntries(empleadosRol.filter(e => e.reservado).map(e => [e.id, e.horas]));
                      if (deficit > 0) {
                          while (deficit > 0.01) {
                              empleadosNoBloqueados.sort((a, b) => a.horas - b.horas);
                              let e = empleadosNoBloqueados[0];
                              e.horas += 0.5;
                              cambios[e.id] = e.horas;
                              deficit -= 0.5;
                          }
                      } else if (deficit < 0) {
                          let exceso = -deficit;
                          while (exceso > 0.01) {
                              empleadosNoBloqueados.sort((a, b) => b.horas - a.horas);
                              let e = empleadosNoBloqueados[0];
                              let current = e.horas;
                              if (current >= 0.5) {
                                  e.horas = current - 0.5;
                                  cambios[e.id] = e.horas;
                                  exceso -= 0.5;
                              } else {
                                  break; 
                              }
                          }
                      }
                      for (const e of empleadosNoBloqueados) {
                          if (this.ajustesManuales[e.id] === undefined) cambios[e.id] = e.horas;
                      }
                      if (Object.keys(cambios).length) {
                          this.ajustesManuales = { ...this.ajustesManuales, ...cambios };
                      }
                      this.recalcularDesajuste(rol);
                  },

                  recalcularDesajuste(rol) {
                      let req = this.confirmData.requeridas[rol] || 0;
                      let empleadosRol = this.getEmpleadosAjuste(rol);
                      
                      let disp = 0;
                      for(let e of empleadosRol) {
                          disp += this.getHorasSemanalesActuales(e);
                      }
                      
                      this.confirmData.disponibles[rol] = disp;
                      
                      if (req > disp) {
                          this.confirmData.deficit[rol] = req - disp;
                          this.confirmData.exceso[rol] = 0;
                      } else if (disp > req) {
                          this.confirmData.exceso[rol] = disp - req;
                          this.confirmData.deficit[rol] = 0;
                      } else {
                          this.confirmData.deficit[rol] = 0;
                          this.confirmData.exceso[rol] = 0;
                      }
                  },

                  startEmpleadoDrag(event, empleado) {
                      event.dataTransfer.setData('application/json', JSON.stringify(empleado));
                      event.dataTransfer.effectAllowed = 'copy';
                  },

                  asignarEmpleadoDrop(event, turno) {
                      event.preventDefault();
                      try {
                          const data = event.dataTransfer.getData('application/json');
                          if (data) {
                              const empleado = JSON.parse(data);
                              if (empleado && empleado.id) {
                                  if (turno.rol !== empleado.rol) {
                                      alert("El empleado no pertenece al rol del turno.");
                                      return;
                                  }
                                  
                                  if (this.festivos.includes(this.fechasISO[this.diaSeleccionado]) && empleado.disponibilidad_festivos === 'no_disponible') {
                                      alert('Este empleado no está disponible para trabajar en festivos.');
                                      return;
                                  }
                                  if (empleado.en_baja) {
                                      if (!confirm("Este empleado está de BAJA MÉDICA. ¿Asignar de todas formas?")) {
                                          return;
                                      }
                                  } else if (this.isEmpleadoAusente(empleado.id, this.diaSeleccionado)) {
                                      if (!confirm("Este empleado está de vacaciones o libre este día. ¿Asignar de todas formas?")) {
                                          return;
                                      }
                                  }
                                  
                                  turno.empleado_id = empleado.id;
                              }
                          }
                      } catch (e) {
                          console.error("Error al asignar empleado", e);
                      }
                  },

                  // --- Funciones para Drag & Drop de Turnos ---
                  initBlockDrag(event, turno, action) {
                      this.activeDragId = turno.id;
                      this.dragAction = action;
                      this.dragStartY = event.clientY;
                      this.dragOrigStartMin = this.timeToMinutes(turno.hora_inicio);
                      this.dragOrigEndMin = this.timeToMinutes(turno.hora_fin);
                      
                      if (this.$refs.gridInner) {
                          this.dragPixelsPerMin = this.$refs.gridInner.clientHeight / 1080;
                      } else if (this.$refs.gridContainer) {
                          this.dragPixelsPerMin = (this.$refs.gridContainer.clientHeight - 40) / 1080;
                      } else {
                          this.dragPixelsPerMin = window.innerHeight / 1080;
                      }
                  },
                  
                  onBlockDrag(event) {
                      if (!this.activeDragId) return;
                      
                      event.preventDefault(); // Evitar selecciÃ³n de texto
                      
                      const deltaY = event.clientY - this.dragStartY;
                      const deltaMin = Math.round((deltaY / this.dragPixelsPerMin) / 30) * 30; // Snapping 15 min
                      
                      // Solo actualizar si hay cambio real para no repintar inÃºtilmente
                      if (deltaMin === 0 && this.dragAction === 'move') return;
                      
                      const turno = this.turnos[this.diaSeleccionado].find(t => t.id === this.activeDragId);
                      if (!turno) return;
                      
                      const minTime = 540; // 09:00
                      const maxTime = 1620; // 03:00 (+1 dÃ­a)
                      
                      let newStart = this.dragOrigStartMin;
                      let newEnd = this.dragOrigEndMin;
                      
                      if (this.dragAction === 'move') {
                          newStart += deltaMin;
                          newEnd += deltaMin;
                          
                          if (newStart < minTime) {
                              newEnd += (minTime - newStart);
                              newStart = minTime;
                          }
                          if (newEnd > maxTime) {
                              newStart -= (newEnd - maxTime);
                              newEnd = maxTime;
                          }
                      } else if (this.dragAction === 'resize-top') {
                          newStart += deltaMin;
                          if (newStart >= newEnd) newStart = newEnd - 15;
                          if (newStart < minTime) newStart = minTime;
                      } else if (this.dragAction === 'resize-bottom') {
                          newEnd += deltaMin;
                          if (newEnd <= newStart) newEnd = newStart + 15;
                          if (newEnd > maxTime) newEnd = maxTime;
                      }
                      
                      // Solo para re-calcular posiciÃ³n en getTurnoStyle reactivamente
                      turno.hora_inicio = this.minToTime(newStart);
                      turno.hora_fin = this.minToTime(newEnd);
                  },
                  endBlockDrag(event) {
                      if (this.activeDragId) {
                          this.activeDragId = null;
                          this.dragAction = null;
                      }
                  }
              }))
          })
