/**
 * Latch Bot SDK Errors
 *
 * Custom error classes for the Latch Bot SDK.
 */

/**
 * Base error class for all Latch Bot SDK errors.
 */
export class LatchBotError extends Error {
  readonly statusCode?: number;
  readonly responseBody?: Record<string, unknown>;

  constructor(
    message: string,
    statusCode?: number,
    responseBody?: Record<string, unknown>
  ) {
    super(message);
    this.name = 'LatchBotError';
    this.statusCode = statusCode;
    this.responseBody = responseBody;

    // Maintains proper stack trace for where error was thrown
    if (Error.captureStackTrace) {
      Error.captureStackTrace(this, LatchBotError);
    }
  }

  toString(): string {
    if (this.statusCode) {
      return `[${this.statusCode}] ${this.message}`;
    }
    return this.message;
  }
}

/**
 * Authentication error (401/403).
 */
export class AuthenticationError extends LatchBotError {
  constructor(
    message: string,
    statusCode?: number,
    responseBody?: Record<string, unknown>
  ) {
    super(message, statusCode, responseBody);
    this.name = 'AuthenticationError';
  }
}

/**
 * Rate limit exceeded error (429).
 */
export class RateLimitError extends LatchBotError {
  readonly retryAfter?: number;

  constructor(
    message: string,
    retryAfter?: number,
    statusCode: number = 429,
    responseBody?: Record<string, unknown>
  ) {
    super(message, statusCode, responseBody);
    this.name = 'RateLimitError';
    this.retryAfter = retryAfter;
  }
}

/**
 * Resource not found error (404).
 */
export class NotFoundError extends LatchBotError {
  constructor(
    message: string,
    statusCode: number = 404,
    responseBody?: Record<string, unknown>
  ) {
    super(message, statusCode, responseBody);
    this.name = 'NotFoundError';
  }
}

/**
 * Validation error (422).
 */
export class ValidationError extends LatchBotError {
  readonly errors?: Record<string, string[]>;

  constructor(
    message: string,
    errors?: Record<string, string[]>,
    statusCode: number = 422,
    responseBody?: Record<string, unknown>
  ) {
    super(message, statusCode, responseBody);
    this.name = 'ValidationError';
    this.errors = errors;
  }
}

/**
 * Server error (5xx).
 */
export class ServerError extends LatchBotError {
  constructor(
    message: string,
    statusCode?: number,
    responseBody?: Record<string, unknown>
  ) {
    super(message, statusCode, responseBody);
    this.name = 'ServerError';
  }
}
