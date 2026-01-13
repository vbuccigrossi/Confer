# Confer TUI v0.2.0 Release Notes

**Release Date:** November 24, 2025
**Package Size:** 49 KB
**This is the last manual distribution - all future updates will be automatic!**

## 🎉 Major Features

### 🔄 Automatic Update System
- **Self-Updating**: TUI checks for updates on startup
- **One-Click Updates**: Users simply press Enter to update
- **No Manual Process**: No more download/extract/install cycle
- **Progress Indicator**: Shows download progress in real-time
- **Release Notes**: Users see what's new before updating
- **Graceful Failure**: Network issues don't block app startup
- **Smart Detection**: Detects virtualenv vs system installs automatically

### 💬 Slash Commands Support
- `/help` - Show all available commands
- `/listbots` - List available bots
- `/addbot <name>` - Add a bot to current conversation
- `/removebot <name>` - Remove a bot from conversation
- `/listusers` - List users in current conversation
- `/adduser <username>` - Add a user to conversation
- `/removeuser <username>` - Remove a user from conversation

### 🤖 Bot Management
- Bots no longer auto-added to channels
- Manual bot management via slash commands
- Support for bot-provided custom commands
- Better bot conversation support

## 🐛 Bug Fixes

### Unread Message Count Fixed
- Fixed accumulating unread counts in TUI and mobile app
- Counts now properly reset when messages are read
- Accurate unread indicators across all interfaces

### Better Error Logging
- Enhanced error logging in TUI
- Better debugging for mark-as-read failures
- Improved error messages for users

## 🎯 Improvements

### Configuration
- Ships with default API URL pre-configured
- No configuration needed for groundstatesystems.work
- Simplified first-run experience

### Command Name
- Command is now just `confer` (was `confer-tui`)
- Cleaner, shorter command
- Matches product branding

### API Integration
- All slash commands work via API
- Command-agnostic client design
- Future commands work without client updates

## 📦 Installation

### New Users
1. Extract `confer-tui-release.tar.gz`
2. Run `./install.sh` (Linux/Mac) or `install.bat` (Windows)
3. Run `confer` - it's pre-configured!

### Existing Users (0.1.0)
1. Install this version manually (one last time!)
2. From now on, updates are automatic on startup
3. Just press Enter when prompted to update

## 🔮 Future Updates

**This is the last manual distribution!**

Starting with v0.2.0, all users will automatically receive updates when they start the TUI. When a new version is available:

1. User runs `confer`
2. Prompt appears: "UPDATE AVAILABLE - Current: 0.2.0, Latest: 0.3.0"
3. Shows release notes
4. User presses Enter
5. Update downloads and installs automatically
6. User restarts TUI to use new version

No more manual download/extract/install cycles!

## 📊 Technical Details

### Version Information
- **Version:** 0.2.0
- **Package Format:** Wheel (.whl) + Source (.tar.gz)
- **Python Requirements:** 3.8+
- **New Dependencies:** requests, packaging

### API Endpoints
- `GET /api/updates/tui/version` - Version check
- `GET /api/updates/tui/download` - Download latest release

### Testing
All features have been comprehensively tested:
- ✅ Version check API
- ✅ Download API
- ✅ Complete update flow (0.1.0 → 0.2.0)
- ✅ Post-update verification
- ✅ User interaction (accept/decline)
- ✅ Integration test (full startup flow)
- ✅ Graceful failure handling
- ✅ Slash commands in all interfaces
- ✅ Unread count reset

## 🙏 Notes for Users

This release represents a major step forward in making Confer TUI easier to maintain and update. The automatic update system means:

- **No more manual updates** - Updates happen automatically
- **Always current** - Stay on the latest version effortlessly
- **Faster bug fixes** - Critical fixes reach users immediately
- **New features faster** - New features deploy without friction

Thank you for using Confer TUI!

## 🔧 For Developers

### Releasing Future Versions

1. Update version in `confer_tui/__init__.py` and `setup.py`
2. Update `UpdateController.php`:
   - Change `$latestVersion` (line 16)
   - Add release notes (lines 22-28)
3. Run `./build-dist.sh`
4. Copy to server: `docker cp confer-tui-release.tar.gz latch-app:/var/www/html/storage/app/public/`

All users will automatically receive the update!

---

**Distribution File:** `confer-tui-release.tar.gz` (49 KB)
**SHA256:** `b23ff4c59ee2d1db8c7c984d9975b65a9fd17d895486d01f5a397ea4e7f8bf27`
**Download:** `https://groundstatesystems.work/api/updates/tui/download`
