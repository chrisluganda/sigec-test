<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detainees', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique();
            $table->string('family_name');
            $table->string('given_names');
            $table->date('date_of_birth')->nullable();
            $table->string('sex')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('detention_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->string('case_reference')->nullable();
            $table->string('case_type');
            $table->string('court_name')->nullable();
            $table->string('court_level')->nullable();
            $table->date('judgment_final_at')->nullable();
            $table->string('case_status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['detainee_id', 'case_type', 'case_status']);
        });

        Schema::create('detention_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detention_case_id')->constrained()->restrictOnDelete();
            $table->string('order_type');
            $table->string('reference');
            $table->string('issuing_authority');
            $table->date('issued_at')->nullable();
            $table->date('effective_at');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->string('document_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['detention_case_id', 'status', 'expires_at']);
        });

        Schema::create('detention_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('detention_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('detention_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_stay_id')->nullable()->constrained('detention_stays')->nullOnDelete();
            $table->string('unit_reference')->nullable();
            $table->timestamp('admitted_at');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->string('release_authority_reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['establishment_id', 'released_at', 'admitted_at']);
            $table->index(['detainee_id', 'released_at']);
        });

        Schema::create('sentence_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detention_case_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_type');
            $table->string('status')->default('requested');
            $table->string('decision_reference')->nullable();
            $table->string('deciding_authority')->nullable();
            $table->date('requested_at')->nullable();
            $table->date('decided_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('conditions')->nullable();
            $table->string('decision_document_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['detention_case_id', 'adjustment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentence_adjustments');
        Schema::dropIfExists('detention_stays');
        Schema::dropIfExists('detention_orders');
        Schema::dropIfExists('detention_cases');
        Schema::dropIfExists('detainees');
    }
};
