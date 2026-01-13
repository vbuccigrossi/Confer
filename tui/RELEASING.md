# How to Release a New TUI Version

This document explains how to release new versions of the Confer TUI now that the auto-update system is in place.

## Quick Release Checklist

- [ ] Update version numbers
- [ ] Update release notes in UpdateController
- [ ] Build distribution package
- [ ] Deploy to server
- [ ] Verify endpoints

## Step-by-Step Process

### 1. Update Version Numbers

Edit both files with the new version:

**File: `confer_tui/__init__.py`**
```python
__version__ = "0.3.0"  # Change this
```

**File: `setup.py`**
```python
setup(
    name="confer-tui",
    version="0.3.0",  # Change this (line 10)
    ...
)
```

### 2. Update Backend Controller

**File: `app/app/Http/Controllers/UpdateController.php`**

Update line 16 with new version:
```php
$latestVersion = '0.3.0'; // Update this
```

Add release notes (lines 22-28):
```php
'release_notes' => [
    '0.3.0' => [
        'New feature A',
        'Fixed bug B',
        'Improved C',
    ],
    '0.2.0' => [
        'Added slash command support',
        'Fixed unread message count issue',
        'Added bot conversation support',
        'Improved error logging',
    ],
],
```

Keep previous version's notes so users upgrading from older versions can see all changes.

### 3. Build Distribution Package

```bash
cd /home/ebrown/Desktop/projects/confer/tui
./build-dist.sh
```

This creates `confer-tui-release.tar.gz` with the new version.

### 4. Deploy to Server

```bash
docker cp confer-tui-release.tar.gz latch-app:/var/www/html/storage/app/public/
```

### 5. Verify Deployment

```bash
# Check version endpoint
curl -s https://groundstatesystems.work/api/updates/tui/version | python3 -m json.tool

# Check download endpoint
curl -I https://groundstatesystems.work/api/updates/tui/download

# Should show new version and release notes
```

### 6. Test Update Flow (Optional but Recommended)

```bash
cd /tmp
mkdir test-update && cd test-update
python3 -m venv venv
source venv/bin/activate
pip install /path/to/old/version.whl

# Patch to old version for testing
python3 -c "
import confer_tui
from pathlib import Path
pkg_path = Path(confer_tui.__file__).parent
with open(pkg_path / '__init__.py', 'w') as f:
    f.write('__version__ = \"0.2.0\"\n')
"

# Test update detection
python3 -c "
from confer_tui.updater import check_for_updates
import json
result = check_for_updates('https://groundstatesystems.work/api')
print(json.dumps(result, indent=2))
"
```

## What Happens After Release

Once you complete these steps:

1. **All users on v0.2.0** will see update prompt next time they run `confer`
2. **New users** downloading the package get the latest version immediately
3. **No manual distribution** needed - users auto-update

## Version Numbering Guide

Follow semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR** (1.0.0): Breaking changes, major rewrites
- **MINOR** (0.3.0): New features, backward compatible
- **PATCH** (0.2.1): Bug fixes, minor improvements

Examples:
- `0.2.0 → 0.2.1`: Bug fix release
- `0.2.0 → 0.3.0`: New features added
- `0.9.0 → 1.0.0`: Major milestone or breaking changes

## Release Notes Guidelines

Good release notes:
- Start with verb: "Added", "Fixed", "Improved", "Removed"
- Be specific: "Fixed unread count bug" not "Fixed bugs"
- User-focused: "Added auto-save" not "Implemented SaveService"
- Keep short: 1 line per item

Example:
```
✅ Good:
- Added dark mode support
- Fixed crash on startup with empty config
- Improved message loading speed by 50%

❌ Bad:
- Various improvements
- Bug fixes
- Updated dependencies
```

## Troubleshooting

### "Update not detected"
- Check version numbers match in all 3 files
- Verify UpdateController has correct version
- Clear any caching on server

### "Download fails"
- Verify file exists: `docker exec latch-app ls -lh /var/www/html/storage/app/public/`
- Check file permissions
- Test download URL directly

### "Install fails"
- Verify wheel file is in tarball: `tar -tzf confer-tui-release.tar.gz | grep .whl`
- Check build output for errors
- Verify all dependencies in setup.py

## Emergency Rollback

If a release has critical issues:

1. Update `UpdateController.php` back to previous version
2. Copy old `confer-tui-release.tar.gz` to server
3. Users will stay on/rollback to safe version
4. Fix issues, increment version, release again

## Files to Track in Git

Commit these files after each release:
- `confer_tui/__init__.py`
- `setup.py`
- `app/app/Http/Controllers/UpdateController.php`
- `RELEASE_NOTES_v*.md` (create for each release)

Don't commit:
- `confer-tui-release.tar.gz` (too large)
- `dist/` directory (build artifacts)
- `build/` directory (build artifacts)

---

**Remember:** After v0.2.0, you'll never need to manually distribute again. Just follow these steps and all users automatically get the update! 🚀
