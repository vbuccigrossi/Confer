/**
 * Latch Bot SDK Client
 *
 * Main client for interacting with the Latch Bot API.
 */

import type {
  Message,
  Conversation,
  SendMessageResponse,
  GetConversationResponse,
} from './types';

import {
  LatchBotError,
  AuthenticationError,
  RateLimitError,
  NotFoundError,
  ValidationError,
  ServerError,
} from './errors';

/**
 * Options for initializing the LatchBot client.
 */
export interface LatchBotOptions {
  /** Bot API token (starts with 'bot_') */
  token: string;
  /** Base URL of the Latch instance */
  baseUrl?: string;
  /** Request timeout in milliseconds */
  timeout?: number;
  /** Maximum number of retries for rate-limited requests */
  maxRetries?: number;
  /** Enable debug logging */
  debug?: boolean;
}

/**
 * Options for sending a message.
 */
export interface SendMessageOptions {
  /** Target conversation ID */
  conversationId: number;
  /** Message content (supports Markdown) */
  text: string;
  /** Optional parent message ID for threaded replies */
  threadId?: number;
}

/**
 * Sleep for a specified duration.
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Latch Bot API client.
 *
 * @example
 * ```typescript
 * const bot = new LatchBot({
 *   token: 'bot_YOUR_TOKEN',
 *   baseUrl: 'https://your-latch-instance.com'
 * });
 *
 * // Send a message
 * const message = await bot.sendMessage({
 *   conversationId: 123,
 *   text: 'Hello, world!'
 * });
 *
 * // Get conversation info
 * const conversation = await bot.getConversation(123);
 * ```
 */
export class LatchBot {
  private readonly token: string;
  private readonly baseUrl: string;
  private readonly timeout: number;
  private readonly maxRetries: number;
  private readonly debug: boolean;

  private static readonly DEFAULT_TIMEOUT = 30000;
  private static readonly DEFAULT_RETRIES = 3;
  private static readonly USER_AGENT = 'LatchBotSDK/1.0.0 (JavaScript)';

  constructor(options: LatchBotOptions) {
    if (!options.token) {
      throw new Error('Token is required');
    }
    if (!options.token.startsWith('bot_')) {
      throw new Error("Invalid token format. Token should start with 'bot_'");
    }

    this.token = options.token;
    this.baseUrl = (options.baseUrl || 'http://localhost').replace(/\/$/, '');
    this.timeout = options.timeout ?? LatchBot.DEFAULT_TIMEOUT;
    this.maxRetries = options.maxRetries ?? LatchBot.DEFAULT_RETRIES;
    this.debug = options.debug ?? false;
  }

  /**
   * Send a message to a conversation.
   *
   * @param options - Message options
   * @returns The created message
   *
   * @example
   * ```typescript
   * const message = await bot.sendMessage({
   *   conversationId: 123,
   *   text: '**Hello** from the bot!'
   * });
   * console.log(`Sent message ${message.id}`);
   * ```
   */
  async sendMessage(options: SendMessageOptions): Promise<Message> {
    const payload: Record<string, unknown> = {
      conversation_id: options.conversationId,
      text: options.text,
    };

    if (options.threadId !== undefined) {
      payload.thread_id = options.threadId;
    }

    const response = await this.request<SendMessageResponse>(
      'POST',
      '/api/bot/messages',
      payload
    );

    return response.message;
  }

  /**
   * Send a reply to a thread.
   *
   * @param conversationId - Conversation ID
   * @param threadId - Parent message ID
   * @param text - Reply content
   * @returns The created reply message
   */
  async sendThreadedReply(
    conversationId: number,
    threadId: number,
    text: string
  ): Promise<Message> {
    return this.sendMessage({
      conversationId,
      text,
      threadId,
    });
  }

