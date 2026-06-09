#!/bin/sh
#
# Provision the marko framework as a sibling checkout so markommerce's path
# repository (`../marko/packages/*`) resolves — for self-contained Docker/CI
# builds where the developer's local `../marko` is NOT mounted.
#
# markommerce packages depend on marko via `self.version` (= dev-develop), which
# the path repository only satisfies when marko is on a branch composer reads as
# `dev-develop`. So we clone the pinned ref and FORCE a local `develop` branch at
# it: the content is pinned (reproducible) while the version resolves to
# dev-develop (compatible with `self.version`). No composer constraint changes.
#
# Idempotent + non-destructive: if a marko checkout is already present (e.g. a
# developer's mounted `../marko`), it is left untouched.
#
# Overridable via env:
#   MARKO_REF   git ref to pin (default: contents of .marko-version)
#   MARKO_DIR   target sibling dir (default: <repo>/../marko)
#   MARKO_REPO  clone URL (default: public marko-php/marko)
set -eu

REPO_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
MARKO_REF="${MARKO_REF:-$(tr -d '[:space:]' < "$REPO_ROOT/.marko-version")}"
MARKO_DIR="${MARKO_DIR:-$REPO_ROOT/../marko}"
MARKO_REPO="${MARKO_REPO:-https://github.com/marko-php/marko.git}"

if [ -d "$MARKO_DIR/.git" ]; then
    echo "marko already present at $MARKO_DIR — leaving it untouched (developer checkout)."
    exit 0
fi

echo "Cloning marko ($MARKO_REPO) pinned at '$MARKO_REF' into $MARKO_DIR ..."
git clone --quiet "$MARKO_REPO" "$MARKO_DIR"
# Force a local `develop` branch at the pinned ref so composer resolves marko as
# dev-develop (what markommerce's `self.version` marko constraints require).
git -C "$MARKO_DIR" checkout --quiet -B develop "$MARKO_REF"
echo "marko ready: $(git -C "$MARKO_DIR" describe --tags --always) (branch develop @ $MARKO_REF)"
