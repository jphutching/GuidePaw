<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';

function betaAdminNotificationEmailEnabled(): bool
{
    $value = strtolower(trim((string) gpEnv('BETA_NOTIFY_EMAIL_ENABLED', 'true')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function betaAdminNotificationBody(array $request): string
{
    $fullName = (string) ($request['full_name'] ?? 'Not provided');
    $email = (string) ($request['email'] ?? 'Not provided');
    $phone = (string) ($request['phone'] ?? 'Not provided');
    $reason = (string) ($request['reason'] ?? 'Not provided');
    $status = (string) ($request['status'] ?? 'pending');
    $createdAt = (string) ($request['created_at'] ?? date('Y-m-d H:i:s'));
    $adminUrl = rtrim((string) gpEnv('APP_URL', 'https://beta.guidepaw.app'), '/') . '/admin_beta_requests.php';

    return "A new GuidePaw beta access request was submitted.\n\n" .
        "Name: {$fullName}\n" .
        "Email: {$email}\n" .
        "Phone: {$phone}\n" .
        "Status: {$status}\n" .
        "Submitted: {$createdAt}\n\n" .
        "Reason / notes:\n{$reason}\n\n" .
        "Review the request here:\n{$adminUrl}\n\n" .
        "GuidePaw admin notification\n";
}

function betaNotifyAdminOfBetaRequest(PDO $pdo, int $requestId): bool
{
    if (!betaAdminNotificationEmailEnabled()) {
        return false;
    }

    $adminEmail = trim((string) gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
    if ($adminEmail === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM beta_access_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new RuntimeException('Beta request not found for admin notification.');
        }

        return gpSendMail($adminEmail, 'New GuidePaw beta access request', betaAdminNotificationBody($request));
    } catch (Throwable $e) {
        error_log('GuidePaw beta admin notification failed: ' . $e->getMessage());
        return false;
    }
}
