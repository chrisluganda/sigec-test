<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detention_stays', function (Blueprint $table) {
            $table->text('release_details')->nullable()->after('release_reason');
        });
    }

    public function down(): void
    {
        Schema::table('detention_stays', function (Blueprint $table) {
            $table->dropColumn('release_details');
        });
    }
};
