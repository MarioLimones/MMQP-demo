<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DemoScheduleLabelTest extends TestCase
{
    private function renderLabel(array $results): string
    {
        return Blade::render('<x-demo-schedule-label :results="$results" :description="true" />', compact('results'));
    }

    public function test_invented_example_shifts_have_the_demo_explanation(): void
    {
        $html = $this->renderLabel([['turno_id' => 'demo-2026-11-02-0']]);
        $this->assertStringContainsString('DEMO · Horario de ejemplo', $html);
        $this->assertStringContainsString('Los nombres y los turnos son inventados.', $html);
    }

    public function test_other_shifts_are_never_mislabelled_as_prepared_examples(): void
    {
        foreach ([[], [['turno_id' => 'visitor-created-1']], [['turno_id' => 'demo-1'], ['turno_id' => 'visitor-created-2']]] as $results) {
            $this->assertStringNotContainsString('Horario de ejemplo', $this->renderLabel($results));
        }
    }

    public function test_explanation_is_hidden_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $this->assertStringNotContainsString('Horario de ejemplo', $this->renderLabel([['turno_id' => 'demo-1']]));
    }
}
