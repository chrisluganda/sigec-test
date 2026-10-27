<?php

namespace Tests\Feature;

use App\Models\Cell;
use App\Models\CellAssignment;
use App\Models\Detainee;
use App\Models\DetaineeAdmissionDraft;
use App\Models\DetentionCase;
use App\Models\DetentionOrder;
use App\Models\DetentionStay;
use App\Models\Establishment;
use App\Models\EstablishmentBuilding;
use App\Models\Incident;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalJourneysTest extends TestCase
{
    use RefreshDatabase;

    public function test_detainee_can_be_moved_between_cells_and_the_move_is_audited(): void
    {
        [$user, $establishment, $detainee, $stay, $oldCell, $newCell] = $this->cellJourney();
        $assignment = CellAssignment::query()->create([
            'cell_id' => $oldCell->id,
            'detainee_id' => $detainee->id,
            'detention_stay_id' => $stay->id,
            'assigned_by' => $user->id,
            'assigned_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('establishments.cell-assignments.move', [$establishment, $assignment]), [
            'cell_id' => $newCell->id,
            'reason' => 'Réorganisation interne',
        ])->assertRedirect();

        $this->assertDatabaseHas('cell_assignments', ['id' => $assignment->id, 'cell_id' => $oldCell->id, 'end_reason' => 'Déplacement interne : Réorganisation interne']);
        $this->assertDatabaseHas('cell_assignments', ['cell_id' => $newCell->id, 'detainee_id' => $detainee->id, 'detention_stay_id' => $stay->id, 'ended_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'detainee.cell_moved', 'establishment_id' => $establishment->id]);
    }

    public function test_assignment_to_cell_in_an_inactive_building_is_rejected(): void
    {
        [$user, $establishment, $detainee, , , $newCell] = $this->cellJourney('inactive');

        $this->actingAs($user)->post(route('establishments.cell-assignments.store', $establishment), [
            'cell_id' => $newCell->id,
            'detainee_id' => $detainee->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('cell_assignments', 0);
    }

    public function test_stock_entry_is_recorded_in_the_audit_log(): void
    {
        [$user, $establishment] = $this->authorizedUser('agent');
        $item = InventoryItem::query()->create(['code' => 'TEST-ITEM', 'name' => 'Article test', 'category' => 'Test', 'unit' => 'pièce', 'is_active' => true]);

        $this->actingAs($user)->post(route('stocks.movements.store', $establishment), [
            'inventory_item_id' => $item->id,
            'movement_type' => 'entry',
            'quantity' => 4,
            'location' => 'Magasin principal',
            'reorder_threshold' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('stock_movements', ['establishment_id' => $establishment->id, 'inventory_item_id' => $item->id, 'movement_type' => 'entry', 'quantity' => 4]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'stock.movement', 'establishment_id' => $establishment->id, 'actor_id' => $user->id]);
    }

    public function test_stock_journey_prevents_an_issue_larger_than_available_quantity(): void
    {
        [$user, $establishment] = $this->authorizedUser('agent');
        $item = InventoryItem::query()->create(['code' => 'TEST-STOCK', 'name' => 'Riz', 'category' => 'Alimentation', 'unit' => 'kg', 'is_active' => true]);

        $this->actingAs($user)->post(route('stocks.movements.store', $establishment), [
            'inventory_item_id' => $item->id,
            'movement_type' => 'entry',
            'quantity' => 10,
            'location' => 'Magasin',
        ])->assertRedirect();

        $this->post(route('stocks.movements.store', $establishment), [
            'inventory_item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => 11,
            'location' => 'Magasin',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('establishment_stocks', ['establishment_id' => $establishment->id, 'inventory_item_id' => $item->id, 'location' => 'Magasin', 'quantity' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_visitor_registration_and_arrival_follow_the_front_desk_journey(): void
    {
        [$user, $establishment, $detainee] = $this->visitJourney();

        $this->actingAs($user)->post(route('visitors.store'), [
            'establishment_id' => $establishment->id,
            'detainee_id' => $detainee->id,
            'family_name' => 'Mwamba',
            'post_name' => null,
            'given_names' => 'Aline',
            'date_of_birth' => '1990-04-12',
            'place_of_birth' => 'Goma',
        ])->assertRedirect(route('visits.create', ['establishment_id' => $establishment->id, 'visitor_id' => 1, 'detainee_id' => $detainee->id]));

        $visitor = Visitor::query()->firstOrFail();
        $this->assertSame('Mwamba', $visitor->family_name);
        $this->assertNull($visitor->post_name);

        $this->post(route('visits.store'), [
            'establishment_id' => $establishment->id,
            'detainee_id' => $detainee->id,
            'visitor_id' => $visitor->id,
            'visit_type' => 'family',
            'relationship' => 'Sœur',
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('prison_visits', ['establishment_id' => $establishment->id, 'detainee_id' => $detainee->id, 'visitor_id' => $visitor->id, 'status' => 'arrived']);
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'date_of_birth' => '1990-04-12 00:00:00', 'place_of_birth' => 'Goma']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'visitor.created', 'establishment_id' => $establishment->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'visit.arrived', 'establishment_id' => $establishment->id]);
    }

    public function test_incident_can_be_reported_reviewed_and_resolved_with_action_history(): void
    {
        [$user, $establishment] = $this->authorizedUser('agent');

        $this->actingAs($user)->post(route('incidents.store'), [
            'establishment_id' => $establishment->id,
            'incident_type' => 'security',
            'severity' => 'high',
            'occurred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'location' => 'Bâtiment A',
            'description' => 'Description confidentielle',
            'immediate_action' => 'Mesure immédiate',
        ])->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $this->patch(route('incidents.status', $incident), ['status' => 'under_review', 'action_description' => 'Examen commencé'])->assertRedirect();
        $this->patch(route('incidents.status', $incident), ['status' => 'resolved', 'action_description' => 'Mesure corrective appliquée'])->assertRedirect();

        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'resolved', 'resolved_by' => $user->id]);
        $this->assertDatabaseCount('incident_actions', 2);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incident.reported', 'summary' => 'Incident signalé']);
        $this->assertDatabaseMissing('audit_logs', ['summary' => 'Description confidentielle']);
    }

    public function test_medical_consultation_is_recorded_without_copying_clinical_notes_to_audit_log(): void
    {
        [$user, $establishment] = $this->authorizedUser('medical');
        $detainee = Detainee::query()->create(['record_number' => 'TEST-DET-1', 'family_name' => 'Test', 'given_names' => 'Patient']);
        $case = DetentionCase::query()->create(['detainee_id' => $detainee->id, 'case_type' => 'pretrial', 'created_by' => $user->id]);
        $order = DetentionOrder::query()->create(['detention_case_id' => $case->id, 'order_type' => 'remand', 'reference' => 'ORD-1', 'issuing_authority' => 'Tribunal', 'effective_at' => today()]);
        DetentionStay::query()->create(['detainee_id' => $detainee->id, 'detention_case_id' => $case->id, 'establishment_id' => $establishment->id, 'detention_order_id' => $order->id, 'admitted_at' => now()->subDay(), 'recorded_by' => $user->id]);

        $this->actingAs($user)->post(route('clinic.consultations.store', $establishment), [
            'detainee_id' => $detainee->id,
            'consulted_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'consultation_type' => 'routine',
            'reason' => 'Note médicale confidentielle',
            'observations' => '',
            'care_provided' => '',
            'referral_required' => false,
            'referral_notes' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('medical_consultations', ['detainee_id' => $detainee->id, 'establishment_id' => $establishment->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'medical.consultation', 'establishment_id' => $establishment->id]);
        $this->assertDatabaseMissing('audit_logs', ['summary' => 'Note médicale confidentielle']);
    }

    public function test_offline_visit_sync_is_idempotent_and_records_an_arrival(): void
    {
        [$user, $establishment] = $this->authorizedUser('agent');
        $detainee = Detainee::query()->create(['record_number' => 'OFFLINE-DET-1', 'family_name' => 'Test', 'given_names' => 'Détenu']);
        $case = DetentionCase::query()->create(['detainee_id' => $detainee->id, 'case_type' => 'pretrial', 'created_by' => $user->id]);
        $order = DetentionOrder::query()->create(['detention_case_id' => $case->id, 'order_type' => 'remand', 'reference' => 'OFFLINE-ORD-1', 'issuing_authority' => 'Tribunal', 'effective_at' => today()]);
        DetentionStay::query()->create(['detainee_id' => $detainee->id, 'detention_case_id' => $case->id, 'establishment_id' => $establishment->id, 'detention_order_id' => $order->id, 'admitted_at' => now()->subDay(), 'recorded_by' => $user->id]);
        $payload = [
            'offline_reference' => 'b6a5bb2f-fb29-4f03-a343-03f7104a3e7d',
            'establishment_code' => $establishment->code,
            'detainee_record_number' => $detainee->record_number,
            'family_name' => 'Visiteur',
            'given_names' => 'Hors connexion',
            'visit_type' => 'family',
            'captured_at' => now()->subMinute()->toIso8601String(),
        ];

        $this->actingAs($user)->postJson(route('visits.offline.sync'), $payload)
            ->assertOk()->assertJsonPath('synced', true)->assertJsonPath('duplicate', false);
        $this->postJson(route('visits.offline.sync'), $payload)
            ->assertOk()->assertJsonPath('synced', true)->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('visitors', 1);
        $this->assertDatabaseCount('prison_visits', 1);
        $this->assertDatabaseHas('prison_visits', ['offline_reference' => $payload['offline_reference'], 'status' => 'arrived']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'visit.arrived', 'establishment_id' => $establishment->id]);
    }

    public function test_offline_admission_is_reviewed_before_creating_the_detainee_case_and_cell_assignment(): void
    {
        [$submitter, $establishment] = $this->authorizedUser('agent');
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['is_active' => true, 'is_central_admin' => false])->save();
        DB::table('establishment_user_access')->insert([
            'user_id' => $reviewer->id,
            'establishment_id' => $establishment->id,
            'role' => 'judicial',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $building = EstablishmentBuilding::query()->create(['establishment_id' => $establishment->id, 'code' => 'ADMISSION-B1', 'name' => 'Bâtiment admission', 'status' => 'active']);
        $cell = Cell::query()->create(['establishment_building_id' => $building->id, 'code' => 'ADMISSION-C1', 'capacity' => 2]);
        $payload = [
            'offline_reference' => 'e032194f-e137-4bb2-a2c2-90b9ea9d8171',
            'establishment_code' => $establishment->code,
            'family_name' => 'Kasongo',
            'post_name' => null,
            'given_names' => 'Marie',
            'date_of_birth' => '1995-05-10',
            'sex' => 'female',
            'case_reference' => 'CASE-1',
            'case_type' => 'pretrial',
            'court_name' => 'Tribunal de paix',
            'court_level' => 'Paix',
            'order_reference' => 'ORDER-1',
            'issuing_authority' => 'Tribunal de paix',
            'issued_at' => today()->toDateString(),
            'effective_at' => today()->toDateString(),
            'building_code' => $building->code,
            'cell_code' => $cell->code,
        ];

        $this->actingAs($submitter)->postJson(route('detainees.offline-admission.sync'), $payload)
            ->assertOk()->assertJsonPath('status', 'pending_review');
        $this->assertDatabaseCount('detainees', 0);

        $draft = DetaineeAdmissionDraft::query()->firstOrFail();
        $this->post(route('admission-reviews.approve', $draft), [])->assertForbidden();
        $this->assertDatabaseHas('detainee_admission_drafts', ['id' => $draft->id, 'status' => 'pending_review']);

        $this->actingAs($reviewer)->post(route('admission-reviews.approve', $draft), [])->assertRedirect();

        $detainee = Detainee::query()->firstOrFail();
        $this->assertSame('Kasongo', $detainee->family_name);
        $this->assertNull($detainee->post_name);
        $this->assertDatabaseHas('detainees', ['id' => $detainee->id, 'given_names' => 'Marie']);
        $this->assertDatabaseHas('detention_stays', ['detainee_id' => $detainee->id, 'establishment_id' => $establishment->id]);
        $this->assertDatabaseHas('cell_assignments', ['detainee_id' => $detainee->id, 'cell_id' => $cell->id, 'ended_at' => null]);
        $this->assertDatabaseHas('detainee_admission_drafts', ['id' => $draft->id, 'status' => 'approved', 'reviewed_by' => $reviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'detainee.admission_approved', 'establishment_id' => $establishment->id]);
    }

    public function test_offline_incident_sync_is_idempotent_and_keeps_details_out_of_audit_log(): void
    {
        [$user, $establishment] = $this->authorizedUser('agent');
        $payload = [
            'offline_reference' => '63f41136-28e4-4f16-9e16-6a1d1a929b73',
            'establishment_code' => $establishment->code,
            'incident_type' => 'security',
            'severity' => 'high',
            'occurred_at' => now()->subMinutes(2)->toIso8601String(),
            'location' => 'Bâtiment A',
            'description' => 'Détail confidentiel du signalement',
            'immediate_action' => 'Action de première réponse',
        ];

        $this->actingAs($user)->postJson(route('incidents.offline.sync'), $payload)
            ->assertOk()->assertJsonPath('synced', true)->assertJsonPath('duplicate', false);
        $this->postJson(route('incidents.offline.sync'), $payload)
            ->assertOk()->assertJsonPath('synced', true)->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseHas('incidents', ['offline_reference' => $payload['offline_reference'], 'status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incident.reported', 'establishment_id' => $establishment->id, 'summary' => 'Incident signalé']);
        $this->assertDatabaseMissing('audit_logs', ['summary' => $payload['description']]);
    }

    private function cellJourney(string $buildingStatus = 'active'): array
    {
        [$user, $establishment] = $this->authorizedUser('custody');
        $building = EstablishmentBuilding::query()->create(['establishment_id' => $establishment->id, 'code' => 'BAT-1', 'name' => 'Bâtiment 1', 'status' => $buildingStatus]);
        $oldCell = Cell::query()->create(['establishment_building_id' => $building->id, 'code' => 'C-1', 'capacity' => 1]);
        $newCell = Cell::query()->create(['establishment_building_id' => $building->id, 'code' => 'C-2', 'capacity' => 1]);
        $detainee = Detainee::query()->create(['record_number' => 'TEST-DET-1', 'family_name' => 'Test', 'given_names' => 'Détenu']);
        $case = DetentionCase::query()->create(['detainee_id' => $detainee->id, 'case_type' => 'pretrial', 'created_by' => $user->id]);
        $order = DetentionOrder::query()->create(['detention_case_id' => $case->id, 'order_type' => 'remand', 'reference' => 'ORD-1', 'issuing_authority' => 'Tribunal', 'effective_at' => today()]);
        $stay = DetentionStay::query()->create(['detainee_id' => $detainee->id, 'detention_case_id' => $case->id, 'establishment_id' => $establishment->id, 'detention_order_id' => $order->id, 'admitted_at' => now()->subDay(), 'recorded_by' => $user->id]);

        return [$user, $establishment, $detainee, $stay, $oldCell, $newCell];
    }

    private function visitJourney(): array
    {
        [$user, $establishment] = $this->authorizedUser('agent');
        $detainee = Detainee::query()->create(['record_number' => 'VISIT-DET-1', 'family_name' => 'Ilunga', 'given_names' => 'Jean']);
        $case = DetentionCase::query()->create(['detainee_id' => $detainee->id, 'case_type' => 'pretrial', 'created_by' => $user->id]);
        $order = DetentionOrder::query()->create(['detention_case_id' => $case->id, 'order_type' => 'remand', 'reference' => 'VISIT-ORD-1', 'issuing_authority' => 'Tribunal', 'effective_at' => today()]);
        DetentionStay::query()->create(['detainee_id' => $detainee->id, 'detention_case_id' => $case->id, 'establishment_id' => $establishment->id, 'detention_order_id' => $order->id, 'admitted_at' => now()->subDay(), 'recorded_by' => $user->id]);

        return [$user, $establishment, $detainee];
    }

    private function authorizedUser(string $role): array
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'is_central_admin' => false])->save();
        $establishment = Establishment::query()->create(['code' => 'TEST-EST', 'name' => 'Établissement test', 'status' => 'active']);
        DB::table('establishment_user_access')->insert([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $establishment];
    }
}
