# GuidePaw ZeptoMail Render Configuration

Status: Working on Render beta.

Working sender set:

- ZEPTO_API_URL=https://api.zeptomail.com/v1.1/email
- ZEPTO_FROM_ADDRESS=admin@guidepaw.app
- ZEPTO_FROM_NAME=GuidePaw
- ZEPTO_BOUNCE_ADDRESS=admin@bounce-zem.guidepaw.app
- ZEPTO_SEND_MAIL_TOKEN=<Render secret: Send Mail token labeled admin@guidepaw.app>

Important rules:

- The sender address, bounce address, and Send Mail token must all match the same ZeptoMail sender.
- Do not mix noreply sender with admin token or admin bounce address.
- Do not commit the real token to GitHub.
- Current known-good beta flow:
  - Admin approves beta access request.
  - ZeptoMail sends invite email.
  - Invite link opens account creation.
  - Account creation succeeds.
  - Login succeeds.
  - Add Dog works.
