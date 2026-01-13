"""
Latch Bot SDK Setup

Installation:
    pip install .

Development installation:
    pip install -e ".[dev]"
"""

from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as f:
    long_description = f.read()

setup(
    name="latch-bot-sdk",
    version="1.0.0",
    author="Latch Team",
    author_email="developers@latch.example.com",
    description="Official Python SDK for building Latch bots",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/your-org/latch-bot-sdk-python",
    packages=find_packages(exclude=["tests", "tests.*", "examples"]),
    classifiers=[
        "Development Status :: 4 - Beta",
        "Intended Audience :: Developers",
        "License :: OSI Approved :: MIT License",
        "Operating System :: OS Independent",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
        "Programming Language :: Python :: 3.12",
        "Topic :: Communications :: Chat",
        "Topic :: Software Development :: Libraries :: Python Modules",
    ],
    python_requires=">=3.8",
    install_requires=[
        "requests>=2.25.0",
    ],
    extras_require={
        "dev": [
            "pytest>=7.0.0",
            "pytest-cov>=4.0.0",
            "black>=23.0.0",
            "mypy>=1.0.0",
            "types-requests>=2.25.0",
        ],
        "flask": [
            "flask>=2.0.0",
        ],
        "fastapi": [
            "fastapi>=0.100.0",
            "uvicorn>=0.20.0",
        ],
    },
    entry_points={
        "console_scripts": [
            "latch-bot=latch_bot.cli:main",
        ],
    },
    project_urls={
        "Bug Reports": "https://github.com/your-org/latch-bot-sdk-python/issues",
        "Documentation": "https://docs.latch.example.com/bot-sdk/python",
        "Source": "https://github.com/your-org/latch-bot-sdk-python",
    },
)
