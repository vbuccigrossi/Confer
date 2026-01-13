# Confer TUI - Distribution Guide

This document explains how to package and distribute the Confer TUI to end users.

## What's Been Created

### Installation Scripts

1. **install.sh** (Linux/macOS)
   - Automated installation script for Unix-based systems
   - Checks Python version (requires 3.8+)
   - Creates virtual environment
   - Installs dependencies
   - Creates launcher script

2. **install.bat** (Windows)
   - Automated installation script for Windows
   - Same functionality as install.sh but for Windows
   - Creates .bat launcher script

3. **build-dist.sh** (Maintainer Tool)
   - Builds distributable packages
   - Creates wheel and source distribution
   - Packages everything into a release archive

### Package Files

- **MANIFEST.in** - Defines which files to include in distributions
- **LICENSE** - MIT License for the project
- **README.md** - Updated with clear installation instructions
- **requirements.txt** - Python dependencies
- **setup.py** - Package metadata and configuration

## How to Create a Distribution Package

As a maintainer, when you want to share the TUI with users:

```bash
cd /home/ebrown/Desktop/projects/confer/tui
./build-dist.sh
```

This creates `confer-tui-release.tar.gz` which contains:
- Pre-built Python packages (wheel and source)
- Installation scripts for all platforms
- Documentation
- Quick start guide (INSTALL.txt)

## How End Users Install

### Option 1: Using the Release Archive (Recommended)

1. Download and extract `confer-tui-release.tar.gz`
2. Follow the instructions in INSTALL.txt

**Linux/macOS:**
```bash
cd confer-tui-release
chmod +x install.sh
./install.sh
./confer-tui --api-url https://groundstatesystems.work/api
```

**Windows:**
```cmd
cd confer-tui-release
install.bat
confer-tui.bat --api-url https://groundstatesystems.work/api
```

### Option 2: Direct Installation from Source

If users have access to the source repository:

```bash
cd /home/ebrown/Desktop/projects/confer/tui
./install.sh  # or install.bat on Windows
```

### Option 3: Pip Install from Wheel

For advanced users who prefer pip:

```bash
pip install confer_tui-0.1.0-py3-none-any.whl
confer --api-url https://groundstatesystems.work/api
```

## Distribution Options

### 1. GitHub Releases
Upload `confer-tui-release.tar.gz` to GitHub Releases with release notes.

### 2. Direct Sharing
Send the `confer-tui-release.tar.gz` file directly to users via email, file sharing, etc.

### 3. Web Hosting
Host the archive on a web server for users to download.

### 4. PyPI (Future)
For wider distribution, consider publishing to PyPI:
```bash
pip install twine
twine upload dist/*
```

## Testing the Distribution

Before sharing with users, always test the installation:

```bash
# Extract to a temp directory
mkdir /tmp/test-install
tar -xzf confer-tui-release.tar.gz -C /tmp/test-install
cd /tmp/test-install/confer-tui-release

# Test installation
./install.sh  # or install.bat on Windows

# Test running with wrapper script
./confer-tui --help

# Test running the app
./confer-tui --api-url https://groundstatesystems.work/api

# Or test with activated venv
source venv/bin/activate
confer --help
```

The install script automatically detects whether it's in a release package (with wheel files in dist/) or source directory, and installs accordingly.

## Updating the Version

To release a new version:

1. Update version in `setup.py`:
   ```python
   version="0.2.0",  # Increment version
   ```

2. Rebuild distribution:
   ```bash
   ./build-dist.sh
   ```

3. Test the new package

4. Distribute `confer-tui-release.tar.gz`

## Current Distribution Package

The current release package (`confer-tui-release.tar.gz`) is ready to distribute and contains:

- ✓ Installation scripts for Linux, macOS, and Windows
- ✓ Pre-built wheel package (confer_tui-0.1.0-py3-none-any.whl)
- ✓ Source distribution (confer_tui-0.1.0.tar.gz)
- ✓ Documentation (README.md, INSTALL.txt)
- ✓ Requirements list

**Size:** ~32KB compressed

## Support and Troubleshooting

Common issues users might encounter:

1. **Python not found**
   - Solution: Install Python 3.8+ from https://www.python.org/

2. **Permission denied on install.sh**
   - Solution: Run `chmod +x install.sh` first

3. **pip install fails**
   - Solution: Upgrade pip with `pip install --upgrade pip`

4. **Module not found errors**
   - Solution: Make sure virtual environment is activated

## Files Summary

```
/home/ebrown/Desktop/projects/confer/tui/
├── confer_tui/              # Source code
├── setup.py                 # Package configuration
├── requirements.txt         # Dependencies
├── README.md               # User documentation
├── LICENSE                 # MIT License
├── MANIFEST.in            # Distribution file list
├── install.sh             # Linux/Mac installer
├── install.bat            # Windows installer
├── build-dist.sh          # Build script (maintainer)
├── run_tui.sh            # Quick launch script
└── confer-tui-release.tar.gz  # Ready-to-distribute package
```
