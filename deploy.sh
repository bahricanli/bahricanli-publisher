#!/usr/bin/env bash
# BahriCanli Publisher — deploy: tüm sitelere SCP ile kopyala
set -e

SITES=("alpindede" "avustralya" "estonya" "italya" "bahriinfo" "bahricanli" "ubuntu" "yunanistan" "codon")
FILES=("bahricanli-publisher.php" "readme.txt")

# Dosyaları /tmp'ye yükle
scp "${FILES[@]}" bmericc@192.168.0.82:/tmp/

for site in "${SITES[@]}"; do
    DIR="/root/wordpress/sites/${site}/wp-content/plugins/bahricanli-publisher"
    echo "▶ ${site} güncelleniyor..."
    for file in "${FILES[@]}"; do
        ssh bmericc@192.168.0.82 "sudo cp /tmp/${file} ${DIR}/${file} && sudo chown www-data:www-data ${DIR}/${file}"
    done
    echo "  ✓ Tamamlandı"
done

echo ""
echo "✅ Deploy tamamlandı."
