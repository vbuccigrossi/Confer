"""Configuration management for Confer TUI."""

import json
import os
from pathlib import Path
from typing import Optional


class Config:
    """Manages TUI configuration."""

    def __init__(self):
        self.config_dir = Path.home() / ".config" / "confer"
        self.config_file = self.config_dir / "config.json"
        self._data = {}
        self._load()

    def _load(self):
        """Load configuration from file."""
        # Set default configuration
        self._data = {
            "api_url": "https://groundstatesystems.work/api"
        }

        # Override with user config if it exists
        if self.config_file.exists():
            try:
                with open(self.config_file, 'r') as f:
                    user_config = json.load(f)
                    self._data.update(user_config)
            except (json.JSONDecodeError, IOError) as e:
                # If config file is corrupted, use defaults
                print(f"Warning: Could not load config file: {e}")
                print(f"Using default configuration")

    def get(self, key: str, default=None):
        """Get a configuration value."""
        return self._data.get(key, default)

    def set(self, key: str, value):
        """Set a configuration value and save."""
        self._data[key] = value
        self.save()

    def save(self):
        """Save configuration to file."""
        # Create config directory if it doesn't exist
        self.config_dir.mkdir(parents=True, exist_ok=True)

        try:
            with open(self.config_file, 'w') as f:
                json.dump(self._data, f, indent=2)
        except IOError as e:
            print(f"Warning: Could not save config file: {e}")

    @property
    def api_url(self) -> Optional[str]:
        """Get the API URL from config."""
        return self.get("api_url")

    @api_url.setter
    def api_url(self, value: str):
        """Set the API URL in config."""
        self.set("api_url", value)


def get_config() -> Config:
    """Get the global config instance."""
    return Config()
