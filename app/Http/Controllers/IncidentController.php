<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    private const ROLES = ['director', 'agent'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishments = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name'])
            : $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', self::ROLES)->where('establishments.status', 'active')->orderBy('name')->get(['establishments.id', 'code', 'name']);
        abort_unless($establishments->isNotEmpty(), 403);
        $selectedId = (int) $request->query('establishment_id', $establishments->first()->id);
        abort_unless($establishments->contains('id', $selectedId), 403);
        $incidents = Incident::query()->with('reporter:id,name')->where('establishment_id', $selectedId)->latest('occurred_at')->paginate(25)->withQueryString();

        return Inertia::render('incidents/index', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'incidents' => $incidents,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->where('status', 'active')],
            'incident_type' => ['required', Rule::in(['violence', 'medical', 'escape_attempt', 'damage', 'fire', 'security', 'other'])],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'immediate_action' => ['nullable', 'string', 'max:5000'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole((int) $data['establishment_id'], self::ROLES)), 403);
        DB::transaction(function () use ($data, $user) {
            $incident = Incident::query()->create([...$data, 'reported_by' => $user->id, 'status' => 'open']);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $incident->establishment_id, 'event' => 'incident.reported', 'subject_type' => 'incident', 'subject_id' => $incident->id, 'summary' => 'Incident signalé']);
        });

        return redirect()->route('incidents.index', ['establishment_id' => $data['establishment_id']])->with('success', 'Incident enregistré dans le registre de l’établissement.');
    }

    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'establishment_code' => ['required', 'string', 'max:80'],
            'incident_type' => ['required', Rule::in(['violence', 'medical', 'escape_attempt', 'damage', 'fire', 'security', 'other'])],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'immediate_action' => ['nullable', 'string', 'max:5000'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishment = Establishment::query()->where('code', $data['establishment_code'])->where('status', 'active')->first();
        if (! $establishment) {
            throw ValidationException::withMessages(['establishment_code' => 'Code d’établissement inconnu ou inactif.']);
        }
        abort_unless($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES), 403);

        $incident = DB::transaction(function () use ($data, $user, $establishment) {
            $existing = Incident::query()->where('offline_reference', $data['offline_reference'])->first();
            if ($existing) {
                abort_unless($existing->establishment_id === $establishment->id, 409);
                return [$existing, true];
            }
            $incident = Incident::query()->create([
                'establishment_id' => $establishment->id,
                'offline_reference' => $data['offline_reference'],
                'incident_type' => $data['incident_type'],
                'severity' => $data['severity'],
                'occurred_at' => $data['occurred_at'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'],
                'immediate_action' => $data['immediate_action'] ?? null,
                'reported_by' => $user->id,
                'status' => 'open',
            ]);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $incident->establishment_id, 'event' => 'incident.reported', 'subject_type' => 'incident', 'subject_id' => $incident->id, 'summary' => 'Incident signalé']);
            return [$incident, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $incident[1], 'incident_id' => $incident[0]->id]);
    }

    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($incident->establishment_id, self::ROLES)), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved'])],
            'action_description' => ['required', 'string', 'max:5000'],
        ]);
        abort_unless($incident->status !== 'resolved', 422, 'Cet incident est déjà clôturé.');
        DB::transaction(function () use ($data, $incident, $user) {
            $incident->update([
                'status' => $data['status'],
                'resolved_by' => $data['status'] === 'resolved' ? $user->id : null,
                'resolved_at' => $data['status'] === 'resolved' ? now() : null,
            ]);
            IncidentAction::query()->create([
                'incident_id' => $incident->id,
                'action_at' => now(),
                'action_type' => $data['status'],
                'description' => $data['action_description'],
                'recorded_by' => $user->id,
            ]);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $incident->establishment_id, 'event' => 'incident.updated', 'subject_type' => 'incident', 'subject_id' => $incident->id, 'summary' => 'Suivi d’incident mis à jour']);
        });

        return redirect()->route('incidents.index', ['establishment_id' => $incident->establishment_id])->with('success', 'Suivi de l’incident enregistré.');
    }
}
