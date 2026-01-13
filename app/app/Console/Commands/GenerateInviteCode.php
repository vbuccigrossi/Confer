<?php

namespace App\Console\Commands;

use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;

class GenerateInviteCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:generate
                            {--workspace= : The workspace ID (defaults to first workspace)}
                            {--admin= : Admin user ID (defaults to first admin)}
                            {--role=member : Role for invited users (admin/member)}
                            {--max-uses= : Maximum number of uses (leave empty for unlimited)}
                            {--days=7 : Number of days until expiration}
                            {--code= : Custom invite code (auto-generated if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an invite code for user registration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get workspace
        $workspaceId = $this->option('workspace');
        if (!$workspaceId) {
            $workspace = Workspace::first();
            if (!$workspace) {
                $this->error('No workspace found. Please create a workspace first.');
                return 1;
            }
            $workspaceId = $workspace->id;
        } else {
            $workspace = Workspace::find($workspaceId);
            if (!$workspace) {
                $this->error("Workspace with ID {$workspaceId} not found.");
                return 1;
            }
        }

        // Get admin user
        $adminId = $this->option('admin');
        if (!$adminId) {
            $admin = User::first();
            if (!$admin) {
                $this->error('No admin user found. Please create a user first.');
                return 1;
            }
            $adminId = $admin->id;
        } else {
            $admin = User::find($adminId);
            if (!$admin) {
                $this->error("User with ID {$adminId} not found.");
                return 1;
            }
        }

        // Validate role
        $role = $this->option('role');
        if (!in_array($role, ['admin', 'member'])) {
            $this->error('Role must be either "admin" or "member".');
            return 1;
        }

        // Get max uses
        $maxUses = $this->option('max-uses');
        if ($maxUses !== null && (!is_numeric($maxUses) || $maxUses < 1)) {
            $this->error('Max uses must be a positive number.');
            return 1;
        }

        // Get expiration days
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('Days must be at least 1.');
            return 1;
        }

        // Create invite
        $invite = Invite::create([
            'workspace_id' => $workspaceId,
            'email' => null, // Not tied to specific email
            'invited_by' => $adminId,
            'role' => $role,
            'is_single_use' => false,
            'max_uses' => $maxUses,
            'use_count' => 0,
            'expires_at' => now()->addDays($days),
            'invite_code' => $this->option('code') ?: strtoupper(\Illuminate\Support\Str::random(8)),
        ]);

        $this->info('');
        $this->info('✓ Invite code generated successfully!');
        $this->info('');
        $this->table(
            ['Property', 'Value'],
            [
                ['Invite Code', $invite->invite_code],
                ['Workspace', $workspace->name],
                ['Role', $invite->role],
                ['Max Uses', $maxUses ?? 'Unlimited'],
                ['Current Uses', $invite->use_count],
                ['Expires', $invite->expires_at->format('Y-m-d H:i:s')],
                ['Days Until Expiry', $invite->expires_at->diffInDays(now())],
            ]
        );
        $this->info('');
        $this->info('Share this code with users who need to register:');
        $this->line('  ' . $invite->invite_code);
        $this->info('');

        return 0;
    }
}
