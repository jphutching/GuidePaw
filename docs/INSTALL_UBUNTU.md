# GuidePaw Ubuntu Install

This is the recommended migration target for the current package.

## 1. Prerequisites
Install:
- PHP with `pdo_pgsql`, `mbstring`, `curl`, `xml`, `zip`
- PostgreSQL
- unzip
- git or ssh access
- optional later: Nginx + PHP-FPM

Example packages on Ubuntu:
```bash
sudo apt update
sudo apt install -y php php-pgsql php-mbstring php-curl php-xml php-zip postgresql unzip
```

## 2. Unpack the project
```bash
mkdir -p ~/projects
cd ~/projects
unzip gpb3.zip -d gpb3
cd gpb3
```

## 3. Create PostgreSQL database
```bash
sudo -u postgres psql
```

Inside psql:
```sql
CREATE USER guidepaw WITH PASSWORD 'change_me_now';
CREATE DATABASE guidepaw OWNER guidepaw;
\q
```

## 4. Import schema
```bash
PGPASSWORD=change_me_now psql -h 127.0.0.1 -U guidepaw -d guidepaw -f "latest postgres sql.txt"
```

## 5. Start with PHP built-in server first
Do this before introducing Nginx:
```bash
APP_ENV=local APP_DEBUG=true DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=guidepaw DB_USERNAME=guidepaw DB_PASSWORD=change_me_now php -S 127.0.0.1:8080
```

Then open:
- `http://127.0.0.1:8080`

## 6. Smoke test
Verify:
- register / initialize profile
- dashboard loads
- dogs page loads
- dog profile save works
- quick log save works
- training history loads
- reports loads
- backup page loads

## 7. Move to Nginx later
Once the app works with PHP built-in server, wire it into Nginx + PHP-FPM.
Use the project folder as the web root and pass PHP files to FPM.
