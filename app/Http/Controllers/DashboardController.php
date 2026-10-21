<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\CellAssignment;
use App\Models\Detainee;
use App\Models\Establishment;
use App\Models\EstablishmentBuilding;
use App\Models\Incident;
use App\Models\MedicalConsultation;
use App\Models\PrisonVisit;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function sigec(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->pluck('id')
            : $user->establishments()->wherePivot('is_active', true)->where('establishments.status', 'active')->pluck('establishments.id');
        $selectedEstablishmentId = $request->query('establishment_id');
        if ($selectedEstablishmentId !== null && $selectedEstablishmentId !== '') {
            abort_unless(ctype_digit((string) $selectedEstablishmentId) && $establishmentIds->contains((int) $selectedEstablishmentId), 404);
            $selectedEstablishmentId = (int) $selectedEstablishmentId;
        } else {
            $selectedEstablishmentId = null;
        }

        $establishments = Establishment::query()->whereIn('id', $establishmentIds)->orderBy('name')->get(['id', 'code', 'name', 'province', 'city']);

        $capacityByEstablishment = Cell::query()
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->groupBy('establishment_buildings.establishment_id')
            ->selectRaw('establishment_buildings.establishment_id, SUM(cells.capacity) as capacity')
            ->pluck('capacity', 'establishment_id');
        $occupiedByEstablishment = CellAssignment::query()
            ->join('cells', 'cell_assignments.cell_id', '=', 'cells.id')
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->whereNull('cell_assignments.ended_at')
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->groupBy('establishment_buildings.establishment_id')
            ->selectRaw('establishment_buildings.establishment_id, COUNT(*) as occupied_places')
            ->pluck('occupied_places', 'establishment_id');
        $activeDetaineesByEstablishment = DB::table('detention_stays')
            ->whereNull('released_at')
            ->whereIn('establishment_id', $establishmentIds)
            ->groupBy('establishment_id')
            ->selectRaw('establishment_id, COUNT(DISTINCT detainee_id) as detainees')
            ->pluck('detainees', 'establishment_id');
        $establishmentOccupancy = $establishments->map(function (Establishment $establishment) use ($capacityByEstablishment, $occupiedByEstablishment, $activeDetaineesByEstablishment) {
            $capacity = (int) $capacityByEstablishment->get($establishment->id, 0);
            $occupied = (int) $occupiedByEstablishment->get($establishment->id, 0);

            return [
                'id' => $establishment->id,
                'code' => $establishment->code,
                'name' => $establishment->name,
                'city' => $establishment->city,
                'province' => $establishment->province,
                'capacity' => $capacity,
                'activeDetainees' => (int) $activeDetaineesByEstablishment->get($establishment->id, 0),
                'occupiedPlaces' => $occupied,
                'occupancyRate' => $capacity > 0 ? (int) round(($occupied / $capacity) * 100) : 0,
                'excess' => max(0, $occupied - $capacity),
            ];
        });
        $overcrowdedCells = DB::table('cells')
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->join('establishments', 'establishment_buildings.establishment_id', '=', 'establishments.id')
            ->leftJoin('cell_assignments', function ($join) {
                $join->on('cell_assignments.cell_id', '=', 'cells.id')->whereNull('cell_assignments.ended_at');
            })
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->when($selectedEstablishmentId !== null, fn ($query) => $query->where('establishment_buildings.establishment_id', $selectedEstablishmentId))
            ->groupBy('cells.id', 'cells.code', 'cells.name', 'cells.capacity', 'establishment_buildings.code', 'establishment_buildings.name', 'establishments.id', 'establishments.code', 'establishments.name')
            ->havingRaw('COUNT(cell_assignments.id) > cells.capacity')
            ->orderByRaw('(COUNT(cell_assignments.id) - cells.capacity) DESC')
            ->limit(10)
            ->get([
                'cells.id', 'cells.code as cell_code', 'cells.name as cell_name', 'cells.capacity',
                'establishment_buildings.code as building_code', 'establishment_buildings.name as building_name',
                'establishments.id as establishment_id', 'establishments.code as establishment_code', 'establishments.name as establishment_name',
                DB::raw('COUNT(cell_assignments.id) as occupied'),
            ])
            ->map(fn ($cell) => [
                'id' => $cell->id,
                'cellCode' => $cell->cell_code,
                'cellName' => $cell->cell_name,
                'capacity' => (int) $cell->capacity,
                'occupied' => (int) $cell->occupied,
                'excess' => (int) $cell->occupied - (int) $cell->capacity,
                'buildingCode' => $cell->building_code,
                'buildingName' => $cell->building_name,
                'establishmentId' => (int) $cell->establishment_id,
                'establishmentCode' => $cell->establishment_code,
                'establishmentName' => $cell->establishment_name,
            ]);
        $activeDetainees = Detainee::query()
            ->whereHas('stays', fn ($query) => $query->whereNull('released_at')->whereIn('establishment_id', $establishmentIds))
            ->count();
        $cells = Cell::query()->where('status', 'active')
            ->whereHas('building', fn ($query) => $query->where('status', 'active')->whereIn('establishment_id', $establishmentIds));
        $cellCount = (clone $cells)->count();
        $capacity = (int) (clone $cells)->sum('capacity');
        $occupied = CellAssignment::query()->whereNull('ended_at')
            ->whereHas('cell', fn ($query) => $query->where('status', 'active')->whereHas('building', fn ($building) => $building->where('status', 'active')->whereIn('establishment_id', $establishmentIds)))
            ->count();
        $canManageStocks = $user->is_central_admin || $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', ['director', 'agent'])->exists();
        $canManageClinic = $user->is_central_admin || $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', ['director', 'medical'])->exists();
        $canManageVisits = $user->is_central_admin || $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', ['director', 'agent', 'custody'])->exists();
        $canManageIncidents = $user->is_central_admin || $user->establishments()->wherePivot('is_active', true)->wherePivotIn('role', ['director', 'agent'])->exists();
        $openIncidents = $canManageIncidents
            ? Incident::query()->whereIn('establishment_id', $establishmentIds)->where('status', '!=', 'resolved')->count()
            : 0;
        $monthConsultations = $canManageClinic
            ? MedicalConsultation::query()->whereIn('establishment_id', $establishmentIds)->whereBetween('consulted_at', [now()->startOfMonth(), now()->endOfMonth()])->count()
            : 0;
        $todayVisits = $canManageVisits
            ? PrisonVisit::query()->whereIn('establishment_id', $establishmentIds)->whereDate('scheduled_at', today())->whereIn('status', ['scheduled', 'arrived'])->count()
            : 0;

        return Inertia::render('sigec/dashboard', [
            'stats' => [
                'establishments' => $establishments->count(),
                'activeDetainees' => $activeDetainees,
                'buildings' => EstablishmentBuilding::query()->where('status', 'active')->whereIn('establishment_id', $establishmentIds)->count(),
                'cells' => $cellCount,
                'occupiedPlaces' => $occupied,
                'capacity' => $capacity,
                'openIncidents' => $openIncidents,
                'monthConsultations' => $monthConsultations,
                'todayVisits' => $todayVisits,
            ],
            'establishmentOccupancy' => $establishmentOccupancy,
            'overcrowdedCells' => $overcrowdedCells,
            'selectedEstablishmentId' => $selectedEstablishmentId,
            'canManageUsers' => $user->is_central_admin,
            'canManageStocks' => $canManageStocks,
            'canManageClinic' => $canManageClinic,
            'canManageVisits' => $canManageVisits,
            'canManageIncidents' => $canManageIncidents,
        ]);
    }

    public function occupancyReport(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->pluck('id')
            : $user->establishments()->wherePivot('is_active', true)->where('establishments.status', 'active')->pluck('establishments.id');
        $selectedEstablishmentId = $request->query('establishment_id');
        if ($selectedEstablishmentId !== null && $selectedEstablishmentId !== '') {
            abort_unless(ctype_digit((string) $selectedEstablishmentId) && $establishmentIds->contains((int) $selectedEstablishmentId), 404);
            $selectedEstablishmentId = (int) $selectedEstablishmentId;
        } else {
            $selectedEstablishmentId = null;
        }
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', 'overcrowded', 'within_capacity', 'empty'], true), 422);
        $search = trim((string) $request->query('search', ''));
        $search = mb_substr($search, 0, 100);

        $cells = DB::table('cells')
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->join('establishments', 'establishment_buildings.establishment_id', '=', 'establishments.id')
            ->leftJoin('cell_assignments', function ($join) {
                $join->on('cell_assignments.cell_id', '=', 'cells.id')->whereNull('cell_assignments.ended_at');
            })
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->when($selectedEstablishmentId !== null, fn ($query) => $query->where('establishment_buildings.establishment_id', $selectedEstablishmentId))
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('cells.code', 'like', "%{$search}%")
                ->orWhere('cells.name', 'like', "%{$search}%")
                ->orWhere('establishment_buildings.code', 'like', "%{$search}%")
                ->orWhere('establishment_buildings.name', 'like', "%{$search}%")
                ->orWhere('establishments.code', 'like', "%{$search}%")
                ->orWhere('establishments.name', 'like', "%{$search}%")))
            ->groupBy('cells.id', 'cells.code', 'cells.name', 'cells.capacity', 'establishment_buildings.code', 'establishment_buildings.name', 'establishments.id', 'establishments.code', 'establishments.name')
            ->when($status === 'overcrowded', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) > cells.capacity'))
            ->when($status === 'within_capacity', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) > 0 AND COUNT(cell_assignments.id) <= cells.capacity'))
            ->when($status === 'empty', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) = 0'))
            ->orderBy('establishments.name')->orderBy('establishment_buildings.code')->orderBy('cells.code')
            ->select([
                'cells.id', 'cells.code as cell_code', 'cells.name as cell_name', 'cells.capacity',
                'establishment_buildings.code as building_code', 'establishment_buildings.name as building_name',
                'establishments.id as establishment_id', 'establishments.code as establishment_code', 'establishments.name as establishment_name',
                DB::raw('COUNT(cell_assignments.id) as occupied'),
            ])->paginate(25)->withQueryString();

        return Inertia::render('reports/occupancy', [
            'cells' => $cells,
            'establishments' => Establishment::query()->whereIn('id', $establishmentIds)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => ['establishment_id' => $selectedEstablishmentId, 'status' => $status, 'search' => $search],
        ]);
    }
    public function occupancyCsv(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->pluck('id')
            : $user->establishments()->wherePivot('is_active', true)->where('establishments.status', 'active')->pluck('establishments.id');
        $selectedEstablishmentId = $request->query('establishment_id');
        if ($selectedEstablishmentId !== null && $selectedEstablishmentId !== '') {
            abort_unless(ctype_digit((string) $selectedEstablishmentId) && $establishmentIds->contains((int) $selectedEstablishmentId), 404);
            $selectedEstablishmentId = (int) $selectedEstablishmentId;
        } else {
            $selectedEstablishmentId = null;
        }
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', 'overcrowded', 'within_capacity', 'empty'], true), 422);
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);

        $rows = DB::table('cells')
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->join('establishments', 'establishment_buildings.establishment_id', '=', 'establishments.id')
            ->leftJoin('cell_assignments', function ($join) {
                $join->on('cell_assignments.cell_id', '=', 'cells.id')->whereNull('cell_assignments.ended_at');
            })
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->when($selectedEstablishmentId !== null, fn ($query) => $query->where('establishment_buildings.establishment_id', $selectedEstablishmentId))
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('cells.code', 'like', "%{$search}%")
                ->orWhere('cells.name', 'like', "%{$search}%")
                ->orWhere('establishment_buildings.code', 'like', "%{$search}%")
                ->orWhere('establishment_buildings.name', 'like', "%{$search}%")
                ->orWhere('establishments.code', 'like', "%{$search}%")
                ->orWhere('establishments.name', 'like', "%{$search}%")))
            ->groupBy('cells.id', 'cells.code', 'cells.name', 'cells.capacity', 'establishment_buildings.code', 'establishment_buildings.name', 'establishments.code', 'establishments.name')
            ->when($status === 'overcrowded', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) > cells.capacity'))
            ->when($status === 'within_capacity', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) > 0 AND COUNT(cell_assignments.id) <= cells.capacity'))
            ->when($status === 'empty', fn ($query) => $query->havingRaw('COUNT(cell_assignments.id) = 0'))
            ->orderBy('establishments.name')->orderBy('establishment_buildings.code')->orderBy('cells.code')
            ->get([
                'establishments.code as establishment_code', 'establishments.name as establishment_name',
                'establishment_buildings.code as building_code', 'establishment_buildings.name as building_name',
                'cells.code as cell_code', 'cells.name as cell_name', 'cells.capacity',
                DB::raw('COUNT(cell_assignments.id) as occupied'),
            ]);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Code établissement', 'Établissement', 'Code bâtiment', 'Bâtiment', 'Code cellule', 'Nom cellule', 'Personnes affectées', 'Capacité', 'Occupation (%)', 'Dépassement', 'État'], ';');
            foreach ($rows as $row) {
                $occupied = (int) $row->occupied;
                $capacity = (int) $row->capacity;
                fputcsv($output, [
                    $row->establishment_code, $row->establishment_name, $row->building_code, $row->building_name,
                    $row->cell_code, $row->cell_name, $occupied, $capacity,
                    $capacity > 0 ? round(($occupied / $capacity) * 100, 2) : 0,
                    max(0, $occupied - $capacity),
                    $occupied > $capacity ? 'Surpopulation' : ($occupied === 0 ? 'Vide' : 'Dans la capacité'),
                ], ';');
            }
            fclose($output);
        }, 'rapport-occupation-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    public function overcrowdingCsv(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishmentIds = $user->is_central_admin
            ? Establishment::query()->where('status', 'active')->pluck('id')
            : $user->establishments()->wherePivot('is_active', true)->where('establishments.status', 'active')->pluck('establishments.id');
        $selectedEstablishmentId = $request->query('establishment_id');
        if ($selectedEstablishmentId !== null && $selectedEstablishmentId !== '') {
            abort_unless(ctype_digit((string) $selectedEstablishmentId) && $establishmentIds->contains((int) $selectedEstablishmentId), 404);
            $selectedEstablishmentId = (int) $selectedEstablishmentId;
        } else {
            $selectedEstablishmentId = null;
        }

        $rows = DB::table('cells')
            ->join('establishment_buildings', 'cells.establishment_building_id', '=', 'establishment_buildings.id')
            ->join('establishments', 'establishment_buildings.establishment_id', '=', 'establishments.id')
            ->leftJoin('cell_assignments', function ($join) {
                $join->on('cell_assignments.cell_id', '=', 'cells.id')->whereNull('cell_assignments.ended_at');
            })
            ->where('cells.status', 'active')
            ->where('establishment_buildings.status', 'active')
            ->whereIn('establishment_buildings.establishment_id', $establishmentIds)
            ->when($selectedEstablishmentId !== null, fn ($query) => $query->where('establishment_buildings.establishment_id', $selectedEstablishmentId))
            ->groupBy('cells.id', 'cells.code', 'cells.name', 'cells.capacity', 'establishment_buildings.code', 'establishment_buildings.name', 'establishments.code', 'establishments.name')
            ->havingRaw('COUNT(cell_assignments.id) > cells.capacity')
            ->orderByRaw('(COUNT(cell_assignments.id) - cells.capacity) DESC')
            ->get([
                'establishments.code as establishment_code', 'establishments.name as establishment_name',
                'establishment_buildings.code as building_code', 'establishment_buildings.name as building_name',
                'cells.code as cell_code', 'cells.name as cell_name', 'cells.capacity',
                DB::raw('COUNT(cell_assignments.id) as occupied'),
            ]);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Code établissement', 'Établissement', 'Code bâtiment', 'Bâtiment', 'Code cellule', 'Nom cellule', 'Personnes affectées', 'Capacité', 'Dépassement'], ';');
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->establishment_code, $row->establishment_name, $row->building_code, $row->building_name,
                    $row->cell_code, $row->cell_name, (int) $row->occupied, (int) $row->capacity,
                    (int) $row->occupied - (int) $row->capacity,
                ], ';');
            }
            fclose($output);
        }, 'rapport-surpopulation-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => ['name' => $invitation->team->name, 'slug' => $invitation->team->slug],
            ]);

        return Inertia::render('dashboard', ['pendingInvitations' => $pendingInvitations]);
    }
}


