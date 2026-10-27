<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\CellAssignment;
use App\Models\AuditLog;
use App\Models\Detainee;
use App\Models\Establishment;
use App\Models\EstablishmentBuilding;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentStructureController extends Controller
{
    public function show(Request $request, Establishment $establishment): Response
    {
        $user = $request->user();
        $this->authorizeStructure($user, $establishment);
        $buildings = $establishment->buildings()->with(['cells' => fn ($query) => $query->withCount(['activeAssignments as occupied'])->with('activeAssignments.detainee')->orderBy('code')])->orderBy('code')->get();
        $hasUnassignedDetainees = Detainee::query()->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
            ->whereDoesntHave('cellAssignments', fn ($query) => $query->whereNull('ended_at'))->exists();

        return Inertia::render('establishments/structure', [
            'establishment' => $establishment->only('id', 'code', 'name'),
            'buildings' => $buildings,
            'hasUnassignedDetainees' => $hasUnassignedDetainees,
        ]);
    }

    public function searchUnassignedDetainees(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $matches = Detainee::query()->whereHas('stays', fn ($query) => $query->where('establishment_id', $establishment->id)->whereNull('released_at'))
            ->whereDoesntHave('cellAssignments', fn ($query) => $query->whereNull('ended_at'))
            ->where(fn ($query) => $query->where('record_number', 'like', "%{$term}%")->orWhere('family_name', 'like', "%{$term}%")->orWhere('post_name', 'like', "%{$term}%")->orWhere('given_names', 'like', "%{$term}%"))
            ->orderBy('family_name')->orderBy('given_names')->limit(20)->get(['id', 'record_number', 'family_name', 'post_name', 'given_names']);

        return response()->json($matches->map(fn (Detainee $detainee) => ['id' => $detainee->id, 'label' => $detainee->record_number.' — '.$detainee->family_name.' '.($detainee->post_name ? $detainee->post_name.' ' : '').$detainee->given_names]));
    }

    public function searchAvailableCells(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $matches = Cell::query()->where('status', 'active')
            ->whereHas('building', fn ($query) => $query->where('establishment_id', $establishment->id)->where('status', 'active'))
            ->with('building:id,code,name')->withCount(['activeAssignments as occupied'])
            ->where(fn ($query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('building', fn ($building) => $building->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderBy('code')->limit(20)->get();

        return response()->json($matches->map(fn (Cell $cell) => ['id' => $cell->id, 'label' => $cell->building->code.' — '.$cell->code, 'description' => $cell->occupied.'/'.$cell->capacity.' place(s) occupée(s)'.($cell->occupied > $cell->capacity ? ' · Surpopulation : +'.($cell->occupied - $cell->capacity) : '')]));
    }

    public function storeBuilding(Request $request, Establishment $establishment): RedirectResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        abort_unless($establishment->status === 'active', 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('establishment_buildings')->where('establishment_id', $establishment->id)],
            'name' => ['required', 'string', 'max:255'], 'building_type' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $establishment->buildings()->create($data);
        return back()->with('success', 'Bâtiment ajouté.');
    }

    public function updateBuilding(Request $request, Establishment $establishment, EstablishmentBuilding $building): RedirectResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        abort_unless($establishment->status === 'active', 403);
        abort_unless($building->establishment_id === $establishment->id, 404);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $building->update($data);

        return back()->with('success', 'Nom du bâtiment modifié.');
    }

    public function storeCell(Request $request, Establishment $establishment): RedirectResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        abort_unless($establishment->status === 'active', 403);
        $data = $request->validate([
            'establishment_building_id' => ['required', 'integer', Rule::exists('establishment_buildings', 'id')->where('establishment_id', $establishment->id)->where('status', 'active')],
            'cells' => ['required', 'array', 'min:1', 'max:100'],
            'cells.*.code' => ['required', 'string', 'max:80'],
            'cells.*.name' => ['nullable', 'string', 'max:255'],
            'cells.*.capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'cells.*.cell_type' => ['nullable', 'string', 'max:100'],
            'cells.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $seenCodes = [];
        foreach ($data['cells'] as $index => $cell) {
            $code = mb_strtolower($cell['code']);
            if (isset($seenCodes[$code]) || Cell::query()->where('establishment_building_id', $data['establishment_building_id'])->where('code', $cell['code'])->exists()) {
                throw ValidationException::withMessages(["cells.{$index}.code" => 'Ce code existe déjà dans ce bâtiment ou dans la liste.']);
            }
            $seenCodes[$code] = true;
        }

        DB::transaction(function () use ($data) {
            foreach ($data['cells'] as $cell) {
                Cell::query()->create([...$cell, 'establishment_building_id' => $data['establishment_building_id']]);
            }
        });

        return back()->with('success', count($data['cells']) === 1 ? 'Cellule ajoutée.' : count($data['cells']).' cellules ajoutées.');
    }

    public function updateCell(Request $request, Establishment $establishment, Cell $cell): RedirectResponse
    {
        $this->authorizeStructure($request->user(), $establishment);
        abort_unless($establishment->status === 'active', 403);
        abort_unless($cell->building()->where('establishment_id', $establishment->id)->exists(), 404);

        $data = $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $cell->update($data);

        return back()->with('success', 'Nom de la cellule modifié.');
    }

    public function assign(Request $request, Establishment $establishment): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeStructure($user, $establishment);
        abort_unless($establishment->status === 'active', 403);
        $data = $request->validate([
            'cell_id' => ['required', 'integer', 'exists:cells,id'],
            'detainee_id' => ['required', 'integer', 'exists:detainees,id'],
        ]);
        DB::transaction(function () use ($data, $establishment, $user) {
            $cell = Cell::query()->whereKey($data['cell_id'])->lockForUpdate()->with('building')->firstOrFail();
            abort_unless($cell->building->establishment_id === $establishment->id && $cell->building->status === 'active' && $cell->status === 'active', 422);
            $stay = Detainee::query()->whereKey($data['detainee_id'])->firstOrFail()->stays()
                ->where('establishment_id', $establishment->id)->whereNull('released_at')->lockForUpdate()->firstOrFail();
            abort_if(CellAssignment::query()->where('detainee_id', $data['detainee_id'])->whereNull('ended_at')->exists(), 422, 'Cette personne est déjà affectée à une cellule.');
            CellAssignment::query()->create(['cell_id' => $cell->id, 'detainee_id' => $data['detainee_id'], 'detention_stay_id' => $stay->id, 'assigned_by' => $user->id, 'assigned_at' => now()]);
            $occupied = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupied > $cell->capacity) {
                AuditLog::query()->create([
                    'actor_id' => $user->id,
                    'establishment_id' => $establishment->id,
                    'event' => 'cell.overcrowding',
                    'subject_type' => 'cell',
                    'subject_id' => $cell->id,
                    'summary' => 'Surpopulation après affectation : '.$occupied.'/'.$cell->capacity.' places.',
                    'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupied, 'excess' => $occupied - $cell->capacity, 'detainee_id' => (int) $data['detainee_id']],
                ]);
            }
        });
        return back()->with('success', 'Affectation enregistrée.');
    }

    public function endAssignment(Request $request, Establishment $establishment, CellAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeStructure($user, $establishment);
        $data = $request->validate(['end_reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($user, $establishment, $assignment, $data) {
            $cell = Cell::query()->whereKey($assignment->cell_id)->lockForUpdate()->with('building')->firstOrFail();
            abort_unless($cell->building->establishment_id === $establishment->id, 404);
            abort_if($assignment->ended_at !== null, 422, 'Cette affectation est déjà clôturée.');
            $occupiedBefore = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            $assignment->update(['ended_at' => now(), 'end_reason' => $data['end_reason']]);
            $occupiedAfter = CellAssignment::query()->where('cell_id', $cell->id)->whereNull('ended_at')->count();
            if ($occupiedBefore > $cell->capacity && $occupiedAfter <= $cell->capacity) {
                AuditLog::query()->create([
                    'actor_id' => $user->id,
                    'establishment_id' => $establishment->id,
                    'event' => 'cell.overcrowding_resolved',
                    'subject_type' => 'cell',
                    'subject_id' => $cell->id,
                    'summary' => 'Surpopulation résorbée : '.$occupiedAfter.'/'.$cell->capacity.' places.',
                    'metadata' => ['cell_code' => $cell->code, 'capacity' => $cell->capacity, 'occupied' => $occupiedAfter],
                ]);
            }
        });
        return back()->with('success', 'Affectation clôturée.');
    }

    public function moveAssignment(Request $request, Establishment $establishment, CellAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeStructure($user, $establishment);
        abort_unless($establishment->status === 'active', 403);
        $data = $request->validate([
            'cell_id' => ['required', 'integer', 'exists:cells,id'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $user, $establishment, $assignment) {
            $current = CellAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->ended_at !== null, 422, 'Cette affectation est déjà clôturée.');
            $oldCell = Cell::query()->whereKey($current->cell_id)->lockForUpdate()->with('building')->firstOrFail();
            $newCell = Cell::query()->whereKey($data['cell_id'])->lockForUpdate()->with('building')->firstOrFail();
            abort_unless($oldCell->building->establishment_id === $establishment->id && $newCell->building->establishment_id === $establishment->id, 404);
            abort_unless($newCell->status === 'active' && $newCell->building->status === 'active', 422, 'La cellule de destination ou son bâtiment est inactif.');
            abort_if($newCell->id === $oldCell->id, 422, 'Choisissez une cellule différente.');

            $now = now();
            $current->update(['ended_at' => $now, 'end_reason' => 'Déplacement interne : '.$data['reason']]);
            $newAssignment = CellAssignment::query()->create([
                'cell_id' => $newCell->id,
                'detainee_id' => $current->detainee_id,
                'detention_stay_id' => $current->detention_stay_id,
                'assigned_by' => $user->id,
                'assigned_at' => $now,
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'detainee.cell_moved',
                'subject_type' => 'cell_assignment',
                'subject_id' => $newAssignment->id,
                'summary' => 'Déplacement interne de cellule enregistré.',
                'metadata' => ['detainee_id' => $current->detainee_id, 'from_cell_id' => $oldCell->id, 'from_cell_code' => $oldCell->code, 'to_cell_id' => $newCell->id, 'to_cell_code' => $newCell->code],
            ]);
        });

        return back()->with('success', 'Déplacement interne enregistré.');
    }
    private function authorizeStructure(User $user, Establishment $establishment): void
    {
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, ['director', 'custody'])), 403);
    }
}

