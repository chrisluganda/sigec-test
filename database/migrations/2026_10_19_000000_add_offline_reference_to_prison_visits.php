<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prison_visits', function (Blueprint $table) {
            $table->uuid('offline_reference')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('prison_visits', function (Blueprint $table) {
            $table->dropUnique(['offline_reference']);
            $table->dropColumn('offline_reference');
        });
    }
};
