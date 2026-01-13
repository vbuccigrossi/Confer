#!/bin/bash
# Build distributable packages for Confer TUI

set -e

echo "========================================="
echo "   Confer TUI - Build Distribution"
echo "========================================="
echo ""

# Clean previous builds
echo "Cleaning previous builds..."
rm -rf build dist *.egg-info
echo "✓ Cleaned"
echo ""

# Check if venv exists and activate it
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
fi

source venv/bin/activate

# Install/upgrade build tools
echo "Installing build tools..."
pip install --upgrade pip build wheel setuptools > /dev/null 2>&1
echo "✓ Build tools installed"
echo ""

# Build source distribution and wheel
echo "Building distributions..."
python -m build
echo "✓ Distributions built"
echo ""

# List built files
echo "========================================="
echo "   Built Packages:"
echo "========================================="
ls -lh dist/
echo ""

# Create a release package
echo "Creating release archive..."
RELEASE_DIR="confer-tui-release"
rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

# Copy essential files
cp -r dist "$RELEASE_DIR/"
cp README.md "$RELEASE_DIR/"
cp requirements.txt "$RELEASE_DIR/"
cp install.sh "$RELEASE_DIR/"
cp install.bat "$RELEASE_DIR/"
chmod +x "$RELEASE_DIR/install.sh"

# Create quick install instructions
cat > "$RELEASE_DIR/INSTALL.txt" << 'EOF'
Confer TUI - Installation Instructions
======================================

For Linux/macOS:
1. Open a terminal in this directory
2. Run: chmod +x install.sh && ./install.sh
3. Run: ./confer-tui --api-url https://groundstatesystems.work/api

For Windows:
1. Open Command Prompt in this directory
2. Run: install.bat
3. Run: confer-tui.bat --api-url https://groundstatesystems.work/api

Alternative Installation (if you have pip):
1. pip install dist/confer_tui-*.whl
2. confer --api-url https://groundstatesystems.work/api

For help:
confer --help
EOF

# Create archive
ARCHIVE_NAME="confer-tui-release.tar.gz"
tar -czf "$ARCHIVE_NAME" "$RELEASE_DIR"

echo "========================================="
echo "   Release Package Created!"
echo "========================================="
echo ""
echo "Archive: $ARCHIVE_NAME"
echo "Size: $(du -h $ARCHIVE_NAME | cut -f1)"
echo ""
echo "You can now distribute this archive to users."
echo "They simply extract it and run install.sh (Linux/Mac) or install.bat (Windows)"
echo ""
