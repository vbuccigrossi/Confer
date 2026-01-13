"""Modal screens for creating channels and DMs."""

import asyncio
from textual.app import ComposeResult
from textual.containers import Container, Vertical
from textual.screen import ModalScreen
from textual.widgets import Input, Label, Button, RadioSet, RadioButton, Checkbox, Static
from textual.binding import Binding


class CreateChannelModal(ModalScreen):
    """Modal for creating a new channel."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    CreateChannelModal {
        align: center middle;
    }

    #dialog {
        width: 60;
        height: auto;
        border: thick $primary;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        text-align: center;
        text-style: bold;
        margin-bottom: 1;
    }

    #dialog Input {
        width: 100%;
        margin-bottom: 1;
    }

    #dialog RadioSet {
        width: 100%;
        margin-bottom: 1;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, api_client, workspace_id, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self.workspace_id = workspace_id

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label("Create Channel")
            yield Label("Channel name:", classes="field-label")
            yield Input(placeholder="general", id="channel-name")
            yield Label("Channel type:", classes="field-label")
            with RadioSet(id="channel-type"):
                yield RadioButton("# Public - Anyone can join", value=True, id="public")
                yield RadioButton("🔒 Private - Invite only", id="private")
            yield Label("Topic (optional):", classes="field-label")
            yield Input(placeholder="What's this channel about?", id="channel-topic")
            with Container(id="buttons"):
                yield Button("Create", variant="primary", id="create-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "create-btn":
            self.run_worker(self.action_create(), exclusive=True)

    async def action_create(self):
        """Create the channel."""
        name_input = self.query_one("#channel-name", Input)
        topic_input = self.query_one("#channel-topic", Input)
        type_radio = self.query_one("#channel-type", RadioSet)

        name = name_input.value.strip()
        if not name:
            self.app.notify("Channel name is required", severity="error")
            return

        topic = topic_input.value.strip()
        channel_type = "public_channel" if type_radio.pressed_button.id == "public" else "private_channel"

        try:
            conversation = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.create_channel,
                self.workspace_id,
                name,
                channel_type,
                topic
            )
            self.dismiss(conversation)
        except Exception as e:
            self.app.notify(f"Error creating channel: {str(e)}", severity="error")

    def action_cancel(self):
        """Cancel channel creation."""
        self.dismiss(None)


class CreateDMModal(ModalScreen):
    """Modal for creating a new DM."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    CreateDMModal {
        align: center middle;
    }

    #dialog {
        width: 60;
        height: auto;
        max-height: 80%;
        border: thick $primary;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        text-align: center;
        text-style: bold;
        margin-bottom: 1;
    }

    #user-list {
        width: 100%;
        height: auto;
        max-height: 20;
        overflow-y: auto;
        border: solid $primary;
        margin-bottom: 1;
        padding: 1;
    }

    .user-item {
        width: 100%;
        height: auto;
        margin-bottom: 1;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, api_client, workspace_id, current_user_id, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self.workspace_id = workspace_id
        self.current_user_id = current_user_id
        self.selected_users = set()

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label("Start a Direct Message")
            yield Label("Select people:", classes="field-label")
            yield Container(Static("Loading users..."), id="user-list")
            with Container(id="buttons"):
                yield Button("Start Conversation", variant="primary", id="create-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    async def on_mount(self):
        """Load workspace members."""
        try:
            members = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.get_workspace_members,
                self.workspace_id
            )

            # Filter out current user
            other_members = [m for m in members if m.get("user_id") != self.current_user_id]

            # Build user list with checkboxes
            user_list = self.query_one("#user-list", Container)
            user_list.remove_children()

            if not other_members:
                user_list.mount(Static("No other users in this workspace"))
                return

            for member in other_members:
                user = member.get("user", {})
                user_name = user.get("name", "Unknown")
                user_email = user.get("email", "")
                user_id = member.get("user_id")

                # Create checkbox for user
                cb = Checkbox(f"{user_name} ({user_email})", id=f"user-{user_id}")
                cb.user_id = user_id
                user_list.mount(cb)

        except Exception as e:
            self.app.notify(f"Error loading users: {str(e)}", severity="error")
            user_list = self.query_one("#user-list", Container)
            user_list.remove_children()
            user_list.mount(Static(f"Error: {str(e)}"))

    def on_checkbox_changed(self, event: Checkbox.Changed):
        """Handle checkbox changes."""
        checkbox = event.checkbox
        if hasattr(checkbox, 'user_id'):
            if checkbox.value:
                self.selected_users.add(checkbox.user_id)
            else:
                self.selected_users.discard(checkbox.user_id)

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "create-btn":
            self.run_worker(self.action_create(), exclusive=True)

    async def action_create(self):
        """Create the DM."""
        if not self.selected_users:
            self.app.notify("Please select at least one person", severity="error")
            return

        try:
            conversation = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.create_dm,
                self.workspace_id,
                list(self.selected_users)
            )
            self.dismiss(conversation)
        except Exception as e:
            self.app.notify(f"Error creating DM: {str(e)}", severity="error")

    def action_cancel(self):
        """Cancel DM creation."""
        self.dismiss(None)


