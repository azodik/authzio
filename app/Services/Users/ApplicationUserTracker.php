<?php

namespace App\Services\Users;

use App\Models\ApplicationUser;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicationUserTracker
{
    public function record(OAuthClient $client, User $user): ApplicationUser
    {
        $now = now();

        return DB::transaction(function () use ($client, $user, $now): ApplicationUser {
            $existing = ApplicationUser::query()
                ->where('organization_id', $client->organization_id)
                ->where('oauth_client_id', $client->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'last_seen_at' => $now,
                    'last_login_at' => $now,
                    'sign_in_count' => $existing->sign_in_count + 1,
                ]);

                return $existing->fresh() ?? $existing;
            }

            return ApplicationUser::create([
                'organization_id' => $client->organization_id,
                'oauth_client_id' => $client->id,
                'user_id' => $user->id,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'last_login_at' => $now,
                'sign_in_count' => 1,
            ]);
        });
    }
}
