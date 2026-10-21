<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_establishment_role_only_opens_its_assigned_modules(): void
    {
        $matrix = [
            'director' => [
                'allowed' => ['detainees.index', 'visits.index', 'visitors.index', 'incidents.index', 'stocks.index', 'clinic.index', 'admission-reviews.index', 'establishments.index'],
                'denied' => ['magistrates.index', 'audit.index', 'users.index', 'establishments.create'],
            ],
            'agent' => [
                'allowed' => ['detainees.index', 'visits.index', 'visitors.index', 'incidents.index', 'stocks.index', 'establishments.index'],
                'denied' => ['clinic.index', 'admission-reviews.index', 'magistrates.index', 'audit.index', 'users.index', 'establishments.create'],
            ],
            'judicial' => [
                'allowed' => ['detainees.index', 'admission-reviews.index', 'establishments.index'],
                'denied' => ['visits.index', 'visitors.index', 'incidents.index', 'stocks.index', 'clinic.index', 'magistrates.index', 'audit.index', 'users.index', 'establishments.create'],
            ],
            'custody' => [
                'allowed' => ['detainees.index', 'visits.index', 'visitors.index', 'establishments.index'],
                'denied' => ['incidents.index', 'stocks.index', 'clinic.index', 'admission-reviews.index', 'magistrates.index', 'audit.index', 'users.index', 'establishments.create'],
            ],
            'medical' => [
                'allowed' => ['clinic.index', 'establishments.index'],
                'denied' => ['detainees.index', 'visits.index', 'visitors.index', 'incidents.index', 'stocks.index', 'admission-reviews.index', 'magistrates.index', 'audit.index', 'users.index', 'establishments.create'],
            ],
        ];

        foreach ($matrix as $role => $expectations) {
            [$user, $establishment] = $this->authorizedUser($role);
            $this->actingAs($user);

            foreach ($expectations['allowed'] as $route) {
                $this->get(route($route, $route === 'establishments.index' ? [] : ['establishment_id' => $establishment->id]))
                    ->assertOk("Role {$role} should access {$route}");
            }

            foreach ($expectations['denied'] as $route) {
                $this->get(route($route, $route === 'establishments.index' ? [] : ['establishment_id' => $establishment->id]))
                    ->assertForbidden("Role {$role} should not access {$route}");
            }
        }
    }

    public function test_central_administrator_can_open_restricted_administration_modules(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'is_central_admin' => true])->save();
        $this->actingAs($user);

        foreach (['magistrates.index', 'audit.index', 'users.index', 'establishments.create'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_users_cannot_open_another_establishments_visit_or_clinic_records(): void
    {
        [$user] = $this->authorizedUser('agent');
        $otherEstablishment = Establishment::query()->create(['code' => 'OTHER-EST', 'name' => 'Autre établissement', 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('visits.index', ['establishment_id' => $otherEstablishment->id]))
            ->assertForbidden();

        $this->get(route('clinic.detainees.search', $otherEstablishment))->assertForbidden();
    }

    private function authorizedUser(string $role): array
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'is_central_admin' => false])->save();
        $establishment = Establishment::query()->create(['code' => strtoupper($role).'-EST', 'name' => "Établissement {$role}", 'status' => 'active']);
        DB::table('establishment_user_access')->insert([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $establishment];
    }
}
