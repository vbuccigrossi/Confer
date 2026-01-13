"""Main application for Confer TUI."""

import argparse
import asyncio
import sys
from textual.app import App
from textual.css.query import NoMatches

from .api_client import ConferAPIClient
from .config import get_config
from .screens import LoginScreen, ChatScreen
from .updater import check_and_prompt_update


class ConferApp(App):
    """Confer TUI Application."""

    TITLE = "Confer - Terminal Chat"

    CSS = """
    Screen {
        background: $surface;
    }

    Static {
        height: auto;
    }
    """

    def __init__(self, api_url: str = "https://localhost/api"):
        super().__init__()
        self.api_client = ConferAPIClient(api_url)

    def on_mount(self):
        """Handle app mount."""
        # Try to load saved token
        if self.api_client.load_saved_token():
            # Already logged in, go to chat
            self.push_screen(ChatScreen(self.api_client))
        else:
            # Show login screen
            self.push_screen(LoginScreen(self.api_client))

    async def login(self, email: str, password: str):
        """Handle login."""
        try:
            # Login via API
            await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.login,
                email,
                password
            )

            # Pop login screen and push chat screen
            self.pop_screen()
            self.push_screen(ChatScreen(self.api_client))

        except Exception as e:
            # Re-raise to let the login screen handle it
            raise


def main():
    """Run the TUI application."""
    parser = argparse.ArgumentParser(description="Confer TUI - Terminal Chat Client")
    parser.add_argument(
        "--api-url",
        default=None,
        help="API base URL (overrides config file)"
    )
    parser.add_argument(
        "--set-api-url",
        metavar="URL",
        help="Save API URL to config file and exit"
    )

    args = parser.parse_args()

    # Load config
    config = get_config()

    # Handle --set-api-url command
    if args.set_api_url:
        config.api_url = args.set_api_url
        print(f"API URL saved to config: {args.set_api_url}")
        print(f"Config file location: {config.config_file}")
        return

    # Determine API URL: command line > config file > default
    api_url = args.api_url or config.api_url or "https://localhost/api"

    # Save to config if specified via command line (for convenience)
    if args.api_url and args.api_url != config.api_url:
        config.api_url = args.api_url

    # Check for updates before starting the app
    try:
        if check_and_prompt_update(api_url):
            # Update was installed, exit so user can restart
            sys.exit(0)
    except KeyboardInterrupt:
        print("\nUpdate check cancelled.")
        sys.exit(0)

    app = ConferApp(api_url=api_url)
    app.run()


if __name__ == "__main__":
    main()
