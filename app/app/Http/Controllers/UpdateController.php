<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    /**
     * Get the latest TUI version information
     */
    public function checkTuiVersion(): JsonResponse
    {
        // This will be the latest version available
        $latestVersion = '0.2.0'; // Update this when you release new versions
        $downloadUrl = 'https://groundstatesystems.work/api/updates/tui/download';
        
        return response()->json([
            'latest_version' => $latestVersion,
            'download_url' => $downloadUrl,
            'release_notes' => [
                '0.2.0' => [
                    'Added slash command support',
                    'Fixed unread message count issue',
                    'Added bot conversation support',
                    'Improved error logging',
                ],
            ],
            'minimum_version' => '0.1.0',
        ]);
    }

    /**
     * Download the latest TUI release package
     */
    public function downloadTui()
    {
        $filePath = storage_path('app/public/confer-tui-release.tar.gz');
        
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Release package not found'], 404);
        }

        return response()->download($filePath, 'confer-tui-release.tar.gz');
    }
}
