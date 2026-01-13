# Confer TUI - Terminal User Interface

A beautiful terminal interface for Confer chat platform.

## ✨ Recent Improvements

- 🔄 **Auto-Update System** - TUI checks for updates on startup and can update itself automatically!
- 💬 **Slash Commands** - Full support for `/help`, `/addbot`, `/listbots`, and more
- 🎯 **Config File Support** - Save your API URL once, never type it again!
- 🔧 **Better macOS Support** - Fixed Ctrl+E/D hotkeys when text input is focused
- 🤖 **Bot Conversations** - Full support for chatting with bots
- 💡 **Unread Highlighting** - Conversations with new messages appear in bold yellow

## Features

- 🔐 **Authentication** - Secure login with token persistence
- 💬 **Channels** - Public and private channel support
- 👤 **Direct Messages** - One-on-one and group DMs
- 🤖 **Bot Conversations** - Chat with AI bots and integrations
- 💬 **Slash Commands** - Execute commands like `/help`, `/addbot`, `/listusers`, etc.
- ⚡ **Real-time Updates** - Auto-refresh every 3 seconds
- ✏️ **Message Management** - Edit and delete your own messages
- 📋 **Message Numbering** - Easy reference for editing/deleting
- 💡 **Visual Indicators** - Bold yellow highlighting for unread conversations
- 🔄 **Auto-Updates** - Automatic update checking and installation on startup
- ⚙️ **Config File** - Persistent settings stored in `~/.config/confer/`
- 🎨 **Beautiful UI** - Clean terminal interface with Textual framework

## Quick Start (Recommended for Users)

### Linux/macOS
```bash
# Run the automated installer
chmod +x install.sh
./install.sh

# Then run the TUI (pre-configured for groundstatesystems.work)
confer
```

### Windows
```cmd
# Run the automated installer
install.bat

# Then run the TUI (pre-configured for groundstatesystems.work)
confer
```

The TUI comes pre-configured to connect to `https://groundstatesystems.work/api`. No additional configuration needed!

## Auto-Updates

The TUI automatically checks for updates on startup. When a new version is available, you'll see:

```
============================================================
UPDATE AVAILABLE
============================================================
Current version: 0.1.0
Latest version:  0.2.0

What's new:
  • Added slash command support
  • Fixed unread message count issue
  • Added bot conversation support
  • Improved error logging

============================================================
Would you like to install this update? [Y/n]:
```

Simply press Enter (or type 'y') to download and install the update automatically. The update is installed in-place with no need to manually uninstall or reconfigure. After the update completes, just restart the TUI to use the new version.

**No more manual update process!** The days of manually removing, downloading, and reinstalling are over.

## Manual Installation

### Requirements
- Python 3.8 or later
- pip (Python package manager)

### Step-by-step

```bash
# 1. Create virtual environment
python3 -m venv venv

# 2. Activate virtual environment
source venv/bin/activate  # On Windows: venv\Scripts\activate

# 3. Install the TUI
pip install -e .

# 4. Run the TUI (pre-configured)
confer
```

## Usage

### Regular Usage

The TUI comes pre-configured to connect to `https://groundstatesystems.work/api`. Just run:

```bash
# Run with default settings (no configuration needed!)
confer

# Override API URL for a different server
confer --api-url http://localhost/api

# Save a custom API URL permanently
confer --set-api-url http://your-server.com/api

# Alternative: run as a module
python -m confer_tui

# See all options
confer --help
```

## Configuration

The TUI stores configuration in `~/.config/confer/config.json`

This includes:
- API URL (saved via `--set-api-url` or automatically when using `--api-url`)
- Auth token (saved automatically on login)

You can manually edit this file if needed:
```json
{
  "api_url": "https://groundstatesystems.work/api"
}
```

## Building Distribution Packages

For maintainers who want to create distributable packages:

```bash
# Build source distribution and wheel
chmod +x build-dist.sh
./build-dist.sh
```

This creates a `confer-tui-release.tar.gz` archive containing:
- Pre-built wheel packages
- Installation scripts for all platforms
- Documentation

You can then share this archive with users who simply extract and run the install script.

## Development

```bash
# Install in editable mode with dev dependencies
pip install -e .

# Run with hot reload (requires textual[dev])
pip install textual[dev]
textual run --dev confer_tui/main.py

# Run tests (if available)
pytest
```

## Keyboard Shortcuts

### Navigation
- `Tab` - Switch between sidebar and messages
- `Shift+Tab` - Switch backwards
- `Up/Down` - Navigate conversations in sidebar
- `Esc` - Clear message input

### Actions
- `Ctrl+C` or `q` - Quit application
- `Ctrl+L` - Logout
- `Ctrl+R` - Refresh messages
- `Ctrl+N` - Create new channel
- `Ctrl+D` - Create new DM
- `Ctrl+B` - Create new bot conversation
- `Ctrl+E` - Edit a message (prompts for message number)
- `Ctrl+X` - Delete a message (prompts for message number)
- `Enter` - Send message (when in input field)

### Editing/Deleting Messages
Messages are numbered in the chat view (e.g., `#1`, `#2`, `#3`). When you press `Ctrl+E` or `Ctrl+X`, you'll be prompted to enter the number of the message you want to edit or delete. You can only edit/delete your own messages.

### Conversation Organization
The sidebar organizes conversations into three sections:

- **━━ CHANNELS ━━** - Public and private channels (prefix: `#`)
- **━━ DIRECT MESSAGES ━━** - One-on-one and group DMs (prefix: `@`)
- **━━ BOTS ━━** - Bot conversations (prefix: `🤖`)

**Visual Indicators:**
- Conversations with unread messages are highlighted in **bold yellow**
- Unread counts appear in **bold red** (e.g., `(3)`)
- Read conversations appear in normal white text
- This makes it easy to spot which chats have new activity!

## Tips & Tricks

### Quick Setup
After installing, set your API URL once and forget about it:
```bash
confer --set-api-url https://groundstatesystems.work/api
confer  # Just run with no arguments!
```

### Editing Messages
1. Look at the message number in yellow (e.g., `#5`)
2. Press `Ctrl+E`
3. Type the message number
4. Edit and save!

### Navigation
- Use `Tab` to switch between sidebar and chat
- Use `Up/Down` arrows in the sidebar to browse conversations
- Press `Ctrl+R` to manually refresh if needed

### macOS Users
All keyboard shortcuts work even when the text input is focused, so you don't need to tab away to use `Ctrl+E`, `Ctrl+D`, etc.

## Troubleshooting

### "No conversations found"
- Make sure you've created conversations in the web UI first
- The TUI currently doesn't support creating channels (use `Ctrl+N` for new channels, `Ctrl+D` for new DMs)
- Try pressing `Ctrl+R` to refresh

### "Connection refused"
- Check that your API URL is correct
- Make sure the server is running
- Try: `confer --set-api-url https://groundstatesystems.work/api`

### Config file location
- Linux/macOS: `~/.config/confer/config.json`
- Windows: `%USERPROFILE%\.config\confer\config.json`
