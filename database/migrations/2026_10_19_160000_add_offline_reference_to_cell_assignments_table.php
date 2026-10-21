<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cell_assignments', function (Blueprint $table) {
            $table->uuid('offline_reference')->nullable()->unique();
            $table->foreignId('source_establishment_id')->nullable()->constrained('establishments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cell_assignments', function (Blueprint $table) {
            $table->dropUnique(['offline_reference']);
            $table->dropConstrainedForeignId('source_establishment_id');
            $table->dropColumn('offline_reference');
        });
    }
};
