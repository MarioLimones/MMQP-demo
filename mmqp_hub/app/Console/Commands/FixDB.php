<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\HorarioSemanal;

class FixDB extends Command
{
    protected $signature = 'fix:db';
    public function handle()
    {
        $historicos = HorarioSemanal::where('generado_por', 20)->where('estado', 'borrador')->get();
        $this->info("Encontrados " . count($historicos) . " horarios");
        
        foreach ($historicos as $h) {
            $viejos = is_array($h->plantilla_diseno) ? $h->plantilla_diseno : json_decode($h->plantilla_diseno, true);
            if (isset($viejos[0]['dia'])) {
                $this->info("Saltando {$h->id}, ya esta bien");
                continue;
            }
            
            $nuevos = [];
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo', ''];
            foreach ($dias as $d) {
                $turnos_requeridos = [];
                if (isset($viejos[$d])) {
                    foreach ($viejos[$d] as $t) {
                        $turnos_requeridos[] = [
                            'turno_id' => $t['id'] ?? ($t['turno_id'] ?? null),
                            'rol_necesario' => $t['rol'] ?? ($t['rol_necesario'] ?? null),
                            'puesto_num' => $t['puesto_num'] ?? 1,
                            'hora_inicio' => $t['hora_inicio'] ?? '00:00',
                            'hora_fin' => $t['hora_fin'] ?? '23:59'
                        ];
                    }
                }
                
                if ($d !== '' || count($turnos_requeridos) > 0) {
                    $nuevos[] = [
                        'dia' => $d === '' ? 'lunes' : $d,
                        'turnos_requeridos' => $turnos_requeridos
                    ];
                }
            }
            
            $consolidated = [];
            foreach ($nuevos as $n) {
                if (!isset($consolidated[$n['dia']])) {
                    $consolidated[$n['dia']] = $n;
                } else {
                    $consolidated[$n['dia']]['turnos_requeridos'] = array_merge($consolidated[$n['dia']]['turnos_requeridos'], $n['turnos_requeridos']);
                }
            }
            
            $h->plantilla_diseno = array_values($consolidated);
            $h->save();
        }
        $this->info("Fix complete.");
    }
}
