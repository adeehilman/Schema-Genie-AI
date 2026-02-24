#!/usr/bin/env python3
"""
Build script for Schema Genie AI plugin.
Reads the version from schema-genie-ai.php, deletes old zip files
in the parent directory, and creates a fresh versioned zip there.
"""

import os
import re
import glob
import zipfile
import sys

# Fix Windows console encoding
if sys.platform == "win32":
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PARENT_DIR = os.path.dirname(SCRIPT_DIR)
FOLDER_NAME = os.path.basename(SCRIPT_DIR)          # "Schema-Genie-AI"
MAIN_FILE = os.path.join(SCRIPT_DIR, "schema-genie-ai.php")

# Folders/files to exclude from the zip
EXCLUDES = {
    ".git",
    ".gitignore",
    "build.py",
    "__pycache__",
    ".DS_Store",
    "Thumbs.db",
    "node_modules",
    ".vscode",
    ".idea",
}


def get_version() -> str:
    """Read version from the main plugin PHP file."""
    with open(MAIN_FILE, "r", encoding="utf-8") as f:
        content = f.read()
    match = re.search(
        r"define\(\s*'SCHEMA_GENIE_AI_VERSION'\s*,\s*'([^']+)'\s*\)", content
    )
    if not match:
        print("[ERROR] Could not find SCHEMA_GENIE_AI_VERSION in schema-genie-ai.php")
        sys.exit(1)
    return match.group(1)


def delete_old_zips():
    """Remove any existing Schema-Genie-AI*.zip files in the parent directory."""
    pattern = os.path.join(PARENT_DIR, f"{FOLDER_NAME}*.zip")
    old_zips = glob.glob(pattern)
    if old_zips:
        for z in old_zips:
            os.remove(z)
            print(f"  [DEL] {os.path.basename(z)}")
    else:
        print("  No old zip files found.")


def should_include(path: str) -> bool:
    """Check if a file/folder should be included in the zip."""
    parts = path.replace("\\", "/").split("/")
    for part in parts:
        if part in EXCLUDES:
            return False
    return True


def create_zip(version: str):
    """Create a new versioned zip file in the parent directory."""
    zip_name = f"{FOLDER_NAME} {version}.zip"
    zip_path = os.path.join(PARENT_DIR, zip_name)
    file_count = 0

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(SCRIPT_DIR):
            # Filter out excluded directories in-place
            dirs[:] = [
                d
                for d in dirs
                if should_include(os.path.relpath(os.path.join(root, d), SCRIPT_DIR))
            ]

            for file in files:
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, SCRIPT_DIR)

                if not should_include(rel_path):
                    continue

                # Inside zip: Schema-Genie-AI/file.php
                arc_name = os.path.join(FOLDER_NAME, rel_path)
                zf.write(full_path, arc_name)
                file_count += 1

    size_kb = os.path.getsize(zip_path) / 1024
    return zip_name, file_count, size_kb


def main():
    print("=" * 50)
    print("  Schema Genie AI - Build Script")
    print("=" * 50)

    version = get_version()
    print(f"\n  Plugin version: v{version}")

    print("\n  Cleaning old builds...")
    delete_old_zips()

    print("\n  Creating zip...")
    zip_name, file_count, size_kb = create_zip(version)

    print(f"\n  [OK] Created: {zip_name}")
    print(f"       Location: {PARENT_DIR}")
    print(f"       Files:   {file_count}")
    print(f"       Size:    {size_kb:.1f} KB")
    print("=" * 50)


if __name__ == "__main__":
    main()
