<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/beta_notifications.php';
require_once __DIR__ . '/includes/sms_notifications.php';

checkLogin();
requireAdmin();

$message = '';
$error = '';

function gpMaskSecret(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return 'missing';
    }
    if (strlen($value) <= 10) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $sampleRequest = [
        'full_name' => 'GuidePaw Test Request',
        'email' => 'test@example.com',
        'phone' => 'test phone',
        'reason' => 'This is a manual admin notification test from GuidePaw.',
        'status' => 'test',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    try {
        if (($_POST['test'] ?? '') === 'telegram') {
            betaNotifyAdminTelegram($sampleRequest);
            $message = 'Telegram test sent. Check your Telegram chat with the GuidePaw alerts bot.';
        } elseif (($_POST['test'] ?? '') === 'email') {
            betaNotifyAdminEmail($sampleRequest);
            $message = 'Email test sent. Check the admin notification inbox.';
        } elseif (($_POST['test'] ?? '') === 'sms') {
            $smsPhone = trim((string) gpEnv('ADMIN_NOTIFY_SMS_PHONE', ''));
            if ($smsPhone === '') {
                throw new RuntimeException('ADMIN_NOTIFY_SMS_PHONE is missing in Render Environment.');
            }
            if (!gpSmsSendRaw($smsPhone, 'GuidePaw SMS test: Twilio/text notifications are configured.')) {
                throw new RuntimeException('SMS test failed. Check SMS_NOTIFY_ENABLED, TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER, and Render logs.');
            }
            $message = 'SMS test sent. Check the admin notification phone.';
        } else {
            throw new RuntimeException('Unknown notification test.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$csrf = generateCsrfToken();
$telegramEnabled = betaAdminNotificationTelegramEnabled();
$emailEnabled = betaAdminNotificationEmailEnabled();
$telegramToken = gpEnv('TELEGRAM_BOT_TOKEN', '');
$telegramChatId = gpEnv('TELEGRAM_CHAT_ID', '');
$adminNotifyEmail = gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app'));
$smsEnabled = gpSmsGlobalEnabled();
$smsProvider = gpSmsProvider();
$twilioSid = gpEnv('TWILIO_ACCOUNT_SID', '');
$twilioToken = gpEnv('TWILIO_AUTH_TOKEN', '');
$twilioFrom = gpEnv('TWILIO_FROM_NUMBER', '');
$adminSmsPhone = gpEnv('ADMIN_NOTIFY_SMS_PHONE', '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Notification Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php guidepawBrandHeader(); ?>
<main class="container py-4" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Notification Test</h1>
        <a class="btn btn-outline-secondary" href="admin.php">Admin Home</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><strong>Test failed:</strong> <?= e($error) ?></div><?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5">Current settings</h2>
            <table class="table table-sm">
                <tr><th>Email enabled</th><td><?= $emailEnabled ? 'yes' : 'no' ?></td></tr>
                <tr><th>Admin notify email</th><td><?= e((string) $adminNotifyEmail) ?></td></tr>
                <tr><th>Telegram enabled</th><td><?= $telegramEnabled ? 'yes' : 'no' ?></td></tr>
                <tr><th>Telegram token</th><td><?= e(gpMaskSecret($telegramToken)) ?></td></tr>
                <tr><th>Telegram chat ID</th><td><?= e((string) ($telegramChatId ?: 'missing')) ?></td></tr>
                <tr><th>SMS enabled</th><td><?= $smsEnabled ? 'yes' : 'no' ?></td></tr>
                <tr><th>SMS provider</th><td><?= e($smsProvider ?: 'missing') ?></td></tr>
                <tr><th>Twilio account SID</th><td><?= e(gpMaskSecret($twilioSid)) ?></td></tr>
                <tr><th>Twilio auth token</th><td><?= e(gpMaskSecret($twilioToken)) ?></td></tr>
                <tr><th>Twilio from number</th><td><?= e(gpMaskSecret($twilioFrom)) ?></td></tr>
                <tr><th>Admin SMS phone</th><td><?= e(gpMaskSecret($adminSmsPhone)) ?></td></tr>
            </table>
            <p class="text-muted mb-0">Tokens and phone numbers are masked on purpose. Real credentials should only live in Render Environment.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Send test</h2>
            <form method="post" class="d-flex gap-2 flex-wrap">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <button class="btn btn-primary" name="test" value="telegram">Send Telegram Test</button>
                <button class="btn btn-outline-primary" name="test" value="email">Send Email Test</button>
                <button class="btn btn-outline-success" name="test" value="sms">Send SMS Test</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
