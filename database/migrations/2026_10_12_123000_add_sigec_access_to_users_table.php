<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_central_admin')->default(false);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('establishment_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'establishment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_user_access');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_central_admin', 'is_active']);
        });
    }
};
