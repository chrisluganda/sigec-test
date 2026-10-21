<?php

namespace App\Http\Controllers;

use App\Models\Magistrate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MagistrateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);

        return Inertia::render('magistrates/index', [
            'magistrates' => Magistrate::query()->withCount('detentionOrders')->orderBy('family_name')->orderBy('post_name')->orderBy('given_names')->get(),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $data = $request->validate([
            'official_reference' => ['nullable', 'string', 'max:255', Rule::unique('magistrates', 'official_reference')],
            'family_name' => ['required', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'given_names' => ['required', 'string', 'max:255'],
            'function' => ['required', 'string', 'max:255'],
            'court_name' => ['required', 'string', 'max:255'],
            'court_level' => ['nullable', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ]);
        Magistrate::query()->create([...$data, 'is_active' => true]);

        return redirect()->route('magistrates.index')->with('success', 'Magistrat ajouté au répertoire central.');
    }

    public function updateStatus(Request $request, Magistrate $magistrate): RedirectResponse
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $magistrate->update(['is_active' => $data['is_active']]);

        return redirect()->route('magistrates.index')->with('success', 'Statut du magistrat mis à jour.');
    }
}
