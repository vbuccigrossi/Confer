<?php

namespace App\Services;

use App\Models\App;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Service for managing app tokens and credentials
 */
class AppTokenService
{
    /**
     * Generate unique client credentials
     */
    public function generateClientCredentials(): array
    {
        return [
            'client_id' => App::generateClientId(),
            'client_secret' => Str::random(64),
        ];
    }

    /**
     * Generate a secure token
     */
    public function generateToken(): string
    {
        return App::generateToken();
    }

    /**
     * Hash a secret using bcrypt
     */
    public function hashSecret(string $secret): string
    {
        return Hash::make($secret);
    }

    /**
     * Verify a token against hashed value
     */
    public function verifyToken(App $app, string $plainToken): bool
    {
        return $app->verifyToken($plainToken);
    }

    /**
     * Create a new app with hashed credentials
     */
    public function createApp(Workspace $workspace, User $user, array $data): array
    {
        // Generate credentials
        $credentials = $this->generateClientCredentials();
        $token = $this->generateToken();

        // Create app with hashed values
        $app = App::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'client_id' => $credentials['client_id'],
            'client_secret' => $this->hashSecret($credentials['client_secret']),
            'token' => $this->hashSecret($token),
            'scopes' => $data['scopes'] ?? [],
            'callback_url' => $data['callback_url'] ?? null,
            'default_conversation_id' => $data['default_conversation_id'] ?? null,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        // Return app with plain credentials (shown once)
        return [
            'app' => $app,
            'credentials' => [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'token' => $token,
            ],
        ];
    }

    /**
     * Regenerate token for an app
     */
    public function regenerateToken(App $app): string
    {
        $newToken = $this->generateToken();
        
        $app->update([
            'token' => $this->hashSecret($newToken),
        ]);

        return $newToken;
    }
}
