<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('building_type')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['establishment_id', 'code']);
            $table->index(['establishment_id', 'status']);
        });

        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_building_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('cell_type')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['establishment_building_id', 'code']);
            $table->index(['establishment_building_id', 'status']);
        });

        Schema::create('cell_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_id')->constrained()->restrictOnDelete();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('detention_stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
            $table->index(['cell_id', 'ended_at']);
            $table->index(['detainee_id', 'ended_at']);
            $table->index(['detention_stay_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_assignments');
        Schema::dropIfExists('cells');
        Schema::dropIfExists('establishment_buildings');
    }
};
