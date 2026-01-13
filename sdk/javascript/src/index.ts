/**
 * Latch Bot SDK for JavaScript/TypeScript
 *
 * A simple SDK for building bots on the Latch chat platform.
 *
 * @example
 * ```typescript
 * import { LatchBot } from '@latch/bot-sdk';
 *
 * const bot = new LatchBot({ token: 'bot_YOUR_TOKEN' });
 * await bot.sendMessage({ conversationId: 123, text: 'Hello!' });
 * ```
 */

export { LatchBot } from './client';
export type { LatchBotOptions } from './client';

export { WebhookServer, CommandContext } from './webhook';
export type { CommandHandler, WebhookServerOptions } from './webhook';

export type {
  Message,
  Conversation,
  User,
  ConversationMember,
  Attachment,
  Reaction,
  CommandPayload,
  BotConfig,
} from './types';

export {
  LatchBotError,
  AuthenticationError,
  RateLimitError,
  NotFoundError,
  ValidationError,
  ServerError,
} from './errors';
