/**
 * Weather Bot Example
 *
 * A complete example of a slash command bot that provides weather information.
 *
 * Usage:
 *   export LATCH_BOT_TOKEN="bot_YOUR_TOKEN"
 *   export LATCH_BASE_URL="https://your-latch-instance.com"
 *   export OPENWEATHER_API_KEY="your_api_key"
 *   npx ts-node weather-bot.ts
 *
 * The bot responds to:
 *   /weather [city]  - Get current weather
 *   /help            - Show help message
 */

import express from 'express';
import { LatchBot, WebhookServer, CommandContext } from '../src';

// Configuration
const TOKEN = process.env.LATCH_BOT_TOKEN;
const BASE_URL = process.env.LATCH_BASE_URL || 'http://localhost';
const WEATHER_API_KEY = process.env.OPENWEATHER_API_KEY;
const PORT = parseInt(process.env.PORT || '3000', 10);

if (!TOKEN) {
  console.error('Error: LATCH_BOT_TOKEN environment variable is required');
  process.exit(1);
}

// Initialize
const app = express();
app.use(express.json());

const bot = new LatchBot({ token: TOKEN, baseUrl: BASE_URL });
const webhook = new WebhookServer({ bot, debug: true });

// Weather API functions
interface WeatherData {
  name: string;
  sys: { country: string };
  main: { temp: number; feels_like: number; humidity: number };
  weather: Array<{ description: string }>;
}

async function getWeather(city: string): Promise<WeatherData | null> {
  if (!WEATHER_API_KEY) {
    return null;
  }

  const url = new URL('https://api.openweathermap.org/data/2.5/weather');
  url.searchParams.set('q', city);
  url.searchParams.set('appid', WEATHER_API_KEY);
  url.searchParams.set('units', 'metric');

  try {
    const response = await fetch(url.toString());
    if (!response.ok) {
      return null;
    }
    return await response.json();
  } catch (error) {
    console.error('Weather API error:', error);
    return null;
  }
}

function getWeatherEmoji(condition: string): string {
  const c = condition.toLowerCase();
  if (c.includes('clear')) return '☀️';
  if (c.includes('cloud')) return '☁️';
  if (c.includes('rain')) return '🌧️';
  if (c.includes('snow')) return '❄️';
  if (c.includes('thunder')) return '⛈️';
  if (c.includes('mist') || c.includes('fog')) return '🌫️';
  return '🌤️';
}

// Command handlers
webhook.onCommand('weather', async (ctx: CommandContext) => {
  const city = ctx.text.trim() || 'London';

  console.log(`Weather request for ${city} by ${ctx.userName}`);

  if (!WEATHER_API_KEY) {
    await ctx.reply(
      'Weather API not configured. Please set OPENWEATHER_API_KEY.'
    );
    return;
  }

  const data = await getWeather(city);

  if (!data) {
    await ctx.reply(
      `Sorry, couldn't get weather for **${city}**. Please check the city name.`
    );
    return;
  }

  const emoji = getWeatherEmoji(data.weather[0].description);
  const message = `
${emoji} **Weather in ${data.name}, ${data.sys.country}**

| Metric | Value |
|--------|-------|
| Temperature | ${data.main.temp.toFixed(1)}°C |
| Feels like | ${data.main.feels_like.toFixed(1)}°C |
| Conditions | ${data.weather[0].description} |
| Humidity | ${data.main.humidity}% |
  `;

  await ctx.reply(message);
});

webhook.onCommand('help', (ctx: CommandContext) => {
  return ctx.replyEphemeral(`
**Weather Bot Commands**

- \`/weather [city]\` - Get current weather for a city
- \`/help\` - Show this help message

**Examples:**
- \`/weather London\`
- \`/weather New York\`
- \`/weather Tokyo\`

Default city is London if not specified.
  `);
});

webhook.onDefault(async (ctx: CommandContext) => {
  await ctx.reply(
    `Unknown command: \`/${ctx.command}\`\n\nUse \`/help\` to see available commands.`
  );
});

// Routes
app.post('/latch/webhook', webhook.middleware());

app.get('/health', (req, res) => {
  res.json({ status: 'ok', bot: 'weather' });
});

// Start server
app.listen(PORT, () => {
  console.log(`Weather Bot running on port ${PORT}`);
  console.log(`Webhook URL: http://localhost:${PORT}/latch/webhook`);

  if (!WEATHER_API_KEY) {
    console.warn(
      'Warning: OPENWEATHER_API_KEY not set - weather lookups will fail.'
    );
    console.warn('Get a free API key at https://openweathermap.org/api');
  }
});
