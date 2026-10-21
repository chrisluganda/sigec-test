<?php

namespace App\Http\Controllers;

use App\Models\Detainee;
use App\Models\Establishment;
use App\Models\MedicalConsultation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClinicController extends Controller
{
    private const ROLES = ['director', 'medical'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishments = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name'])
            : $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', self::ROLES)->where('establishments.status', 'active')->orderBy('name')->get(['establishments.id', 'code', 'name']);
        abort_unless($user->is_central_admin || $establishments->isNotEmpty(), 403);

        $selectedId = (int) $request->query('establishment_id', $establishments->first()?->id);
        abort_unless($establishments->contains('id', $selectedId), 403);
        $hasDetainees = Detainee::query()->whereHas('stays', fn ($query) => $query->where('establishment_id', $selectedId)->whereNull('released_at'))->exists();
        $consultations = MedicalConsultation::query()->with(['detainee:id,record_number,family_name,post_name,given_names', 'clinician:id,name'])
            ->where('establishment_id', $selectedId)->latest('consulted_at')->paginate(25)->withQueryString();

        return Inertia::render('clinic/index', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'hasDetainees' => $hasDetainees,
            'consultations' => $consultations,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function searchDetainees(Request $request, Establishment $establishment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES)), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $detainees = Detainee::query()
            ->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
            ->where(fn ($query) => $query->where('record_number', 'like', "%{$term}%")->orWhere('family_name', 'like', "%{$term}%")->orWhere('post_name', 'like', "%{$term}%")->orWhere('given_names', 'like', "%{$term}%"))
            ->orderBy('family_name')->orderBy('given_names')->limit(20)->get(['id', 'record_number', 'family_name', 'post_name', 'given_names']);

        return response()->json($detainees->map(fn (Detainee $detainee) => ['id' => $detainee->id, 'label' => $detainee->record_number.' — '.$detainee->family_name.' '.($detainee->post_name ? $detainee->post_name.' ' : '').$detainee->given_names]));
    }

    public function store(Request $request, Establishment $establishment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES)), 403);
        abort_unless($establishment->status === 'active', 403);
        $data = $request->validate([
            'detainee_id' => ['required', 'integer', 'exists:detainees,id'],
            'consulted_at' => ['required', 'date', 'before_or_equal:now'],
            'consultation_type' => ['required', Rule::in(['initial', 'routine', 'urgent', 'follow_up', 'other'])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'care_provided' => ['nullable', 'string', 'max:10000'],
            'referral_required' => ['required', 'boolean'],
            'referral_notes' => ['nullable', 'required_if:referral_required,1', 'string', 'max:5000'],
        ]);
        abort_unless(Detainee::query()->whereKey($data['detainee_id'])->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))->exists(), 422, 'Cette personne n’est pas actuellement détenue dans cet établissement.');

        DB::transaction(function () use ($data, $establishment, $user) {
            $consultation = MedicalConsultation::query()->create([
                ...$data,
                'establishment_id' => $establishment->id,
                'clinician_id' => $user->id,
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'medical.consultation',
                'subject_type' => 'medical_consultation',
                'subject_id' => $consultation->id,
                'summary' => 'Consultation médicale enregistrée.',
            ]);
        });

        return redirect()->route('clinic.index', ['establishment_id' => $establishment->id])->with('success', 'Consultation enregistrée dans le dossier médical.');
    }

    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'establishment_code' => ['required', 'string', 'max:80'],
            'detainee_record_number' => ['required', 'string', 'max:100'],
            'consulted_at' => ['required', 'date', 'before_or_equal:now'],
            'consultation_type' => ['required', Rule::in(['initial', 'routine', 'urgent', 'follow_up', 'other'])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'care_provided' => ['nullable', 'string', 'max:10000'],
            'referral_required' => ['required', 'boolean'],
            'referral_notes' => ['nullable', 'required_if:referral_required,1', 'string', 'max:5000'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishment = Establishment::query()->where('code', $data['establishment_code'])->where('status', 'active')->first();
        if (! $establishment) {
            throw ValidationException::withMessages(['establishment_code' => 'Code d’établissement inconnu ou inactif.']);
        }
        abort_unless($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES), 403);
        [$consultation, $duplicate] = DB::transaction(function () use ($data, $user, $establishment) {
            $existing = MedicalConsultation::query()->where('offline_reference', $data['offline_reference'])->first();
            if ($existing) {
                abort_unless($existing->establishment_id === $establishment->id, 409);
                return [$existing, true];
            }
            $detainee = Detainee::query()->where('record_number', $data['detainee_record_number'])
                ->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
                ->first();
            if (! $detainee) {
                throw ValidationException::withMessages(['detainee_record_number' => 'Aucun détenu actif portant ce numéro dans cet établissement.']);
            }
            $consultation = MedicalConsultation::query()->create([
                'offline_reference' => $data['offline_reference'],
                'detainee_id' => $detainee->id,
                'establishment_id' => $establishment->id,
                'clinician_id' => $user->id,
                'consulted_at' => $data['consulted_at'],
                'consultation_type' => $data['consultation_type'],
                'reason' => $data['reason'] ?? null,
                'observations' => $data['observations'] ?? null,
                'care_provided' => $data['care_provided'] ?? null,
                'referral_required' => (bool) $data['referral_required'],
                'referral_notes' => $data['referral_notes'] ?? null,
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'medical.consultation',
                'subject_type' => 'medical_consultation',
                'subject_id' => $consultation->id,
                'summary' => 'Consultation médicale enregistrée hors connexion.',
            ]);
            return [$consultation, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $duplicate, 'consultation_id' => $consultation->id]);
    }
}
