<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    /**
     * Update the user's notification preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'sound_notifications' => 'required|boolean',
        ]);

        $request->user()->update($validated);

        return back()->with('status', 'notification-preferences-updated');
    }
}
