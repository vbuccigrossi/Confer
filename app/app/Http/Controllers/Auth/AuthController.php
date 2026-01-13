<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and return token
     *
     * Token naming conventions for analytics:
     * - mobile-{device_id} for mobile app
     * - tui-{device_id} for TUI client
     * - Other names for web clients
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'force_new_session' => 'nullable|boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate a unique token name based on device info
        $deviceName = $request->input('device_name', 'Unknown Device');
        $deviceId = $request->input('device_id');
        $forceNewSession = $request->boolean('force_new_session', false);

        // If device_id is provided, check if a token already exists for this device
        if ($deviceId) {
            // Use the device_name if provided (e.g., "tui-abc123" or "mobile-abc123")
            // Fall back to "mobile-{device_id}" for backwards compatibility with older mobile apps
            $tokenName = $deviceName && str_contains($deviceName, $deviceId)
                ? $deviceName
                : "mobile-{$deviceId}";

            // Find existing token for this device
            $existingToken = $user->tokens()
                ->where('name', $tokenName)
                ->first();

            if ($existingToken) {
                if ($forceNewSession) {
                    // Delete existing token and create new one
                    $existingToken->delete();
                } else {
                    // Update last_used_at timestamp and return success
                    $existingToken->forceFill([
                        'last_used_at' => now(),
                    ])->save();

                    return response()->json([
                        'user' => $user,
                        'message' => 'Session already exists. Please use your stored token.',
                        'existing_session' => true,
                    ], 200);
                }
            }

            // Create new token with device identifier
            $token = $user->createToken($tokenName)->plainTextToken;
        } else {
            // Fallback for clients that don't send device info
            $token = $user->createToken($deviceName)->plainTextToken;
        }

        return response()->json([
            'user' => $user,
            'token' => $token,
            'existing_session' => false,
        ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Refresh token (create new token and revoke old one)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('workspaces'),
        ]);
    }

    /**
     * Get all active sessions (tokens)
     */
    public function sessions(Request $request)
    {
        $tokens = $request->user()->tokens;

        return response()->json([
            'sessions' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                ];
            }),
        ]);
    }

    /**
     * Revoke a specific session (token)
     */
    public function revokeSession(Request $request, $tokenId)
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'message' => 'Session revoked successfully',
        ]);
    }

    /**
     * Update user's last seen timestamp (heartbeat)
     */
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        $user->update(['last_seen_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
