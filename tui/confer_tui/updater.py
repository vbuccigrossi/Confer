"""Auto-updater for Confer TUI."""

import os
import sys
import tarfile
import tempfile
import shutil
import subprocess
from pathlib import Path
from typing import Optional, Dict, Any

import requests
from packaging import version

from . import __version__


def check_for_updates(api_url: str) -> Optional[Dict[str, Any]]:
    """Check if a newer version is available.

    Args:
        api_url: Base API URL (e.g., "https://groundstatesystems.work/api")

    Returns:
        Update info dict if update available, None otherwise
    """
    try:
        # Remove /api suffix if present to get base URL
        base_url = api_url.replace('/api', '')
        version_url = f"{base_url}/api/updates/tui/version"

        response = requests.get(version_url, timeout=5)
        response.raise_for_status()

        data = response.json()
        latest = data['latest_version']
        current = __version__

        # Compare versions
        if version.parse(latest) > version.parse(current):
            return {
                'current_version': current,
                'latest_version': latest,
                'download_url': data['download_url'],
                'release_notes': data.get('release_notes', {}).get(latest, [])
            }

        return None

    except Exception as e:
        # Silently fail - don't block app startup
        print(f"Warning: Could not check for updates: {e}")
        return None


def download_and_install_update(download_url: str) -> bool:
    """Download and install the update.

    Args:
        download_url: URL to download the release tarball

    Returns:
        True if successful, False otherwise
    """
    try:
        print(f"\nDownloading update from {download_url}...")

        # Download to temp file
        with tempfile.NamedTemporaryFile(delete=False, suffix='.tar.gz') as tmp_file:
            response = requests.get(download_url, stream=True, timeout=30)
            response.raise_for_status()

            total_size = int(response.headers.get('content-length', 0))
            downloaded = 0

            for chunk in response.iter_content(chunk_size=8192):
                if chunk:
                    tmp_file.write(chunk)
                    downloaded += len(chunk)
                    if total_size > 0:
                        percent = (downloaded / total_size) * 100
                        print(f"\rProgress: {percent:.1f}%", end='', flush=True)

            print("\n")
            tmp_tarball = tmp_file.name

        # Extract to temp directory
        with tempfile.TemporaryDirectory() as tmp_dir:
            print("Extracting update...")
            with tarfile.open(tmp_tarball, 'r:gz') as tar:
                tar.extractall(tmp_dir)

            # Find the extracted directory (should be confer-tui-*)
            extracted_dirs = [d for d in Path(tmp_dir).iterdir() if d.is_dir() and d.name.startswith('confer-tui')]

            if not extracted_dirs:
                print("Error: Could not find extracted directory")
                return False

            extracted_dir = extracted_dirs[0]

            # Look for the wheel file in dist/ subdirectory
            dist_dir = extracted_dir / 'dist'
            if not dist_dir.exists():
                print("Error: Could not find dist/ directory in package")
                return False

            # Find the .whl file
            wheel_files = list(dist_dir.glob('*.whl'))
            if not wheel_files:
                print("Error: Could not find wheel file in dist/")
                return False

            wheel_file = wheel_files[0]

            # Determine if we're in a virtualenv
            in_virtualenv = hasattr(sys, 'real_prefix') or (
                hasattr(sys, 'base_prefix') and sys.base_prefix != sys.prefix
            )

            # Install using pip (--user only if not in virtualenv)
            print("Installing update...")
            install_cmd = [sys.executable, '-m', 'pip', 'install', '--upgrade', '--force-reinstall', str(wheel_file)]
            if not in_virtualenv:
                install_cmd.insert(4, '--user')  # Insert --user before --upgrade

            result = subprocess.run(
                install_cmd,
                capture_output=True,
                text=True
            )

            if result.returncode != 0:
                print(f"Error installing update: {result.stderr}")
                return False

            print("Update installed successfully!")
            return True

    except Exception as e:
        print(f"Error downloading/installing update: {e}")
        return False

    finally:
        # Clean up temp tarball
        try:
            if 'tmp_tarball' in locals():
                os.unlink(tmp_tarball)
        except:
            pass


def prompt_for_update(update_info: Dict[str, Any]) -> bool:
    """Prompt user to install update.

    Args:
        update_info: Update information dict

    Returns:
        True if user wants to update, False otherwise
    """
    print("\n" + "="*60)
    print("UPDATE AVAILABLE")
    print("="*60)
    print(f"Current version: {update_info['current_version']}")
    print(f"Latest version:  {update_info['latest_version']}")

    if update_info.get('release_notes'):
        print("\nWhat's new:")
        for note in update_info['release_notes']:
            print(f"  • {note}")

    print("\n" + "="*60)

    while True:
        response = input("Would you like to install this update? [Y/n]: ").strip().lower()

        if response in ['', 'y', 'yes']:
            return True
        elif response in ['n', 'no']:
            return False
        else:
            print("Please enter 'y' or 'n'")


def check_and_prompt_update(api_url: str) -> bool:
    """Check for updates and prompt user to install if available.

    Args:
        api_url: Base API URL

    Returns:
        True if update was installed (app should restart), False otherwise
    """
    update_info = check_for_updates(api_url)

    if update_info is None:
        return False

    if prompt_for_update(update_info):
        if download_and_install_update(update_info['download_url']):
            print("\nPlease restart the application to use the new version.")
            return True

    return False
