@echo off
REM Confer TUI - Easy Installation Script for Windows

echo =========================================
echo    Confer TUI Installation Script
echo =========================================
echo.

REM Detect if we're in a release package or source directory
if exist "dist\confer_tui-*.whl" (
    set INSTALL_MODE=release
    echo Installing from release package
) else (
    set INSTALL_MODE=source
    echo Installing from source
)
echo.

REM Check Python version
echo Checking Python version...
python --version >nul 2>&1
if errorlevel 1 (
    echo Error: Python is not installed or not in PATH!
    echo Please install Python 3.8 or later from https://www.python.org/
    pause
    exit /b 1
)

for /f "tokens=2" %%i in ('python --version 2^>^&1') do set PYTHON_VERSION=%%i
echo Found Python %PYTHON_VERSION%
echo.

REM Create virtual environment
echo Creating virtual environment...
if exist venv\ (
    echo Virtual environment already exists, skipping creation
) else (
    python -m venv venv
    echo Virtual environment created
)
echo.

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat
echo Virtual environment activated
echo.

REM Upgrade pip
echo Upgrading pip...
python -m pip install --upgrade pip >nul 2>&1
echo pip upgraded
echo.

REM Install the package
echo Installing Confer TUI...
if "%INSTALL_MODE%"=="release" (
    REM Install from wheel in release package
    for %%f in (dist\confer_tui-*.whl) do (
        pip install "%%f" >nul 2>&1
        goto :installed
    )
    :installed
) else (
    REM Install from source in development mode
    pip install -e . >nul 2>&1
)
echo Confer TUI installed
echo.

REM Create wrapper script
echo Creating launch script...
(
echo @echo off
echo REM Confer TUI launcher
echo call "%%~dp0venv\Scripts\activate.bat"
echo confer %%*
) > confer-tui.bat

echo Launch script created
echo.

echo =========================================
echo    Installation Complete!
echo =========================================
echo.
echo To run Confer TUI:
echo.
echo   confer-tui.bat --api-url https://groundstatesystems.work/api
echo.
echo Or activate the virtual environment first:
echo.
echo   venv\Scripts\activate.bat
echo   confer --api-url https://groundstatesystems.work/api
echo.
echo For help:
echo   confer --help
echo.
pause
