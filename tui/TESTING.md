# Confer TUI Testing Guide

## Prerequisites

1. **Backend Services Running**
   ```bash
   cd /home/ebrown/Desktop/projects/confer
   docker-compose up
   ```
   Verify services are running:
   ```bash
   docker-compose ps
   ```

2. **API Accessible**
   - Web interface: http://localhost:8080 or https://localhost
   - API base URL: https://localhost/api

3. **Test Account**
   - Register an account at http://localhost:8080/register first
   - Or use an existing account

## Installation

```bash
cd /home/ebrown/Desktop/projects/confer/tui

# Create and activate virtual environment
python3 -m venv venv
source venv/bin/activate

# Install TUI
pip install -e .
```

## Running the TUI

### Method 1: Using the confer command

```bash
# Activate virtual environment
source venv/bin/activate

# Run with default API URL (http://localhost/api)
confer

# Run with HTTPS (recommended)
confer --api-url https://localhost/api
```

### Method 2: Using Python module

```bash
# Activate virtual environment
source venv/bin/activate

# Run as module
python -m confer_tui --api-url https://localhost/api
```

## Testing Checklist

### 1. Login Flow
- [ ] TUI displays login screen with ASCII logo
- [ ] Can enter email address
- [ ] Can enter password (masked)
- [ ] Pressing Enter on password field submits login
- [ ] Invalid credentials show error message
- [ ] Valid credentials proceed to chat screen
- [ ] Token is saved to ~/.confer/config.json
- [ ] Reopening TUI auto-logs in with saved token

### 2. Chat Screen Layout
- [ ] Sidebar shows on the left
- [ ] Three sections visible: CHANNELS, DIRECT MESSAGES, BOTS
- [ ] Main message area shows in center
- [ ] Message input box at bottom
- [ ] Header shows current conversation name

### 3. Conversation Navigation
- [ ] Can see list of channels (# prefix)
- [ ] Can see list of DMs (@ prefix)
- [ ] Can see list of bot conversations (🤖 prefix)
- [ ] Arrow keys navigate conversation list
- [ ] Pressing Enter selects conversation
- [ ] Selected conversation loads messages

### 4. Messaging
- [ ] Messages display with username and timestamp
- [ ] Markdown formatting renders correctly
- [ ] Can type in message input
- [ ] Pressing Enter sends message
- [ ] Message appears in conversation after sending
- [ ] Can switch between conversations

### 5. Bot Interactions
- [ ] Bot conversations appear in BOTS section
- [ ] Can open bot conversation
- [ ] Can send commands to bots (e.g., /help, /news tech)
- [ ] Bot responses display correctly
- [ ] Bot markdown formatting works

### 6. Keyboard Shortcuts
- [ ] Tab switches between sidebar and message input
- [ ] Ctrl+C quits application
- [ ] q quits application
- [ ] Up/Down navigates conversations
- [ ] Ctrl+L logs out

### 7. Error Handling
- [ ] Graceful handling of network errors
- [ ] Graceful handling of invalid API responses
- [ ] Empty conversation states handled
- [ ] Loading states visible

## Known Limitations

1. **Real-time Updates**: The current TUI implementation doesn't include WebSocket support, so messages won't appear in real-time. You'll need to switch conversations or refresh to see new messages.

2. **SSL Certificate**: If using HTTPS with self-signed certificates, the API client may need to disable SSL verification (already handled in code).

3. **File Attachments**: Not yet implemented in TUI.

4. **Message Editing/Deletion**: Not yet implemented in TUI.

5. **Reactions**: Not yet implemented in TUI.

6. **Thread Replies**: Not yet implemented in TUI.

## Troubleshooting

### "Connection refused" error
- Verify Docker services are running: `docker-compose ps`
- Check API is accessible: `curl -k https://localhost/api/auth/profile`

### "Invalid credentials" error
- Verify account exists in web interface
- Check email and password are correct
- Try registering new account at http://localhost:8080/register

### "Module not found" error
- Activate virtual environment: `source venv/bin/activate`
- Reinstall: `pip install -e .`

### TUI won't start
- Check Python version: `python3 --version` (requires 3.8+)
- Check all dependencies installed: `pip list | grep -E "(textual|httpx|rich)"`

### Token errors
- Delete saved token: `rm ~/.confer/config.json`
- Login again with fresh credentials

## Development

### Running with debug/dev mode
```bash
# Install textual-dev tools
pip install textual-dev

# Run with dev console
textual console
# In another terminal:
textual run --dev confer_tui/main.py
```

### Checking logs
```bash
# TUI doesn't write logs by default, but you can add logging:
# Edit api_client.py and add:
import logging
logging.basicConfig(level=logging.DEBUG, filename='~/confer-tui.log')
```

## Next Steps

Once basic testing is complete, potential enhancements:
1. Add WebSocket support for real-time messages
2. Add file upload/download
3. Add message editing and deletion
4. Add reactions support
5. Add thread navigation
6. Add search functionality
7. Add user presence indicators
8. Add typing indicators
