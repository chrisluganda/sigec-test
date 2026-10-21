<?php

namespace App\Console\Commands;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Console\Command;

class GrantSigecAccess extends Command
{
    protected $signature = 'sigec:grant-access {email} {role : central_admin|director|agent|medical|judicial|custody} {--establishment= : Establishment ID for a local role}';

    protected $description = 'Grant a SIGEC account a central role or a role scoped to one establishment';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user account matches that email.');

            return self::FAILURE;
        }

        $role = $this->argument('role');

        if ($role === 'central_admin') {
            $user->forceFill(['is_central_admin' => true])->save();
            $this->info('Central administrator access granted.');

            return self::SUCCESS;
        }

        $roles = ['director', 'agent', 'medical', 'judicial', 'custody'];

        if (! in_array($role, $roles, true)) {
            $this->error('Role must be central_admin, director, agent, medical, judicial, or custody.');

            return self::FAILURE;
        }

        $establishmentId = filter_var($this->option('establishment'), FILTER_VALIDATE_INT);
        $establishment = $establishmentId ? Establishment::query()->find($establishmentId) : null;

        if (! $establishment) {
            $this->error('Provide a valid --establishment ID for a local role.');

            return self::FAILURE;
        }

        $user->establishments()->syncWithoutDetaching([
            $establishment->id => ['role' => $role, 'is_active' => true],
        ]);

        $this->info("The {$role} role was granted for establishment #{$establishment->id}.");

        return self::SUCCESS;
    }
}
