<?php
declare(strict_types=1);

function publicDogProfileSecret(): string
{
    $secret = getenv('APP_KEY') ?: getenv('SESSION_SECRET') ?: getenv('PUBLIC_PROFILE_SECRET') ?: '';
    if ($secret === '') {
        $secret = __DIR__ . '|guidepaw-public-dog-profile-v1';
    }
    return $secret;
}

function publicDogProfileToken(int $dogId): string
{
    return substr(hash_hmac('sha256', (string) $dogId, publicDogProfileSecret()), 0, 24);
}

function publicDogProfileTokenValid(int $dogId, string $token): bool
{
    return hash_equals(publicDogProfileToken($dogId), $token);
}

function publicDogProfileUrl(int $dogId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'guidepaw.app';
    return $scheme . '://' . $host . '/public_dog_profile.php?dog=' . $dogId . '&token=' . publicDogProfileToken($dogId);
}
