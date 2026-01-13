"""Main chat screen for Confer TUI."""

import asyncio
from textual.app import ComposeResult
from textual.containers import Container, Horizontal, Vertical, ScrollableContainer
from textual.screen import Screen
from textual.widgets import Input, Static, ListItem, ListView, Label, Footer, Header
from textual.binding import Binding
from textual.reactive import reactive
from datetime import datetime
from rich.text import Text
from rich.markdown import Markdown
from .create_modals import CreateChannelModal, CreateDMModal, EditMessageModal, DeleteMessageModal, SelectMessageModal


class ConversationList(ListView):
    """List of conversations in sidebar."""

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.conversations = []

    def set_conversations(self, conversations):
        """Update the list of conversations."""
        self.conversations = conversations
        self.clear()

        # Group by type
        channels = [c for c in conversations if c["type"] in ["public_channel", "private_channel"]]
        dms = [c for c in conversations if c["type"] in ["dm", "group_dm"]]
        bots = [c for c in conversations if c["type"] == "bot_dm"]

        # Add channels
        if channels:
            self.append(ListItem(Static("━━ CHANNELS ━━", classes="section-header")))
            for conv in channels:
                name = conv.get("name", "Unknown")
                unread = conv.get("unread_count", 0)
                # Highlight conversations with unread messages
                if unread > 0:
                    label = f"[bold yellow]# {name}[/bold yellow] [bold red]({unread})[/bold red]"
                else:
                    label = f"# {name}"
                item = ListItem(Static(label, classes="conversation-item"), id=f"conv-{conv['id']}")
                item.conversation = conv
                self.append(item)

        # Add DMs
        if dms:
            self.append(ListItem(Static("━━ DIRECT MESSAGES ━━", classes="section-header")))
            for conv in dms:
                name = conv.get("display_name", conv.get("name", "Unknown"))
                unread = conv.get("unread_count", 0)
                # Highlight conversations with unread messages
                if unread > 0:
                    label = f"[bold yellow]@ {name}[/bold yellow] [bold red]({unread})[/bold red]"
                else:
                    label = f"@ {name}"
                item = ListItem(Static(label, classes="conversation-item"), id=f"conv-{conv['id']}")
                item.conversation = conv
                self.append(item)

        # Add Bots
        if bots:
            self.append(ListItem(Static("━━ BOTS ━━", classes="section-header")))
            for conv in bots:
                name = conv.get("display_name", conv.get("name", "Unknown"))
                unread = conv.get("unread_count", 0)
                # Highlight conversations with unread messages
                if unread > 0:
                    label = f"[bold yellow]🤖 {name}[/bold yellow] [bold red]({unread})[/bold red]"
                else:
                    label = f"🤖 {name}"
                item = ListItem(Static(label, classes="conversation-item"), id=f"conv-{conv['id']}")
                item.conversation = conv
                self.append(item)


class MessageDisplay(ScrollableContainer):
    """Display area for messages."""

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.messages = []
        self._content = Static("Loading...", id="message-content")
        self.current_user_id = None

    def on_mount(self):
        """Mount the content widget."""
        self.mount(self._content)

    def set_messages(self, messages):
        """Update displayed messages."""
        self.messages = messages
        self.update_display()

    def update_display(self):
        """Render messages."""
        if not self.messages:
            self._content.update("No messages yet. Start the conversation!")
            return

        output = []

        for i, msg in enumerate(self.messages):
            user = msg.get("user")
            if not user:
                continue

            user_name = user.get("name", "Unknown")
            timestamp = msg.get("created_at", "")
            body = msg.get("body_md", msg.get("body", ""))
            edited_at = msg.get("edited_at")
            msg_id = msg.get("id")
            user_id = msg.get("user_id")

            # Format timestamp
            try:
                dt = datetime.fromisoformat(timestamp.replace("Z", "+00:00"))
                time_str = dt.strftime("%H:%M")
            except:
                time_str = ""

            # Build message line with message number for selection
            msg_text = f"[bold yellow]#{i+1}[/bold yellow] [bold cyan]{user_name}[/bold cyan]"
            if time_str:
                msg_text += f" [dim]{time_str}[/dim]"
            if edited_at:
                msg_text += f" [dim italic](edited)[/dim italic]"

            # Show edit/delete hint for own messages
            if user_id == self.current_user_id:
                msg_text += f" [dim](Ctrl+E:edit Ctrl+X:delete)[/dim]"

            msg_text += f"\n  {body}\n"

            output.append(msg_text)

        # Join all messages
        full_text = "\n".join(output)
        self._content.update(full_text)

        # Scroll to bottom
        self.scroll_end(animate=False)


