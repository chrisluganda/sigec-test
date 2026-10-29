<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return Cache::lock('sigec:first-user-registration', 30)->block(10, function () use ($input): User {
            return DB::transaction(function () use ($input): User {
                if (User::query()->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'La création de compte est fermée. Contactez l’administration centrale.',
                    ]);
                }

                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);
                $user->forceFill(['is_central_admin' => true, 'is_active' => true])->save();

                $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

                return $user;
            });
        });
    }
}
