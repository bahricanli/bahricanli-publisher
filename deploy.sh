#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# AlpinDede Content API Plugin — Deploy Script
# Kullanım:
#   ./deploy.sh alpindede      → alpindede.com'a deploy et
#   ./deploy.sh estonya        → 192.168.0.82'ye deploy et
#   ./deploy.sh all            → her ikisine deploy et
# ─────────────────────────────────────────────────────────────────────────────

set -e

PLUGIN_FILE="$(dirname "$0")/alpindede-content-api.php"
PLUGIN_NAME="alpindede-content-api"
REMOTE_PATH="/var/www/html/wp-content/plugins/${PLUGIN_NAME}/"

# ── Site tanımları ────────────────────────────────────────────────────────────
declare -A SITES
SITES[alpindede_host]="alpindede.com"
SITES[alpindede_user]="root"                  # SSH kullanıcısı
SITES[alpindede_path]="/var/www/html/wp-content/plugins/${PLUGIN_NAME}/"

SITES[estonya_host]="192.168.0.82"
SITES[estonya_user]="root"
SITES[estonya_path]="/var/www/html/wp-content/plugins/${PLUGIN_NAME}/"

# ─────────────────────────────────────────────────────────────────────────────

deploy_to() {
    local SITE="$1"
    local HOST="${SITES[${SITE}_host]}"
    local USER="${SITES[${SITE}_user]}"
    local PATH_REMOTE="${SITES[${SITE}_path]}"

    echo ""
    echo "▶ ${SITE} → ${USER}@${HOST}:${PATH_REMOTE}"

    # Uzak klasörü oluştur
    ssh "${USER}@${HOST}" "mkdir -p ${PATH_REMOTE}"

    # Plugin dosyasını kopyala
    scp "${PLUGIN_FILE}" "${USER}@${HOST}:${PATH_REMOTE}${PLUGIN_NAME}.php"

    # wp-cli ile aktif et (varsa)
    ssh "${USER}@${HOST}" "
        if command -v wp &>/dev/null; then
            WP_DIR=\$(find /var/www -name 'wp-config.php' 2>/dev/null | head -1 | xargs dirname 2>/dev/null || echo '/var/www/html')
            wp plugin activate ${PLUGIN_NAME} --path=\${WP_DIR} --allow-root 2>/dev/null && echo '  ✓ Plugin aktif edildi (wp-cli)' || echo '  ⚠ wp-cli mevcut ama aktif etme başarısız — WP admin panelinden aktif edin'
        else
            echo '  ⚠ wp-cli bulunamadı — WP admin panelinden manuel aktif edin'
        fi
    "

    echo "  ✓ ${SITE} deploy tamamlandı"
}

TARGET="${1:-all}"

case "$TARGET" in
    alpindede)
        deploy_to alpindede
        ;;
    estonya)
        deploy_to estonya
        ;;
    all)
        deploy_to alpindede
        deploy_to estonya
        ;;
    *)
        echo "Kullanım: $0 [alpindede|estonya|all]"
        exit 1
        ;;
esac

echo ""
echo "✅ Deploy tamamlandı."
echo ""
echo "Sonraki adımlar:"
echo "  1. WordPress admin → Eklentiler → 'AlpinDede Content API' aktif edin"
echo "  2. Ayarlar → AlpinDede API → Token girin (Laravel .env'deki WORDPRESS_PLUGIN_TOKEN ile aynı)"
echo "  3. Laravel'de: php artisan db:seed --class=SitesSeeder (token güncellemek için)"
