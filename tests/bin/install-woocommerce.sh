#!/bin/bash
#
# Installs WooCommerce into the test wp-content directory so the PHPUnit suite
# can cover the WooCommerce-dependent retrievers (orders, customers, products).
#
# Usage:
#   tests/bin/install-woocommerce.sh [version]
#
# Without a version the latest stable release from wordpress.org is used.
# Set WC_SOURCE_DIR to copy an already extracted WooCommerce plugin directory
# instead of downloading (useful offline).

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET_DIR="$PLUGIN_DIR/tests/tmp/wp-content/plugins"
VERSION="${1:-}"

mkdir -p "$TARGET_DIR"
rm -rf "$TARGET_DIR/woocommerce"

if [ -n "$WC_SOURCE_DIR" ]; then
    echo "Copying WooCommerce from $WC_SOURCE_DIR"
    cp -a "$WC_SOURCE_DIR" "$TARGET_DIR/woocommerce"
else
    if [ -n "$VERSION" ]; then
        URL="https://downloads.wordpress.org/plugin/woocommerce.${VERSION}.zip"
    else
        URL="https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip"
    fi
    TMP_ZIP="$(mktemp --suffix=.zip)"
    trap 'rm -f "$TMP_ZIP"' EXIT
    echo "Downloading $URL"
    curl -sSL -o "$TMP_ZIP" "$URL"
    unzip -q "$TMP_ZIP" -d "$TARGET_DIR"
fi

grep -m1 'Version:' "$TARGET_DIR/woocommerce/woocommerce.php"
echo "WooCommerce installed in $TARGET_DIR/woocommerce"
