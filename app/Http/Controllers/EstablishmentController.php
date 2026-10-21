<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);

        $query = Establishment::query()->orderBy('name');
        if (! $user->is_central_admin) {
            $accessibleIds = $user->establishments()
                ->wherePivot('is_active', true)
                ->pluck('establishments.id');
            $query->whereIn('id', $accessibleIds);
        }

        $establishments = $query->get([
            'id', 'code', 'name', 'province', 'city', 'status',
            'facility_type', 'administration_type', 'security_level', 'penitentiary_region',
        ]);
        $establishments->each(function ($establishment) use ($user) {
            $establishment->setAttribute('can_manage_structure', $user->is_central_admin || $user->hasEstablishmentRole($establishment->id, ['director', 'custody']));
        });

        return Inertia::render('establishments/index', [
            'establishments' => $establishments,
            'canCreate' => $user->is_central_admin,
            'canManageUsers' => $user->is_central_admin,
            'canViewDetainees' => $user->is_central_admin || $accessibleIds->isNotEmpty(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);

        return Inertia::render('establishments/create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $validated = $request->validate($this->rules());
        $validated['security_level'] = $this->securityLevel($validated['facility_type']);

        $establishment = Establishment::create($validated);

        return redirect()->route('establishments.structure', $establishment);
    }

    public function edit(Request $request, Establishment $establishment): Response
    {
        $this->authorizeManagement($request->user(), $establishment);

        return Inertia::render('establishments/edit', [
            'establishment' => $establishment,
        ]);
    }

    public function update(Request $request, Establishment $establishment): RedirectResponse
    {
        $this->authorizeManagement($request->user(), $establishment);
        $validated = $request->validate($this->rules($establishment));
        $validated['security_level'] = $this->securityLevel($validated['facility_type']);
        $establishment->update($validated);

        return redirect()->route('establishments.index');
    }

    private function authorizeManagement(User $user, Establishment $establishment): void
    {
        abort_unless(
            $user->is_active && ($user->is_central_admin || $user->hasEstablishmentRole($establishment->id, 'director')),
            403,
        );
    }

    private function rules(?Establishment $establishment = null): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('establishments', 'code')->ignore($establishment?->id)],
            'name' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'facility_type' => ['required', Rule::in(['arrest_correction', 'central_prison', 'detention_center', 'high_security_prison', 'military_prison'])],
            'administration_type' => ['required', Rule::in(['civil', 'military'])],
            'penitentiary_region' => ['nullable', 'string', 'max:255'],
            'court_jurisdiction' => ['nullable', 'string', 'max:255'],
            'authorization_reference' => ['nullable', 'string', 'max:255'],
            'authorization_date' => ['nullable', 'date'],
            'women_dedicated' => ['required', 'boolean'],
            'separate_women_quarters' => ['required', 'boolean'],
            'separate_pretrial_quarters' => ['required', 'boolean'],
        ];
    }

    private function securityLevel(string $facilityType): string
    {
        return match ($facilityType) {
            'high_security_prison' => 'maximum',
            'central_prison', 'military_prison' => 'medium',
            default => 'minimum',
        };
    }
}








