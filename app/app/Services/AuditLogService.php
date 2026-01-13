<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Centralized service for audit logging
 */
class AuditLogService
{
    /**
     * Generic audit log creator
     */
    public function log(
        Workspace $workspace,
        string $action,
        ?User $actor = null,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): AuditLog {
        return AuditLog::create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $targetType,
            'subject_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Log from current HTTP request context
     */
    public function logFromRequest(
        Workspace $workspace,
        string $action,
        ?User $actor,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = []
    ): AuditLog {
        $request = app(Request::class);

        return $this->log(
            workspace: $workspace,
            action: $action,
            actor: $actor,
            targetType: $targetType,
            targetId: $targetId,
            metadata: $metadata,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    /**
     * Log workspace member added
     */
    public function logWorkspaceMemberAdded(
        Workspace $workspace,
        User $actor,
        User $member,
        string $role
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.member.added',
            actor: $actor,
            targetType: User::class,
            targetId: $member->id,
            metadata: [
                'member_name' => $member->name,
                'member_email' => $member->email,
                'role' => $role,
            ]
        );
    }

    /**
     * Log workspace member role changed
     */
    public function logWorkspaceMemberRoleChanged(
        Workspace $workspace,
        User $actor,
        User $member,
        string $oldRole,
        string $newRole
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.member.role_changed',
            actor: $actor,
            targetType: User::class,
            targetId: $member->id,
            metadata: [
                'member_name' => $member->name,
                'member_email' => $member->email,
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ]
        );
    }

    /**
     * Log workspace member password reset
     */
    public function logWorkspaceMemberPasswordReset(
        Workspace $workspace,
        User $actor,
        User $member
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.member.password_reset',
            actor: $actor,
            targetType: User::class,
            targetId: $member->id,
            metadata: [
                'member_name' => $member->name,
                'member_email' => $member->email,
            ]
        );
    }

    /**
     * Log workspace member removed
     */
    public function logWorkspaceMemberRemoved(
        Workspace $workspace,
        User $actor,
        User $member
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.member.removed',
            actor: $actor,
            targetType: User::class,
            targetId: $member->id,
            metadata: [
                'member_name' => $member->name,
                'member_email' => $member->email,
            ]
        );
    }

    /**
     * Log conversation created
     */
    public function logConversationCreated(
        Conversation $conversation,
        User $actor
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $conversation->workspace,
            action: 'conversation.created',
            actor: $actor,
            targetType: Conversation::class,
            targetId: $conversation->id,
            metadata: [
                'conversation_name' => $conversation->name,
                'conversation_type' => $conversation->type,
                'is_private' => $conversation->is_private,
            ]
        );
    }

    /**
     * Log conversation archived
     */
    public function logConversationArchived(
        Conversation $conversation,
        User $actor
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $conversation->workspace,
            action: 'conversation.archived',
            actor: $actor,
            targetType: Conversation::class,
            targetId: $conversation->id,
            metadata: [
                'conversation_name' => $conversation->name,
            ]
        );
    }

    /**
     * Log conversation unarchived
     */
    public function logConversationUnarchived(
        Conversation $conversation,
        User $actor
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $conversation->workspace,
            action: 'conversation.unarchived',
            actor: $actor,
            targetType: Conversation::class,
            targetId: $conversation->id,
            metadata: [
                'conversation_name' => $conversation->name,
            ]
        );
    }

    /**
     * Log message deleted by admin
     */
    public function logMessageDeletedByAdmin(
        Message $message,
        User $admin
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $message->conversation->workspace,
            action: 'message.deleted_by_admin',
            actor: $admin,
            targetType: Message::class,
            targetId: $message->id,
            metadata: [
                'conversation_id' => $message->conversation_id,
                'conversation_name' => $message->conversation->name,
                'original_author_id' => $message->user_id,
                'message_preview' => substr($message->body, 0, 100),
            ]
        );
    }

    /**
     * Log workspace settings changed
     */
    public function logWorkspaceSettingsChanged(
        Workspace $workspace,
        User $actor,
        array $changes
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.settings.changed',
            actor: $actor,
            targetType: Workspace::class,
            targetId: $workspace->id,
            metadata: [
                'changes' => $changes,
            ]
        );
    }

    /**
     * Log retention purge executed
     */
    public function logRetentionPurgeExecuted(
        Workspace $workspace,
        User $actor,
        int $deletedCount,
        array $conversationCounts = []
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'retention.purge_executed',
            actor: $actor,
            targetType: Workspace::class,
            targetId: $workspace->id,
            metadata: [
                'deleted_count' => $deletedCount,
                'conversation_counts' => $conversationCounts,
            ]
        );
    }

    /**
     * Log invite sent
     */
    public function logInviteSent(
        Workspace $workspace,
        User $actor,
        string $email,
        string $role
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.invite.sent',
            actor: $actor,
            metadata: [
                'email' => $email,
                'role' => $role,
            ]
        );
    }

    /**
     * Log invite accepted
     */
    public function logInviteAccepted(
        Workspace $workspace,
        User $user,
        string $email
    ): AuditLog {
        return $this->logFromRequest(
            workspace: $workspace,
            action: 'workspace.invite.accepted',
            actor: $user,
            targetType: User::class,
            targetId: $user->id,
            metadata: [
                'email' => $email,
            ]
        );
    }
}
