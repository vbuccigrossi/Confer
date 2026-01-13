"""Setup script for Confer TUI."""

from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="confer-tui",
    version="0.2.0",
    author="Confer Team",
    description="Terminal User Interface for Confer chat platform",
    long_description=long_description,
    long_description_content_type="text/markdown",
    packages=find_packages(),
    python_requires=">=3.8",
    install_requires=[
        "textual==0.47.1",
        "httpx==0.25.2",
        "python-dotenv==1.0.0",
        "rich==13.7.0",
        "websockets==12.0",
        "requests>=2.31.0",
        "packaging>=23.0",
    ],
    entry_points={
        "console_scripts": [
            "confer=confer_tui.main:main",
        ],
    },
    classifiers=[
        "Development Status :: 3 - Alpha",
        "Intended Audience :: End Users/Desktop",
        "Topic :: Communications :: Chat",
        "License :: OSI Approved :: MIT License",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
    ],
)
