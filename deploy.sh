#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Content Manager API Plugin — Deploy Script
# Kullanım:
#   ./deploy.sh alpindede   → alpindede eklentisini kopyala
#   ./deploy.sh estonya     → estonya eklentisini kopyala
#   ./deploy.sh all         → her ikisine kopyala
# ─────────────────────────────────────────────────────────────────────────────

set -e

PLUGIN_FILE="$(dirname "$0")/content-manager-api.php"
PLUGIN_NAME="content-manager-api"

PATHS=(
    "/root/wordpress/sites/alpindede/wp-content/plugins/${PLUGIN_NAME}"
    "/root/wordpress/sites/estonya/wp-content/plugins/${PLUGIN_NAME}"
)

deploy_to() {
    local DEST="$1"
    echo "▶ Kopyalanıyor: ${DEST}/"
    mkdir -p "${DEST}"
    cp "${PLUGIN_FILE}" "${DEST}/${PLUGIN_NAME}.php"
    echo "  ✓ Tamamlandı"
}

case "${1:-all}" in
    alpindede)
        deploy_to "/root/wordpress/sites/alpindede/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    estonya)
        deploy_to "/root/wordpress/sites/estonya/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    all)
        deploy_to "/root/wordpress/sites/alpindede/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/estonya/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    *)
        echo "Kullanım: $0 [alpindede|estonya|all]"
        exit 1
        ;;
esac

echo ""
echo "✅ Deploy tamamlandı."
echo "   WordPress admin → Eklentiler → 'Content Manager API' aktif edin"
echo "   Ayarlar → Content Manager API → Token girin"
