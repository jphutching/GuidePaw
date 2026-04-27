# GuidePaw Developer Notes

## URLs

LAN: https://10.230.194.242

ZeroTier: https://10.147.18.184

Admin: https://10.147.18.184/admin.php

## Paths

Served app: /var/www/guidepaw

Project source: /home/james/projects/gpb3/gpb3

## Database

Database: guidepaw

Useful commands:

sudo -u postgres psql guidepaw
sudo -u postgres pg_dump guidepaw -f /tmp/guidepaw-db.sql

## Git

Repo: https://github.com/jphutching/GuidePaw.git

Branch: main

Common commands:

cd /home/james/projects/gpb3/gpb3
git status
git add .
git commit -m "message"
git push

## Runtime checks

php -l /var/www/guidepaw/index.php
sudo nginx -t
curl -k -I https://10.147.18.184

## Services

systemctl status nginx
systemctl status php8.5-fpm
systemctl status postgresql
systemctl status zerotier-one

## Training Progression Core

Added MVP training progression foundation:

- `sql/migrations/pgsql/003_training_progression_roadmap.sql`
- `admin_feature_roadmap.php`
- `candidate_assessment.php`
- `training_goal_intake.php`
- `habit_repair.php`
- `training_session_log.php`

Added reusable helpers:

- `includes/candidate_scoring.php`
- `includes/training_goals.php`
- `includes/behavior_incidents.php`
- `includes/training_progression.php`

Dashboard now includes Training Core links:

- Candidate Assessment
- Goal Intake
- Habit Repair
- Session Log
- Feature Roadmap for admins

Training Program now shows:

- Today's Easy Win
- Active goals
- Current modules
- Progress/regression status
- Log Session link

Live files synced to `/var/www/guidepaw`.

