<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\CellAssignment;
use App\Models\Detainee;
use App\Models\DetaineeBiometric;
use App\Models\DetentionStay;
use App\Models\Establishment;
use App\Models\EstablishmentBuilding;
use App\Models\Magistrate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DetaineeController extends Controller
{
    private const ACCESS_ROLES = ['director', 'agent', 'judicial', 'custody'];

    private const IDENTITY_EDIT_ROLES = ['director', 'judicial'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $this->accessibleEstablishmentIds($user);
        abort_unless($user->is_central_admin || $establishmentIds->isNotEmpty(), 403);

        $query = Detainee::query()
            ->whereHas('stays', function ($query) use ($user, $establishmentIds) {
                $query->whereNull('released_at');
                if (! $user->is_central_admin) {
                    $query->whereIn('establishment_id', $establishmentIds);
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->query('search'));
                $query->where(fn ($matches) => $matches->where('record_number', 'like', "%{$term}%")
                    ->orWhere('family_name', 'like', "%{$term}%")
                    ->orWhere('post_name', 'like', "%{$term}%")
                    ->orWhere('given_names', 'like', "%{$term}%"));
            })
            ->orderBy('family_name')->orderBy('post_name')->orderBy('given_names');
        $detainees = $query->with([
            'currentStay.establishment',
            'currentStay.cellAssignments' => fn ($assignments) => $assignments->whereNull('ended_at')->with('cell.building'),
            'cases' => fn ($query) => $query->latest(),
        ])
            ->paginate(25, ['id', 'record_number', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'sex'])->withQueryString();

        return Inertia::render('detainees/index', [
            'detainees' => $detainees,
            'search' => (string) $request->query('search', ''),
            'canCreate' => true,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $this->accessibleEstablishmentIds($user);
        abort_unless($user->is_central_admin || $establishmentIds->isNotEmpty(), 403);

        $query = Establishment::query()->where('status', 'active')->orderBy('name');
        if (! $user->is_central_admin) {
            $query->whereIn('id', $establishmentIds);
        }

        $establishments = $query->get(['id', 'code', 'name']);
        $establishments->each(function ($establishment) {
            $buildings = EstablishmentBuilding::query()->where('establishment_id', $establishment->id)->where('status', 'active')
                ->whereHas('cells', fn ($cells) => $cells->where('status', 'active'))
                ->orderBy('code')->get(['id', 'code', 'name']);
            $establishment->setAttribute('available_buildings', $buildings->map(fn ($building) => ['id' => $building->id, 'label' => $building->code.' — '.$building->name]));
            $establishment->setAttribute('has_active_cells', $buildings->isNotEmpty());
        });

        return Inertia::render('detainees/create', [
            'establishments' => $establishments,
            'magistrates' => Magistrate::query()->where('is_active', true)->orderBy('family_name')->orderBy('given_names')->get(['id', 'family_name', 'post_name', 'given_names', 'function', 'court_name', 'court_level']),
        ]);
    }

    public function editIdentity(Request $request, Detainee $detainee): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $allowed = $detainee->stays()->pluck('establishment_id')->contains(fn ($id) => $user->hasEstablishmentRole((int) $id, self::IDENTITY_EDIT_ROLES));
        abort_unless($user->is_central_admin || $allowed, 403);

        return Inertia::render('detainees/edit-identity', ['detainee' => $detainee->only(['id', 'record_number', 'family_name', 'post_name', 'given_names', 'date_of_birth', 'sex'])]);
    }

    public function updateIdentity(Request $request, Detainee $detainee): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentId = $detainee->stays()->pluck('establishment_id')->first(fn ($id) => $user->hasEstablishmentRole((int) $id, self::IDENTITY_EDIT_ROLES));
        abort_unless($user->is_central_admin || $establishmentId, 403);
        $data = $request->validate([
            'family_name' => ['required', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
        ]);
        DB::transaction(function () use ($detainee, $user, $establishmentId, $data) {
            $locked = Detainee::query()->whereKey($detainee->id)->lockForUpdate()->firstOrFail();
            $locked->update($data);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishmentId,
                'event' => 'detainee.identity_updated',
                'subject_type' => 'detainee',
                'subject_id' => $locked->id,
                'summary' => 'Identité du dossier corrigée; les valeurs personnelles ne sont pas copiées dans le journal.',
            ]);
        });

        return redirect()->route('detainees.show', $detainee)->with('success', 'Identité mise à jour.');
    }

    public function show(Request $request, Detainee $detainee): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $stays = $detainee->stays()->with([
            'establishment',
            'cellAssignments' => fn ($query) => $query->with('cell.building')->orderByDesc('assigned_at'),
        ])->latest('admitted_at')->get();
        $accessibleEstablishmentIds = $stays->filter(fn ($stay) => $user->hasEstablishmentRole($stay->establishment_id, self::ACCESS_ROLES))->pluck('establishment_id')->unique()->values();
        abort_unless($user->is_central_admin || $accessibleEstablishmentIds->isNotEmpty(), 403);
        $currentStay = $stays->first(fn ($stay) => $stay->released_at === null);
        $canTransfer = $user->is_central_admin || ($currentStay && $user->hasEstablishmentRole($currentStay->establishment_id, ['director', 'custody']));
        $canEditIdentity = $user->is_central_admin || $accessibleEstablishmentIds->contains(fn ($id) => $user->hasEstablishmentRole((int) $id, self::IDENTITY_EDIT_ROLES));
        $transferIds = $user->is_central_admin ? collect() : $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', ['director', 'custody'])->where('establishments.status', 'active')->pluck('establishments.id');

        $detainee->load([
            'cases' => fn ($query) => $query->latest()->with(['orders.magistrate', 'sentenceAdjustments']),
            'biometrics' => fn ($query) => $query->where('status', 'active')->select(['id', 'detainee_id', 'finger_position', 'capture_device', 'collection_basis', 'captured_at', 'captured_by', 'status'])->latest(),
        ]);
        $visits = $detainee->visits()->with(['visitor:id,family_name,post_name,given_names', 'establishment:id,name'])
            ->when(! $user->is_central_admin, fn ($query) => $query->whereIn('establishment_id', $accessibleEstablishmentIds))
            ->latest('scheduled_at')->limit(50)
            ->get(['id', 'detainee_id', 'visitor_id', 'establishment_id', 'visit_type', 'relationship', 'status', 'scheduled_at']);

        return Inertia::render('detainees/show', [
            'detainee' => $detainee,
            'stays' => $stays,
            'visits' => $visits,
            'photoUrl' => $detainee->photo_path ? route('detainees.photo', $detainee) : null,
            'transferEstablishments' => Establishment::query()->where('status', 'active')->when(! $user->is_central_admin, fn ($query) => $query->whereIn('id', $transferIds))->orderBy('name')->get(['id', 'code', 'name']),
            'canTransfer' => $canTransfer,
            'canEditIdentity' => $canEditIdentity,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function searchTransferCells(Request $request, Establishment $establishment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, ['director', 'custody'])), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $cells = Cell::query()->where('status', 'active')

            ->whereHas('building', fn ($query) => $query->where('establishment_id', $establishment->id)->where('status', 'active'))
            ->where(fn ($query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhereHas('building', fn ($building) => $building->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->with('building:id,code,name')->withCount(['activeAssignments as occupied'])->orderBy('code')->limit(20)->get(['id', 'code', 'name', 'capacity']);

        return response()->json($cells->map(fn (Cell $cell) => ['id' => $cell->id, 'label' => $cell->building->code.' — '.$cell->code.($cell->name ? ' · '.$cell->name : ''), 'description' => $cell->occupied.'/'.$cell->capacity.' place(s) occupée(s)'.($cell->occupied > $cell->capacity ? ' · Surpopulation : +'.($cell->occupied - $cell->capacity) : '')]));
    }

    public function transfer(Request $request, Detainee $detainee): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $data = $request->validate([
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->where('status', 'active')],
            'cell_id' => ['required', 'integer', 'exists:cells,id'],
            'transfer_reference' => ['required', 'string', 'max:255'],
            'transfer_reason' => ['required', 'string', 'max:1000'],
            'transferred_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        DB::transaction(function () use ($data, $detainee, $user) {
            $stay = $detainee->stays()->whereNull('released_at')->lockForUpdate()->firstOrFail();
            $sourceEstablishmentId = $stay->establishment_id;
            $destination = Establishment::query()->findOrFail((int) $data['establishment_id']);
            abort_unless($user->is_central_admin || ($user->hasEstablishmentRole($stay->establishment_id, ['director', 'custody']) && $user->hasEstablishmentRole($destination->id, ['director', 'custody'])), 403);
            abort_if(Carbon::parse($data['transferred_at'])->lt($stay->admitted_at), 422, 'La date du transfert ne peut pas précéder l’admission.');

            $cell = Cell::query()->whereKey($data['cell_id'])->lockForUpdate()->with('building')->firstOrFail();
            abort_unless($cell->building->establishment_id === $destination->id && $cell->building->status === 'active' && $cell->status === 'active', 422, 'La cellule doit être active et dépendre de l’établissement de destination.');
            $oldAssignment = CellAssignment::query()->where('detainee_id', $detainee->id)->whereNull('ended_at')->lockForUpdate()->first();
            abort_if($oldAssignment && $oldAssignment->cell_id === $cell->id, 422, 'La personne est déjà affectée à cette cellule.');
            $at = Carbon::parse($data['transferred_at']);
            if ($oldAssignment) {
                abort_if($at->lt($oldAssignment->assigned_at), 422, 'La date du mouvement ne peut pas précéder l’affectation actuelle.');
                $oldCell = Cell::query()->whereKey($oldAssignment->cell_id)->lockForUpdate()->firstOrFail();
                $occupiedBefore = CellAssignment::query()->where('cell_id', $oldCell->id)->whereNull('ended_at')->count();
                $oldAssignment->update(['ended_at' => $at, 'end_reason' => 'Transfert · '.$data['transfer_reference'].' · '.$data['transfer_reason']]);
                $occupiedAfter = CellAssignment::query()->where('cell_id', $oldCell->id)->whereNull('ended_at')->count();
                $this->logResolvedOvercrowding($user, $sourceEstablishmentId, $oldCell, $occupiedBefore, $occupiedAfter);
            }

            if ($destination->id !== $stay->establishment_id) {
                $stay->update(['released_at' => $at, 'release_reason' => 'Transfert vers '.$destination->name.' · '.$data['transfer_reason'], 'release_authority_reference' => $data['transfer_reference']]);
                $stay = DetentionStay::query()->create([
                    'detainee_id' => $detainee->id, 'detention_case_id' => $stay->detention_case_id,
                    'establishment_id' => $destination->id, 'detention_order_id' => $stay->detention_order_id,
                    'previous_stay_id' => $stay->id, 'admitted_at' => $at, 'recorded_by' => $user->id,
                ]);
            }

            CellAssignment::query()->create(['cell_id' => $cell->id, 'detainee_id' => $detainee->id, 'detention_stay_id' => $stay->id, 'assigned_by' => $user->id, 'assigned_at' => $at]);
            $occupiedInDestination = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupiedInDestination > $cell->capacity) {
                AuditLog::query()->create([
                    'actor_id' => $user->id, 'establishment_id' => $destination->id, 'event' => 'cell.overcrowding',
                    'subject_type' => 'cell', 'subject_id' => $cell->id,
                    'summary' => 'Surpopulation après mouvement : '.$occupiedInDestination.'/'.$cell->capacity.' places.',
                    'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupiedInDestination, 'excess' => $occupiedInDestination - $cell->capacity, 'detainee_id' => $detainee->id],
                ]);
            }
            AuditLog::query()->create([
                'actor_id' => $user->id, 'establishment_id' => $destination->id,
                'event' => $destination->id === $sourceEstablishmentId ? 'detainee.cell_moved' : 'detainee.transferred',
                'subject_type' => 'detainee', 'subject_id' => $detainee->id,
                'summary' => $destination->id === $sourceEstablishmentId ? 'Changement de cellule enregistré' : 'Transfert interétablissements enregistré',
                'metadata' => ['from_establishment_id' => $sourceEstablishmentId, 'to_establishment_id' => $destination->id],
            ]);
        });

        return redirect()->route('detainees.show', $detainee)->with('success', 'Mouvement enregistré avec sa référence et conservé dans l’historique du dossier.');
    }

    public function syncOfflineTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'detainee_record_number' => ['required', 'string', 'max:100'],
            'destination_establishment_code' => ['required', 'string', 'max:80'],
            'destination_building_code' => ['required', 'string', 'max:100'],
            'destination_cell_code' => ['required', 'string', 'max:100'],
            'transfer_reference' => ['required', 'string', 'max:255'],
            'transfer_reason' => ['required', 'string', 'max:1000'],
            'transferred_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $destination = Establishment::query()->where('code', $data['destination_establishment_code'])->where('status', 'active')->first();
        if (! $destination) {
            throw ValidationException::withMessages(['destination_establishment_code' => 'Code d’établissement de destination inconnu ou inactif.']);
        }

        $result = DB::transaction(function () use ($data, $user, $destination) {
            $existing = CellAssignment::query()->where('offline_reference', $data['offline_reference'])
                ->with(['cell.building', 'detentionStay'])->first();
            if ($existing) {
                $this->authorizeOfflineTransferDuplicate($user, $existing, $destination->id);

                return [$existing->id, true];
            }

            $detainee = Detainee::query()->where('record_number', $data['detainee_record_number'])->lockForUpdate()->first();
            if (! $detainee) {
                throw ValidationException::withMessages(['detainee_record_number' => 'Aucun détenu ne correspond à ce numéro de dossier.']);
            }
            $stay = $detainee->stays()->whereNull('released_at')->lockForUpdate()->first();
            if (! $stay) {
                throw ValidationException::withMessages(['detainee_record_number' => 'Ce détenu n’a actuellement aucun séjour actif.']);
            }
            abort_unless($user->is_central_admin || ($user->hasEstablishmentRole($stay->establishment_id, ['director', 'custody']) && $user->hasEstablishmentRole($destination->id, ['director', 'custody'])), 403);

            $at = Carbon::parse($data['transferred_at']);
            if ($at->lt($stay->admitted_at)) {
                throw ValidationException::withMessages(['transferred_at' => 'La date du mouvement ne peut pas précéder l’admission actuelle.']);
            }
            $building = EstablishmentBuilding::query()->where('establishment_id', $destination->id)
                ->where('code', $data['destination_building_code'])->where('status', 'active')->first();
            if (! $building) {
                throw ValidationException::withMessages(['destination_building_code' => 'Bâtiment inconnu ou inactif dans cet établissement.']);
            }
            $cell = Cell::query()->where('establishment_building_id', $building->id)->where('code', $data['destination_cell_code'])
                ->where('status', 'active')->with('building')->lockForUpdate()->first();
            if (! $cell) {
                throw ValidationException::withMessages(['destination_cell_code' => 'Cellule inconnue ou inactive dans cet établissement.']);
            }
            $oldAssignment = CellAssignment::query()->where('detainee_id', $detainee->id)->whereNull('ended_at')->lockForUpdate()->first();
            if ($oldAssignment && $oldAssignment->cell_id === $cell->id) {
                throw ValidationException::withMessages(['destination_cell_code' => 'Le détenu est déjà affecté à cette cellule.']);
            }
            if ($oldAssignment && $at->lt($oldAssignment->assigned_at)) {
                throw ValidationException::withMessages(['transferred_at' => 'La date du mouvement ne peut pas précéder l’affectation actuelle.']);
            }
            if ($oldAssignment) {
                $oldCell = Cell::query()->whereKey($oldAssignment->cell_id)->lockForUpdate()->first();
                $occupiedBefore = $oldCell ? CellAssignment::query()->where('cell_id', $oldCell->id)->whereNull('ended_at')->count() : 0;
                $oldAssignment->update(['ended_at' => $at, 'end_reason' => 'Transfert · '.$data['transfer_reference'].' · '.$data['transfer_reason']]);
                if ($oldCell) {
                    $occupiedAfter = CellAssignment::query()->where('cell_id', $oldCell->id)->whereNull('ended_at')->count();
                    $this->logResolvedOvercrowding($user, $stay->establishment_id, $oldCell, $occupiedBefore, $occupiedAfter);
                }
            }

            $sourceEstablishmentId = $stay->establishment_id;
            if ($destination->id !== $sourceEstablishmentId) {
                $stay->update(['released_at' => $at, 'release_reason' => 'Transfert vers '.$destination->name.' · '.$data['transfer_reason'], 'release_authority_reference' => $data['transfer_reference']]);
                $stay = DetentionStay::query()->create([
                    'detainee_id' => $detainee->id,
                    'detention_case_id' => $stay->detention_case_id,
                    'establishment_id' => $destination->id,
                    'detention_order_id' => $stay->detention_order_id,
                    'previous_stay_id' => $stay->id,
                    'admitted_at' => $at,
                    'recorded_by' => $user->id,
                ]);
            }
            $assignment = CellAssignment::query()->create([
                'offline_reference' => $data['offline_reference'],
                'source_establishment_id' => $sourceEstablishmentId,
                'cell_id' => $cell->id,
                'detainee_id' => $detainee->id,
                'detention_stay_id' => $stay->id,
                'assigned_by' => $user->id,
                'assigned_at' => $at,
            ]);
            $occupied = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupied > $cell->capacity) {
                AuditLog::query()->create([
                    'actor_id' => $user->id,
                    'establishment_id' => $destination->id,
                    'event' => 'cell.overcrowding',
                    'subject_type' => 'cell',
                    'subject_id' => $cell->id,
                    'summary' => 'Surpopulation après mouvement synchronisé : '.$occupied.'/'.$cell->capacity.' places.',
                    'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupied, 'excess' => $occupied - $cell->capacity, 'detainee_id' => $detainee->id],
                ]);
            }
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $destination->id,
                'event' => $destination->id === $sourceEstablishmentId ? 'detainee.cell_moved' : 'detainee.transferred',
                'subject_type' => 'detainee',
                'subject_id' => $detainee->id,
                'summary' => 'Mouvement de détenu synchronisé hors connexion.',
                'metadata' => ['from_establishment_id' => $sourceEstablishmentId, 'to_establishment_id' => $destination->id],
            ]);

            return [$assignment->id, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $result[1], 'assignment_id' => $result[0]]);
    }

    private function authorizeOfflineTransferDuplicate(User $user, CellAssignment $assignment, int $destinationId): void
    {
        $sourceId = $assignment->source_establishment_id ?? $assignment->detentionStay?->establishment_id;
        $actualDestinationId = $assignment->cell?->building?->establishment_id;
        abort_unless($actualDestinationId === $destinationId, 409);
        abort_unless($user->is_central_admin || ($sourceId && $user->hasEstablishmentRole($sourceId, ['director', 'custody']) && $user->hasEstablishmentRole($destinationId, ['director', 'custody'])), 403);
    }

    public function photo(Request $request, Detainee $detainee): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $canView = $user->is_central_admin || $detainee->stays()->get(['establishment_id'])->contains(
            fn ($stay) => $user->hasEstablishmentRole($stay->establishment_id, self::ACCESS_ROLES),
        );
        abort_unless($canView, 403);
        abort_unless($detainee->photo_path && Storage::disk('local')->exists($detainee->photo_path), 404);

        return Storage::disk('local')->response($detainee->photo_path, 'photo-'.$detainee->record_number, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function searchAdmissionBuildings(Request $request, Establishment $establishment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ACCESS_ROLES)), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $matches = EstablishmentBuilding::query()->where('establishment_id', $establishment->id)->where('status', 'active')
            ->whereHas('cells', fn ($cells) => $cells->where('status', 'active'))
            ->where(fn ($query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))
            ->orderBy('code')->limit(20)->get(['id', 'code', 'name']);

        return response()->json($matches->map(fn (EstablishmentBuilding $building) => ['id' => $building->id, 'label' => $building->code.' — '.$building->name]));
    }

    public function searchAdmissionCells(Request $request, Establishment $establishment, EstablishmentBuilding $building): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ACCESS_ROLES)), 403);
        abort_unless($building->establishment_id === $establishment->id && $building->status === 'active', 404);
        $matches = Cell::query()->where('cells.establishment_building_id', $building->id)->where('cells.status', 'active')

            ->withCount(['activeAssignments as occupied'])
            ->orderBy('cells.code')->get(['id', 'code', 'name', 'capacity']);

        return response()->json($matches->map(fn (Cell $cell) => ['id' => $cell->id, 'label' => $cell->code.($cell->name ? ' — '.$cell->name : ''), 'description' => $cell->occupied.'/'.$cell->capacity.' place(s) occupée(s)'.($cell->occupied > $cell->capacity ? ' · Surpopulation : +'.($cell->occupied - $cell->capacity) : '')]));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $validated = $request->validate([
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'cell_id' => ['required', 'integer', 'exists:cells,id'],
            'family_name' => ['required', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'fingerprint_template' => ['nullable', 'file', 'max:32'],
            'finger_position' => ['required_with:fingerprint_template', 'nullable', Rule::in(['left_thumb', 'left_index', 'right_thumb', 'right_index', 'other'])],
            'capture_device' => ['required_with:fingerprint_template', 'nullable', 'string', 'max:255'],
            'collection_basis' => ['required_with:fingerprint_template', 'nullable', 'string', 'max:255'],
            'case_reference' => ['nullable', 'string', 'max:255'],
            'case_type' => ['required', Rule::in(['pretrial', 'convicted'])],
            'court_name' => ['required', 'string', 'max:255'],
            'court_level' => ['nullable', 'string', 'max:255'],
            'judgment_final_at' => ['nullable', 'required_if:case_type,convicted', 'date', 'before_or_equal:today'],
            'order_reference' => ['required', 'string', 'max:255'],
            'magistrate_id' => ['nullable', 'integer', Rule::exists('magistrates', 'id')->where('is_active', true)],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'effective_at' => ['required', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:effective_at'],
            'unit_reference' => ['nullable', 'string', 'max:255'],
            'title_verified' => ['accepted'],
        ]);

        $establishment = Establishment::query()->findOrFail((int) $validated['establishment_id']);
        abort_unless(
            $user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ACCESS_ROLES),
            403,
        );

        DB::transaction(function () use ($validated, $user, $establishment, $request) {
            $detainee = Detainee::query()->create([
                'record_number' => 'DET-'.Str::ulid(),
                'family_name' => $validated['family_name'],
                'post_name' => $validated['post_name'] ?? null,
                'given_names' => $validated['given_names'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'sex' => $validated['sex'],
                'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('detainees/photos', 'local') : null,
                'photo_captured_at' => $request->hasFile('photo') ? now() : null,
                'photo_recorded_by' => $request->hasFile('photo') ? $user->id : null,
                'created_by' => $user->id,
            ]);

            $case = $detainee->cases()->create([
                'case_reference' => $validated['case_reference'] ?? null,
                'case_type' => $validated['case_type'],
                'court_name' => $validated['court_name'],
                'court_level' => $validated['court_level'] ?? null,
                'judgment_final_at' => $validated['judgment_final_at'] ?? null,
                'case_status' => 'open',
                'created_by' => $user->id,
            ]);

            $order = $case->orders()->create([
                'order_type' => $validated['case_type'] === 'pretrial' ? 'pretrial_detention' : 'sentence',
                'magistrate_id' => $validated['magistrate_id'] ?? null,
                'reference' => $validated['order_reference'],
                'issuing_authority' => $validated['issuing_authority'],
                'issued_at' => $validated['issued_at'] ?? null,
                'effective_at' => $validated['effective_at'],
                'expires_at' => $validated['expires_at'] ?? null,
                'status' => 'active',
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            $stay = $detainee->stays()->create([
                'detention_case_id' => $case->id,
                'establishment_id' => $establishment->id,
                'detention_order_id' => $order->id,
                'unit_reference' => $validated['unit_reference'] ?? null,
                'admitted_at' => now(),
                'recorded_by' => $user->id,
            ]);

            $cell = Cell::query()->whereKey($validated['cell_id'])->lockForUpdate()->with('building')->firstOrFail();
            if ($cell->building->establishment_id !== $establishment->id || $cell->building->status !== 'active' || $cell->status !== 'active') {
                throw ValidationException::withMessages(['cell_id' => 'La cellule choisie ne dépend pas de cet établissement ou n’est pas active.']);
            }
            CellAssignment::query()->create([
                'cell_id' => $cell->id, 'detainee_id' => $detainee->id, 'detention_stay_id' => $stay->id,
                'assigned_by' => $user->id, 'assigned_at' => now(),
            ]);
            $occupiedInCell = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupiedInCell > $cell->capacity) {
                AuditLog::query()->create([
                    'actor_id' => $user->id, 'establishment_id' => $establishment->id, 'event' => 'cell.overcrowding',
                    'subject_type' => 'cell', 'subject_id' => $cell->id,
                    'summary' => 'Surpopulation après admission : '.$occupiedInCell.'/'.$cell->capacity.' places.',
                    'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupiedInCell, 'excess' => $occupiedInCell - $cell->capacity, 'detainee_id' => $detainee->id],
                ]);
            }
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $establishment->id, 'event' => 'detainee.admitted', 'subject_type' => 'detainee', 'subject_id' => $detainee->id, 'summary' => 'Admission et affectation enregistrées']);

            if ($request->hasFile('fingerprint_template')) {
                DetaineeBiometric::query()->create([
                    'detainee_id' => $detainee->id,
                    'finger_position' => $validated['finger_position'],
                    // The model's encrypted cast encrypts this raw content at rest.
                    // Keep the binary string intact to avoid Base64 inflating the ciphertext beyond SQL TEXT limits.
                    'encrypted_template' => $request->file('fingerprint_template')->getContent(),
                    'capture_device' => $validated['capture_device'],
                    'collection_basis' => $validated['collection_basis'],
                    'captured_at' => now(),
                    'captured_by' => $user->id,
                ]);
            }
        });

        return redirect()->route('detainees.index');
    }

    public function release(Request $request, Detainee $detainee): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $validated = $request->validate([
            'release_reason' => ['required', Rule::in(['release_order', 'sentence_completed', 'conditional_release', 'other'])],
            'release_authority_reference' => ['required', 'string', 'max:255'],
            'release_authority' => ['required', 'string', 'max:255'],
            'released_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        DB::transaction(function () use ($detainee, $user, $validated) {
            $stay = $detainee->stays()->whereNull('released_at')->with('detentionOrder')->lockForUpdate()->firstOrFail();
            abort_unless($user->is_central_admin || $user->hasEstablishmentRole($stay->establishment_id, ['director', 'judicial', 'custody']), 403);
            if (Carbon::parse($validated['released_at'])->lt($stay->admitted_at)) {
                abort(422, 'La date de libération ne peut pas précéder l’admission.');
            }
            $activeAssignments = $stay->cellAssignments()->whereNull('ended_at')->get(['id', 'cell_id']);
            $occupancyBeforeByCell = [];
            foreach ($activeAssignments->pluck('cell_id')->unique()->sort()->values() as $cellId) {
                $cell = Cell::query()->whereKey($cellId)->lockForUpdate()->first();
                if ($cell) {
                    $occupancyBeforeByCell[$cellId] = ['cell' => $cell, 'occupied' => CellAssignment::query()->where('cell_id', $cellId)->whereNull('ended_at')->count()];
                }
            }
            $stay->cellAssignments()->whereNull('ended_at')->update(['ended_at' => $validated['released_at'], 'end_reason' => 'Libération']);
            foreach ($occupancyBeforeByCell as $cellId => $state) {
                $occupiedAfter = CellAssignment::query()->where('cell_id', $cellId)->whereNull('ended_at')->count();
                $this->logResolvedOvercrowding($user, $stay->establishment_id, $state['cell'], $state['occupied'], $occupiedAfter);
            }
            $stay->update([
                'released_at' => $validated['released_at'],
                'release_reason' => $validated['release_reason'].' — '.$validated['release_authority'],
                'release_authority_reference' => $validated['release_authority_reference'],
            ]);
            $stay->detentionOrder?->update(['status' => 'released']);
            AuditLog::query()->create(['actor_id' => $user->id, 'establishment_id' => $stay->establishment_id, 'event' => 'detainee.released', 'subject_type' => 'detainee', 'subject_id' => $detainee->id, 'summary' => 'Libération enregistrée']);
        });

        return redirect()->route('detainees.index');
    }

    private function logResolvedOvercrowding(User $user, int $establishmentId, Cell $cell, int $occupiedBefore, int $occupiedAfter): void
    {
        if ($occupiedBefore <= $cell->capacity || $occupiedAfter > $cell->capacity) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'establishment_id' => $establishmentId,
            'event' => 'cell.overcrowding_resolved',
            'subject_type' => 'cell',
            'subject_id' => $cell->id,
            'summary' => 'Surpopulation résorbée après mouvement ou libération : '.$occupiedAfter.'/'.$cell->capacity.' places.',
            'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupiedAfter],
        ]);
    }

    /** @return Collection<int, int|string> */
    private function accessibleEstablishmentIds(User $user)
    {
        if ($user->is_central_admin) {
            return collect();
        }

        return $user->establishments()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', self::ACCESS_ROLES)
            ->where('establishments.status', 'active')
            ->pluck('establishments.id');
    }
}