class ChatScreen(Screen):
    """Main chat screen."""

    BINDINGS = [
        Binding("ctrl+c,q", "quit", "Quit", show=True),
        Binding("ctrl+l", "logout", "Logout", show=True),
        Binding("ctrl+r", "refresh", "Refresh", show=True),
        Binding("ctrl+n", "new_channel", "New Channel", show=True),
        Binding("ctrl+d", "new_dm", "New DM", show=True, priority=True),
        Binding("ctrl+b", "new_bot_dm", "New Bot", show=True, priority=True),
        Binding("ctrl+e", "edit_message", "Edit Msg", show=True, priority=True),
        Binding("ctrl+x", "delete_message", "Delete Msg", show=True, priority=True),
        Binding("tab", "focus_next", "Next", show=False),
        Binding("shift+tab", "focus_previous", "Prev", show=False),
    ]

    CSS = """
    ChatScreen {
        layout: horizontal;
    }

    #sidebar {
        width: 30;
        height: 100%;
        border-right: solid $primary;
        background: $surface;
    }

    #main-area {
        width: 1fr;
        height: 100%;
        layout: vertical;
    }

    #conv-header {
        height: 3;
        background: $primary;
        content-align: center middle;
        text-style: bold;
    }

    #messages {
        height: 1fr;
        background: $surface;
        padding: 1;
        overflow-y: auto;
    }

    #input-area {
        height: auto;
        min-height: 3;
        background: $surface-darken-1;
        padding: 1;
    }

    #message-input {
        width: 100%;
    }

    .section-header {
        background: $primary-darken-2;
        text-align: center;
        text-style: bold;
        color: $text-muted;
        padding: 0 1;
    }

    .conversation-item {
        padding: 0 2;
    }

    .message-header {
        margin-bottom: 0;
    }

    .message-body {
        margin-left: 2;
        margin-bottom: 0;
    }

    .message-reactions {
        margin-left: 2;
        margin-bottom: 0;
        color: $accent;
    }

    .message-spacer {
        height: 1;
    }

    ListView:focus {
        border: tall $accent;
    }

    Input:focus {
        border: tall $accent;
    }
    """

    current_workspace_id: reactive[int | None] = reactive(None)
    current_conversation_id: reactive[int | None] = reactive(None)
    selected_message_number: reactive[int | None] = reactive(None)

    def __init__(self, api_client, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.api_client = api_client
        self._poll_timer = None
        self._last_message_id = None
        self.selected_message_number = None

    def compose(self) -> ComposeResult:
        """Create child widgets."""
        yield Header()

        with Horizontal():
            # Sidebar
            with Vertical(id="sidebar"):
                yield ConversationList(id="conversation-list")

            # Main area
            with Vertical(id="main-area"):
                yield Static("Select a conversation", id="conv-header")
                yield MessageDisplay(id="messages")
                with Container(id="input-area"):
                    yield Input(placeholder="Type a message...", id="message-input")

        yield Footer()

    async def on_mount(self):
        """Load data when screen mounts."""
        await self.load_workspaces()

    async def load_workspaces(self):
        """Load workspaces and conversations."""
        try:
            self.notify("Loading workspaces...", timeout=2)
            workspaces = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.get_workspaces
            )

            # Debug: check type
            if not isinstance(workspaces, list):
                self.notify(f"ERROR: workspaces is {type(workspaces)}, not list", severity="error", timeout=10)
                return

            if workspaces and len(workspaces) > 0:
                # Use first workspace
                first_ws = workspaces[0]

                # Debug: check type of first workspace
                if not isinstance(first_ws, dict):
                    self.notify(f"ERROR: workspace item is {type(first_ws)}, not dict", severity="error", timeout=10)
                    return

                self.current_workspace_id = first_ws.get("id")
                ws_name = first_ws.get("name", "Unknown")
                self.notify(f"Loaded workspace: {ws_name}", timeout=2)
                await self.load_conversations()
            else:
                self.notify("No workspaces found. Please create a workspace first.", severity="warning", timeout=10)
                self.query_one("#conv-header", Static).update("No workspaces found")
        except Exception as e:
            import traceback
            error_detail = traceback.format_exc()
            self.notify(f"Error loading workspaces: {str(e)}", severity="error", timeout=10)
            self.query_one("#conv-header", Static).update(f"Error: {str(e)[:50]}")

    async def load_conversations(self, silent=False):
        """Load conversations for current workspace."""
        if not self.current_workspace_id:
            return

        try:
            if not silent:
                self.notify("Loading conversations...", timeout=2)
            conversations = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.get_conversations,
                self.current_workspace_id
            )

            conv_list = self.query_one("#conversation-list", ConversationList)
            conv_list.set_conversations(conversations)

            if conversations and len(conversations) > 0:
                if not silent:
                    self.notify(f"Loaded {len(conversations)} conversations", timeout=2)
                # Select first conversation if available (only on first load)
                if not silent and not self.current_conversation_id:
                    self.current_conversation_id = conversations[0]["id"]
                    await self.load_messages()
            else:
                if not silent:
                    self.notify("No conversations found in this workspace.", severity="warning", timeout=5)
                    self.query_one("#conv-header", Static).update("No conversations - create one on the web!")
        except Exception as e:
            import traceback
            error_detail = traceback.format_exc()
            if not silent:
                self.notify(f"Error loading conversations: {e}", severity="error", timeout=10)
                self.query_one("#conv-header", Static).update(f"Error loading conversations")

    async def load_conversations_silently(self):
        """Reload conversations without notifications (used after marking messages as read)."""
        await self.load_conversations(silent=True)

    async def load_messages(self, silent=False):
        """Load messages for current conversation."""
        if not self.current_conversation_id:
            return

        try:
            data = await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.get_messages,
                self.current_conversation_id,
                200  # limit - increased to show more history
            )

            messages = data.get("messages", [])
            total = data.get("total", len(messages))
            has_more = data.get("has_more", False)

            if not silent:
                status_msg = f"Loaded {len(messages)} messages"
                if has_more:
                    status_msg += f" (of {total} total)"
                self.notify(status_msg, timeout=2)

            # Reverse messages so oldest is first (messages come from API newest-first)
            messages.reverse()

            # Track last message ID for polling
            if messages:
                self._last_message_id = messages[-1].get("id")

                # Mark the latest message as read to update unread counts
                try:
                    await asyncio.get_event_loop().run_in_executor(
                        None,
                        self.api_client.mark_as_read,
                        self._last_message_id
                    )
                    # Reload conversations to update unread counts in sidebar
                    await self.load_conversations_silently()
                except Exception as e:
                    # Log the error but don't fail the message load
                    import traceback
                    error_detail = traceback.format_exc()
                    self.log(f"Error marking message as read: {str(e)}\n{error_detail}")

            msg_display = self.query_one("#messages", MessageDisplay)
            msg_display.current_user_id = self.api_client.user.get("id") if self.api_client.user else None
            msg_display.set_messages(messages)

            # Update header with conversation name
            conv_list = self.query_one("#conversation-list", ConversationList)
            current_conv = next(
                (c for c in conv_list.conversations if c["id"] == self.current_conversation_id),
                None
            )
            if current_conv:
                name = current_conv.get("name") or current_conv.get("display_name", "Unknown")
                self.query_one("#conv-header", Static).update(f"# {name}")

        except Exception as e:
            import traceback
            if not silent:
                self.notify(f"Error loading messages: {str(e)[:100]}", severity="error", timeout=10)
            # Log full error for debugging
            print(f"ERROR loading messages: {e}")
            print(traceback.format_exc())

    async def poll_messages(self):
        """Poll for new messages every 3 seconds."""
        while True:
            await asyncio.sleep(3)
            if self.current_conversation_id:
                await self.load_messages(silent=True)

    def start_polling(self):
        """Start background polling for messages."""
        if self._poll_timer:
            return  # Already polling
        self._poll_timer = self.set_interval(3, self.check_new_messages, pause=False)

    def stop_polling(self):
        """Stop background polling."""
        if self._poll_timer:
            self._poll_timer.stop()
            self._poll_timer = None

    async def check_new_messages(self):
        """Check for new messages (called by timer)."""
        if self.current_conversation_id:
            await self.load_messages(silent=True)

    async def on_list_view_selected(self, event: ListView.Selected):
        """Handle conversation selection."""
        if hasattr(event.item, "conversation"):
            self.current_conversation_id = event.item.conversation["id"]
            self.notify("Loading messages...", timeout=2)
            await self.load_messages()
            # Start polling for this conversation
            self.start_polling()
            # Focus message input
            try:
                self.query_one("#message-input", Input).focus()
            except:
                pass  # Input might not be visible yet

    async def on_input_submitted(self, event: Input.Submitted):
        """Handle message send."""
        if event.input.id == "message-input":
            await self.send_message()

    async def send_message(self):
        """Send a message."""
        if not self.current_conversation_id:
            return

        input_widget = self.query_one("#message-input", Input)
        message = input_widget.value.strip()

        if not message:
            return

        try:
            await asyncio.get_event_loop().run_in_executor(
                None,
                self.api_client.send_message,
                self.current_conversation_id,
                message
            )

            # Clear input
            input_widget.value = ""

            # Auto-reload messages after a short delay
            await asyncio.sleep(0.5)
            await self.load_messages(silent=True)

        except Exception as e:
            self.notify(f"Error sending message: {e}", severity="error")

    def action_logout(self):
        """Logout and return to login screen."""
        self.api_client.logout()
        self.app.pop_screen()

    async def action_refresh(self):
        """Refresh messages."""
        await self.load_messages()

    def on_unmount(self):
        """Cleanup when screen is unmounted."""
        self.stop_polling()

    def action_new_channel(self):
        """Show modal to create a new channel."""
        if not self.current_workspace_id:
            self.notify("No workspace selected", severity="error")
            return

        self.run_worker(self._show_new_channel_modal(), exclusive=True)

    async def _show_new_channel_modal(self):
        """Worker to show new channel modal."""
        result = await self.app.push_screen_wait(
            CreateChannelModal(self.api_client, self.current_workspace_id)
        )

        if result:
            self.notify(f"Channel created: {result.get('name', 'Unknown')}", severity="information")
            await self.load_conversations()

    def action_new_dm(self):
        """Show modal to create a new DM."""
        if not self.current_workspace_id:
            self.notify("No workspace selected", severity="error")
            return

        # Get current user ID from the API client's stored user
        current_user_id = self.api_client.user.get("id") if self.api_client.user else None
        if not current_user_id:
            self.notify("User ID not available", severity="error")
            return

        self.run_worker(self._show_new_dm_modal(current_user_id), exclusive=True)

    async def _show_new_dm_modal(self, current_user_id):
        """Worker to show new DM modal."""
        result = await self.app.push_screen_wait(
            CreateDMModal(self.api_client, self.current_workspace_id, current_user_id)
        )

        if result:
            # Get display name for the DM
            display_name = result.get("display_name", "DM")
            self.notify(f"DM created with {display_name}", severity="information")
            await self.load_conversations()
            # Auto-select the new conversation
            self.current_conversation_id = result.get("id")
            await self.load_messages()

    def action_new_bot_dm(self):
        """Show modal to create a new bot DM."""
        if not self.current_workspace_id:
            self.notify("No workspace selected", severity="error")
            return

        # Get current user ID from the API client's stored user
        current_user_id = self.api_client.user.get("id") if self.api_client.user else None
        if not current_user_id:
            self.notify("User ID not available", severity="error")
            return

        self.run_worker(self._show_new_bot_dm_modal(current_user_id), exclusive=True)

    async def _show_new_bot_dm_modal(self, current_user_id):
        """Worker to show new bot DM modal."""
        from .create_modals import CreateBotDMModal
        result = await self.app.push_screen_wait(
            CreateBotDMModal(self.api_client, self.current_workspace_id, current_user_id)
        )

        if result:
            # Get display name for the bot DM
            display_name = result.get("display_name", "Bot")
            self.notify(f"Bot conversation created with {display_name}", severity="information")
            await self.load_conversations()
            # Auto-select the new conversation
            self.current_conversation_id = result.get("id")
            await self.load_messages()

    def action_edit_message(self):
        """Prompt for message number to edit."""
        msg_display = self.query_one("#messages", MessageDisplay)

        if not msg_display.messages:
            self.notify("No messages to edit", severity="warning")
            return

        # Find own messages
        current_user_id = self.api_client.user.get("id") if self.api_client.user else None
        own_messages = [m for m in msg_display.messages if m.get("user_id") == current_user_id]

        if not own_messages:
            self.notify("You have no messages to edit in this conversation", severity="warning")
            return

        # Show selection modal
        total_messages = len(msg_display.messages)
        self.run_worker(self._select_message_to_edit(total_messages), exclusive=True)

    async def _select_message_to_edit(self, total_messages):
        """Worker to select which message to edit."""
        msg_num = await self.app.push_screen_wait(
            SelectMessageModal("Edit", total_messages)
        )

        if msg_num is None:
            return

        # Get the selected message
        msg_display = self.query_one("#messages", MessageDisplay)
        if 1 <= msg_num <= len(msg_display.messages):
            message = msg_display.messages[msg_num - 1]

            # Check if it's the user's own message
            current_user_id = self.api_client.user.get("id") if self.api_client.user else None
            if message.get("user_id") != current_user_id:
                self.notify("You can only edit your own messages", severity="error")
                return

            await self._show_edit_modal(message)

    async def _show_edit_modal(self, message):
        """Worker to show edit message modal."""
        result = await self.app.push_screen_wait(
            EditMessageModal(self.api_client, message)
        )

        if result:
            self.notify("Message updated", severity="information")
            await self.load_messages(silent=True)

    def action_delete_message(self):
        """Prompt for message number to delete."""
        msg_display = self.query_one("#messages", MessageDisplay)

        if not msg_display.messages:
            self.notify("No messages to delete", severity="warning")
            return

        # Find own messages
        current_user_id = self.api_client.user.get("id") if self.api_client.user else None
        own_messages = [m for m in msg_display.messages if m.get("user_id") == current_user_id]

        if not own_messages:
            self.notify("You have no messages to delete in this conversation", severity="warning")
            return

        # Show selection modal
        total_messages = len(msg_display.messages)
        self.run_worker(self._select_message_to_delete(total_messages), exclusive=True)

    async def _select_message_to_delete(self, total_messages):
        """Worker to select which message to delete."""
        msg_num = await self.app.push_screen_wait(
            SelectMessageModal("Delete", total_messages)
        )

        if msg_num is None:
            return

        # Get the selected message
        msg_display = self.query_one("#messages", MessageDisplay)
        if 1 <= msg_num <= len(msg_display.messages):
            message = msg_display.messages[msg_num - 1]

            # Check if it's the user's own message
            current_user_id = self.api_client.user.get("id") if self.api_client.user else None
            if message.get("user_id") != current_user_id:
                self.notify("You can only delete your own messages", severity="error")
                return

            await self._show_delete_modal(message)

    async def _show_delete_modal(self, message):
        """Worker to show delete message modal."""
        result = await self.app.push_screen_wait(
            DeleteMessageModal(self.api_client, message)
        )

        if result:
            self.notify("Message deleted", severity="information")
            await self.load_messages(silent=True)
