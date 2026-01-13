<?php

namespace App\Actions\Fortify;

use App\Models\Invite;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'invite_code' => ['required', 'string'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        // Validate invite code
        $invite = Invite::findByCode($input['invite_code']);

        if (!$invite || !$invite->canBeUsed()) {
            throw ValidationException::withMessages([
                'invite_code' => ['The provided invite code is invalid or has expired.'],
            ]);
        }

        return DB::transaction(function () use ($input, $invite) {
            $user = tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), function (User $user) use ($invite) {
                $this->createTeam($user);

                // Add user to the workspace from the invite
                $this->addToWorkspace($user, $invite);
            });

            // Increment invite code usage count
            $invite->incrementUseCount();

            return $user;
        });
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));
    }

    /**
     * Add user to workspace from invite
     */
    protected function addToWorkspace(User $user, Invite $invite): void
    {
        // Add user as workspace member (unguard to allow workspace_id and user_id)
        WorkspaceMember::unguard();

        WorkspaceMember::create([
            'workspace_id' => $invite->workspace_id,
            'user_id' => $user->id,
            'role' => $invite->role, // Use the role from the invite (admin or member)
            'joined_at' => now(),
        ]);

        WorkspaceMember::reguard();

        // Set this workspace as the user's current workspace
        $user->update(['current_workspace_id' => $invite->workspace_id]);
    }
}
