#!/usr/bin/env bash
set -euo pipefail

REPO="/home/james/projects/gpb3/gpb3"
LIVE="/var/www/guidepaw"

cd "$REPO"

echo "== PHP syntax: repo =="
for f in *.php; do php -l "$f" >/dev/null; done
find includes -name "*.php" -type f -print0 | xargs -0 -r -n1 php -l >/dev/null

echo "== Syncing live files =="
sudo cp *.php "$LIVE/"
sudo cp app.js sw.js manifest.json offline.html styles.css "$LIVE/" 2>/dev/null || true
sudo cp -r includes "$LIVE/"
sudo cp -r assets "$LIVE/"

echo "== PHP syntax: live =="
cd "$LIVE"
for f in *.php; do php -l "$f" >/dev/null; done
find includes -name "*.php" -type f -print0 | xargs -0 -r -n1 php -l >/dev/null

echo "== Brand header check =="
missing=0
for f in *.php; do
  if grep -qi "<body" "$f"; then
    if ! grep -q "brand_header.php" "$f" || ! grep -q "guidepawBrandHeader" "$f"; then
      echo "MISSING BRAND HEADER: $f"
      missing=1
    fi
  fi
done

echo "== Missing linked PHP files =="
bad_links=0
while read -r target; do
  [ -z "$target" ] && continue
  if [ ! -f "$target" ]; then
    echo "MISSING LINK TARGET: $target"
    bad_links=1
  fi
done < <(
  grep -RhoE 'href="[^"]+\.php[^"]*"' . \
    | sed -E 's/href="//; s/[?#].*//; s/"//' \
    | sort -u
)

echo "== Key HTTP checks =="
for url in \
  index.php dogs.php training_program.php training_session_log.php \
  training_goal_intake.php candidate_assessment.php habit_repair.php \
  admin.php admin_feature_roadmap.php settings.php profile.php
do
  code=$(curl -k -s -o /tmp/gp_deploy_check.out -w "%{http_code}" "https://10.147.18.184/$url?deploycheck=1")
  echo "$code  $url"
done

if [ "$missing" -ne 0 ] || [ "$bad_links" -ne 0 ]; then
  echo "Deploy completed with warnings."
  exit 1
fi

echo "Deploy and smoke checks complete."

# Ensure writable upload directories survive deploys
sudo mkdir -p /var/www/guidepaw/uploads/feedback
sudo chown -R www-data:www-data /var/www/guidepaw/uploads
sudo find /var/www/guidepaw/uploads -type d -exec chmod 775 {} \;
sudo find /var/www/guidepaw/uploads -type f -exec chmod 664 {} \;

# Ensure writable upload directories survive deploys
sudo mkdir -p /var/www/guidepaw/uploads/feedback
sudo chown -R www-data:www-data /var/www/guidepaw/uploads
sudo find /var/www/guidepaw/uploads -type d -exec chmod 775 {} \;
sudo find /var/www/guidepaw/uploads -type f -exec chmod 664 {} \;

# Ensure writable upload directories survive deploys
sudo mkdir -p /var/www/guidepaw/uploads/feedback
sudo chown -R www-data:www-data /var/www/guidepaw/uploads
sudo find /var/www/guidepaw/uploads -type d -exec chmod 775 {} \;
sudo find /var/www/guidepaw/uploads -type f -exec chmod 664 {} \;