class EditMessageModal(ModalScreen):
    """Modal for editing a message."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    EditMessageModal {
        align: center middle;
    }

    #dialog {
        width: 60;
        height: auto;
        border: thick $primary;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        text-align: center;
        text-style: bold;
        margin-bottom: 1;
    }

    #dialog Input {
        width: 100%;
        margin-bottom: 1;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, api_client, message, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self.message = message

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label("Edit Message")
            yield Label("Message text:", classes="field-label")
            yield Input(value=self.message.get("body_md", ""), id="message-text")
            with Container(id="buttons"):
                yield Button("Save", variant="primary", id="save-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "save-btn":
            self.run_worker(self.action_save(), exclusive=True)

    async def action_save(self):
        """Save the edited message."""
        text_input = self.query_one("#message-text", Input)
        new_text = text_input.value.strip()

        if not new_text:
            self.app.notify("Message cannot be empty", severity="error")
            return

        try:
            await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.edit_message,
                self.message["id"],
                new_text
            )
            self.dismiss(True)
        except Exception as e:
            self.app.notify(f"Error editing message: {str(e)}", severity="error")

    def action_cancel(self):
        """Cancel editing."""
        self.dismiss(False)


class DeleteMessageModal(ModalScreen):
    """Modal for confirming message deletion."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    DeleteMessageModal {
        align: center middle;
    }

    #dialog {
        width: 50;
        height: auto;
        border: thick $error;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        text-align: center;
        margin-bottom: 1;
    }

    .warning {
        text-style: bold;
        color: $error;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, api_client, message, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self.message = message

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label("Delete Message", classes="warning")
            yield Label("Are you sure you want to delete this message?")
            yield Label("This action cannot be undone.")
            with Container(id="buttons"):
                yield Button("Delete", variant="error", id="delete-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "delete-btn":
            self.run_worker(self.action_delete(), exclusive=True)

    async def action_delete(self):
        """Delete the message."""
        try:
            await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.delete_message,
                self.message["id"]
            )
            self.dismiss(True)
        except Exception as e:
            self.app.notify(f"Error deleting message: {str(e)}", severity="error")

    def action_cancel(self):
        """Cancel deletion."""
        self.dismiss(False)


class SelectMessageModal(ModalScreen):
    """Modal for selecting a message by number."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    SelectMessageModal {
        align: center middle;
    }

    #dialog {
        width: 50;
        height: auto;
        border: thick $primary;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        margin-bottom: 1;
    }

    #dialog Input {
        width: 100%;
        margin-bottom: 1;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, action_name: str, total_messages: int, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.action_name = action_name
        self.total_messages = total_messages

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label(f"Select Message to {self.action_name}", classes="title")
            yield Label(f"Enter message number (1-{self.total_messages}):")
            yield Input(placeholder="1", id="message-number", type="integer")
            with Container(id="buttons"):
                yield Button("Select", variant="primary", id="select-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    def on_mount(self):
        """Focus the input when modal opens."""
        self.query_one("#message-number", Input).focus()

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "select-btn":
            self.action_select()

    def on_input_submitted(self, event: Input.Submitted) -> None:
        """Handle Enter key in input."""
        self.action_select()

    def action_select(self):
        """Submit the selected message number."""
        input_widget = self.query_one("#message-number", Input)
        try:
            msg_num = int(input_widget.value.strip())
            if 1 <= msg_num <= self.total_messages:
                self.dismiss(msg_num)
            else:
                self.app.notify(f"Please enter a number between 1 and {self.total_messages}", severity="error")
        except (ValueError, AttributeError):
            self.app.notify("Please enter a valid number", severity="error")

    def action_cancel(self):
        """Cancel selection."""
        self.dismiss(None)


class CreateBotDMModal(ModalScreen):
    """Modal for creating a new bot DM."""

    BINDINGS = [
        Binding("escape", "cancel", "Cancel", show=False),
    ]

    CSS = """
    CreateBotDMModal {
        align: center middle;
    }

    #dialog {
        width: 60;
        height: auto;
        max-height: 80%;
        border: thick $primary;
        background: $surface;
        padding: 1 2;
    }

    #dialog > Label {
        width: 100%;
        text-align: center;
        text-style: bold;
        margin-bottom: 1;
    }

    #bot-list {
        width: 100%;
        height: auto;
        max-height: 20;
        overflow-y: auto;
        border: solid $primary;
        margin-bottom: 1;
        padding: 1;
    }

    .bot-item {
        width: 100%;
        height: auto;
        margin-bottom: 1;
    }

    #buttons {
        width: 100%;
        height: auto;
        align: center middle;
        margin-top: 1;
    }

    #buttons Button {
        margin: 0 1;
    }
    """

    def __init__(self, api_client, workspace_id, current_user_id, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self.workspace_id = workspace_id
        self.current_user_id = current_user_id
        self.selected_bot = None

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        with Container(id="dialog"):
            yield Label("Start a Bot Conversation")
            yield Label("Available bots:", classes="field-label")
            yield Container(Static("Loading bots...",), id="bot-list")
            with Container(id="buttons"):
                yield Button("Start Conversation", variant="primary", id="create-btn")
                yield Button("Cancel", variant="default", id="cancel-btn")

    async def on_mount(self):
        """Load available bots."""
        try:
            # Search for all users to find bots
            users = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.search_users,
                self.workspace_id,  # Required workspace_id parameter
                "bot"  # Search query for bots
            )

            # Filter for bot users (email ends with @bots.local)
            bots = [
                u for u in users
                if u.get("email", "").endswith("@bots.local")
                and u.get("id") != self.current_user_id
            ]

            bot_list = self.query_one("#bot-list", Container)
            bot_list.remove_children()

            if not bots:
                bot_list.mount(Static("No bots are currently available."))
                return

            for bot_user in bots:
                bot_name = bot_user.get("name", "Unknown Bot")
                bot_id = bot_user.get("id")

                # Create radio button for bot
                from textual.widgets import RadioButton
                rb = RadioButton(f"🤖 {bot_name}", id=f"bot-{bot_id}")
                rb.bot_id = bot_id
                rb.bot_name = bot_name
                bot_list.mount(rb)

        except Exception as e:
            self.app.notify(f"Error loading bots: {str(e)}", severity="error")
            bot_list = self.query_one("#bot-list", Container)
            bot_list.remove_children()
            bot_list.mount(Static(f"Error: {str(e)}"))

    def on_radio_button_changed(self, event):
        """Handle radio button selection."""
        if hasattr(event.radio_button, 'bot_id') and event.radio_button.value:
            self.selected_bot = {
                'id': event.radio_button.bot_id,
                'name': event.radio_button.bot_name
            }

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Handle button press."""
        if event.button.id == "cancel-btn":
            self.action_cancel()
        elif event.button.id == "create-btn":
            self.run_worker(self.action_create(), exclusive=True)

    async def action_create(self):
        """Create the bot DM."""
        if not self.selected_bot:
            self.app.notify("Please select a bot", severity="error")
            return

        try:
            conversation = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.create_bot_dm,
                self.workspace_id,
                self.selected_bot['id']
            )
            self.dismiss(conversation)
        except Exception as e:
            self.app.notify(f"Error creating bot conversation: {str(e)}", severity="error")

    def action_cancel(self):
        """Cancel bot DM creation."""
        self.dismiss(None)
