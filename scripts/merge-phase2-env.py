#!/usr/bin/env python3
"""Merge Phase 2 env vars into production .env without overwriting protected keys."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

PROTECTED_EXACT = {
    "APP_KEY",
    "APP_URL",
    "APP_ENV",
    "APP_DEBUG",
    "INGEST_API_TOKEN",
    "TELEGRAM_WEBHOOK_SECRET",
    "TELEGRAM_BOT_TOKEN",
    "TELEGRAM_CHAT_IDS",
    "TELEGRAM_CHAT_ID",
}

PROTECTED_PREFIXES = (
    "DB_",
    "REDIS_",
    "CACHE_",
    "SESSION_",
    "QUEUE_",
    "MAIL_",
)


def parse_env(text: str) -> dict[str, str]:
    values: dict[str, str] = {}
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            continue
        key, _, value = stripped.partition("=")
        values[key.strip()] = value.strip()
    return values


def is_protected(key: str) -> bool:
    if key in PROTECTED_EXACT:
        return True
    return any(key.startswith(prefix) for prefix in PROTECTED_PREFIXES)


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: merge-phase2-env.py <production.env> <output.append>", file=sys.stderr)
        return 1

    prod_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    local_text = (ROOT / ".env").read_text()
    example_text = (ROOT / ".env.example").read_text()
    prod_text = prod_path.read_text()

    local = parse_env(local_text)
    example = parse_env(example_text)
    prod = parse_env(prod_text)

    # Prefer local values for integrations; fall back to .env.example defaults.
    candidates = {**example, **local}

    additions: list[str] = []
    skipped_protected = 0
    skipped_existing = 0

    for key in sorted(candidates):
        if key in prod and prod[key] != "":
            skipped_existing += 1
            continue
        if is_protected(key) and key in prod:
            skipped_protected += 1
            continue
        if candidates[key] == "":
            continue
        value = candidates[key]
        # .env files use single backslashes; .env.example escapes them for documentation.
        if key == "DEDUP_FUZZY_STRATEGY":
            value = value.replace("\\\\", "\\")
        additions.append(f"{key}={value}")

    sections: list[str] = [
        "# --- Phase 2 merged configuration (auto-generated) ---",
        *additions,
        "",
    ]

    output_path.write_text("\n".join(sections))
    print(f"additions={len(additions)} skipped_existing={skipped_existing} skipped_protected={skipped_protected}")
    for line in additions:
        key = line.split("=", 1)[0]
        print(f"ADD {key}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
