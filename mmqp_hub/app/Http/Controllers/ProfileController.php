<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill([
            'nombre' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Display the user's personal data form.
     */
    public function datosPersonales(Request $request): View
    {
        return view('profile.datos-personales', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the user's notifications form.
     */
    public function notificaciones(Request $request): View
    {
        return view('profile.notificaciones', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the user's security form.
     */
    public function seguridad(Request $request): View
    {
        return view('profile.seguridad', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the user's time-off requests form.
     */
    public function editDisponibilidad(Request $request): View
    {
        abort_unless($request->user()->isEmployee(), 403);
        $user = $request->user();
        $solicitudes = $user->solicitudesAusencia()->orderBy('fecha', 'desc')->get();

        return view('profile.disponibilidad', compact('user', 'solicitudes'));
    }

    /**
     * Store a new time-off request.
     */
    public function updateDisponibilidad(Request $request): RedirectResponse
    {
        app(\App\Services\AbsenceService::class)->create($request->user(), $request->all(), true);
        return Redirect::route('profile.disponibilidad')->with('status', '¡Solicitud enviada correctamente! El administrador la revisará pronto.');
    }

    /**
     * Display the user's vacations view.
     */
    public function vacaciones(Request $request): View
    {
        abort_unless($request->user()->isEmployee(), 403);
        $user = $request->user();
        $vacacionesRaw = $user->vacaciones ?? [];
        $historicalCount = count(app(\App\Services\AbsenceAvailability::class)->unresolvedDates($user));
        
        // Sort dates chronologically
        usort($vacacionesRaw, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        $agrupadas = [];
        $tempGroup = [];

        foreach ($vacacionesRaw as $dateStr) {
            $date = \Carbon\Carbon::parse($dateStr)->locale('es');
            if (empty($tempGroup)) {
                $tempGroup[] = $date;
            } else {
                $lastDate = end($tempGroup);
                if ($lastDate->copy()->addDay()->format('Y-m-d') === $date->format('Y-m-d')) {
                    $tempGroup[] = $date;
                } else {
                    $agrupadas[] = $this->formatRangoVacaciones($tempGroup);
                    $tempGroup = [$date];
                }
            }
        }
        if (!empty($tempGroup)) {
            $agrupadas[] = $this->formatRangoVacaciones($tempGroup);
        }

        return view('profile.vacaciones', [
            'user' => $user,
            'vacaciones_json' => json_encode($vacacionesRaw),
            'vacaciones_agrupadas' => $agrupadas,
            'historicalCount' => $historicalCount,
        ]);
    }

    /**
     * Helper to format a group of consecutive Carbon dates into a string.
     */
    private function formatRangoVacaciones(array $fechas): string
    {
        if (count($fechas) === 1) {
            return $fechas[0]->translatedFormat('j \d\e F \d\e Y');
        }

        $inicio = $fechas[0];
        $fin = end($fechas);

        if ($inicio->year === $fin->year) {
            if ($inicio->month === $fin->month) {
                return 'Del ' . $inicio->format('j') . ' al ' . $fin->translatedFormat('j \d\e F \d\e Y');
            } else {
                return 'Del ' . $inicio->translatedFormat('j \d\e F') . ' al ' . $fin->translatedFormat('j \d\e F \d\e Y');
            }
        }

        return 'Del ' . $inicio->translatedFormat('j \d\e F \d\e Y') . ' al ' . $fin->translatedFormat('j \d\e F \d\e Y');
    }
}
