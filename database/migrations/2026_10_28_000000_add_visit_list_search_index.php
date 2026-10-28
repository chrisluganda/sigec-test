<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'prison_visits_establishment_status_schedule_idx';

    public function up(): void
    {
        Schema::table('prison_visits', function (Blueprint $table): void {
            $table->index(['establishment_id', 'status', 'scheduled_at'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('prison_visits', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
