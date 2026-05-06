<?php

require_once __DIR__ . '/app_config.php';

function appDebugEnabled(): bool {
    return strtolower(appEnv('APP_DEBUG', 'false')) === 'true';
}

function appLogsPath(): string {
    $dir = appStorageRoot() . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/app.log';
}

function appErrorId(): string {
    try {
        return 'GP-' . gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    } catch (Throwable $e) {
        return 'GP-' . gmdate('Ymd-His') . '-' . substr(md5((string) microtime(true)), 0, 8);
    }
}

function logAppError(string $level, string $message, array $context = []): void {
    $payload = [
        'time' => date('c'),
        'level' => strtoupper($level),
        'message' => $message,
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? null,
        'context' => $context,
    ];
    @file_put_contents(appLogsPath(), json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function isApiRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_starts_with($uri, '/api/') || str_contains($uri, '/api/') || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

function rememberLastAppError(string $errorId, Throwable $e): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['last_app_error'] = [
        'error_id' => $errorId,
        'time' => date('c'),
        'page' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? null,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    ];
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    $errorId = appErrorId();

    logAppError('error', $e->getMessage(), [
        'error_id' => $errorId,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => appDebugEnabled() ? $e->getTraceAsString() : null,
    ]);
    rememberLastAppError($errorId, $e);

    $message = appDebugEnabled() ? $e->getMessage() : 'Something went wrong. Please try again.';
    if (isApiRequest()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message, 'error_id' => $errorId]);
        return;
    }

    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeErrorId = htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8');
    $reportUrl = 'feedback.php?from_error=1&error_id=' . rawurlencode($errorId);

    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Application Error</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
    echo '<div class="container py-5" style="max-width:720px"><div class="card shadow-sm"><div class="card-body">';
    echo '<h1 class="h4 mb-3">Application Error</h1>';
    echo '<p class="text-muted">' . $safeMessage . '</p>';
    echo '<div class="alert alert-warning small"><strong>Error ID:</strong> ' . $safeErrorId . '<br>Use Report issue so GuidePaw can attach this error context automatically.</div>';
    echo '<a href="index.php" class="btn btn-primary">Back to dashboard</a> <a href="' . htmlspecialchars($reportUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-danger">Report issue with details</a>';
    echo '</div></div></div></body></html>';
});
