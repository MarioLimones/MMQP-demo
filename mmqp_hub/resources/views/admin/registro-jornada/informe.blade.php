<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Jornada</title>
    <!-- Incluir Tailwind solo para el renderizado inicial y la impresión -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilos específicos para la impresión A4 */
        @page {
            size: A4;
            margin: 1.5cm;
        }
        body {
            background-color: #f3f4f6; /* Para pantalla */
            font-family: Arial, sans-serif;
            color: #111827;
        }
        .page-break {
            page-break-after: always;
        }
        .page-break:last-child {
            page-break-after: avoid;
        }
        .a4-sheet {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        @media print {
            body { background: white; }
            .a4-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: 0;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Controles en pantalla -->
    <div class="no-print w-full bg-slate-800 text-white p-4 flex justify-between items-center fixed top-0 z-50">
        <div>
            <h1 class="font-bold">Vista Previa: Registro de Jornada</h1>
            <p class="text-sm text-slate-300">Periodo: {{ $fechaInicio->format('d/m/Y') }} al {{ $fechaFin->format('d/m/Y') }}</p>
        </div>
        <div class="flex gap-4">
            <button onclick="window.close()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded">Cerrar</button>
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Imprimir Informe
            </button>
        </div>
    </div>

    <!-- Espaciador para la barra fija -->
    <div class="h-24 no-print"></div>

    <!-- Hojas de informe -->
    @foreach($reporte as $datos)
    <div class="a4-sheet page-break">
        
        <!-- Cabecera Oficial -->
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold uppercase underline">Registro Diario de Jornada</h1>
            <p class="text-xs mt-1">Real Decreto-ley 8/2019, de 8 de marzo, de medidas urgentes de protección social y de lucha contra la precariedad laboral en la jornada de trabajo.</p>
        </div>

        <!-- Bloque de Empresa y Empleado -->
        <div class="grid grid-cols-2 gap-8 mb-6 text-sm border-2 border-slate-800 p-4">
            <div>
                <h3 class="font-bold border-b border-slate-300 mb-2 pb-1">DATOS DE LA EMPRESA</h3>
                <p><strong>Empresa:</strong> ___________________________</p>
                <p><strong>CIF/NIF:</strong> _____________________________</p>
                <p><strong>Centro de Trabajo:</strong> ____________________</p>
            </div>
            <div>
                <h3 class="font-bold border-b border-slate-300 mb-2 pb-1">DATOS DEL TRABAJADOR</h3>
                <p><strong>Nombre y Apellidos:</strong> {{ $datos['empleado']->nombre }} {{ $datos['empleado']->apellidos }}</p>
                <p><strong>NIF/NIE:</strong> {{ $datos['empleado']->dni ?? '__________________' }}</p>
                <p><strong>Mes / Año:</strong> {{ $mesReporte }}</p>
            </div>
        </div>

        <!-- Tabla de Registros -->
        <table class="w-full text-sm border-collapse border border-slate-800 mb-6 text-center">
            <thead>
                <tr class="bg-slate-100">
                    <th class="border border-slate-800 py-1" rowspan="2">Día</th>
                    <th class="border border-slate-800 py-1" colspan="2">Horario Realizado</th>
                    <th class="border border-slate-800 py-1" rowspan="2">Horas<br>Ordinarias</th>
                    <th class="border border-slate-800 py-1" rowspan="2">Horas<br>Extras</th>
                    <th class="border border-slate-800 py-1" rowspan="2">Firma del Trabajador<br>(Fichaje Diario)</th>
                </tr>
                <tr class="bg-slate-100">
                    <th class="border border-slate-800 py-1">Entrada</th>
                    <th class="border border-slate-800 py-1">Salida</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos['dias'] as $dia)
                <tr>
                    <td class="border border-slate-800 py-1">{{ $dia['fecha'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $dia['entrada'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $dia['salida'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $dia['horas_ordinarias'] ?: '' }}</td>
                    <td class="border border-slate-800 py-1">{{ $dia['horas_extra'] ?: '' }}</td>
                    <td class="border border-slate-800 py-1 text-transparent">Firma</td>
                </tr>
                @endforeach
                
                <!-- Fila de Totales -->
                <tr class="bg-slate-100 font-bold">
                    <td colspan="3" class="border border-slate-800 py-2 text-right pr-4">TOTAL HORAS:</td>
                    <td class="border border-slate-800 py-2">{{ $datos['total_ordinarias'] }}</td>
                    <td class="border border-slate-800 py-2"></td>
                    <td class="border border-slate-800 py-2"></td>
                </tr>
            </tbody>
        </table>

        <!-- Firmas finales -->
        <div class="grid grid-cols-2 gap-8 text-sm pt-4">
            <div class="text-center">
                <p class="mb-16">Firma del Trabajador</p>
                <p class="border-t border-slate-400 pt-1 w-3/4 mx-auto">(Firma)</p>
            </div>
            <div class="text-center">
                <p class="mb-16">Sello y Firma de la Empresa</p>
                <p class="border-t border-slate-400 pt-1 w-3/4 mx-auto">(Sello y Firma)</p>
            </div>
        </div>

    </div>
    @endforeach

    <script>
        // Imprimir automáticamente al abrir (opcional, lo desactivo para que puedan ver la vista previa primero)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
