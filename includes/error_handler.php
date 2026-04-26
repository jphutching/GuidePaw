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

function logAppError(string $level, string $message, array $context = []): void {
    $payload = [
        'time' => date('c'),
        'level' => strtoupper($level),
        'message' => $message,
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'context' => $context,
    ];
    @file_put_contents(appLogsPath(), json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function isApiRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_starts_with($uri, '/api/') || str_contains($uri, '/api/') || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    logAppError('error', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => appDebugEnabled() ? $e->getTraceAsString() : null,
    ]);

    $message = appDebugEnabled() ? $e->getMessage() : 'Something went wrong. Please try again.';
    if (isApiRequest()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        return;
    }

    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Application Error</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
    echo '<div class="container py-5" style="max-width:720px"><div class="card shadow-sm"><div class="card-body">';
    echo '<h1 class="h4 mb-3">Application Error</h1>';
    echo '<p class="text-muted">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="index.php" class="btn btn-primary">Back to dashboard</a> <a href="feedback.php" class="btn btn-outline-secondary">Report issue</a>';
    echo '</div></div></div></body></html>';
});
