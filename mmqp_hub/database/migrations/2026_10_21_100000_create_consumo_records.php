<?php

use App\Models\ConsumoSheet;
use App\Services\ConsumoHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumo_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_id')->constrained('consumo_sheets')->restrictOnDelete();
            $table->unsignedInteger('sheet_version');
            $table->dateTime('registered_at');
            $table->date('registration_date')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('title');
            $table->json('import_metadata');
            $table->json('snapshot');
            $table->unique(['sheet_id', 'sheet_version']);
        });

        ConsumoSheet::with('report')->chunkById(100, function ($sheets) {
            $history = app(ConsumoHistory::class);
            foreach ($sheets as $sheet) {
                $history->backfill($sheet);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumo_records');
    }
};