  /**
   * Get information about a conversation.
   *
   * @param conversationId - Conversation ID
   * @returns The conversation details
   *
   * @example
   * ```typescript
   * const conv = await bot.getConversation(123);
   * console.log(`Channel: ${conv.name}, Members: ${conv.members.length}`);
   * ```
   */
  async getConversation(conversationId: number): Promise<Conversation> {
    const response = await this.request<GetConversationResponse>(
      'GET',
      `/api/bot/conversations/${conversationId}`
    );

    return response.conversation;
  }

  /**
   * Make an HTTP request to the Latch API.
   */
  private async request<T>(
    method: string,
    path: string,
    body?: Record<string, unknown>,
    retryCount: number = 0
  ): Promise<T> {
    const url = `${this.baseUrl}${path}`;

    if (this.debug) {
      console.log(`[LatchBot] ${method} ${url}`);
      if (body) {
        console.log('[LatchBot] Request body:', JSON.stringify(body, null, 2));
      }
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(url, {
        method,
        headers: {
          Authorization: `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'User-Agent': LatchBot.USER_AGENT,
        },
        body: body ? JSON.stringify(body) : undefined,
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (this.debug) {
        console.log(`[LatchBot] Response status: ${response.status}`);
      }

      return await this.handleResponse<T>(
        response,
        method,
        path,
        body,
        retryCount
      );
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof LatchBotError) {
        throw error;
      }

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new LatchBotError(`Request timed out after ${this.timeout}ms`);
        }
        throw new LatchBotError(`Request failed: ${error.message}`);
      }

      throw new LatchBotError('Request failed');
    }
  }

  /**
   * Handle the API response and throw appropriate errors.
   */
  private async handleResponse<T>(
    response: Response,
    method: string,
    path: string,
    body?: Record<string, unknown>,
    retryCount: number = 0
  ): Promise<T> {
    const status = response.status;

    // Success
    if (status >= 200 && status < 300) {
      const text = await response.text();
      if (!text) {
        return {} as T;
      }
      try {
        const data = JSON.parse(text);
        if (this.debug) {
          console.log('[LatchBot] Response:', JSON.stringify(data, null, 2));
        }
        return data as T;
      } catch {
        return { raw: text } as T;
      }
    }

    // Parse error response
    let responseBody: Record<string, unknown>;
    try {
      responseBody = await response.json();
    } catch {
      responseBody = { error: await response.text() };
    }

    const errorMessage =
      (responseBody.error as string) ||
      (responseBody.message as string) ||
      'Unknown error';

    // Handle specific error codes
    if (status === 401) {
      throw new AuthenticationError(
        'Invalid or missing authentication token',
        status,
        responseBody
      );
    }

    if (status === 403) {
      const code = responseBody.code as string;
      if (code === 'TOKEN_EXPIRED') {
        throw new AuthenticationError('Token has expired', status, responseBody);
      }
      if (code === 'BOT_INACTIVE') {
        throw new AuthenticationError('Bot is not active', status, responseBody);
      }
      throw new AuthenticationError(
        `Access denied: ${errorMessage}`,
        status,
        responseBody
      );
    }

    if (status === 404) {
      throw new NotFoundError(errorMessage, status, responseBody);
    }

    if (status === 422) {
      throw new ValidationError(
        errorMessage,
        responseBody.errors as Record<string, string[]>,
        status,
        responseBody
      );
    }

    if (status === 429) {
      const retryAfter = parseInt(
        response.headers.get('Retry-After') || '60',
        10
      );

      if (retryCount < this.maxRetries) {
        console.warn(
          `[LatchBot] Rate limited, retrying in ${retryAfter}s ` +
            `(attempt ${retryCount + 1}/${this.maxRetries})`
        );
        await sleep(retryAfter * 1000);
        return this.request(method, path, body, retryCount + 1);
      }

      throw new RateLimitError(
        'Rate limit exceeded',
        retryAfter,
        status,
        responseBody
      );
    }

    if (status >= 500) {
      throw new ServerError(
        `Server error: ${errorMessage}`,
        status,
        responseBody
      );
    }

    throw new LatchBotError(errorMessage, status, responseBody);
  }

  toString(): string {
    return `LatchBot(baseUrl='${this.baseUrl}')`;
  }
}
