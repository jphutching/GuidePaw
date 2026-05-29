<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy | <?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<div class="container" style="max-width:860px;margin:40px auto;padding:0 20px 60px;">
  <h1>Privacy Policy</h1>
  <p style="color:#666;">Last updated: May 28, 2026</p>

  <p>GuidePaw ("we," "us," or "our") operates the GuidePaw website and the GuidePaw Companion mobile application. This policy explains what information we collect, how we use it, and your rights.</p>

  <h2>Information We Collect</h2>
  <h3>Account &amp; Profile Data</h3>
  <p>When you register, we collect your name, email address, username, and password (stored as a bcrypt hash). You may optionally add a phone number and address.</p>

  <h3>Dog &amp; Training Data</h3>
  <p>You may add service dog profiles including the dog's name, breed, date of birth, health records, training logs, certification documents, and photos. This information is stored and used solely to provide the service to you.</p>

  <h3>Location Data</h3>
  <p>The mobile app requests access to your device's location (fine and coarse) to support navigation features and to pre-fill location fields when you report a found dog. Location is never collected in the background and is never sold or shared with third parties.</p>

  <h3>Camera</h3>
  <p>The mobile app requests camera access to let you photograph your dog or scan QR codes. Photos are uploaded only when you explicitly choose to attach them.</p>

  <h3>Usage &amp; Log Data</h3>
  <p>We collect standard server logs (IP address, browser/app version, pages visited, timestamps) for security monitoring and debugging. We also use Google Analytics 4 to understand aggregate usage patterns. GA4 data is anonymized and does not include personally identifiable information.</p>

  <h2>How We Use Your Information</h2>
  <ul>
    <li>To provide and improve the GuidePaw service</li>
    <li>To send transactional emails (account verification, password reset, found-dog alerts)</li>
    <li>To process subscription payments via Stripe</li>
    <li>To respond to support requests</li>
    <li>To comply with legal obligations</li>
  </ul>

  <h2>Third-Party Services</h2>
  <table style="width:100%;border-collapse:collapse;margin:12px 0;">
    <thead><tr style="background:#f5f5f5;"><th style="padding:8px;text-align:left;border:1px solid #ddd;">Service</th><th style="padding:8px;text-align:left;border:1px solid #ddd;">Purpose</th><th style="padding:8px;text-align:left;border:1px solid #ddd;">Privacy Policy</th></tr></thead>
    <tbody>
      <tr><td style="padding:8px;border:1px solid #ddd;">Stripe</td><td style="padding:8px;border:1px solid #ddd;">Payment processing</td><td style="padding:8px;border:1px solid #ddd;"><a href="https://stripe.com/privacy" target="_blank">stripe.com/privacy</a></td></tr>
      <tr><td style="padding:8px;border:1px solid #ddd;">ZeptoMail (Zoho)</td><td style="padding:8px;border:1px solid #ddd;">Transactional email delivery</td><td style="padding:8px;border:1px solid #ddd;"><a href="https://www.zoho.com/privacy.html" target="_blank">zoho.com/privacy</a></td></tr>
      <tr><td style="padding:8px;border:1px solid #ddd;">Google Analytics 4</td><td style="padding:8px;border:1px solid #ddd;">Aggregate usage analytics</td><td style="padding:8px;border:1px solid #ddd;"><a href="https://policies.google.com/privacy" target="_blank">policies.google.com/privacy</a></td></tr>
      <tr><td style="padding:8px;border:1px solid #ddd;">Render</td><td style="padding:8px;border:1px solid #ddd;">Cloud hosting</td><td style="padding:8px;border:1px solid #ddd;"><a href="https://render.com/privacy" target="_blank">render.com/privacy</a></td></tr>
    </tbody>
  </table>

  <h2>Data Retention</h2>
  <p>Your account data is retained for as long as your account is active. You may request deletion of your account and all associated data by emailing <a href="mailto:admin@guidepaw.app">admin@guidepaw.app</a>. Deletion requests are processed within 30 days.</p>

  <h2>Children's Privacy</h2>
  <p>GuidePaw is not directed to children under 13. We do not knowingly collect personal information from children under 13.</p>

  <h2>Your Rights</h2>
  <p>Depending on your location, you may have the right to access, correct, or delete your personal data, or to object to certain processing. Contact us at <a href="mailto:admin@guidepaw.app">admin@guidepaw.app</a> to exercise these rights.</p>

  <h2>Security</h2>
  <p>We use HTTPS for all data transmission, bcrypt for password storage, and token-based API authentication. No security system is perfect; if you discover a vulnerability please disclose it responsibly to <a href="mailto:admin@guidepaw.app">admin@guidepaw.app</a>.</p>

  <h2>Changes to This Policy</h2>
  <p>We may update this policy from time to time. The "Last updated" date at the top reflects the most recent revision. Continued use of the service after changes constitutes acceptance.</p>

  <h2>Contact</h2>
  <p>GuidePaw<br>
  Email: <a href="mailto:admin@guidepaw.app">admin@guidepaw.app</a></p>
</div>
</body>
</html>
