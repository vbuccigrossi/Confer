#!/bin/bash
# Confer TUI - Easy Installation Script
# Works on Linux and macOS

set -e  # Exit on error

echo "========================================="
echo "   Confer TUI Installation Script"
echo "========================================="
echo ""

# Detect if we're in a release package or source directory
if [ -d "dist" ] && [ -f "dist/confer_tui-"*.whl ]; then
    INSTALL_MODE="release"
    echo "📦 Installing from release package"
else
    INSTALL_MODE="source"
    echo "📦 Installing from source"
fi
echo ""

# Check Python version
echo "Checking Python version..."
if ! command -v python3 &> /dev/null; then
    echo "❌ Error: Python 3 is not installed!"
    echo "Please install Python 3.8 or later from https://www.python.org/"
    exit 1
fi

PYTHON_VERSION=$(python3 --version | cut -d' ' -f2)
PYTHON_MAJOR=$(echo $PYTHON_VERSION | cut -d'.' -f1)
PYTHON_MINOR=$(echo $PYTHON_VERSION | cut -d'.' -f2)

if [ "$PYTHON_MAJOR" -lt 3 ] || { [ "$PYTHON_MAJOR" -eq 3 ] && [ "$PYTHON_MINOR" -lt 8 ]; }; then
    echo "❌ Error: Python 3.8 or later is required!"
    echo "You have Python $PYTHON_VERSION"
    echo "Please upgrade from https://www.python.org/"
    exit 1
fi

echo "✓ Python $PYTHON_VERSION found"
echo ""

# Create virtual environment
echo "Creating virtual environment..."
if [ -d "venv" ]; then
    echo "⚠ Virtual environment already exists, skipping creation"
else
    python3 -m venv venv
    echo "✓ Virtual environment created"
fi
echo ""

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate
echo "✓ Virtual environment activated"
echo ""

# Upgrade pip
echo "Upgrading pip..."
pip install --upgrade pip > /dev/null 2>&1
echo "✓ pip upgraded"
echo ""

# Install the package
echo "Installing Confer TUI..."
if [ "$INSTALL_MODE" = "release" ]; then
    # Install from wheel in release package
    WHEEL_FILE=$(ls dist/confer_tui-*.whl | head -n 1)
    pip install "$WHEEL_FILE" > /dev/null 2>&1
else
    # Install from source in development mode
    pip install -e . > /dev/null 2>&1
fi
echo "✓ Confer TUI installed"
echo ""

# Create wrapper script
echo "Creating launch script..."
cat > confer-tui << 'EOF'
#!/bin/bash
# Confer TUI launcher
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/venv/bin/activate"
confer "$@"
EOF

chmod +x confer-tui
echo "✓ Launch script created"
echo ""

echo "========================================="
echo "   Installation Complete!"
echo "========================================="
echo ""
echo "To run Confer TUI:"
echo ""
echo "  ./confer-tui --api-url https://groundstatesystems.work/api"
echo ""
echo "Or activate the virtual environment first:"
echo ""
echo "  source venv/bin/activate"
echo "  confer --api-url https://groundstatesystems.work/api"
echo ""
echo "For help:"
echo "  confer --help"
echo ""
