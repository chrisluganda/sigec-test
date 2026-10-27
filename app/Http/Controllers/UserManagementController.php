<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request->user());

        return Inertia::render('users/index', [
            'users' => User::query()->with(['establishments' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'establishments' => Establishment::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->where('status', 'active')],
            'role' => ['required', Rule::in(['director', 'agent', 'judicial', 'custody', 'medical'])],
        ]);

        DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->establishments()->attach($data['establishment_id'], [
                'role' => $data['role'],
                'is_active' => true,
                'granted_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Utilisateur créé et accès à l’établissement attribué.');
    }

    public function grantAccess(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        abort_if($managedUser->is_central_admin, 422, 'Les droits de l’administrateur central ne se modifient pas ici.');
        $data = $request->validate([
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->where('status', 'active')],
            'role' => ['required', Rule::in(['director', 'agent', 'judicial', 'custody', 'medical'])],
        ]);

        DB::transaction(function () use ($managedUser, $request, $data) {
            // A staff account belongs to one active establishment at a time.
            // Keep prior links for history, but revoke their access immediately.
            DB::table('establishment_user_access')
                ->where('user_id', $managedUser->id)
                ->where('establishment_id', '!=', $data['establishment_id'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);

            $managedUser->establishments()->syncWithoutDetaching([
                $data['establishment_id'] => [
                    'role' => $data['role'],
                    'is_active' => true,
                    'granted_by' => $request->user()->id,
                ],
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Nouvel établissement attribué. Les accès aux anciens établissements ont été désactivés.');
    }

    public function setActive(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        abort_if($managedUser->is($request->user()) || $managedUser->is_central_admin, 422, 'Ce compte ne peut pas être désactivé depuis cette page.');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $managedUser->is_active = $data['is_active'];
        $managedUser->save();

        return redirect()->route('users.index')->with('success', $data['is_active'] ? 'Compte activé.' : 'Compte désactivé.');
    }

    private function authorizeAdmin(User $user): void
    {
        abort_unless($user->is_active && $user->is_central_admin, 403);
    }
}




