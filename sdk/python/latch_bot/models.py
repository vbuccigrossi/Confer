"""
Latch Bot SDK Models

Data models representing Latch entities.
"""

from dataclasses import dataclass, field
from datetime import datetime
from typing import Optional, List, Dict, Any


@dataclass
class User:
    """Represents a Latch user."""

    id: int
    name: str
    email: Optional[str] = None
    avatar_url: Optional[str] = None

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "User":
        """Create a User from a dictionary."""
        return cls(
            id=data["id"],
            name=data["name"],
            email=data.get("email"),
            avatar_url=data.get("avatar_url"),
        )


@dataclass
class ConversationMember:
    """Represents a member of a conversation."""

    id: int
    conversation_id: int
    user: User

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "ConversationMember":
        """Create a ConversationMember from a dictionary."""
        return cls(
            id=data["id"],
            conversation_id=data.get("conversation_id", 0),
            user=User.from_dict(data["user"]) if data.get("user") else None,
        )


@dataclass
class Attachment:
    """Represents a file attachment."""

    id: int
    filename: str
    mime_type: str
    size: int
    url: Optional[str] = None

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "Attachment":
        """Create an Attachment from a dictionary."""
        return cls(
            id=data["id"],
            filename=data["filename"],
            mime_type=data["mime_type"],
            size=data["size"],
            url=data.get("url"),
        )


@dataclass
class Reaction:
    """Represents a reaction on a message."""

    id: int
    emoji: str
    user_id: int
    user: Optional[User] = None

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "Reaction":
        """Create a Reaction from a dictionary."""
        return cls(
            id=data["id"],
            emoji=data["emoji"],
            user_id=data["user_id"],
            user=User.from_dict(data["user"]) if data.get("user") else None,
        )


@dataclass
class Message:
    """Represents a Latch message."""

    id: int
    conversation_id: int
    user_id: int
    body_md: str
    body_html: str
    parent_message_id: Optional[int] = None
    created_at: Optional[datetime] = None
    updated_at: Optional[datetime] = None
    user: Optional[User] = None
    reactions: List[Reaction] = field(default_factory=list)
    attachments: List[Attachment] = field(default_factory=list)

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "Message":
        """Create a Message from a dictionary."""
        return cls(
            id=data["id"],
            conversation_id=data["conversation_id"],
            user_id=data["user_id"],
            body_md=data.get("body_md", ""),
            body_html=data.get("body_html", ""),
            parent_message_id=data.get("parent_message_id"),
            created_at=_parse_datetime(data.get("created_at")),
            updated_at=_parse_datetime(data.get("updated_at")),
            user=User.from_dict(data["user"]) if data.get("user") else None,
            reactions=[
                Reaction.from_dict(r) for r in data.get("reactions", [])
            ],
            attachments=[
                Attachment.from_dict(a) for a in data.get("attachments", [])
            ],
        )


@dataclass
class Conversation:
    """Represents a Latch conversation (channel or DM)."""

    id: int
    workspace_id: int
    name: Optional[str]
    description: Optional[str]
    type: str  # public_channel, private_channel, dm, group_dm
    is_archived: bool = False
    created_by: Optional[int] = None
    members: List[ConversationMember] = field(default_factory=list)

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "Conversation":
        """Create a Conversation from a dictionary."""
        return cls(
            id=data["id"],
            workspace_id=data["workspace_id"],
            name=data.get("name"),
            description=data.get("description"),
            type=data["type"],
            is_archived=data.get("is_archived", False),
            created_by=data.get("created_by"),
            members=[
                ConversationMember.from_dict(m) for m in data.get("members", [])
            ],
        )

    @property
    def is_channel(self) -> bool:
        """Check if this is a channel (public or private)."""
        return self.type in ("public_channel", "private_channel")

    @property
    def is_dm(self) -> bool:
        """Check if this is a direct message."""
        return self.type in ("dm", "group_dm")

    @property
    def is_public(self) -> bool:
        """Check if this is a public channel."""
        return self.type == "public_channel"


@dataclass
class CommandPayload:
    """Represents a slash command invocation payload."""

    command: str
    text: str
    conversation_id: int
    user_id: int
    user_name: str
    workspace_id: int
    config: Dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> "CommandPayload":
        """Create a CommandPayload from a dictionary."""
        return cls(
            command=data["command"],
            text=data.get("text", ""),
            conversation_id=data["conversation_id"],
            user_id=data["user_id"],
            user_name=data.get("user_name", ""),
            workspace_id=data["workspace_id"],
            config=data.get("config", {}),
        )


def _parse_datetime(value: Optional[str]) -> Optional[datetime]:
    """Parse an ISO datetime string."""
    if not value:
        return None
    try:
        # Handle various ISO formats
        if value.endswith("Z"):
            value = value[:-1] + "+00:00"
        return datetime.fromisoformat(value)
    except (ValueError, TypeError):
        return None
