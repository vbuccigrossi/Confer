#!/usr/bin/env python3
"""
Weather Bot Example

A complete example of a slash command bot that provides weather information.

Usage:
    export LATCH_BOT_TOKEN="bot_YOUR_TOKEN"
    export LATCH_BASE_URL="https://your-latch-instance.com"
    export OPENWEATHER_API_KEY="your_api_key"  # Get from openweathermap.org
    python weather_bot.py

The bot responds to:
    /weather [city]     - Get current weather
    /forecast [city]    - Get 5-day forecast
    /help               - Show help message
"""

import os
import logging
import requests
from flask import Flask, request, jsonify
from latch_bot import LatchBot, WebhookServer

# Configuration
TOKEN = os.environ.get("LATCH_BOT_TOKEN")
BASE_URL = os.environ.get("LATCH_BASE_URL", "http://localhost")
WEATHER_API_KEY = os.environ.get("OPENWEATHER_API_KEY")
PORT = int(os.environ.get("PORT", "3000"))

if not TOKEN:
    print("Error: LATCH_BOT_TOKEN environment variable is required")
    exit(1)

# Initialize
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
bot = LatchBot(token=TOKEN, base_url=BASE_URL)
webhook = WebhookServer(bot, debug=True)


def get_weather(city: str) -> dict:
    """Fetch current weather from OpenWeatherMap API."""
    if not WEATHER_API_KEY:
        return {"error": "Weather API not configured"}

    try:
        response = requests.get(
            "https://api.openweathermap.org/data/2.5/weather",
            params={
                "q": city,
                "appid": WEATHER_API_KEY,
                "units": "metric",
            },
            timeout=10,
        )
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        logger.error(f"Weather API error: {e}")
        return {"error": str(e)}


def get_forecast(city: str) -> dict:
    """Fetch 5-day forecast from OpenWeatherMap API."""
    if not WEATHER_API_KEY:
        return {"error": "Weather API not configured"}

    try:
        response = requests.get(
            "https://api.openweathermap.org/data/2.5/forecast",
            params={
                "q": city,
                "appid": WEATHER_API_KEY,
                "units": "metric",
                "cnt": 5,  # 5 forecast entries
            },
            timeout=10,
        )
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        logger.error(f"Weather API error: {e}")
        return {"error": str(e)}


def format_weather_emoji(condition: str) -> str:
    """Get emoji for weather condition."""
    condition = condition.lower()
    if "clear" in condition:
        return "☀️"
    elif "cloud" in condition:
        return "☁️"
    elif "rain" in condition:
        return "🌧️"
    elif "snow" in condition:
        return "❄️"
    elif "thunder" in condition:
        return "⛈️"
    elif "mist" in condition or "fog" in condition:
        return "🌫️"
    return "🌤️"


@webhook.command("weather")
def handle_weather(ctx):
    """Handle /weather command."""
    city = ctx.text.strip() if ctx.text else "London"

    logger.info(f"Weather request for {city} by {ctx.user_name}")

    data = get_weather(city)

    if "error" in data:
        ctx.reply(f"Sorry, couldn't get weather for **{city}**. Please check the city name.")
        return

    if data.get("cod") == "404":
        ctx.reply(f"City **{city}** not found. Please check the spelling.")
        return

    try:
        name = data["name"]
        country = data["sys"]["country"]
        temp = data["main"]["temp"]
        feels_like = data["main"]["feels_like"]
        humidity = data["main"]["humidity"]
        condition = data["weather"][0]["description"]
        emoji = format_weather_emoji(condition)

        message = f"""
{emoji} **Weather in {name}, {country}**

| Metric | Value |
|--------|-------|
| Temperature | {temp:.1f}°C |
| Feels like | {feels_like:.1f}°C |
| Conditions | {condition.title()} |
| Humidity | {humidity}% |
        """

        ctx.reply(message)

    except (KeyError, IndexError) as e:
        logger.error(f"Error parsing weather data: {e}")
        ctx.reply("Sorry, there was an error processing the weather data.")


@webhook.command("forecast")
def handle_forecast(ctx):
    """Handle /forecast command."""
    city = ctx.text.strip() if ctx.text else "London"

    logger.info(f"Forecast request for {city} by {ctx.user_name}")

    data = get_forecast(city)

    if "error" in data:
        ctx.reply(f"Sorry, couldn't get forecast for **{city}**.")
        return

    try:
        name = data["city"]["name"]
        country = data["city"]["country"]

        rows = []
        for item in data["list"]:
            dt = item["dt_txt"]
            temp = item["main"]["temp"]
            condition = item["weather"][0]["description"]
            emoji = format_weather_emoji(condition)
            rows.append(f"| {dt} | {temp:.1f}°C | {emoji} {condition.title()} |")

        message = f"""
**5-Period Forecast for {name}, {country}**

| Time | Temp | Conditions |
|------|------|------------|
{chr(10).join(rows)}
        """

        ctx.reply(message)

    except (KeyError, IndexError) as e:
        logger.error(f"Error parsing forecast data: {e}")
        ctx.reply("Sorry, there was an error processing the forecast data.")


@webhook.command("help")
def handle_help(ctx):
    """Handle /help command."""
    return ctx.reply_ephemeral("""
**Weather Bot Commands**

- `/weather [city]` - Get current weather for a city
- `/forecast [city]` - Get 5-period forecast
- `/help` - Show this help message

**Examples:**
- `/weather London`
- `/weather New York`
- `/forecast Tokyo`

Default city is London if not specified.
    """)


@webhook.default
def handle_unknown(ctx):
    """Handle unknown commands."""
    ctx.reply(
        f"Unknown command: `/{ctx.command}`\n\n"
        "Use `/help` to see available commands."
    )


@app.route("/latch/webhook", methods=["POST"])
def latch_webhook():
    """Handle incoming webhooks from Latch."""
    result = webhook.handle(request.json)
    return jsonify(result)


@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint."""
    return jsonify({"status": "ok", "bot": "weather"})


if __name__ == "__main__":
    logger.info(f"Starting Weather Bot on port {PORT}")
    logger.info(f"Webhook URL: http://localhost:{PORT}/latch/webhook")

    if not WEATHER_API_KEY:
        logger.warning(
            "OPENWEATHER_API_KEY not set - weather lookups will fail. "
            "Get a free API key at https://openweathermap.org/api"
        )

    app.run(host="0.0.0.0", port=PORT, debug=True)
