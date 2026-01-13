"""
Latch Bot SDK Client

Main client for interacting with the Latch Bot API.
"""

import time
import logging
from typing import Optional, Dict, Any
from urllib.parse import urljoin

import requests

from .models import Message, Conversation
from .exceptions import (
    LatchBotError,
    AuthenticationError,
    RateLimitError,
    NotFoundError,
    ValidationError,
    ServerError,
)

logger = logging.getLogger(__name__)


class LatchBot:
    """
    Latch Bot API client.

    Example usage:
        bot = LatchBot(
            token="bot_YOUR_TOKEN",
            base_url="https://your-latch-instance.com"
        )

        # Send a message
        message = bot.send_message(
            conversation_id=123,
            text="Hello, world!"
        )

        # Get conversation info
        conversation = bot.get_conversation(123)
    """

    DEFAULT_TIMEOUT = 30
    DEFAULT_RETRIES = 3
    USER_AGENT = "LatchBotSDK/1.0.0 (Python)"

    def __init__(
        self,
        token: str,
        base_url: str = "http://localhost",
        timeout: int = DEFAULT_TIMEOUT,
        max_retries: int = DEFAULT_RETRIES,
        debug: bool = False,
    ):
        """
        Initialize the Latch Bot client.

        Args:
            token: Bot API token (starts with 'bot_')
            base_url: Base URL of the Latch instance
            timeout: Request timeout in seconds
            max_retries: Maximum number of retries for rate-limited requests
            debug: Enable debug logging
        """
        if not token:
            raise ValueError("Token is required")
        if not token.startswith("bot_"):
            raise ValueError("Invalid token format. Token should start with 'bot_'")

        self.token = token
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.max_retries = max_retries
        self.debug = debug

        if debug:
            logging.basicConfig(level=logging.DEBUG)
            logger.setLevel(logging.DEBUG)

        self._session = requests.Session()
        self._session.headers.update(
            {
                "Authorization": f"Bearer {token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": self.USER_AGENT,
            }
        )

    def send_message(
        self,
        conversation_id: int,
        text: str,
        thread_id: Optional[int] = None,
    ) -> Message:
        """
        Send a message to a conversation.

        Args:
            conversation_id: ID of the conversation to send to
            text: Message content (supports Markdown)
            thread_id: Optional parent message ID for threaded replies

        Returns:
            Message: The created message

        Raises:
            ValidationError: If the message content is invalid
            NotFoundError: If the conversation doesn't exist
            AuthenticationError: If the token is invalid or expired
            RateLimitError: If rate limited

        Example:
            message = bot.send_message(
                conversation_id=123,
                text="**Hello** from the bot!"
            )
            print(f"Sent message {message.id}")
        """
        payload: Dict[str, Any] = {
            "conversation_id": conversation_id,
            "text": text,
        }
        if thread_id is not None:
            payload["thread_id"] = thread_id

        response = self._request("POST", "/api/bot/messages", json=payload)
        return Message.from_dict(response["message"])

    def send_threaded_reply(
        self,
        conversation_id: int,
        thread_id: int,
        text: str,
    ) -> Message:
        """
        Send a reply to a thread.

        Args:
            conversation_id: ID of the conversation
            thread_id: ID of the parent message
            text: Reply content (supports Markdown)

        Returns:
            Message: The created reply message
        """
        return self.send_message(
            conversation_id=conversation_id,
            text=text,
            thread_id=thread_id,
        )

    def get_conversation(self, conversation_id: int) -> Conversation:
        """
        Get information about a conversation.

        Args:
            conversation_id: ID of the conversation

        Returns:
            Conversation: The conversation details

        Raises:
            NotFoundError: If the conversation doesn't exist
            AuthenticationError: If the bot doesn't have access

        Example:
            conv = bot.get_conversation(123)
            print(f"Channel: {conv.name}, Members: {len(conv.members)}")
        """
        response = self._request("GET", f"/api/bot/conversations/{conversation_id}")
        return Conversation.from_dict(response["conversation"])

    def _request(
        self,
        method: str,
        path: str,
        json: Optional[Dict[str, Any]] = None,
        params: Optional[Dict[str, Any]] = None,
        retry_count: int = 0,
    ) -> Dict[str, Any]:
        """
        Make an HTTP request to the Latch API.

        Args:
            method: HTTP method (GET, POST, etc.)
            path: API path (e.g., /api/bot/messages)
            json: JSON body data
            params: Query parameters
            retry_count: Current retry attempt

        Returns:
            Dict containing the response data

        Raises:
            LatchBotError: On API errors
        """
        url = urljoin(self.base_url, path)

        if self.debug:
            logger.debug(f"{method} {url}")
            if json:
                logger.debug(f"Request body: {json}")

        try:
            response = self._session.request(
                method=method,
                url=url,
                json=json,
                params=params,
                timeout=self.timeout,
            )
        except requests.RequestException as e:
            raise LatchBotError(f"Request failed: {e}")

        if self.debug:
            logger.debug(f"Response status: {response.status_code}")
            logger.debug(f"Response body: {response.text[:500]}")

        return self._handle_response(response, method, path, json, params, retry_count)

    def _handle_response(
        self,
        response: requests.Response,
        method: str,
        path: str,
        json: Optional[Dict[str, Any]],
        params: Optional[Dict[str, Any]],
        retry_count: int,
    ) -> Dict[str, Any]:
        """Handle the API response and raise appropriate exceptions."""
        status = response.status_code

        # Success
        if 200 <= status < 300:
            if not response.text:
                return {}
            try:
                return response.json()
            except ValueError:
                return {"raw": response.text}

        # Try to parse error response
        try:
            body = response.json()
        except ValueError:
            body = {"error": response.text}

        error_message = body.get("error", body.get("message", "Unknown error"))

        # Handle specific error codes
        if status == 401:
            raise AuthenticationError(
                "Invalid or missing authentication token",
                status_code=status,
                response_body=body,
            )

        if status == 403:
            code = body.get("code", "")
            if code == "TOKEN_EXPIRED":
                raise AuthenticationError(
                    "Token has expired",
                    status_code=status,
                    response_body=body,
                )
            elif code == "BOT_INACTIVE":
                raise AuthenticationError(
                    "Bot is not active",
                    status_code=status,
                    response_body=body,
                )
            raise AuthenticationError(
                f"Access denied: {error_message}",
                status_code=status,
                response_body=body,
            )

        if status == 404:
            raise NotFoundError(
                error_message,
                status_code=status,
                response_body=body,
            )

        if status == 422:
            raise ValidationError(
                error_message,
                errors=body.get("errors", {}),
                status_code=status,
                response_body=body,
            )

        if status == 429:
            retry_after = int(response.headers.get("Retry-After", 60))

            if retry_count < self.max_retries:
                logger.warning(
                    f"Rate limited, retrying in {retry_after}s "
                    f"(attempt {retry_count + 1}/{self.max_retries})"
                )
                time.sleep(retry_after)
                return self._request(
                    method, path, json, params, retry_count=retry_count + 1
                )

            raise RateLimitError(
                "Rate limit exceeded",
                retry_after=retry_after,
                status_code=status,
                response_body=body,
            )

        if status >= 500:
            raise ServerError(
                f"Server error: {error_message}",
                status_code=status,
                response_body=body,
            )

        raise LatchBotError(
            error_message,
            status_code=status,
            response_body=body,
        )

    def __repr__(self) -> str:
        return f"LatchBot(base_url='{self.base_url}')"
