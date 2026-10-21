<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detainee_admission_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('offline_reference')->unique();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('encrypted_payload');
            $table->string('status')->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detainee_admission_drafts');
    }
};
