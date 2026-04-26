# GuidePaw Phone Sandbox Install

Recommended phone sandbox:
- host Termux runs PostgreSQL
- Debian proot runs PHP and the app

This is the most stable phone path we validated.

## 1. Host Termux
Install:
```bash
pkg update && pkg upgrade -y
termux-setup-storage
pkg install -y postgresql proot-distro unzip curl git openssh
```

## 2. Host PostgreSQL
```bash
mkdir -p ~/pgdata
initdb ~/pgdata
pg_ctl -D ~/pgdata -l ~/pglog start
psql postgres
```

Inside psql:
```sql
CREATE USER guidepaw WITH PASSWORD 'change_me_now';
CREATE DATABASE guidepaw OWNER guidepaw;
\q
```

## 3. Debian proot
```bash
proot-distro install debian
proot-distro login debian
apt update
apt install -y php php-pgsql php-mbstring unzip curl git
```

## 4. Copy and unpack
Inside Debian:
```bash
mkdir -p /root/projects
cp /data/data/com.termux/files/home/storage/downloads/gpb3.zip /root/projects/
cd /root/projects
unzip -o gpb3.zip -d gpb3
cd gpb3
```

## 5. Import schema from Debian into host PostgreSQL
```bash
PGPASSWORD=change_me_now psql -h 127.0.0.1 -U guidepaw -d guidepaw -f "latest postgres sql.txt"
```

## 6. Start PHP in Debian
```bash
APP_ENV=local APP_DEBUG=true DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=guidepaw DB_USERNAME=guidepaw DB_PASSWORD=change_me_now php -S 127.0.0.1:8080
```

## 7. Browse
- `http://127.0.0.1:8080`

## 8. Optional SSH on host Termux
```bash
pkg install -y openssh
passwd
sshd
```
SSH port is `8022`.
