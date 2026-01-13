/**
 * Latch Bot SDK Types
 *
 * TypeScript interfaces for Latch entities.
 */

/**
 * Represents a Latch user.
 */
export interface User {
  id: number;
  name: string;
  email?: string;
  avatar_url?: string;
}

/**
 * Represents a member of a conversation.
 */
export interface ConversationMember {
  id: number;
  conversation_id: number;
  user: User;
}

/**
 * Represents a file attachment.
 */
export interface Attachment {
  id: number;
  filename: string;
  mime_type: string;
  size: number;
  url?: string;
}

/**
 * Represents a reaction on a message.
 */
export interface Reaction {
  id: number;
  emoji: string;
  user_id: number;
  user?: User;
}

/**
 * Represents a Latch message.
 */
export interface Message {
  id: number;
  conversation_id: number;
  user_id: number;
  body_md: string;
  body_html: string;
  parent_message_id?: number | null;
  created_at?: string;
  updated_at?: string;
  user?: User;
  reactions: Reaction[];
  attachments: Attachment[];
}

/**
 * Conversation types.
 */
export type ConversationType =
  | 'public_channel'
  | 'private_channel'
  | 'dm'
  | 'group_dm';

/**
 * Represents a Latch conversation (channel or DM).
 */
export interface Conversation {
  id: number;
  workspace_id: number;
  name?: string;
  description?: string;
  type: ConversationType;
  is_archived: boolean;
  created_by?: number;
  members: ConversationMember[];
}

/**
 * Bot configuration values.
 */
export type BotConfig = Record<string, unknown>;

/**
 * Slash command invocation payload.
 */
export interface CommandPayload {
  command: string;
  text: string;
  conversation_id: number;
  user_id: number;
  user_name: string;
  workspace_id: number;
  config?: BotConfig;
}

/**
 * API response for sending a message.
 */
export interface SendMessageResponse {
  success: boolean;
  message: Message;
}

/**
 * API response for getting a conversation.
 */
export interface GetConversationResponse {
  conversation: Conversation;
}

/**
 * Ephemeral response (only visible to command invoker).
 */
export interface EphemeralResponse {
  type: 'ephemeral';
  text: string;
}

/**
 * Acknowledgment response.
 */
export interface AckResponse {
  ok: boolean;
}

/**
 * Error response from the API.
 */
export interface ErrorResponse {
  error: string;
  code?: string;
  errors?: Record<string, string[]>;
}
