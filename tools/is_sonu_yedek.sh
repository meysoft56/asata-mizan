#!/usr/bin/env bash
# ASATA MIZAN — Anlık iş sonu yedeği
# Kullanım: tools/is_sonu_yedek.sh [etiket]
# Kaynağı (git izlenen dosyalar) zaman damgalı ZIP yapıp Google Drive'daki
# ASATA_MIZAN klasörüne yükler; MD5'i YEDEK_MANIFEST.txt'ye işler.
# Gereksinim: gws CLI (Google Workspace) yapılandırılmış ve oturumlu olmalı.

set -euo pipefail

ETIKET="${1:-}"
KLASOR_ID="$(cat "$(dirname "$0")/drive_klasor_id.txt")"
STAMP="$(date +%Y%m%d-%H%M%S)"
AD="ASATA_MIZAN_Kaynak_AUTO_${STAMP}${ETIKET:+-$ETIKET}.zip"
# Not: gws CLI --upload yolunu geçerli çalışma dizini İÇİNDE ister; yedek
# dizini bu yüzden çalışma dizini altında, kaynak ağacın dışında tutulur.
CIKTI_DIR="${ASATA_YEDEK_DIR:-$PWD/asata_mizan_yedekler}"
mkdir -p "$CIKTI_DIR"
ZIP="$CIKTI_DIR/$AD"

GIT_DIR=$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)
git -C "$GIT_DIR" archive --format=zip --prefix="asata-mizan/" --output="$ZIP" HEAD
MD5=$(md5sum "$ZIP" | cut -d' ' -f1)
BOYUT=$(stat -c%s "$ZIP")

echo "ZIP: $ZIP ($BOYUT bayt, MD5 $MD5)"

SONUC=$(gws drive files create \
  --params "{\"fields\": \"id,name,parents\"}" \
  --json "{\"name\": \"$AD\", \"parents\": [\"$KLASOR_ID\"]}" \
  --upload "$ZIP")
FILE_ID=$(echo "$SONUC" | python3 -c "import json,sys; print(json.load(sys.stdin)['id'])")

MANIFEST="$GIT_DIR/YEDEK_MANIFEST.txt"
printf '%s\t%s\t%s bayt\tMD5=%s\tDrive=%s\t%s\n' \
  "$STAMP" "${ETIKET:-is-sonu}" "$BOYUT" "$MD5" "$FILE_ID" "$AD" >> "$MANIFEST"

echo "Drive yüklendi: fileId=$FILE_ID"
echo "Manifest: $MANIFEST"
