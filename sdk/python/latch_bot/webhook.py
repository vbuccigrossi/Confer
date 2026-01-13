"""
Latch Bot SDK Webhook Server

Server for handling slash command callbacks.
"""

import logging
from typing import Callable, Dict, Optional, Any, Awaitable
from dataclasses import dataclass

from .client import LatchBot
from .models import CommandPayload

logger = logging.getLogger(__name__)


@dataclass
class CommandContext:
    """
    Context object passed to command handlers.

    Provides easy access to command data and helper methods.
    """

    payload: CommandPayload
    bot: LatchBot

    @property
    def command(self) -> str:
        """The command name (without /)."""
        return self.payload.command

    @property
    def text(self) -> str:
        """The text/arguments provided with the command."""
        return self.payload.text

    @property
    def args(self) -> list:
        """The text split into arguments."""
        return self.payload.text.split() if self.payload.text else []

    @property
    def conversation_id(self) -> int:
        """The conversation where the command was invoked."""
        return self.payload.conversation_id

    @property
    def user_id(self) -> int:
        """The user who invoked the command."""
        return self.payload.user_id

    @property
    def user_name(self) -> str:
        """The name of the user who invoked the command."""
        return self.payload.user_name

    @property
    def workspace_id(self) -> int:
        """The workspace where the command was invoked."""
        return self.payload.workspace_id

    @property
    def config(self) -> Dict[str, Any]:
        """
        Bot configuration values set by workspace admin.

        Returns a dict of configuration key-value pairs as defined
        in the bot's config_schema and configured by the admin.
        """
        return self.payload.config

    def reply(self, text: str) -> None:
        """
        Send a reply message to the conversation.

        Args:
            text: Message content (supports Markdown)
        """
        self.bot.send_message(
            conversation_id=self.conversation_id,
            text=text,
        )

    def reply_ephemeral(self, text: str) -> Dict[str, Any]:
        """
        Return an ephemeral (only visible to invoker) response.

        Note: This should be returned from the handler to be sent
        as the immediate response.

        Args:
            text: Message content

        Returns:
            Dict with ephemeral response format
        """
        return {"type": "ephemeral", "text": text}


CommandHandler = Callable[[CommandContext], Optional[Dict[str, Any]]]


class WebhookServer:
    """
    Webhook server for handling slash command callbacks.

    Can be used standalone or integrated with existing web frameworks.

    Example (standalone with Flask):
        from flask import Flask, request
        from latch_bot import LatchBot, WebhookServer

        app = Flask(__name__)
        bot = LatchBot(token="bot_YOUR_TOKEN")
        webhook = WebhookServer(bot)

        @webhook.command("weather")
        def handle_weather(ctx):
            city = ctx.text or "London"
            # Fetch weather...
            ctx.reply(f"Weather in {city}: Sunny, 22°C")

        @app.route('/latch/webhook', methods=['POST'])
        def latch_webhook():
            return webhook.handle(request.json)

    Example (with FastAPI):
        from fastapi import FastAPI, Request
        from latch_bot import LatchBot, WebhookServer

        app = FastAPI()
        bot = LatchBot(token="bot_YOUR_TOKEN")
        webhook = WebhookServer(bot)

        @webhook.command("ping")
        def handle_ping(ctx):
            return ctx.reply_ephemeral("Pong!")

        @app.post('/latch/webhook')
        async def latch_webhook(request: Request):
            data = await request.json()
            return webhook.handle(data)
    """

    def __init__(self, bot: LatchBot, debug: bool = False):
        """
        Initialize the webhook server.

        Args:
            bot: LatchBot client instance
            debug: Enable debug logging
        """
        self.bot = bot
        self.debug = debug
        self._handlers: Dict[str, CommandHandler] = {}
        self._default_handler: Optional[CommandHandler] = None

        if debug:
            logging.basicConfig(level=logging.DEBUG)
            logger.setLevel(logging.DEBUG)

    def command(
        self, name: str
    ) -> Callable[[CommandHandler], CommandHandler]:
        """
        Decorator to register a command handler.

        Args:
            name: Command name (without /)

        Example:
            @webhook.command("weather")
            def handle_weather(ctx):
                ctx.reply(f"Weather for {ctx.text}")
        """

        def decorator(func: CommandHandler) -> CommandHandler:
            self._handlers[name.lower()] = func
            logger.debug(f"Registered handler for command: {name}")
            return func

        return decorator

    def default(self, func: CommandHandler) -> CommandHandler:
        """
        Decorator to register a default handler for unknown commands.

        Example:
            @webhook.default
            def handle_unknown(ctx):
                ctx.reply(f"Unknown command: /{ctx.command}")
        """
        self._default_handler = func
        return func

    def on_command(self, name: str, handler: CommandHandler) -> None:
        """
        Register a command handler programmatically.

        Args:
            name: Command name (without /)
            handler: Handler function
        """
        self._handlers[name.lower()] = handler

    def handle(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Handle an incoming webhook request.

        Args:
            data: Request JSON data

        Returns:
            Response dict (can be ephemeral or acknowledgment)
        """
        if self.debug:
            logger.debug(f"Received webhook: {data}")

        try:
            payload = CommandPayload.from_dict(data)
        except (KeyError, TypeError) as e:
            logger.error(f"Invalid payload: {e}")
            return {"error": "Invalid payload"}

        ctx = CommandContext(payload=payload, bot=self.bot)

        # Find handler
        handler = self._handlers.get(payload.command.lower())
        if handler is None:
            handler = self._default_handler

        if handler is None:
            logger.warning(f"No handler for command: {payload.command}")
            return {"ok": True}

        try:
            result = handler(ctx)
            if result is not None:
                return result
            return {"ok": True}
        except Exception as e:
            logger.exception(f"Handler error for /{payload.command}: {e}")
            return {"error": str(e)}

    def get_flask_handler(self):
        """
        Get a Flask-compatible handler function.

        Returns:
            Function that can be used as a Flask route handler

        Example:
            from flask import Flask
            app = Flask(__name__)

            @app.route('/webhook', methods=['POST'])
            def webhook():
                return webhook_server.get_flask_handler()()
        """
        from flask import request, jsonify

        def handler():
            result = self.handle(request.json)
            return jsonify(result)

        return handler


def create_flask_app(
    bot: LatchBot,
    webhook_path: str = "/latch/webhook",
) -> "Flask":
    """
    Create a Flask app with webhook endpoint configured.

    Args:
        bot: LatchBot client instance
        webhook_path: URL path for the webhook endpoint

    Returns:
        Flask application

    Example:
        bot = LatchBot(token="bot_YOUR_TOKEN")
        app = create_flask_app(bot)

        @bot.webhook.command("hello")
        def hello(ctx):
            ctx.reply("Hello!")

        app.run(port=3000)
    """
    from flask import Flask, request, jsonify

    app = Flask(__name__)
    webhook = WebhookServer(bot)

    # Attach webhook to bot for easy access
    bot.webhook = webhook

    @app.route(webhook_path, methods=["POST"])
    def handle_webhook():
        result = webhook.handle(request.json)
        return jsonify(result)

    @app.route("/health", methods=["GET"])
    def health():
        return jsonify({"status": "ok"})

    return app
