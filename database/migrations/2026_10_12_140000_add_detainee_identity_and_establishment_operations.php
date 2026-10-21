<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magistrates', function (Blueprint $table) {
            $table->id();
            $table->string('official_reference')->nullable()->unique();
            $table->string('family_name');
            $table->string('given_names');
            $table->string('function');
            $table->string('court_name');
            $table->string('court_level')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('detention_orders', function (Blueprint $table) {
            $table->foreignId('magistrate_id')->nullable()->after('detention_case_id')->constrained('magistrates')->restrictOnDelete();
        });

        Schema::table('detainees', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->timestamp('photo_captured_at')->nullable();
            $table->foreignId('photo_recorded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('detainee_biometrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->string('finger_position')->nullable();
            $table->text('encrypted_template')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('capture_device')->nullable();
            $table->string('collection_basis')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['detainee_id', 'status']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('unit');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('establishment_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('location')->default('Magasin principal');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reorder_threshold', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['establishment_id', 'inventory_item_id', 'location']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity', 12, 3);
            $table->string('source_location')->nullable();
            $table->string('destination_location')->nullable();
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('related_establishment_id')->nullable()->constrained('establishments')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['establishment_id', 'inventory_item_id', 'created_at']);
        });

        Schema::create('medical_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consulted_at');
            $table->string('consultation_type');
            $table->text('reason')->nullable();
            $table->text('observations')->nullable();
            $table->text('care_provided')->nullable();
            $table->boolean('referral_required')->default(false);
            $table->text('referral_notes')->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'detainee_id', 'consulted_at']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('family_name');
            $table->string('given_names');
            $table->string('document_type')->nullable();
            $table->string('document_reference')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prison_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('visitor_id')->constrained()->restrictOnDelete();
            $table->string('visit_type');
            $table->string('relationship')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('outcome')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['establishment_id', 'detainee_id', 'scheduled_at']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('incident_type');
            $table->string('severity');
            $table->timestamp('occurred_at');
            $table->string('location')->nullable();
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'status', 'occurred_at']);
        });

        Schema::create('incident_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('detainee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('involvement_role');
            $table->timestamps();
            $table->index(['incident_id', 'involvement_role']);
        });

        Schema::create('incident_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->timestamp('action_at');
            $table->string('action_type');
            $table->text('description');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['incident_id', 'action_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_actions');
        Schema::dropIfExists('incident_participants');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('prison_visits');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('medical_consultations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('establishment_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('detainee_biometrics');

        Schema::table('detention_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('magistrate_id');
        });
        Schema::dropIfExists('magistrates');

        Schema::table('detainees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_recorded_by');
            $table->dropColumn(['photo_path', 'photo_captured_at']);
        });
    }
};
