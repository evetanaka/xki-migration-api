#!/bin/bash
set -e

rm -rf var/cache/*
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "DEBUG ROUTES:" >&2
php bin/console debug:router --env=prod 2>&1 >&2

php bin/console doctrine:migrations:migrate --no-interaction 2>&1 || true

# Auto-import NFT CSV if table is empty
NFT_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) as c FROM nft_asset" --env=prod 2>/dev/null | grep -oP '\d+' | head -1 || echo "0")
if [ "$NFT_COUNT" = "0" ] && [ -f data/nft-metadata.csv ]; then
  echo "NFT table empty, importing CSV..."
  php bin/console app:import-nft-csv data/nft-metadata.csv --clear --env=prod 2>&1
  echo "NFT import complete"
else
  echo "NFT table has $NFT_COUNT rows, skipping import"
fi

exec apache2-foreground
