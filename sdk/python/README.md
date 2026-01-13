# Latch Bot SDK for Python

Official Python SDK for building bots on the Latch chat platform.

## Installation

```bash
pip install latch-bot-sdk
```

For Flask integration:
```bash
pip install "latch-bot-sdk[flask]"
```

For FastAPI integration:
```bash
pip install "latch-bot-sdk[fastapi]"
```

## Quick Start

### Sending Messages

```python
from latch_bot import LatchBot

# Initialize the bot
bot = LatchBot(
    token="bot_YOUR_TOKEN_HERE",
    base_url="https://your-latch-instance.com"
)

# Send a message
message = bot.send_message(
    conversation_id=123,
    text="Hello from Python! **Bold** and *italic* work too."
)

print(f"Sent message {message.id}")
```

### Handling Slash Commands

```python
from latch_bot import LatchBot, WebhookServer
from flask import Flask, request, jsonify

app = Flask(__name__)
bot = LatchBot(token="bot_YOUR_TOKEN")
webhook = WebhookServer(bot)

@webhook.command("weather")
def handle_weather(ctx):
    city = ctx.text or "London"
    # Fetch weather data...
    ctx.reply(f"Weather in {city}: Sunny, 22°C")

@webhook.command("help")
def handle_help(ctx):
    return ctx.reply_ephemeral("""
**Available Commands:**
- `/weather [city]` - Get weather for a city
- `/help` - Show this message
    """)

@app.route('/latch/webhook', methods=['POST'])
def latch_webhook():
    result = webhook.handle(request.json)
    return jsonify(result)

if __name__ == '__main__':
    app.run(port=3000)
```

## API Reference

### LatchBot

The main client for interacting with the Latch API.

```python
bot = LatchBot(
    token="bot_YOUR_TOKEN",      # Required: Bot API token
    base_url="https://...",      # Latch instance URL
    timeout=30,                   # Request timeout in seconds
    max_retries=3,               # Max retries for rate limits
    debug=False                  # Enable debug logging
)
```

#### Methods

##### `send_message(conversation_id, text, thread_id=None)`

Send a message to a conversation.

```python
message = bot.send_message(
    conversation_id=123,
    text="Hello!",
    thread_id=456  # Optional: for threaded replies
)
```

##### `send_threaded_reply(conversation_id, thread_id, text)`

Send a reply to a thread.

```python
reply = bot.send_threaded_reply(
    conversation_id=123,
    thread_id=456,
    text="This is a reply"
)
```

##### `get_conversation(conversation_id)`

Get conversation details.

```python
conv = bot.get_conversation(123)
print(f"Channel: {conv.name}")
print(f"Type: {conv.type}")
print(f"Members: {len(conv.members)}")
```

### WebhookServer

Server for handling slash command callbacks.

```python
webhook = WebhookServer(bot, debug=False)

# Register command handlers
@webhook.command("mycommand")
def handler(ctx):
    ctx.reply("Response")

# Handle unknown commands
@webhook.default
def unknown(ctx):
    ctx.reply(f"Unknown command: /{ctx.command}")
```

### CommandContext

Context object passed to command handlers.

```python
@webhook.command("example")
def handler(ctx):
    ctx.command          # Command name (without /)
    ctx.text             # Raw text after command
    ctx.args             # Text split into list
    ctx.conversation_id  # Conversation ID
    ctx.user_id          # User who invoked
    ctx.user_name        # User's display name
    ctx.workspace_id     # Workspace ID
    ctx.config           # Dict of bot configuration values

    # Send a visible reply
    ctx.reply("Everyone can see this")

    # Return ephemeral (only invoker sees)
    return ctx.reply_ephemeral("Only you see this")
```

### Bot Configuration

Bots can define a configuration schema in their manifest. Workspace admins can then configure these settings when installing or managing the bot.

#### Accessing Configuration

Configuration values are automatically passed to your command handlers via `ctx.config`:

