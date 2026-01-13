<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\BotToken;
use App\Models\SlashCommand;
use App\Models\Workspace;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin bots controller - workspace bot installation and management
 */
class AdminBotsController extends Controller
{
    public function __construct(
        private AuditLogService $auditService
    ) {
    }

    /**
     * List installed bots for workspace
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $installations = BotInstallation::where('workspace_id', $workspace->id)
            ->with(['bot', 'installer:id,name', 'tokens', 'slashCommands'])
            ->orderByDesc('installed_at')
            ->get()
            ->map(function ($installation) {
                return [
                    'id' => $installation->id,
                    'bot' => [
                        'id' => $installation->bot->id,
                        'name' => $installation->bot->name,
                        'slug' => $installation->bot->slug,
                        'description' => $installation->bot->description,
                        'avatar_url' => $installation->bot->avatar_url,
                        'webhook_url' => $installation->bot->webhook_url,
                        'config_schema' => $installation->bot->config_schema,
                        'requires_configuration' => $installation->bot->requiresConfiguration(),
                    ],
                    'config' => $installation->config,
                    'is_active' => $installation->is_active,
                    'installed_at' => $installation->installed_at,
                    'installer' => $installation->installer ? [
                        'id' => $installation->installer->id,
                        'name' => $installation->installer->name,
                    ] : null,
                    'tokens_count' => $installation->tokens->count(),
                    'has_active_token' => $installation->tokens->where('expires_at', '>', now())->count() > 0 
                        || $installation->tokens->whereNull('expires_at')->count() > 0,
                    'slash_commands' => $installation->slashCommands->map(function ($cmd) {
                        return [
                            'id' => $cmd->id,
                            'command' => $cmd->command,
                            'description' => $cmd->description,
                            'is_active' => $cmd->is_active,
                        ];
                    }),
                ];
            });

        return response()->json($installations);
    }

    /**
     * List available bots from registry (not yet installed)
     */
    public function available(Request $request, Workspace $workspace): JsonResponse
    {
        $installedBotIds = BotInstallation::where('workspace_id', $workspace->id)
            ->pluck('bot_id');

        $availableBots = Bot::where('is_active', true)
            ->whereNotIn('id', $installedBotIds)
            ->with('creator:id,name')
            ->get()
            ->map(function ($bot) {
                return [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'slug' => $bot->slug,
                    'description' => $bot->description,
                    'avatar_url' => $bot->avatar_url,
                    'config_schema' => $bot->config_schema,
                    'requires_configuration' => $bot->requiresConfiguration(),
                    'creator' => $bot->creator ? [
                        'id' => $bot->creator->id,
                        'name' => $bot->creator->name,
                    ] : null,
                ];
            });

        return response()->json($availableBots);
    }

