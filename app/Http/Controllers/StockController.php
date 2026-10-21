<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\EstablishmentStock;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    private const ROLES = ['director', 'agent'];

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
        $stocks = EstablishmentStock::query()->with('item')->where('establishment_id', $selectedId)->orderBy('location')->orderBy('inventory_item_id')->get();
        $movements = StockMovement::query()->with(['item', 'recorder'])->where('establishment_id', $selectedId)->latest('created_at')->paginate(25)->withQueryString();

        return Inertia::render('stocks/index', [
            'establishments' => $establishments,
            'selectedEstablishmentId' => $selectedId,
            'hasItems' => InventoryItem::query()->where('is_active', true)->exists(),
            'stocks' => $stocks,
            'movements' => $movements,
            'canManageCatalog' => $user->is_central_admin,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function searchItems(Request $request, Establishment $establishment): JsonResponse
    {
        abort_unless($this->canManage($request->user(), $establishment), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $items = InventoryItem::query()->where('is_active', true)
            ->where(fn ($query) => $query->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"))
            ->orderBy('name')->limit(20)->get(['id', 'code', 'name', 'category', 'unit']);

        return response()->json($items->map(fn (InventoryItem $item) => ['id' => $item->id, 'label' => $item->code.' — '.$item->name, 'description' => $item->category.' · '.$item->unit]));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('inventory_items', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        InventoryItem::query()->create($data);

        return redirect()->route('stocks.index')->with('success', 'Article ajouté au catalogue.');
    }

    public function movement(Request $request, Establishment $establishment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES)), 403);
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'movement_type' => ['required', Rule::in(['entry', 'issue'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'location' => ['required', 'string', 'max:255'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $establishment, $user) {
            $stock = EstablishmentStock::query()->where('establishment_id', $establishment->id)
                ->where('inventory_item_id', $data['inventory_item_id'])->where('location', $data['location'])
                ->lockForUpdate()->first();
            if (! $stock) {
                $stock = EstablishmentStock::query()->create([
                    'establishment_id' => $establishment->id,
                    'inventory_item_id' => $data['inventory_item_id'],
                    'location' => $data['location'],
                    'quantity' => 0,
                    'reorder_threshold' => $data['reorder_threshold'] ?? 0,
                ]);
                $stock = EstablishmentStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            }
            if (array_key_exists('reorder_threshold', $data) && $data['reorder_threshold'] !== null) {
                $stock->reorder_threshold = $data['reorder_threshold'];
            }
            $quantity = (float) $data['quantity'];
            $current = (float) $stock->quantity;
            if ($data['movement_type'] === 'issue' && $quantity > $current) {
                throw ValidationException::withMessages(['quantity' => 'La quantité à sortir dépasse le stock disponible à cet emplacement.']);
            }
            $stock->quantity = round($data['movement_type'] === 'entry' ? $current + $quantity : $current - $quantity, 3);
            $stock->save();
            $movement = StockMovement::query()->create([
                'establishment_id' => $establishment->id,
                'inventory_item_id' => $data['inventory_item_id'],
                'movement_type' => $data['movement_type'],
                'quantity' => $quantity,
                'source_location' => $data['movement_type'] === 'issue' ? $data['location'] : null,
                'destination_location' => $data['movement_type'] === 'entry' ? $data['location'] : null,
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'] ?? null,
                'recorded_by' => $user->id,
                'created_at' => now(),
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'stock.movement',
                'subject_type' => 'stock_movement',
                'subject_id' => $movement->id,
                'summary' => ($data['movement_type'] === 'entry' ? 'Entrée' : 'Sortie').' de stock enregistrée.',
                'metadata' => ['inventory_item_id' => (int) $data['inventory_item_id'], 'movement_type' => $data['movement_type'], 'quantity' => $quantity, 'location' => $data['location']],
            ]);
        });

        return redirect()->route('stocks.index', ['establishment_id' => $establishment->id])->with('success', 'Mouvement de stock enregistré.');
    }

    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offline_reference' => ['required', 'uuid'],
            'establishment_code' => ['required', 'string', 'max:80'],
            'item_code' => ['required', 'string', 'max:100'],
            'movement_type' => ['required', Rule::in(['entry', 'issue'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'location' => ['required', 'string', 'max:255'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'captured_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $establishment = Establishment::query()->where('code', $data['establishment_code'])->where('status', 'active')->first();
        if (! $establishment) {
            throw ValidationException::withMessages(['establishment_code' => 'Code d’établissement inconnu ou inactif.']);
        }
        abort_unless($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES), 403);
        [$movement, $duplicate] = DB::transaction(function () use ($data, $user, $establishment) {
            $existing = StockMovement::query()->where('offline_reference', $data['offline_reference'])->first();
            if ($existing) {
                abort_unless($existing->establishment_id === $establishment->id, 409);
                return [$existing, true];
            }
            $item = InventoryItem::query()->where('code', $data['item_code'])->where('is_active', true)->first();
            if (! $item) {
                throw ValidationException::withMessages(['item_code' => 'Code article inconnu ou inactif. Vérifiez le catalogue puis réessayez.']);
            }
            $stock = EstablishmentStock::query()->where('establishment_id', $establishment->id)
                ->where('inventory_item_id', $item->id)->where('location', $data['location'])->lockForUpdate()->first();
            if (! $stock) {
                $stock = EstablishmentStock::query()->create([
                    'establishment_id' => $establishment->id,
                    'inventory_item_id' => $item->id,
                    'location' => $data['location'],
                    'quantity' => 0,
                    'reorder_threshold' => $data['reorder_threshold'] ?? 0,
                ]);
                $stock = EstablishmentStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            }
            if (array_key_exists('reorder_threshold', $data) && $data['reorder_threshold'] !== null) {
                $stock->reorder_threshold = $data['reorder_threshold'];
            }
            $quantity = (float) $data['quantity'];
            $current = (float) $stock->quantity;
            if ($data['movement_type'] === 'issue' && $quantity > $current) {
                throw ValidationException::withMessages(['quantity' => 'La quantité à sortir dépasse le stock disponible à cet emplacement.']);
            }
            $stock->quantity = round($data['movement_type'] === 'entry' ? $current + $quantity : $current - $quantity, 3);
            $stock->save();
            $movement = StockMovement::query()->create([
                'establishment_id' => $establishment->id,
                'offline_reference' => $data['offline_reference'],
                'inventory_item_id' => $item->id,
                'movement_type' => $data['movement_type'],
                'quantity' => $quantity,
                'source_location' => $data['movement_type'] === 'issue' ? $data['location'] : null,
                'destination_location' => $data['movement_type'] === 'entry' ? $data['location'] : null,
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'] ?? null,
                'recorded_by' => $user->id,
                'created_at' => $data['captured_at'],
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'establishment_id' => $establishment->id,
                'event' => 'stock.movement',
                'subject_type' => 'stock_movement',
                'subject_id' => $movement->id,
                'summary' => ($data['movement_type'] === 'entry' ? 'Entrée' : 'Sortie').' de stock enregistrée hors connexion.',
                'metadata' => ['inventory_item_id' => $item->id, 'movement_type' => $data['movement_type'], 'quantity' => $quantity, 'location' => $data['location']],
            ]);
            return [$movement, false];
        });

        return response()->json(['synced' => true, 'duplicate' => $duplicate, 'movement_id' => $movement->id]);
    }

    private function canManage(User $user, Establishment $establishment): bool
    {
        return $user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, self::ROLES));
    }
}



