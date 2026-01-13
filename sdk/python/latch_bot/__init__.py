"""
Latch Bot SDK for Python

A simple SDK for building bots on the Latch chat platform.

Example usage:
    from latch_bot import LatchBot

    bot = LatchBot(token="bot_YOUR_TOKEN")
    bot.send_message(conversation_id=123, text="Hello!")
"""

from .client import LatchBot
from .models import Message, Conversation, User, ConversationMember
from .exceptions import (
    LatchBotError,
    AuthenticationError,
    RateLimitError,
    NotFoundError,
    ValidationError,
)
from .webhook import WebhookServer, CommandContext

__version__ = "1.0.0"
__all__ = [
    "LatchBot",
    "Message",
    "Conversation",
    "User",
    "ConversationMember",
    "LatchBotError",
    "AuthenticationError",
    "RateLimitError",
    "NotFoundError",
    "ValidationError",
    "WebhookServer",
    "CommandContext",
]
