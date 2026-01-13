# Latch Bot SDK for JavaScript/TypeScript

Official JavaScript/TypeScript SDK for building bots on the Latch chat platform.

## Installation

```bash
npm install @latch/bot-sdk
# or
yarn add @latch/bot-sdk
# or
pnpm add @latch/bot-sdk
```

## Quick Start

### Sending Messages

```typescript
import { LatchBot } from '@latch/bot-sdk';

// Initialize the bot
const bot = new LatchBot({
  token: 'bot_YOUR_TOKEN_HERE',
  baseUrl: 'https://your-latch-instance.com',
});

// Send a message
const message = await bot.sendMessage({
  conversationId: 123,
  text: 'Hello from JavaScript! **Bold** and *italic* work too.',
});

console.log(`Sent message ${message.id}`);
```

### Handling Slash Commands

```typescript
import express from 'express';
import { LatchBot, WebhookServer } from '@latch/bot-sdk';

const app = express();
app.use(express.json());

const bot = new LatchBot({ token: process.env.LATCH_BOT_TOKEN! });
const webhook = new WebhookServer({ bot });

webhook.onCommand('weather', async (ctx) => {
  const city = ctx.text || 'London';
  await ctx.reply(`Weather in ${city}: Sunny, 22°C`);
});

webhook.onCommand('help', (ctx) => {
  return ctx.replyEphemeral(`
**Available Commands:**
- \`/weather [city]\` - Get weather for a city
- \`/help\` - Show this message
  `);
});

app.post('/latch/webhook', webhook.middleware());

app.listen(3000, () => {
  console.log('Bot server running on port 3000');
});
```

## API Reference

### LatchBot

The main client for interacting with the Latch API.

```typescript
const bot = new LatchBot({
  token: 'bot_YOUR_TOKEN',      // Required: Bot API token
  baseUrl: 'https://...',       // Latch instance URL
  timeout: 30000,               // Request timeout in ms
  maxRetries: 3,                // Max retries for rate limits
  debug: false,                 // Enable debug logging
});
```

#### Methods

##### `sendMessage(options)`

Send a message to a conversation.

```typescript
const message = await bot.sendMessage({
  conversationId: 123,
  text: 'Hello!',
  threadId: 456, // Optional: for threaded replies
});
```

##### `sendThreadedReply(conversationId, threadId, text)`

Send a reply to a thread.

```typescript
const reply = await bot.sendThreadedReply(123, 456, 'This is a reply');
```

##### `getConversation(conversationId)`

Get conversation details.

```typescript
const conv = await bot.getConversation(123);
console.log(`Channel: ${conv.name}`);
console.log(`Type: ${conv.type}`);
console.log(`Members: ${conv.members.length}`);
```

### WebhookServer

Server for handling slash command callbacks.

```typescript
const webhook = new WebhookServer({ bot, debug: false });

// Register command handlers
webhook.onCommand('mycommand', async (ctx) => {
  await ctx.reply('Response');
});

// Handle unknown commands
webhook.onDefault(async (ctx) => {
  await ctx.reply(`Unknown command: /${ctx.command}`);
});
```

### CommandContext

Context object passed to command handlers.

```typescript
webhook.onCommand('example', async (ctx) => {
  ctx.command;        // Command name (without /)
  ctx.text;           // Raw text after command
  ctx.args;           // Text split into array
  ctx.conversationId; // Conversation ID
  ctx.userId;         // User who invoked
  ctx.userName;       // User's display name
  ctx.workspaceId;    // Workspace ID
  ctx.config;         // Bot configuration (Record<string, unknown>)

  // Send a visible reply
  await ctx.reply('Everyone can see this');

  // Return ephemeral (only invoker sees)
  return ctx.replyEphemeral('Only you see this');
});
```

### Bot Configuration

Bots can define a configuration schema in their manifest. Workspace admins can then configure these settings when installing or managing the bot.

#### Accessing Configuration

Configuration values are automatically passed to your command handlers via `ctx.config`:

```typescript
webhook.onCommand('gitlab', async (ctx) => {
  // Access configuration set by workspace admin
  const gitlabUrl = ctx.config.gitlab_url as string;
  const apiToken = ctx.config.api_token as string;
  const defaultProject = ctx.config.default_project as string;

  if (!gitlabUrl || !apiToken) {
    return ctx.replyEphemeral(
      'GitLab is not configured. Please ask an admin to configure the bot.'
    );
  }

  // Use the configuration
  const client = new GitLabClient(gitlabUrl, apiToken);
  // ...
});
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

| Type | Description | TypeScript Type |
|------|-------------|-----------------|
| `string` | Single-line text | `string` |
| `text` | Multi-line text | `string` |
| `number` | Numeric value | `number` |
| `boolean` | True/false toggle | `boolean` |
| `select` | Dropdown selection | `string` |
| `url` | URL with validation | `string` |
| `secret` | Sensitive data (stored encrypted) | `string` |

## Types

### Message

```typescript
interface Message {
  id: number;
  conversation_id: number;
  user_id: number;
  body_md: string;
  body_html: string;
  parent_message_id?: number;
  created_at?: string;
  user?: User;
  reactions: Reaction[];
  attachments: Attachment[];
}
```

### Conversation

```typescript
interface Conversation {
  id: number;
  workspace_id: number;
  name?: string;
  description?: string;
  type: 'public_channel' | 'private_channel' | 'dm' | 'group_dm';
  is_archived: boolean;
  members: ConversationMember[];
}
```

### User

```typescript
interface User {
  id: number;
  name: string;
  email?: string;
  avatar_url?: string;
}
```

## Error Handling

```typescript
import {
  LatchBot,
  LatchBotError,
  AuthenticationError,
  RateLimitError,
  NotFoundError,
  ValidationError,
} from '@latch/bot-sdk';

const bot = new LatchBot({ token: '...' });

try {
  await bot.sendMessage({ conversationId: 123, text: 'Hello' });
} catch (error) {
  if (error instanceof AuthenticationError) {
    console.log('Auth failed:', error.message);
  } else if (error instanceof RateLimitError) {
    console.log(`Rate limited, retry after ${error.retryAfter}s`);
  } else if (error instanceof NotFoundError) {
    console.log('Not found:', error.message);
  } else if (error instanceof ValidationError) {
    console.log('Validation error:', error.message);
    console.log('Field errors:', error.errors);
  } else if (error instanceof LatchBotError) {
    console.log(`API error [${error.statusCode}]:`, error.message);
  }
}
```

## Examples

See the [examples](./examples) directory for complete examples:

- `simple-bot.ts` - Basic message sending
- `slash-command-bot.ts` - Slash command handling
- `weather-bot.ts` - Complete weather bot example

## Requirements

- Node.js >= 18.0.0 (uses native `fetch`)

## Development

```bash
# Clone the repository
git clone https://github.com/your-org/latch-bot-sdk-js
cd latch-bot-sdk-js

# Install dependencies
npm install

# Build
npm run build

# Run tests
npm test

# Format code
npm run format
```

## License

MIT License - see [LICENSE](./LICENSE) for details.
