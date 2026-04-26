<?php

function appEnv(string $key, ?string $default = null): ?string {
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

function appName(): string {
    return appEnv('APP_NAME', 'GuidePaw Beta');
}

function appShortName(): string {
    return appEnv('APP_SHORT_NAME', 'GuidePaw');
}

function appUrl(): string {
    return rtrim(appEnv('APP_URL', ''), '/');
}

function appStorageRoot(): string {
    return rtrim(appEnv('APP_STORAGE_PATH', __DIR__ . '/../storage'), '/');
}

function appMode(): string {
    return appEnv('APP_MODE', 'beta');
}

function betaBannerEnabled(): bool {
    return strtolower(appEnv('APP_BETA_BANNER_ENABLED', 'true')) !== 'false';
}

function betaBannerLabel(): string {
    return appEnv('APP_BETA_BANNER_LABEL', 'BETA / MIGRATION BUILD');
}

function appFeedbackPath(): string {
    return appStorageRoot() . '/feedback';
}

function appAllowDbMigrations(): bool {
    return strtolower(appEnv('APP_ALLOW_DB_MIGRATIONS', 'false')) === 'true';
}
