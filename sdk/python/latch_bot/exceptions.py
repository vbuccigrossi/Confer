"""
Latch Bot SDK Exceptions

Custom exceptions for the Latch Bot SDK.
"""

from typing import Optional, Dict, Any


class LatchBotError(Exception):
    """Base exception for all Latch Bot SDK errors."""

    def __init__(
        self,
        message: str,
        status_code: Optional[int] = None,
        response_body: Optional[Dict[str, Any]] = None,
    ):
        super().__init__(message)
        self.message = message
        self.status_code = status_code
        self.response_body = response_body or {}

    def __str__(self) -> str:
        if self.status_code:
            return f"[{self.status_code}] {self.message}"
        return self.message


class AuthenticationError(LatchBotError):
    """Raised when authentication fails (401/403)."""

    pass


class RateLimitError(LatchBotError):
    """Raised when rate limit is exceeded (429)."""

    def __init__(
        self,
        message: str,
        retry_after: Optional[int] = None,
        status_code: int = 429,
        response_body: Optional[Dict[str, Any]] = None,
    ):
        super().__init__(message, status_code, response_body)
        self.retry_after = retry_after


class NotFoundError(LatchBotError):
    """Raised when a resource is not found (404)."""

    pass


class ValidationError(LatchBotError):
    """Raised when validation fails (422)."""

    def __init__(
        self,
        message: str,
        errors: Optional[Dict[str, list]] = None,
        status_code: int = 422,
        response_body: Optional[Dict[str, Any]] = None,
    ):
        super().__init__(message, status_code, response_body)
        self.errors = errors or {}


class ServerError(LatchBotError):
    """Raised when a server error occurs (5xx)."""

    pass