    /**
     * Install a bot from registry
     */
    public function install(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'bot_id' => ['required', 'exists:bots,id'],
            'config' => ['nullable', 'array'],
        ]);

        $bot = Bot::findOrFail($validated['bot_id']);

        // Check if already installed
        $existing = BotInstallation::where('workspace_id', $workspace->id)
            ->where('bot_id', $bot->id)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Bot is already installed in this workspace'], 422);
        }

        // Merge provided config with defaults from schema
        $config = array_merge(
            $bot->getDefaultConfig(),
            $validated['config'] ?? []
        );

        // Validate config against schema
        $configErrors = $bot->validateConfig($config);
        if (!empty($configErrors)) {
            return response()->json([
                'error' => 'Invalid configuration',
                'config_errors' => $configErrors,
            ], 422);
        }

        // Create installation
        $installation = BotInstallation::create([
            'bot_id' => $bot->id,
            'workspace_id' => $workspace->id,
            'installed_by_user_id' => $request->user()->id,
            'config' => $config,
            'is_active' => true,
            'installed_at' => now(),
        ]);

        // Generate initial token
        $plainToken = BotToken::generateToken();
        $token = BotToken::create([
            'bot_installation_id' => $installation->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'Default Token',
            'scopes' => ['chat:write', 'channels:read'],
            'expires_at' => null, // No expiration by default
        ]);

        // Log installation
        $this->auditService->log(
            $workspace,
            'bot.installed',
            $request->user(),
            Bot::class,
            $bot->id,
            ['bot_name' => $bot->name, 'installation_id' => $installation->id]
        );

        return response()->json([
            'message' => 'Bot installed successfully',
            'installation' => [
                'id' => $installation->id,
                'bot' => [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'slug' => $bot->slug,
                ],
                'is_active' => $installation->is_active,
            ],
            'token' => $plainToken, // Only shown once!
        ], 201);
    }

    /**
     * Install a bot from manifest URL
     */
    public function installFromManifest(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'manifest_url' => ['required', 'url'],
            'config' => ['nullable', 'array'],
        ]);

        // Fetch manifest
        try {
            $response = Http::timeout(10)->get($validated['manifest_url']);
            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch manifest'], 422);
            }
            $manifest = $response->json();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch manifest: ' . $e->getMessage()], 422);
        }

        // Validate manifest structure
        if (!isset($manifest['name']) || !isset($manifest['webhook_url'])) {
            return response()->json(['error' => 'Invalid manifest: missing required fields (name, webhook_url)'], 422);
        }

        // Create or find bot by slug
        $slug = Str::slug($manifest['name']);
        $bot = Bot::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest['name'],
                'description' => $manifest['description'] ?? null,
                'avatar_url' => $manifest['avatar_url'] ?? null,
                'webhook_url' => $manifest['webhook_url'],
                'config_schema' => $manifest['config_schema'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
            ]
        );

        // Update config_schema if bot already exists but manifest has new schema
        if (isset($manifest['config_schema']) && $bot->config_schema !== $manifest['config_schema']) {
            $bot->update(['config_schema' => $manifest['config_schema']]);
        }

        // Check if already installed
        $existing = BotInstallation::where('workspace_id', $workspace->id)
            ->where('bot_id', $bot->id)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Bot is already installed in this workspace'], 422);
        }

        // Merge provided config with defaults from manifest and schema
        $config = array_merge(
            $bot->getDefaultConfig(),
            $manifest['default_config'] ?? [],
            $validated['config'] ?? []
        );

        // Validate config against schema
        $configErrors = $bot->validateConfig($config);
        if (!empty($configErrors)) {
            return response()->json([
                'error' => 'Invalid configuration',
                'config_errors' => $configErrors,
                'config_schema' => $bot->config_schema,
            ], 422);
        }

        // Create installation
        $installation = BotInstallation::create([
            'bot_id' => $bot->id,
            'workspace_id' => $workspace->id,
            'installed_by_user_id' => $request->user()->id,
            'config' => $config,
            'is_active' => true,
            'installed_at' => now(),
        ]);

        // Register slash commands from manifest
        if (isset($manifest['slash_commands']) && is_array($manifest['slash_commands'])) {
            foreach ($manifest['slash_commands'] as $cmd) {
                if (isset($cmd['command'])) {
                    SlashCommand::create([
                        'bot_installation_id' => $installation->id,
                        'command' => ltrim($cmd['command'], '/'),
                        'description' => $cmd['description'] ?? null,
                        'usage_hint' => $cmd['usage_hint'] ?? null,
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Generate initial token
        $plainToken = BotToken::generateToken();
        BotToken::create([
            'bot_installation_id' => $installation->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'Default Token',
            'scopes' => $manifest['scopes'] ?? ['chat:write', 'channels:read'],
            'expires_at' => null,
        ]);

        // Log installation
        $this->auditService->log(
            $workspace,
            'bot.installed_from_manifest',
            $request->user(),
            Bot::class,
            $bot->id,
            ['bot_name' => $bot->name, 'manifest_url' => $validated['manifest_url']]
        );

        return response()->json([
            'message' => 'Bot installed successfully from manifest',
            'installation' => [
                'id' => $installation->id,
                'bot' => [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'slug' => $bot->slug,
                    'description' => $bot->description,
                ],
                'is_active' => $installation->is_active,
            ],
            'token' => $plainToken,
        ], 201);
    }

    /**
     * Update bot installation (config, active status)
     */
    public function update(Request $request, Workspace $workspace, BotInstallation $installation): JsonResponse
    {
        if ($installation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        $validated = $request->validate([
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['config'])) {
            // Merge with existing config (allows partial updates)
            $newConfig = array_merge(
                $installation->config ?? [],
                $validated['config']
            );

            // Validate config against schema
            $configErrors = $installation->bot->validateConfig($newConfig);
            if (!empty($configErrors)) {
                return response()->json([
                    'error' => 'Invalid configuration',
                    'config_errors' => $configErrors,
                ], 422);
            }

            $installation->config = $newConfig;
        }

        if (isset($validated['is_active'])) {
            $installation->is_active = $validated['is_active'];
        }

        $installation->save();

        // Log update
        $this->auditService->log(
            $workspace,
            'bot.updated',
            $request->user(),
            BotInstallation::class,
            $installation->id,
            ['bot_name' => $installation->bot->name]
        );

        return response()->json([
            'message' => 'Bot installation updated',
            'installation' => [
                'id' => $installation->id,
                'config' => $installation->config,
                'is_active' => $installation->is_active,
            ],
        ]);
    }

    /**
     * Uninstall a bot
     */
    public function uninstall(Request $request, Workspace $workspace, BotInstallation $installation): JsonResponse
    {
        if ($installation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        $botName = $installation->bot->name;
        $botId = $installation->bot->id;

        // Delete tokens and slash commands (cascade should handle this, but be explicit)
        $installation->tokens()->delete();
        $installation->slashCommands()->delete();
        $installation->delete();

        // Log uninstall
        $this->auditService->log(
            $workspace,
            'bot.uninstalled',
            $request->user(),
            Bot::class,
            $botId,
            ['bot_name' => $botName]
        );

        return response()->json(['message' => 'Bot uninstalled successfully']);
    }

    /**
     * Generate a new token for bot installation
     */
    public function generateToken(Request $request, Workspace $workspace, BotInstallation $installation): JsonResponse
    {
        if ($installation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'expires_in' => ['nullable', 'string', Rule::in(['7d', '30d', '90d', '1y', 'never'])],
        ]);

        $expiresAt = match ($validated['expires_in'] ?? 'never') {
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            '90d' => now()->addDays(90),
            '1y' => now()->addYear(),
            'never' => null,
        };

        $plainToken = BotToken::generateToken();
        $token = BotToken::create([
            'bot_installation_id' => $installation->id,
            'token' => hash('sha256', $plainToken),
            'name' => $validated['name'],
            'scopes' => $validated['scopes'] ?? ['chat:write', 'channels:read'],
            'expires_at' => $expiresAt,
        ]);

        // Log token creation
        $this->auditService->log(
            $workspace,
            'bot.token_created',
            $request->user(),
            BotToken::class,
            $token->id,
            ['bot_name' => $installation->bot->name, 'token_name' => $validated['name']]
        );

        return response()->json([
            'message' => 'Token created successfully',
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'expires_at' => $token->expires_at,
                'plain_token' => $plainToken, // Only shown once!
            ],
        ], 201);
    }

    /**
     * Revoke a bot token
     */
    public function revokeToken(Request $request, Workspace $workspace, BotInstallation $installation, BotToken $token): JsonResponse
    {
        if ($installation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        if ($token->bot_installation_id !== $installation->id) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        $tokenName = $token->name;
        $token->delete();

        // Log token revocation
        $this->auditService->log(
            $workspace,
            'bot.token_revoked',
            $request->user(),
            BotInstallation::class,
            $installation->id,
            ['bot_name' => $installation->bot->name, 'token_name' => $tokenName]
        );

        return response()->json(['message' => 'Token revoked successfully']);
    }

    /**
     * List tokens for a bot installation
     */
    public function listTokens(Request $request, Workspace $workspace, BotInstallation $installation): JsonResponse
    {
        if ($installation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        $tokens = $installation->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'is_expired' => $token->isExpired(),
            ];
        });

        return response()->json($tokens);
    }
}
