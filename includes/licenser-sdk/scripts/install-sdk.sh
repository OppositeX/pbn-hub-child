#!/usr/bin/env bash
# install-sdk.sh
# Copy the Licenser SDK into a target plugin and replace the namespace placeholder.
#
# Usage: ./install-sdk.sh <target-dir> <namespace>
#   <target-dir>  Path where the SDK should be installed (e.g. ../my-plugin/includes/licenser-sdk)
#   <namespace>   PHP namespace to replace __LICENSER_NAMESPACE__ with (e.g. "Gloo\\CanvasStudio\\Licenser"
#                 — but pass it as "Gloo\\\\CanvasStudio\\\\Licenser" in shell to escape backslashes)
#
# Example:
#   ./install-sdk.sh ../canvas-studio/includes/licenser-sdk 'Gloo\\CanvasStudio'
#
# NOTE: The namespace passed is appended with `\Licenser` automatically because the
# SDK files declare `namespace __LICENSER_NAMESPACE__\Licenser`. So pass the parent
# only.

set -euo pipefail

TARGET_DIR="${1:-}"
NAMESPACE="${2:-}"

if [[ -z "$TARGET_DIR" || -z "$NAMESPACE" ]]; then
	echo "Usage: $0 <target-dir> <namespace>"
	echo "  e.g.  $0 ../canvas-studio/includes/licenser-sdk 'Gloo\\\\CanvasStudio'"
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SDK_DIR="$SCRIPT_DIR/../sdk"

if [[ ! -d "$SDK_DIR" ]]; then
	echo "SDK directory not found: $SDK_DIR" >&2
	exit 1
fi

mkdir -p "$TARGET_DIR"
cp -R "$SDK_DIR"/. "$TARGET_DIR"/

# Replace placeholder. We use perl for portable in-place edits across macOS/Linux.
find "$TARGET_DIR" -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do
	perl -pi -e "s/__LICENSER_NAMESPACE__/${NAMESPACE//\\/\\\\}/g" "$file"
done

echo "Licenser SDK installed in: $TARGET_DIR"
echo "Namespace prefix:          $NAMESPACE"
