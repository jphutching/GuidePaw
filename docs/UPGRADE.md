# Upgrade notes

Current package baseline:
- PostgreSQL only
- mobile nav and wallet page updates included
- current tested laptop path is PHP built-in server first, then Nginx/PHP-FPM

If moving from an older package:
1. back up your project and database
2. compare `.env` / runtime environment variables
3. re-import or migrate schema carefully
4. verify Training History, Reports, Quick Log GPS, and ADA Wallet Card
