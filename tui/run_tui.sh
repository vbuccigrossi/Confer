#!/bin/bash
# Quick start script for Confer TUI

cd "$(dirname "$0")"

# Check if venv exists
if [ ! -d "venv" ]; then
    echo "❌ Virtual environment not found!"
    echo "Run: python3 -m venv venv && source venv/bin/activate && pip install -e ."
    exit 1
fi

# Activate venv
source venv/bin/activate

# Check if confer is installed
if ! command -v confer &> /dev/null; then
    echo "❌ Confer not installed!"
    echo "Run: pip install -e ."
    exit 1
fi

# Run TUI
echo "🚀 Starting Confer TUI..."
echo "API: https://groundstatesystems.work/api"
echo ""

confer --api-url https://groundstatesystems.work/api
