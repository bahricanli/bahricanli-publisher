#!/usr/bin/env bash
# BahriCanli Publisher — deploy: alpindede, avustralya, estonya, italya
set -e

SITES=("alpindede" "avustralya" "estonya" "italya")

for site in "${SITES[@]}"; do
    DIR="/root/wordpress/sites/${site}/wp-content/plugins/bahricanli-publisher"
    echo "▶ ${site} güncelleniyor..."
    ssh bmericc@192.168.0.82 "sudo git -C ${DIR} pull origin main"
    echo "  ✓ Tamamlandı"
done

echo ""
echo "✅ Deploy tamamlandı."
