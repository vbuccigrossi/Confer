/**
 * Simple Latch Bot Example
 *
 * This example demonstrates basic message sending functionality.
 *
 * Usage:
 *   export LATCH_BOT_TOKEN="bot_YOUR_TOKEN"
 *   export LATCH_BASE_URL="https://your-latch-instance.com"
 *   npx ts-node simple-bot.ts
 */

import { LatchBot, LatchBotError } from '../src';

// Configuration from environment variables
const TOKEN = process.env.LATCH_BOT_TOKEN;
const BASE_URL = process.env.LATCH_BASE_URL || 'http://localhost';
const CONVERSATION_ID = parseInt(process.env.LATCH_CONVERSATION_ID || '1', 10);

if (!TOKEN) {
  console.error('Error: LATCH_BOT_TOKEN environment variable is required');
  process.exit(1);
}

async function main() {
  // Initialize the bot
  const bot = new LatchBot({
    token: TOKEN,
    baseUrl: BASE_URL,
    debug: true, // Enable debug logging
  });

  console.log(`Bot initialized: ${bot}`);

  try {
    // Send a simple message
    const message = await bot.sendMessage({
      conversationId: CONVERSATION_ID,
      text: 'Hello from the JavaScript SDK!',
    });
    console.log(`Sent message: ${message.id}`);

    // Send a formatted message with Markdown
    const formattedMessage = await bot.sendMessage({
      conversationId: CONVERSATION_ID,
      text: `
**Welcome to Latch Bot SDK**

Here's what I can do:
- Send *formatted* messages
- Use \`code blocks\`
- Include [links](https://example.com)

> This is a blockquote

\`\`\`javascript
// Code blocks work too!
console.log("Hello, World!");
\`\`\`
      `,
    });
    console.log(`Sent formatted message: ${formattedMessage.id}`);

    // Get conversation info
    const conv = await bot.getConversation(CONVERSATION_ID);
    console.log(`Conversation: ${conv.name || 'DM'}`);
    console.log(`Type: ${conv.type}`);
    console.log(`Members: ${conv.members.length}`);
  } catch (error) {
    if (error instanceof LatchBotError) {
      console.error(`Error: ${error}`);
    } else {
      console.error('Unexpected error:', error);
    }
    process.exit(1);
  }
}

main();