```python
@webhook.command("gitlab")
def handle_gitlab(ctx):
    # Access configuration set by workspace admin
    gitlab_url = ctx.config.get("gitlab_url")
    api_token = ctx.config.get("api_token")
    default_project = ctx.config.get("default_project")

    if not gitlab_url or not api_token:
        return ctx.reply_ephemeral(
            "GitLab is not configured. Please ask an admin to configure the bot."
        )

    # Use the configuration
    client = GitLabClient(gitlab_url, api_token)
    # ...
```

#### Configuration Schema in Manifest

Define what configuration your bot needs in the manifest:

```json
{
  "name": "GitLab Bot",
  "webhook_url": "https://your-bot.example.com/webhook",
  "config_schema": {
    "fields": [
      {
        "name": "gitlab_url",
        "type": "url",
        "label": "GitLab URL",
        "description": "Your GitLab instance URL",
        "required": true,
        "placeholder": "https://gitlab.example.com"
      },
      {
        "name": "api_token",
        "type": "secret",
        "label": "API Token",
        "description": "Personal access token with api scope",
        "required": true
      },
      {
        "name": "default_project",
        "type": "string",
        "label": "Default Project",
        "required": false
      },
      {
        "name": "notify_on_push",
        "type": "boolean",
        "label": "Notify on Push",
        "default": true
      },
      {
        "name": "notification_level",
        "type": "select",
        "label": "Notification Level",
        "default": "all",
        "options": [
          {"value": "all", "label": "All Events"},
          {"value": "important", "label": "Important Only"},
          {"value": "none", "label": "None"}
        ]
      }
    ]
  }
}
```

#### Supported Field Types

| Type | Description | Python Type |
|------|-------------|-------------|
| `string` | Single-line text | `str` |
| `text` | Multi-line text | `str` |
| `number` | Numeric value | `int` or `float` |
| `boolean` | True/false toggle | `bool` |
| `select` | Dropdown selection | `str` |
| `url` | URL with validation | `str` |
| `secret` | Sensitive data (stored encrypted) | `str` |

## Models

### Message

```python
message.id               # int
message.conversation_id  # int
message.user_id          # int
message.body_md          # str (Markdown)
message.body_html        # str (rendered HTML)
message.parent_message_id  # Optional[int]
message.created_at       # datetime
message.user             # User
message.reactions        # List[Reaction]
message.attachments      # List[Attachment]
```

### Conversation

```python
conv.id            # int
conv.workspace_id  # int
conv.name          # Optional[str]
conv.description   # Optional[str]
conv.type          # str: public_channel, private_channel, dm, group_dm
conv.is_archived   # bool
conv.members       # List[ConversationMember]
conv.is_channel    # bool (property)
conv.is_dm         # bool (property)
conv.is_public     # bool (property)
```

### User

```python
user.id          # int
user.name        # str
user.email       # Optional[str]
user.avatar_url  # Optional[str]
```

## Exception Handling

```python
from latch_bot import (
    LatchBot,
    LatchBotError,
    AuthenticationError,
    RateLimitError,
    NotFoundError,
    ValidationError,
)

bot = LatchBot(token="...")

try:
    bot.send_message(conversation_id=123, text="Hello")
except AuthenticationError as e:
    print(f"Auth failed: {e}")
except RateLimitError as e:
    print(f"Rate limited, retry after {e.retry_after}s")
except NotFoundError as e:
    print(f"Not found: {e}")
except ValidationError as e:
    print(f"Validation error: {e}")
    print(f"Field errors: {e.errors}")
except LatchBotError as e:
    print(f"API error [{e.status_code}]: {e}")
```

## Examples

See the [examples](./examples) directory for complete examples:

- `simple_bot.py` - Basic message sending
- `slash_command_bot.py` - Slash command handling
- `weather_bot.py` - Complete weather bot example
- `github_webhook.py` - GitHub integration

## Development

```bash
# Clone the repository
git clone https://github.com/your-org/latch-bot-sdk-python
cd latch-bot-sdk-python

# Install in development mode
pip install -e ".[dev]"

# Run tests
pytest

# Format code
black latch_bot

# Type checking
mypy latch_bot
```

## License

MIT License - see [LICENSE](./LICENSE) for details.
