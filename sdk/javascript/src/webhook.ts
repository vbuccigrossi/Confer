/**
 * Latch Bot SDK Webhook Server
 *
 * Server for handling slash command callbacks.
 */

import type {
  CommandPayload,
  EphemeralResponse,
  AckResponse,
  BotConfig,
} from './types';
import type { LatchBot } from './client';

/**
 * Context object passed to command handlers.
 */
export class CommandContext {
  readonly payload: CommandPayload;
  private readonly bot: LatchBot;

  constructor(payload: CommandPayload, bot: LatchBot) {
    this.payload = payload;
    this.bot = bot;
  }

  /** The command name (without /) */
  get command(): string {
    return this.payload.command;
  }

  /** The text/arguments provided with the command */
  get text(): string {
    return this.payload.text;
  }

  /** The text split into arguments */
  get args(): string[] {
    return this.payload.text ? this.payload.text.split(/\s+/) : [];
  }

  /** The conversation where the command was invoked */
  get conversationId(): number {
    return this.payload.conversation_id;
  }

  /** The user who invoked the command */
  get userId(): number {
    return this.payload.user_id;
  }

  /** The name of the user who invoked the command */
  get userName(): string {
    return this.payload.user_name;
  }

  /** The workspace where the command was invoked */
  get workspaceId(): number {
    return this.payload.workspace_id;
  }

  /**
   * Bot configuration values set by workspace admin.
   *
   * Returns a record of configuration key-value pairs as defined
   * in the bot's config_schema and configured by the admin.
   */
  get config(): BotConfig {
    return this.payload.config ?? {};
  }

  /**
   * Send a reply message to the conversation.
   *
   * @param text - Message content (supports Markdown)
   */
  async reply(text: string): Promise<void> {
    await this.bot.sendMessage({
      conversationId: this.conversationId,
      text,
    });
  }

  /**
   * Return an ephemeral (only visible to invoker) response.
   *
   * @param text - Message content
   * @returns Ephemeral response object
   */
  replyEphemeral(text: string): EphemeralResponse {
    return { type: 'ephemeral', text };
  }
}

/**
 * Command handler function type.
 */
export type CommandHandler = (
  ctx: CommandContext
) => void | EphemeralResponse | AckResponse | Promise<void | EphemeralResponse | AckResponse>;

/**
 * Options for the WebhookServer.
 */
export interface WebhookServerOptions {
  /** LatchBot client instance */
  bot: LatchBot;
  /** Enable debug logging */
  debug?: boolean;
}

/**
 * Webhook server for handling slash command callbacks.
 *
 * @example
 * ```typescript
 * import express from 'express';
 * import { LatchBot, WebhookServer } from '@latch/bot-sdk';
 *
 * const app = express();
 * const bot = new LatchBot({ token: 'bot_YOUR_TOKEN' });
 * const webhook = new WebhookServer({ bot });
 *
 * webhook.onCommand('weather', async (ctx) => {
 *   const city = ctx.text || 'London';
 *   await ctx.reply(`Weather in ${city}: Sunny, 22°C`);
 * });
 *
 * app.post('/latch/webhook', express.json(), (req, res) => {
 *   const result = webhook.handle(req.body);
 *   res.json(result);
 * });
 *
 * app.listen(3000);
 * ```
 */
export class WebhookServer {
  private readonly bot: LatchBot;
  private readonly debug: boolean;
  private readonly handlers: Map<string, CommandHandler> = new Map();
  private defaultHandler?: CommandHandler;

  constructor(options: WebhookServerOptions) {
    this.bot = options.bot;
    this.debug = options.debug ?? false;
  }

  /**
   * Register a command handler.
   *
   * @param name - Command name (without /)
   * @param handler - Handler function
   *
   * @example
   * ```typescript
   * webhook.onCommand('ping', async (ctx) => {
   *   await ctx.reply('Pong!');
   * });
   * ```
   */
  onCommand(name: string, handler: CommandHandler): this {
    this.handlers.set(name.toLowerCase(), handler);
    if (this.debug) {
      console.log(`[WebhookServer] Registered handler for command: ${name}`);
    }
    return this;
  }

  /**
   * Register a default handler for unknown commands.
   *
   * @param handler - Handler function
   */
  onDefault(handler: CommandHandler): this {
    this.defaultHandler = handler;
    return this;
  }

  /**
   * Handle an incoming webhook request.
   *
   * @param data - Request JSON data
   * @returns Response object
   */
  async handle(
    data: Record<string, unknown>
  ): Promise<EphemeralResponse | AckResponse | { error: string }> {
    if (this.debug) {
      console.log('[WebhookServer] Received webhook:', JSON.stringify(data, null, 2));
    }

    // Validate payload
    if (
      typeof data.command !== 'string' ||
      typeof data.conversation_id !== 'number' ||
      typeof data.user_id !== 'number' ||
      typeof data.workspace_id !== 'number'
    ) {
      console.error('[WebhookServer] Invalid payload');
      return { error: 'Invalid payload' };
    }

    const payload: CommandPayload = {
      command: data.command as string,
      text: (data.text as string) || '',
      conversation_id: data.conversation_id as number,
      user_id: data.user_id as number,
      user_name: (data.user_name as string) || '',
      workspace_id: data.workspace_id as number,
      config: (data.config as BotConfig) || {},
    };

    const ctx = new CommandContext(payload, this.bot);

    // Find handler
    let handler = this.handlers.get(payload.command.toLowerCase());
    if (!handler) {
      handler = this.defaultHandler;
    }

    if (!handler) {
      if (this.debug) {
        console.warn(`[WebhookServer] No handler for command: ${payload.command}`);
      }
      return { ok: true };
    }

    try {
      const result = await handler(ctx);
      if (result !== undefined) {
        return result;
      }
      return { ok: true };
    } catch (error) {
      console.error(
        `[WebhookServer] Handler error for /${payload.command}:`,
        error
      );
      return { error: error instanceof Error ? error.message : 'Unknown error' };
    }
  }

  /**
   * Get an Express-compatible middleware function.
   *
   * @returns Express middleware
   *
   * @example
   * ```typescript
   * app.post('/webhook', express.json(), webhook.middleware());
   * ```
   */
  middleware(): (
    req: { body: Record<string, unknown> },
    res: { json: (data: unknown) => void }
  ) => Promise<void> {
    return async (req, res) => {
      const result = await this.handle(req.body);
      res.json(result);
    };
  }
}
