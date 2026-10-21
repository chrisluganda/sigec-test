<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['establishment_id', 'is_active']);
        });

        DB::table('prison_visits')->select('visitor_id', 'establishment_id')->orderBy('id')->get()
            ->unique('visitor_id')->each(fn ($visit) => DB::table('visitors')->where('id', $visit->visitor_id)->whereNull('establishment_id')->update(['establishment_id' => $visit->establishment_id]));
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['establishment_id', 'is_active']);
            $table->dropConstrainedForeignId('establishment_id');
        });
    }
};
