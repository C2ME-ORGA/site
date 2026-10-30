#!/usr/bin/env bash
set -Eeuo pipefail

TARGET="${1:-}"
case "$TARGET" in
    preproduction|production) ;;
    *)
        echo "Usage: $0 {preproduction|production}" >&2
        exit 2
        ;;
esac

exec sudo -n /usr/local/sbin/c2me-deploy "$TARGET"
