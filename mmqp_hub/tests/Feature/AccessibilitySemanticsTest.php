<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AccessibilitySemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        if ($baseline = getenv('ACCESSIBILITY_BASELINE_VIEWS')) {
            View::getFinder()->setPaths([$baseline, resource_path('views')]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['nombre' => 'Persona QA', 'rol' => 'auxiliar', 'es_admin' => true]);
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function assertNamed(DOMElement $control, DOMXPath $xpath): void
    {
        $name = '';
        if ($labelledBy = trim($control->getAttribute('aria-labelledby'))) {
            foreach (preg_split('/\s+/', $labelledBy) as $id) {
                $labels = $xpath->query('//*[@id="'.$id.'"]');
                $this->assertCount(1, $labels, 'Each accessible name reference must identify one element.');
                $name .= ' '.trim($labels->item(0)->textContent);
            }
            $name = trim($name);
        }
        if ($name === '') {
            $name = trim($control->getAttribute('aria-label'));
        }
        if ($name === '' && ($id = $control->getAttribute('id'))) {
            foreach ($xpath->query('//label[@for="'.$id.'"]') as $label) {
                $name .= trim($label->textContent);
            }
        }
        if ($name === '') {
            $name = trim($xpath->evaluate('string(ancestor::label[1])', $control));
        }
        $this->assertNotSame('', $name, 'The '.$control->tagName.' '.$control->getAttribute('name').' needs an associated accessible name.');
    }

    public function test_employee_form_labels_are_associated_with_their_controls(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/empleados/crear')->assertOk();
        $xpath = $this->document($response->getContent());
        foreach (['nombre', 'apellidos', 'dni', 'telefono', 'email', 'password', 'rol', 'horas_mensuales', 'fecha_inicio', 'fecha_fin_contrato', 'baja_desde'] as $field) {
            $controls = $xpath->query('//*[@name="'.$field.'"]');
            $this->assertCount(1, $controls);
            $this->assertNamed($controls->item(0), $xpath);
        }
        foreach ($xpath->query('//input[@name="disponibilidad_festivos"]') as $control) {
            $this->assertNamed($control, $xpath);
        }
        $this->assertNamed($xpath->query('//select[@x-model="estadoLaboral"]')->item(0), $xpath);
    }

    public function test_employee_filters_and_icon_view_buttons_have_names(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/empleados')->assertOk();
        $xpath = $this->document($response->getContent());
        foreach ($xpath->query('//input[@name="buscar"]|//select[@name="estado" or @name="rol" or @name="situacion" or @name="disponibilidad"]') as $control) {
            $this->assertNamed($control, $xpath);
        }
        foreach ($xpath->query('//button[@aria-haspopup="listbox"]') as $control) {
            $this->assertNamed($control, $xpath);
        }
        $buttons = $xpath->query('//button[@aria-pressed]');
        $this->assertCount(2, $buttons);
        foreach ($buttons as $button) {
            $this->assertNamed($button, $xpath);
        }
    }

    public function test_attendance_date_filter_has_a_name(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/fichajes')->assertOk();
        $xpath = $this->document($response->getContent());
        $this->assertNamed($xpath->query('//input[@type="date" and @name="fecha"]')->item(0), $xpath);
    }

    public function test_vacation_month_navigation_buttons_have_distinct_names(): void
    {
        $response = $this->actingAs($this->admin())->get('/perfil/vacaciones')->assertOk();
        $xpath = $this->document($response->getContent());
        $buttons = $xpath->query('//div[contains(@x-data, "vacacionesCalendar")]/div[1]/button');
        $this->assertCount(2, $buttons);
        $names = [];
        foreach ($buttons as $button) {
            $this->assertNamed($button, $xpath);
            $names[] = $button->getAttribute('aria-label');
        }
        $this->assertCount(2, array_unique($names));
    }

    public function test_account_name_includes_the_visible_employee_role(): void
    {
        $response = $this->actingAs($this->admin())->get('/perfil/datos')->assertOk();
        $xpath = $this->document($response->getContent());
        $buttons = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " navigation-account-toggle ")]');
        $this->assertGreaterThan(0, $buttons->length);
        foreach ($buttons as $button) {
            if ($button->hasAttribute('aria-labelledby')) {
                $name = '';
                foreach (preg_split('/\s+/', trim($button->getAttribute('aria-labelledby'))) as $id) {
                    $references = $xpath->query('//*[@id="'.$id.'"]');
                    $this->assertCount(1, $references, 'Each account name reference must identify one element.');
                    $name .= ' '.$references->item(0)->textContent;
                }
            } else {
                $name = $button->hasAttribute('aria-label') ? $button->getAttribute('aria-label') : $button->textContent;
            }
            $this->assertStringContainsString('Persona QA', $name);
            $this->assertStringContainsString('auxiliar', $name);
        }
    }

    public function test_consumption_today_label_contains_its_visible_today_marker(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        try {
            $response = $this->actingAs($this->admin())->get('/admin/consumos?month=2026-10')->assertOk();
            $xpath = $this->document($response->getContent());
            $today = $xpath->query('//a[@data-history-date="2026-10-24"]');
            $this->assertCount(1, $today);
            $this->assertStringContainsString('Hoy', $today->item(0)->getAttribute('aria-label'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_spanish_interface_language_does_not_follow_an_untranslated_framework_locale(): void
    {
        app()->setLocale('en');
        $guest = $this->document($this->get('/login')->assertOk()->getContent());
        $this->assertSame('es', $guest->query('/html')->item(0)->getAttribute('lang'));
        $app = $this->document($this->actingAs($this->admin())->get('/perfil/datos')->assertOk()->getContent());
        $this->assertSame('es', $app->query('/html')->item(0)->getAttribute('lang'));
    }

    public function test_schedule_dialogs_reference_unique_headings_inside_their_own_dialog(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/horarios')->assertOk();
        $xpath = $this->document($response->getContent());
        $dialogs = $xpath->query('//*[@role="dialog" or local-name()="dialog"][@aria-labelledby]');
        $this->assertGreaterThanOrEqual(7, $dialogs->length);
        foreach ($dialogs as $dialog) {
            foreach (preg_split('/\s+/', trim($dialog->getAttribute('aria-labelledby'))) as $id) {
                $headings = $xpath->query('//*[@id="'.$id.'"]');
                $this->assertCount(1, $headings, 'A dialog title reference must identify exactly one heading.');
                $heading = $headings->item(0);
                $this->assertMatchesRegularExpression('/^h[1-6]$/', $heading->nodeName);
                $owner = $xpath->query('ancestor::*[@role="dialog" or local-name()="dialog"][1]', $heading)->item(0);
                $this->assertTrue($owner?->isSameNode($dialog), 'A dialog must be labelled by its own heading.');
            }
        }
    }
}
