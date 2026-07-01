#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Content Manager API Plugin — Deploy Script
# Kullanım:
#   ./deploy.sh alpindede    → alpindede eklentisini kopyala
#   ./deploy.sh estonya      → estonya eklentisini kopyala
#   ./deploy.sh bahriinfo    → bahri.info eklentisini kopyala
#   ./deploy.sh bahricanli   → bahricanli.tr eklentisini kopyala
#   ./deploy.sh all          → tüm sitelere kopyala
# ─────────────────────────────────────────────────────────────────────────────

set -e

PLUGIN_FILE="/root/wordpress/sites/content-manager/wordpress-plugin/content-manager-api.php"
PLUGIN_NAME="content-manager-api"

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
    bahriinfo)
        deploy_to "/root/wordpress/sites/bahriinfo/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    bahricanli)
        deploy_to "/root/wordpress/sites/bahricanli/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    avustralya)
        deploy_to "/root/wordpress/sites/avustralya/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    ubuntu)
        deploy_to "/root/wordpress/sites/ubuntu/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    all)
        deploy_to "/root/wordpress/sites/alpindede/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/estonya/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/bahriinfo/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/bahricanli/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/avustralya/wp-content/plugins/${PLUGIN_NAME}"
        deploy_to "/root/wordpress/sites/ubuntu/wp-content/plugins/${PLUGIN_NAME}"
        ;;
    *)
        echo "Kullanım: $0 [alpindede|estonya|bahriinfo|bahricanli|all]"
        exit 1
        ;;
esac

echo ""
echo "✅ Deploy tamamlandı."
echo "   WordPress admin → Eklentiler → 'Content Manager API' aktif edin"
echo "   Ayarlar → Content Manager API → Token girin"
