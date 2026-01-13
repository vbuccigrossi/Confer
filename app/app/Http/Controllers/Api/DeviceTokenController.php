<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Store a device token for push notifications.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:ios,android,web',
        ]);

        // Check if this token already exists for this user
        $deviceToken = DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->first();

        if ($deviceToken) {
            // Update existing token
            $deviceToken->update([
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ]);
        } else {
            // Create new token
            $deviceToken = DeviceToken::create([
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Device token registered successfully',
            'token' => $deviceToken,
        ], 201);
    }

    /**
     * Remove a device token (when user logs out or uninstalls).
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'message' => 'Device token removed successfully',
        ]);
    }
}
