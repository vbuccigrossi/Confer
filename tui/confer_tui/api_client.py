"""API client for Confer backend."""

import httpx
from typing import Optional, Dict, List, Any
import json
import uuid
from pathlib import Path


class ConferAPIClient:
    """Client for interacting with Confer API."""

    def __init__(self, base_url: str = "http://localhost/api"):
        self.base_url = base_url.rstrip('/')
        self.token: Optional[str] = None
        self.user: Optional[Dict] = None
        # Disable SSL verification for self-signed certificates in development
        # Disable redirects to see the actual response
        self.client = httpx.Client(timeout=30.0, verify=False, follow_redirects=False)

    def _headers(self) -> Dict[str, str]:
        """Get headers with auth token."""
        headers = {
            "Content-Type": "application/json",
            "X-Client-Type": "tui",
        }
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        return headers

    def _get_device_id(self) -> str:
        """Get or create a persistent device ID for this TUI installation."""
        config_path = self._get_config_path()
        config_dir = config_path.parent
        device_id_path = config_dir / "device_id"

        if device_id_path.exists():
            return device_id_path.read_text().strip()

        # Generate a new device ID
        device_id = uuid.uuid4().hex[:16]
        device_id_path.write_text(device_id)
        return device_id

    def login(self, email: str, password: str) -> Dict[str, Any]:
        """Login and get auth token."""
        try:
            # Get device ID for consistent token naming
            device_id = self._get_device_id()

            response = self.client.post(
                f"{self.base_url}/auth/login",
                json={
                    "email": email,
                    "password": password,
                    "device_name": f"tui-{device_id}",
                    "device_id": device_id,
                },
                headers={"Content-Type": "application/json", "X-Client-Type": "tui"}
            )
            response.raise_for_status()
            data = response.json()

            # Check if we got a token
            if "token" not in data:
                raise ValueError(f"No token in response: {data}")

            self.token = data["token"]
            self.user = data["user"]
            self._save_token()
            return data
        except httpx.HTTPStatusError as e:
            # Extract error message from response if available
            try:
                error_data = e.response.json()
                error_msg = error_data.get("message", str(e))
            except:
                error_msg = f"HTTP {e.response.status_code}: {e.response.text[:100]}"
            raise Exception(error_msg)
        except Exception as e:
            raise Exception(f"Login error: {str(e)}")

    def register(self, name: str, email: str, password: str) -> Dict[str, Any]:
        """Register a new user."""
        device_id = self._get_device_id()

        response = self.client.post(
            f"{self.base_url}/auth/register",
            json={
                "name": name,
                "email": email,
                "password": password,
                "password_confirmation": password,
                "device_name": f"tui-{device_id}",
                "device_id": device_id,
            },
            headers={"Content-Type": "application/json", "X-Client-Type": "tui"}
        )
        response.raise_for_status()
        data = response.json()
        self.token = data["token"]
        self.user = data["user"]
        self._save_token()
        return data

    def logout(self):
        """Logout and clear token."""
        if self.token:
            try:
                self.client.post(
                    f"{self.base_url}/auth/logout",
                    headers=self._headers()
                )
            except:
                pass
        self.token = None
        self.user = None
        self._clear_token()

    def get_profile(self) -> Dict[str, Any]:
        """Get current user profile."""
        response = self.client.get(
            f"{self.base_url}/auth/profile",
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def get_workspaces(self) -> List[Dict]:
        """Get all workspaces for current user."""
        response = self.client.get(
            f"{self.base_url}/workspaces",
            headers=self._headers()
        )
        response.raise_for_status()
        data = response.json()
        # API returns array directly, not wrapped in object
        if isinstance(data, list):
            return data
        # Fallback if wrapped in object
        return data.get("data", data if isinstance(data, list) else [])

    def get_conversations(self, workspace_id: int) -> List[Dict]:
        """Get all conversations for a workspace."""
        response = self.client.get(
            f"{self.base_url}/conversations",
            params={"workspace_id": workspace_id},
            headers=self._headers()
        )
        response.raise_for_status()
        data = response.json()
        # API may return array directly or wrapped in {conversations: [...]}
        if isinstance(data, list):
            return data
        return data.get("conversations", [])

    def get_messages(self, conversation_id: int, limit: int = 50, before: Optional[int] = None) -> Dict:
        """Get messages for a conversation."""
        params = {"limit": limit}
        if before:
            params["before"] = before

        response = self.client.get(
            f"{self.base_url}/conversations/{conversation_id}/messages",
            params=params,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def send_message(self, conversation_id: int, body_md: str, parent_message_id: Optional[int] = None) -> Dict:
        """Send a message to a conversation."""
        payload = {"body_md": body_md}
        if parent_message_id:
            payload["parent_message_id"] = parent_message_id

        response = self.client.post(
            f"{self.base_url}/conversations/{conversation_id}/messages",
            json=payload,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def edit_message(self, message_id: int, body_md: str) -> Dict:
        """Edit a message."""
        response = self.client.patch(
            f"{self.base_url}/messages/{message_id}",
            json={"body_md": body_md},
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def delete_message(self, message_id: int) -> Dict:
        """Delete a message."""
        response = self.client.delete(
            f"{self.base_url}/messages/{message_id}",
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def add_reaction(self, message_id: int, emoji: str) -> Dict:
        """Add a reaction to a message."""
        response = self.client.post(
            f"{self.base_url}/messages/{message_id}/reactions",
            json={"emoji": emoji},
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def remove_reaction(self, message_id: int, emoji: str):
        """Remove a reaction from a message."""
        response = self.client.delete(
            f"{self.base_url}/messages/{message_id}/reactions/{emoji}",
            headers=self._headers()
        )
        response.raise_for_status()

    def search_messages(self, query: str, workspace_id: int, conversation_id: Optional[int] = None) -> Dict:
        """Search messages."""
        params = {"q": query, "workspace_id": workspace_id}
        if conversation_id:
            params["conversation_id"] = conversation_id

        response = self.client.get(
            f"{self.base_url}/search",
            params=params,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def create_channel(self, workspace_id: int, name: str, channel_type: str = "public_channel", topic: str = "") -> Dict:
        """Create a new channel."""
        payload = {
            "workspace_id": workspace_id,
            "type": channel_type,
            "name": name,
            "topic": topic
        }
        response = self.client.post(
            f"{self.base_url}/conversations",
            json=payload,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def create_dm(self, workspace_id: int, user_ids: List[int]) -> Dict:
        """Create a new DM or group DM."""
        # Ensure all user_ids are integers and filter out any None values
        clean_user_ids = [int(uid) for uid in user_ids if uid is not None]

        payload = {
            "workspace_id": workspace_id,
            "type": "group_dm" if len(clean_user_ids) > 1 else "dm",
            "member_ids": clean_user_ids
        }
        response = self.client.post(
            f"{self.base_url}/conversations",
            json=payload,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def get_workspace_members(self, workspace_id: int) -> List[Dict]:
        """Get all members of a workspace."""
        response = self.client.get(
            f"{self.base_url}/workspaces/{workspace_id}/members",
            headers=self._headers()
        )
        response.raise_for_status()
        data = response.json()
        # API may return array directly or wrapped
        if isinstance(data, list):
            return data
        return data.get("members", data.get("data", []))

    def mark_as_read(self, message_id: int) -> Dict:
        """Mark a message (and all messages before it in the conversation) as read."""
        response = self.client.post(
            f"{self.base_url}/messages/{message_id}/read",
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def search_users(self, workspace_id: int, query: str = "bot") -> List[Dict]:
        """Search for users in a workspace."""
        response = self.client.get(
            f"{self.base_url}/users/search",
            params={"query": query, "workspace_id": workspace_id},
            headers=self._headers()
        )
        response.raise_for_status()
        data = response.json()
        if isinstance(data, list):
            return data
        return data.get("users", data.get("data", []))

    def create_bot_dm(self, workspace_id: int, bot_user_id: int) -> Dict:
        """Create a new bot DM conversation."""
        payload = {
            "workspace_id": workspace_id,
            "type": "bot_dm",
            "member_ids": [bot_user_id]
        }
        response = self.client.post(
            f"{self.base_url}/conversations",
            json=payload,
            headers=self._headers()
        )
        response.raise_for_status()
        return response.json()

    def _get_config_path(self) -> Path:
        """Get path to config file."""
        config_dir = Path.home() / ".confer"
        config_dir.mkdir(exist_ok=True)
        return config_dir / "config.json"

    def _save_token(self):
        """Save token to config file."""
        config_path = self._get_config_path()
        config = {"token": self.token, "base_url": self.base_url}
        if self.user:
            config["user"] = self.user
        config_path.write_text(json.dumps(config, indent=2))

    def _clear_token(self):
        """Clear saved token."""
        config_path = self._get_config_path()
        if config_path.exists():
            config_path.unlink()

    def load_saved_token(self) -> bool:
        """Load token from config file."""
        config_path = self._get_config_path()
        if not config_path.exists():
            return False

        try:
            config = json.loads(config_path.read_text())
            self.token = config.get("token")
            self.user = config.get("user")
            self.base_url = config.get("base_url", self.base_url)
            return bool(self.token)
        except:
            return False
