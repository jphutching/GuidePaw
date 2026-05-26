#!/usr/bin/env bash
set -euo pipefail

REPO="/home/james/projects/guidepaw"
LIVE="/var/www/guidepaw"

cd "$REPO"

echo "== PHP syntax: repo =="
for f in *.php; do php -l "$f" >/dev/null; done
find includes -name "*.php" -type f -print0 | xargs -0 -r -n1 php -l >/dev/null

echo "== Syncing live files =="
sudo cp *.php "$LIVE/"
sudo cp robots.txt "$LIVE/" 2>/dev/null || true
sudo cp -r api "$LIVE/"
sudo cp app.js sw.js manifest.json offline.html styles.css "$LIVE/" 2>/dev/null || true
sudo cp -r includes "$LIVE/"
sudo cp -r assets "$LIVE/"
sudo mkdir -p "$LIVE/downloads"
sudo cp downloads/*.apk "$LIVE/downloads/" 2>/dev/null || true
sudo rm -rf "$LIVE/android"
sudo rm -f "$LIVE/bridge_apk.php" "$LIVE/wearable_bridge.php" "$LIVE/api/wearables.php" "$LIVE/bridge/GuidePaw-Bridge-debug.apk" "$LIVE/bridge/GuidePaw-Companion-debug.apk"
sudo rmdir "$LIVE/bridge" 2>/dev/null || true

echo "== Fixing file ownership =="
sudo chown -R www-data:www-data "$LIVE"/*.php "$LIVE"/api "$LIVE"/includes "$LIVE"/assets 2>/dev/null || true

echo "== PHP syntax: live =="
cd "$LIVE"
for f in *.php; do php -l "$f" >/dev/null; done
find api -name "*.php" -type f -print0 | xargs -0 -r -n1 php -l >/dev/null
find includes -name "*.php" -type f -print0 | xargs -0 -r -n1 php -l >/dev/null

echo "== Brand header check =="
missing=0
for f in *.php; do
  if grep -qi "<body" "$f"; then
    case "$f" in
      login.php|public_dog_profile.php|report_found_dog.php)
        continue
        ;;
    esac
    if ! grep -q "brand_header.php" "$f" || ! grep -q "guidepawBrandHeader" "$f"; then
      echo "MISSING BRAND HEADER: $f"
      missing=1
    fi
  fi
done

for f in api/*.php; do
  [ -f "$f" ] || continue
  if grep -qi "<body" "$f"; then
    echo "MISSING BRAND HEADER: $f"
    missing=1
  fi
done

echo "== Missing linked PHP files =="
bad_links=0
while IFS= read -r target; do
  [ -z "$target" ] && continue
  case "$target" in
    http://*|https://*|mailto:*)
      continue
      ;;
    /*)
      target="${target#/}"
      ;;
  esac
  if [ ! -f "$target" ]; then
    echo "MISSING LINK TARGET: $target"
    bad_links=1
  fi
done < <(
  find . \
    \( -path "./.git" -o -path "./.git/*" -o -path "./.env*" -o -path "./node_modules" -o -path "./node_modules/*" -o -path "./vendor" -o -path "./vendor/*" -o -path "./scripts" -o -path "./scripts/*" -o -path "./tests" -o -path "./tests/*" -o -path "./docs" -o -path "./docs/*" \) -prune -o \
    -type f \( -name "*.php" -o -name "*.html" -o -name "*.htm" \) \
    -print0 \
    | xargs -0 -r grep -RhoE 'href="[^"]+\.php[^"]*"' \
    | sed -E 's/href="//; s/[?#].*//; s/"//' \
    | grep -v '^[[:space:]]*$' \
    | grep -v '^<' \
    | sort -u
)

echo "== Key HTTP checks =="
for url in \
  index.php dogs.php training_program.php training_session_log.php \
  training_goal_intake.php goal_builder.php behavior_risk_scoring.php regression_engine.php wearable_integrations.php trainer_marketplace.php candidate_assessment.php candidate_comparison.php community_challenges.php habit_repair.php tactical_training.php \
  admin.php admin_tactical_requests.php admin_feature_roadmap.php settings.php profile.php
do
  code=$(curl -k -s -o /tmp/gp_deploy_check.out -w "%{http_code}" "https://10.147.18.184/$url?deploycheck=1")
  echo "$code  $url"
done

echo "== Syncing companion version in nginx =="
VER_NAME=$(php -r "require '$REPO/includes/app_config.php'; echo gpEnv('GUIDEPAW_COMPANION_VERSION_NAME', '0.078');" 2>/dev/null || grep "VERSION_NAME" "$REPO/android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/CompanionAppVersion.kt" | grep -oP '"\K[^"]+')
VER_CODE=$(php -r "require '$REPO/includes/app_config.php'; echo gpEnv('GUIDEPAW_COMPANION_VERSION_CODE', '78');" 2>/dev/null || grep "VERSION_CODE" "$REPO/android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/CompanionAppVersion.kt" | grep -oP '= \K[0-9]+')
NGINX_CONF="/etc/nginx/sites-available/guidepaw"
sudo sed -i "s|fastcgi_param GUIDEPAW_COMPANION_VERSION_NAME .*;|fastcgi_param GUIDEPAW_COMPANION_VERSION_NAME ${VER_NAME};|" "$NGINX_CONF"
sudo sed -i "s|fastcgi_param GUIDEPAW_COMPANION_VERSION_CODE .*;|fastcgi_param GUIDEPAW_COMPANION_VERSION_CODE ${VER_CODE};|" "$NGINX_CONF"
sudo nginx -t >/dev/null 2>&1 && sudo systemctl reload nginx
echo "  companion version → ${VER_NAME} (code ${VER_CODE})"

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
