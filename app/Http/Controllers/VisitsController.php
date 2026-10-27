<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Detainee;
use App\Models\Establishment;
use App\Models\PrisonVisit;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VisitsController extends Controller
{
    private const ROLES = ['director', 'agent', 'custody'];

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
        $filters = $request->validate(['date' => ['nullable', 'date']]);
        $selectedDate = $filters['date'] ?? today()->toDateString();
        $visits = PrisonVisit::query()->with(['detainee:id,record_number,family_name,post_name,given_names', 'visitor:id,family_name,post_name,given_names,phone', 'recorder:id,name'])
            ->where('establishment_id', $selectedId)->whereDate('scheduled_at', $selectedDate)
            ->latest('scheduled_at')->paginate(50)->withQueryString();

        return Inertia::render('visits/index', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'selectedDate' => $selectedDate,
            'visits' => $visits,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function visitors(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishments = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name'])
            : $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', self::ROLES)->where('establishments.status', 'active')->orderBy('name')->get(['establishments.id', 'code', 'name']);
        abort_unless($user->is_central_admin || $establishments->isNotEmpty(), 403);

        $selectedId = (int) $request->query('establishment_id', $establishments->first()?->id);
        abort_unless($establishments->contains('id', $selectedId), 403);
        $visitors = Visitor::query()->withCount('visits')->where('establishment_id', $selectedId)
            ->orderBy('family_name')->orderBy('post_name')->orderBy('given_names')->paginate(25)->withQueryString();

        return Inertia::render('visitors/index', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'hasDetainees' => Detainee::query()->whereHas('stays', fn ($query) => $query->where('establishment_id', $selectedId)->whereNull('released_at'))->exists(),
            'visitors' => $visitors,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function create(Request $request): Response
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
        $visitor = $request->integer('visitor_id')
            ? Visitor::query()->where('establishment_id', $selectedId)->where('is_active', true)->find($request->integer('visitor_id'))
            : null;
        $detainee = $request->integer('detainee_id')
            ? Detainee::query()->whereKey($request->integer('detainee_id'))->whereHas('stays', fn ($query) => $query->where('establishment_id', $selectedId)->whereNull('released_at'))->first()
            : null;

        return Inertia::render('visits/create', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'hasDetainees' => $hasDetainees,
            'selectedVisitor' => $visitor ? ['id' => $visitor->id, 'family_name' => $visitor->family_name, 'post_name' => $visitor->post_name, 'given_names' => $visitor->given_names, 'date_of_birth' => ($visitor->date_of_birth ? Carbon::parse($visitor->date_of_birth)->toDateString() : null), 'place_of_birth' => $visitor->place_of_birth, 'document_type' => $visitor->document_type, 'document_reference' => $visitor->document_reference, 'phone' => $visitor->phone] : null,
            'selectedDetainee' => $detainee ? ['id' => $detainee->id, 'label' => $detainee->record_number.' — '.$detainee->family_name.' '.($detainee->post_name ? $detainee->post_name.' ' : '').$detainee->given_names] : null,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function searchDetainees(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeVisitAccess($request->user(), $establishment->id);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $detainees = Detainee::query()
            ->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
            ->where(fn ($query) => $query->where('record_number', 'like', "%{$term}%")
                ->orWhere('family_name', 'like', "%{$term}%")
                ->orWhere('post_name', 'like', "%{$term}%")
                ->orWhere('given_names', 'like', "%{$term}%"))
            ->orderBy('family_name')->orderBy('post_name')->orderBy('given_names')->limit(20)
            ->get(['id', 'record_number', 'family_name', 'post_name', 'given_names']);

        return response()->json($detainees->map(fn (Detainee $detainee) => ['id' => $detainee->id, 'label' => $detainee->record_number.' — '.$detainee->family_name.' '.($detainee->post_name ? $detainee->post_name.' ' : '').$detainee->given_names]));
    }

    public function searchVisitors(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeVisitAccess($request->user(), $establishment->id);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $visitors = Visitor::query()->where('establishment_id', $establishment->id)->where('is_active', true)
            ->where(fn ($query) => $query->where('family_name', 'like', "%{$term}%")
                ->orWhere('post_name', 'like', "%{$term}%")
                ->orWhere('given_names', 'like', "%{$term}%")
                ->orWhere('document_reference', 'like', "%{$term}%"))
            ->orderBy('family_name')->orderBy('given_names')->limit(20)->get();

        return response()->json($visitors->map(fn (Visitor $visitor) => ['id' => $visitor->id, 'label' => $visitor->family_name.' '.($visitor->post_name ? $visitor->post_name.' ' : '').$visitor->given_names, 'description' => $visitor->document_reference ?? $visitor->phone]));
    }

    public function storeVisitor(Request $request): RedirectResponse
    {
        $this->authorizeVisitAccess($request->user(), $request->integer('establishment_id'));
        $data = $request->validate([
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'visitor_id' => ['nullable', 'integer', Rule::exists('visitors', 'id')->where('is_active', true)->where('establishment_id', $request->input('establishment_id'))],
            'family_name' => ['required_without:visitor_id', 'nullable', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required_without:visitor_id', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'detainee_id' => ['nullable', 'integer', 'exists:detainees,id'],
        ]);
        if (! empty($data['detainee_id']) && ! Detainee::query()->whereKey($data['detainee_id'])->whereHas('stays', fn ($query) => $query->where('establishment_id', $data['establishment_id'])->whereNull('released_at'))->exists()) {
            throw ValidationException::withMessages(['detainee_id' => 'Cette personne n’est pas actuellement détenue dans cet établissement.']);
        }
        if (! empty($data['visitor_id'])) {
            $visitor = Visitor::query()->where('establishment_id', $data['establishment_id'])->where('is_active', true)->findOrFail((int) $data['visitor_id']);
        } else {
            $visitor = Visitor::query()->create([
                'establishment_id' => $data['establishment_id'], 'family_name' => $data['family_name'], 'post_name' => $data['post_name'] ?? null, 'given_names' => $data['given_names'],
                'date_of_birth' => $data['date_of_birth'] ?? null, 'place_of_birth' => $data['place_of_birth'] ?? null,
                'document_type' => $data['document_type'] ?? null, 'document_reference' => $data['document_reference'] ?? null,
                'phone' => $data['phone'] ?? null, 'is_active' => true,
            ]);
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'establishment_id' => $data['establishment_id'], 'event' => 'visitor.created', 'subject_type' => 'visitor', 'subject_id' => $visitor->id, 'summary' => 'Visiteur enregistré']);
        }

        return redirect()->route('visits.create', array_filter(['establishment_id' => $data['establishment_id'], 'visitor_id' => $visitor->id, 'detainee_id' => $data['detainee_id'] ?? null]))->with('success', 'Visiteur sélectionné. Vérifiez les renseignements puis enregistrez son arrivée.');
    }

    public function store(Request $request): RedirectResponse
    {
        $establishment = Establishment::query()->findOrFail($request->integer('establishment_id'));
        $this->authorizeVisitAccess($request->user(), $establishment->id);
        $data = $request->validate([
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'detainee_id' => ['required', 'integer', 'exists:detainees,id'],
            'visitor_id' => ['nullable', 'integer', Rule::exists('visitors', 'id')->where('is_active', true)->where('establishment_id', $request->input('establishment_id'))],
            'family_name' => ['required_without:visitor_id', 'nullable', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required_without:visitor_id', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'visit_type' => ['required', Rule::in(['family', 'legal', 'official', 'other'])],
            'relationship' => ['nullable', 'string', 'max:100'],
        ]);
        abort_unless(Detainee::query()->whereKey($data['detainee_id'])->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))->exists(), 422, 'Cette personne n’est pas actuellement détenue dans cet établissement.');

        $arrivedAt = now();
        DB::transaction(function () use ($data, $request, $establishment, $arrivedAt) {
            if (! empty($data['visitor_id'])) {
                $visitor = Visitor::query()->findOrFail((int) $data['visitor_id']);
                $visitor->update([
                    'family_name' => ($data['family_name'] ?? null) ?: $visitor->family_name,
                    'post_name' => $data['post_name'] ?? $visitor->post_name,
                    'given_names' => ($data['given_names'] ?? null) ?: $visitor->given_names,
                    'date_of_birth' => $data['date_of_birth'] ?? $visitor->date_of_birth,
                    'place_of_birth' => $data['place_of_birth'] ?? $visitor->place_of_birth,
                    'document_type' => $data['document_type'] ?? $visitor->document_type,
                    'document_reference' => $data['document_reference'] ?? $visitor->document_reference,
                    'phone' => $data['phone'] ?? $visitor->phone,
                ]);
            } else {
                $visitor = Visitor::query()->create([
                    'establishment_id' => $establishment->id,
                    'family_name' => $data['family_name'],
                    'post_name' => $data['post_name'] ?? null,
                    'given_names' => $data['given_names'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'document_type' => $data['document_type'] ?? null,
                    'document_reference' => $data['document_reference'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'is_active' => true,
                ]);
                AuditLog::query()->create([
                    'actor_id' => $request->user()->id,
                    'establishment_id' => $establishment->id,
                    'event' => 'visitor.created',
                    'subject_type' => 'visitor',
                    'subject_id' => $visitor->id,
                    'summary' => 'Visiteur enregistré lors de la création d’une visite',
                ]);
            }

            $visit = PrisonVisit::query()->create([
                'establishment_id' => $establishment->id,
                'detainee_id' => $data['detainee_id'],
                'visitor_id' => $visitor->id,
                'visit_type' => $data['visit_type'],
                'relationship' => $data['relationship'] ?? null,
                'scheduled_at' => $arrivedAt,
                'arrived_at' => $arrivedAt,
                'status' => 'arrived',
                'recorded_by' => $request->user()->id,
            ]);
            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'establishment_id' => $establishment->id,
                'event' => 'visit.arrived',
                'subject_type' => 'visit',
                'subject_id' => $visit->id,
                'summary' => 'Arrivée du visiteur enregistrée',
                'metadata' => ['status' => 'arrived'],
            ]);
        });

        return redirect()->route('visits.index', ['establishment_id' => $establishment->id])->with('success', 'Arrivée du visiteur enregistrée. À sa sortie, marquez la visite comme terminée.');
    }

    public function updateStatus(Request $request, PrisonVisit $visit): RedirectResponse
    {
        $this->authorizeVisitAccess($request->user(), $visit->establishment_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['arrived', 'completed', 'cancelled'])],
            'cancellation_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:2000'],
        ]);
        abort_unless($visit->status === 'scheduled' || ($visit->status === 'arrived' && in_array($data['status'], ['completed', 'cancelled'], true)), 422, 'Cette visite ne peut plus changer de statut.');
        $visit->status = $data['status'];
        if ($data['status'] === 'arrived') {
            $visit->arrived_at = now();
        }
        if (in_array($data['status'], ['completed', 'cancelled'], true)) {
            $visit->ended_at = now();
        }
        if ($data['status'] === 'cancelled') {
            $visit->cancellation_reason = $data['cancellation_reason'];
        }
        $visit->save();
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'establishment_id' => $visit->establishment_id,
            'event' => 'visit.'.$data['status'],
            'subject_type' => 'visit',
            'subject_id' => $visit->id,
            'summary' => 'Statut de visite mis à jour',
            'metadata' => ['status' => $data['status']],
        ]);

        return redirect()->route('visits.index', ['establishment_id' => $visit->establishment_id])->with('success', 'Statut de la visite mis à jour.');
    }

    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'establishment_code' => ['required', 'string', 'max:80'],
            'detainee_record_number' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'visit_type' => ['required', Rule::in(['family', 'legal', 'official', 'other'])],
            'relationship' => ['nullable', 'string', 'max:100'],
            'captured_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $user = $request->user();
        abort_unless($user?->is_active, 403);

        $establishment = Establishment::query()->where('code', $data['establishment_code'])->where('status', 'active')->firstOrFail();
        $this->authorizeVisitAccess($user, $establishment->id);

        [$visit, $duplicate] = DB::transaction(function () use ($data, $user, $establishment) {
            $existing = PrisonVisit::query()->where('offline_reference', $data['offline_reference'])->first();
            if ($existing) {
                abort_unless($existing->establishment_id === $establishment->id, 409, 'Cette référence a déjà été utilisée dans un autre établissement.');

                return [$existing, true];
            }

            $detainee = Detainee::query()->where('record_number', $data['detainee_record_number'])
                ->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
                ->first();
            if (! $detainee) {
                throw ValidationException::withMessages(['detainee_record_number' => 'Aucun détenu actif ne correspond à ce numéro dans cet établissement.']);
            }
            $visitor = Visitor::query()->create([
                'establishment_id' => $establishment->id,
                'family_name' => $data['family_name'],
                'post_name' => $data['post_name'] ?? null,
                'given_names' => $data['given_names'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'document_reference' => $data['document_reference'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);
            $visit = PrisonVisit::query()->create([
                'offline_reference' => $data['offline_reference'],
                'establishment_id' => $establishment->id,
                'detainee_id' => $detainee->id,
                'visitor_id' => $visitor->id,
                'visit_type' => $data['visit_type'],
                'relationship' => $data['relationship'] ?? null,
                'scheduled_at' => $data['captured_at'],
                'arrived_at' => $data['captured_at'],
                'status' => 'arrived',
                'recorded_by' => $user->id,
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'visitor.created',
                'subject_type' => 'visitor',
                'subject_id' => $visitor->id,
                'summary' => 'Visiteur enregistré après synchronisation hors connexion.',
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'visit.arrived',
                'subject_type' => 'visit',
                'subject_id' => $visit->id,
                'summary' => 'Arrivée d’une visite synchronisée hors connexion.',
                'metadata' => ['offline' => true],
            ]);

            return [$visit, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $duplicate, 'visit_id' => $visit->id]);
    }

    private function authorizeVisitAccess(User $user, int $establishmentId): void
    {
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishmentId, self::ROLES)), 403);
    }
}
