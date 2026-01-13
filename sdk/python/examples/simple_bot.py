#!/usr/bin/env python3
"""
Simple Latch Bot Example

This example demonstrates basic message sending functionality.

Usage:
    export LATCH_BOT_TOKEN="bot_YOUR_TOKEN"
    export LATCH_BASE_URL="https://your-latch-instance.com"
    python simple_bot.py
"""

import os
from latch_bot import LatchBot, LatchBotError

# Configuration from environment variables
TOKEN = os.environ.get("LATCH_BOT_TOKEN")
BASE_URL = os.environ.get("LATCH_BASE_URL", "http://localhost")
CONVERSATION_ID = int(os.environ.get("LATCH_CONVERSATION_ID", "1"))

if not TOKEN:
    print("Error: LATCH_BOT_TOKEN environment variable is required")
    exit(1)


def main():
    # Initialize the bot
    bot = LatchBot(
        token=TOKEN,
        base_url=BASE_URL,
        debug=True,  # Enable debug logging
    )

    print(f"Bot initialized: {bot}")

    try:
        # Send a simple message
        message = bot.send_message(
            conversation_id=CONVERSATION_ID,
            text="Hello from the Python SDK!",
        )
        print(f"Sent message: {message.id}")

        # Send a formatted message with Markdown
        formatted_message = bot.send_message(
            conversation_id=CONVERSATION_ID,
            text="""
**Welcome to Latch Bot SDK**

Here's what I can do:
- Send *formatted* messages
- Use `code blocks`
- Include [links](https://example.com)

> This is a blockquote

```python
# Code blocks work too!
print("Hello, World!")
```
            """,
        )
        print(f"Sent formatted message: {formatted_message.id}")

        # Get conversation info
        conv = bot.get_conversation(CONVERSATION_ID)
        print(f"Conversation: {conv.name or 'DM'}")
        print(f"Type: {conv.type}")
        print(f"Members: {len(conv.members)}")

    except LatchBotError as e:
        print(f"Error: {e}")
        exit(1)


if __name__ == "__main__":
    main()
