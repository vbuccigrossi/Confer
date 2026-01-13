"""Login screen for Confer TUI."""

from textual.app import ComposeResult
from textual.containers import Container, Vertical
from textual.screen import Screen
from textual.widgets import Input, Button, Static, Label
from textual.binding import Binding


class LoginScreen(Screen):
    """Login screen."""

    BINDINGS = [
        Binding("escape", "app.quit", "Quit", show=True),
    ]

    CSS = """
    LoginScreen {
        align: center middle;
    }

    #login-container {
        width: 60;
        height: auto;
        border: thick $primary;
        background: $surface;
        padding: 2;
    }

    #logo {
        text-align: center;
        color: $accent;
        text-style: bold;
        margin-bottom: 1;
    }

    #title {
        text-align: center;
        margin-bottom: 2;
    }

    #error {
        color: $error;
        text-align: center;
        margin-top: 1;
        height: auto;
    }

    Input {
        margin-bottom: 1;
    }

    Button {
        width: 100%;
        margin-top: 1;
    }

    #register-hint {
        text-align: center;
        margin-top: 2;
        color: $text-muted;
    }
    """

    def __init__(self, api_client, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="login-container"):
            yield Static("""
  ╔═══════════════════════════════════╗
  ║    ╔═╗╔═╗╔╗╔╔═╗╔═╗╦═╗            ║
  ║    ║  ║ ║║║║╠╣ ║╣ ╠╦╝            ║
  ║    ╚═╝╚═╝╝╚╝╚  ╚═╝╩╚═            ║
  ╚═══════════════════════════════════╝
""", id="logo")
            yield Label("Welcome to Confer", id="title")
            yield Input(placeholder="Email", id="email-input")
            yield Input(placeholder="Password", password=True, id="password-input")
            yield Button("Login", variant="primary", id="login-button")
            yield Static("", id="error")
            yield Static("Press 'r' to register a new account", id="register-hint")

    def on_mount(self):
        """Focus email input when screen loads."""
        self.query_one("#email-input", Input).focus()

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "login-button":
            self.run_worker(self.action_login(), exclusive=True)

    def on_input_submitted(self, event: Input.Submitted) -> None:
        """Handle enter key in input fields."""
        if event.input.id == "email-input":
            self.query_one("#password-input", Input).focus()
        elif event.input.id == "password-input":
            self.run_worker(self.action_login(), exclusive=True)

    async def action_login(self):
        """Perform login."""
        email_input = self.query_one("#email-input", Input)
        password_input = self.query_one("#password-input", Input)
        error_label = self.query_one("#error", Static)

        email = email_input.value.strip()
        password = password_input.value

        if not email or not password:
            error_label.update("Please enter both email and password")
            self.notify("Please enter both email and password", severity="warning")
            return

        # Clear error
        error_label.update("")

        # Disable button while logging in
        button = self.query_one("#login-button", Button)
        button.disabled = True
        button.label = "Logging in..."

        self.notify("Connecting to server...", timeout=2)

        try:
            await self.app.login(email, password)
            self.notify("Login successful!", severity="information")
        except Exception as e:
            # Show detailed error message
            import traceback
            error_msg = str(e)
            if not error_msg:
                error_msg = f"{type(e).__name__}"
                tb = traceback.format_exc()
                if len(tb) > 0:
                    error_msg += f"\n{tb[:300]}"

            error_label.update(f"Login failed: {error_msg}")
            self.notify(f"Login failed: {error_msg[:100]}", severity="error", timeout=10)
            button.disabled = False
            button.label = "Login"

    def on_key(self, event) -> None:
        """Handle key presses."""
        if event.key == "r":
            self.app.push_screen("register")
