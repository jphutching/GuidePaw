<?php
if (!function_exists('gpApproxAgeYearsFromBirthDate')) {
    function gpApproxAgeYearsFromBirthDate(string $dateOfBirth, ?DateTimeInterface $asOf = null): ?float
    {
        $dateOfBirth = trim($dateOfBirth);
        if ($dateOfBirth === '') {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($dateOfBirth);
        } catch (Exception $e) {
            return null;
        }

        $now = $asOf ? DateTimeImmutable::createFromInterface($asOf) : new DateTimeImmutable('now');
        if ($birth > $now) {
            return null;
        }

        $seconds = $now->getTimestamp() - $birth->getTimestamp();
        $years = $seconds / (365.2425 * 24 * 60 * 60);
        return round(max(0.0, $years), 1);
    }
}
