<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\CellAssignment;
use App\Models\Detainee;
use App\Models\DetaineeAdmissionDraft;
use App\Models\DetentionCase;
use App\Models\DetentionOrder;
use App\Models\DetentionStay;
use App\Models\Establishment;
use App\Models\Magistrate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DetaineeAdmissionDraftController extends Controller
{
    private const SUBMIT_ROLES = ['director', 'agent', 'judicial', 'custody'];
    private const REVIEW_ROLES = ['director', 'judicial'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishments = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name'])
            : $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', self::REVIEW_ROLES)->where('establishments.status', 'active')->orderBy('name')->get(['establishments.id', 'code', 'name']);
        abort_unless($establishments->isNotEmpty(), 403);
        $ids = $establishments->pluck('id');
        $drafts = DetaineeAdmissionDraft::query()->with(['establishment:id,code,name', 'submitter:id,name', 'reviewer:id,name'])
            ->whereIn('establishment_id', $ids)->latest()->limit(100)->get()
            ->map(fn (DetaineeAdmissionDraft $draft) => [
                'id' => $draft->id,
                'offline_reference' => $draft->offline_reference,
                'status' => $draft->status,
                'created_at' => $draft->created_at?->toIso8601String(),
                'reviewed_at' => $draft->reviewed_at?->toIso8601String(),
                'review_note' => $draft->review_note,
                'establishment' => $draft->establishment,
                'submitter' => $draft->submitter?->name,
                'reviewer' => $draft->reviewer?->name,
                'payload' => $draft->payload(),
            ]);

        return Inertia::render('admission-reviews/index', [
            'drafts' => $drafts,
            'magistrates' => Magistrate::query()->where('is_active', true)->orderBy('court_name')->orderBy('family_name')->orderBy('post_name')->get(['id', 'family_name', 'post_name', 'given_names', 'function', 'court_name']),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'establishment_code' => ['required', 'string', 'max:80'],
            'family_name' => ['required', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
            'case_reference' => ['nullable', 'string', 'max:255'],
            'case_type' => ['required', Rule::in(['pretrial', 'convicted'])],
            'court_name' => ['required', 'string', 'max:255'],
            'court_level' => ['nullable', 'string', 'max:255'],
            'judgment_final_at' => ['nullable', 'required_if:case_type,convicted', 'date', 'before_or_equal:today'],
            'order_reference' => ['required', 'string', 'max:255'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'effective_at' => ['required', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:effective_at'],
            'unit_reference' => ['nullable', 'string', 'max:255'],
            'building_code' => ['required', 'string', 'max:80'],
            'cell_code' => ['required', 'string', 'max:80'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishment = Establishment::query()->where('code', $data['establishment_code'])->where('status', 'active')->first();
        if (! $establishment) throw ValidationException::withMessages(['establishment_code' => 'Code d’établissement inconnu ou inactif.']);
        abort_unless($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::SUBMIT_ROLES), 403);

        $result = DB::transaction(function () use ($data, $user, $establishment) {
            $draft = DetaineeAdmissionDraft::query()->where('offline_reference', $data['offline_reference'])->lockForUpdate()->first();
            if ($draft) {
                abort_unless($draft->establishment_id === $establishment->id, 409);
                if ($draft->submitted_by === $user->id && in_array($draft->status, ['pending_review', 'rejected'], true)) {
                    unset($data['establishment_code']);
                    $updated = $draft->payload() !== $data;
                    if ($updated || $draft->status === 'rejected') {
                        $draft->update([
                            'encrypted_payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
                            'status' => 'pending_review',
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                            'review_note' => null,
                        ]);
                        AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $establishment->id, 'event' => 'detainee.admission_updated', 'subject_type' => 'admission_draft', 'subject_id' => $draft->id, 'summary' => 'Pré-admission corrigée et renvoyée en vérification']);
                    }
                }
                return [$draft, true];
            }
            unset($data['establishment_code']);
            $draft = DetaineeAdmissionDraft::query()->create([
                'offline_reference' => $data['offline_reference'],
                'establishment_id' => $establishment->id,
                'submitted_by' => $user->id,
                'encrypted_payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
                'status' => 'pending_review',
            ]);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $establishment->id, 'event' => 'detainee.admission_submitted', 'subject_type' => 'admission_draft', 'subject_id' => $draft->id, 'summary' => 'Pré-admission transmise pour vérification judiciaire']);
            return [$draft, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $result[1], 'draft_id' => $result[0]->id, 'status' => $result[0]->status]);
    }

    public function status(Request $request, string $offlineReference): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $draft = DetaineeAdmissionDraft::query()->where('offline_reference', $offlineReference)->firstOrFail();
        abort_unless($draft->submitted_by === $user->id || $user->is_central_admin || $user->hasEstablishmentRole($draft->establishment_id, self::REVIEW_ROLES), 403);
        return response()->json(['status' => $draft->status, 'review_note' => $draft->review_note, 'reviewed_at' => $draft->reviewed_at?->toIso8601String()]);
    }

    public function approve(Request $request, DetaineeAdmissionDraft $draft): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $data = $request->validate(['magistrate_id' => ['nullable', 'integer', Rule::exists('magistrates', 'id')->where('is_active', true)]]);
        DB::transaction(function () use ($draft, $user, $data) {
            $draft = DetaineeAdmissionDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_central_admin || $user->hasEstablishmentRole($draft->establishment_id, self::REVIEW_ROLES), 403);
            abort_unless($draft->status === 'pending_review', 422, 'Cette pré-admission a déjà été traitée.');
            $payload = $draft->payload();
            $validator = Validator::make($payload, [
                'family_name' => ['required', 'string', 'max:255'], 'post_name' => ['nullable', 'string', 'max:255'], 'given_names' => ['required', 'string', 'max:255'],
                'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'], 'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
                'case_reference' => ['nullable', 'string', 'max:255'], 'case_type' => ['required', Rule::in(['pretrial', 'convicted'])],
                'court_name' => ['required', 'string', 'max:255'], 'court_level' => ['nullable', 'string', 'max:255'],
                'judgment_final_at' => ['nullable', 'required_if:case_type,convicted', 'date', 'before_or_equal:today'],
                'order_reference' => ['required', 'string', 'max:255'], 'issuing_authority' => ['required', 'string', 'max:255'],
                'issued_at' => ['nullable', 'date', 'before_or_equal:today'], 'effective_at' => ['required', 'date', 'before_or_equal:today'],
                'expires_at' => ['nullable', 'date', 'after_or_equal:effective_at'], 'unit_reference' => ['nullable', 'string', 'max:255'],
                'building_code' => ['required', 'string', 'max:80'], 'cell_code' => ['required', 'string', 'max:80'],
            ]);
            if ($validator->fails()) throw new ValidationException($validator);
            if (! empty($data['magistrate_id'])) {
                $magistrate = Magistrate::query()->whereKey($data['magistrate_id'])->where('is_active', true)->firstOrFail();
                if (mb_strtolower($magistrate->court_name) !== mb_strtolower($payload['court_name'])) {
                    throw ValidationException::withMessages(['magistrate_id' => 'Le magistrat sélectionné ne correspond pas à la juridiction indiquée.']);
                }
            }
            $cell = Cell::query()->where('code', $payload['cell_code'])->where('status', 'active')->whereHas('building', fn ($q) => $q->where('code', $payload['building_code'])->where('status', 'active')->where('establishment_id', $draft->establishment_id))->lockForUpdate()->first();
            if (! $cell) throw ValidationException::withMessages(['cell_code' => 'Bâtiment ou cellule introuvable ou inactif dans cet établissement.']);

            $detainee = Detainee::query()->create(['record_number' => 'DET-'.Str::ulid(), 'family_name' => $payload['family_name'], 'post_name' => $payload['post_name'] ?? null, 'given_names' => $payload['given_names'], 'date_of_birth' => $payload['date_of_birth'] ?? null, 'sex' => $payload['sex'], 'created_by' => $draft->submitted_by ?? $user->id]);
            $case = DetentionCase::query()->create(['detainee_id' => $detainee->id, 'case_reference' => $payload['case_reference'] ?? null, 'case_type' => $payload['case_type'], 'court_name' => $payload['court_name'], 'court_level' => $payload['court_level'] ?? null, 'judgment_final_at' => $payload['judgment_final_at'] ?? null, 'case_status' => 'open', 'created_by' => $draft->submitted_by ?? $user->id]);
            $order = DetentionOrder::query()->create(['detention_case_id' => $case->id, 'magistrate_id' => $data['magistrate_id'] ?? null, 'order_type' => $payload['case_type'] === 'pretrial' ? 'pretrial_detention' : 'sentence', 'reference' => $payload['order_reference'], 'issuing_authority' => $payload['issuing_authority'], 'issued_at' => $payload['issued_at'] ?? null, 'effective_at' => $payload['effective_at'], 'expires_at' => $payload['expires_at'] ?? null, 'status' => 'active', 'verified_by' => $user->id, 'verified_at' => now()]);
            $stay = DetentionStay::query()->create(['detainee_id' => $detainee->id, 'detention_case_id' => $case->id, 'establishment_id' => $draft->establishment_id, 'detention_order_id' => $order->id, 'unit_reference' => $payload['unit_reference'] ?? null, 'admitted_at' => now(), 'recorded_by' => $user->id]);
            CellAssignment::query()->create(['cell_id' => $cell->id, 'detainee_id' => $detainee->id, 'detention_stay_id' => $stay->id, 'assigned_by' => $user->id, 'assigned_at' => now()]);
            $occupied = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupied > $cell->capacity) AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $draft->establishment_id, 'event' => 'cell.overcrowding', 'subject_type' => 'cell', 'subject_id' => $cell->id, 'summary' => 'Surpopulation après admission : '.$occupied.'/'.$cell->capacity.' places.', 'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupied, 'excess' => $occupied - $cell->capacity, 'detainee_id' => $detainee->id]]);
            $draft->update(['status' => 'approved', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $draft->establishment_id, 'event' => 'detainee.admitted', 'subject_type' => 'detainee', 'subject_id' => $detainee->id, 'summary' => 'Pré-admission vérifiée ; admission et affectation enregistrées']);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $draft->establishment_id, 'event' => 'detainee.admission_approved', 'subject_type' => 'admission_draft', 'subject_id' => $draft->id, 'summary' => 'Ordre vérifié et pré-admission approuvée']);
        });
        return redirect()->route('admission-reviews.index')->with('success', 'Ordre vérifié : l’admission officielle et l’affectation en cellule sont enregistrées.');
    }

    public function reject(Request $request, DetaineeAdmissionDraft $draft): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($draft, $user, $data) {
            $draft = DetaineeAdmissionDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_central_admin || $user->hasEstablishmentRole($draft->establishment_id, self::REVIEW_ROLES), 403);
            abort_unless($draft->status === 'pending_review', 422, 'Cette pré-admission a déjà été traitée.');
            $draft->update(['status' => 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $draft->establishment_id, 'event' => 'detainee.admission_rejected', 'subject_type' => 'admission_draft', 'subject_id' => $draft->id, 'summary' => 'Pré-admission rejetée après examen']);
        });
        return redirect()->route('admission-reviews.index')->with('success', 'Pré-admission rejetée avec motif. Aucun dossier officiel de détenu n’a été créé.');
    }
}
