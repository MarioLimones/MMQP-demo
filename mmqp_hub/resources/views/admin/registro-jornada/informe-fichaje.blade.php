<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Fichajes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }
        body {
            background-color: #f3f4f6;
            font-family: Arial, sans-serif;
            color: #111827;
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

    <div class="no-print w-full bg-slate-800 text-white p-4 flex justify-between items-center fixed top-0 z-50">
        <div>
            <h1 class="font-bold">Vista Previa: Detalle de Fichajes</h1>
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

    <div class="h-24 no-print"></div>

    <div class="a4-sheet">
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold uppercase underline">Detalle de Entradas y Salidas</h1>
            <p class="text-sm mt-1">Periodo del {{ $fechaInicio->format('d/m/Y') }} al {{ $fechaFin->format('d/m/Y') }}</p>
        </div>

        <table class="w-full text-sm border-collapse border border-slate-800 mb-6 text-center">
            <thead>
                <tr class="bg-slate-100">
                    <th class="border border-slate-800 py-2">Fecha</th>
                    <th class="border border-slate-800 py-2">Empleado</th>
                    <th class="border border-slate-800 py-2">Hora Entrada</th>
                    <th class="border border-slate-800 py-2">Hora Salida</th>
                    <th class="border border-slate-800 py-2">Horas Calculadas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fichajesTotales as $fichaje)
                <tr>
                    <td class="border border-slate-800 py-1">{{ $fichaje['fecha'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $fichaje['empleado']->nombre }} {{ $fichaje['empleado']->apellidos }}</td>
                    <td class="border border-slate-800 py-1">{{ $fichaje['entrada'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $fichaje['salida'] }}</td>
                    <td class="border border-slate-800 py-1">{{ $fichaje['horas'] }}</td>
                </tr>
                @endforeach
                @if(count($fichajesTotales) == 0)
                <tr>
                    <td colspan="5" class="border border-slate-800 py-4 text-slate-500">No hay fichajes registrados en este periodo.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

</body>
</html>
