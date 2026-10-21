<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('facility_type')->nullable();
            $table->string('administration_type')->nullable();
            $table->string('security_level')->nullable();
            $table->string('penitentiary_region')->nullable();
            $table->string('court_jurisdiction')->nullable();
            $table->string('authorization_reference')->nullable();
            $table->date('authorization_date')->nullable();
            $table->boolean('women_dedicated')->default(false);
            $table->boolean('separate_women_quarters')->default(false);
            $table->boolean('separate_pretrial_quarters')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn([
                'facility_type',
                'administration_type',
                'security_level',
                'penitentiary_region',
                'court_jurisdiction',
                'authorization_reference',
                'authorization_date',
                'women_dedicated',
                'separate_women_quarters',
                'separate_pretrial_quarters',
            ]);
        });
    }
};
