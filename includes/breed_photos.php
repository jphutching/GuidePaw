<?php
/**
 * Maps GuidePaw breed catalog names to Dog CEO API slugs.
 * Returns null for breeds not covered by Dog CEO — the caller skips the photo.
 * Dog CEO API: https://dog.ceo/dog-api/ (CC0, no key required)
 */
function breedPhotoSlug(string $breedName): ?string
{
    static $map = [
        'Labrador Retriever'                   => 'labrador',
        'Golden Retriever'                     => 'retriever/golden',
        'German Shepherd Dog'                  => 'germanshepherd',
        'Standard Poodle'                      => 'poodle/standard',
        'Miniature Poodle'                     => 'poodle/miniature',
        'Toy Poodle'                           => 'poodle/toy',
        'Boxer'                                => 'boxer',
        'Doberman Pinscher'                    => 'doberman',
        'Rottweiler'                           => 'rottweiler',
        'Bernese Mountain Dog'                 => 'mountain/bernese',
        'Great Dane'                           => 'dane/great',
        'Australian Shepherd'                  => 'shepherd/australian',
        'Miniature American Shepherd'          => 'shepherd/australian',
        'Border Collie'                        => 'collie/border',
        'Rough Collie'                         => 'collie',
        'Pembroke Welsh Corgi'                 => 'corgi/pembroke',
        'Cardigan Welsh Corgi'                 => 'corgi/cardigan',
        'Belgian Malinois'                     => 'malinois',
        'Vizsla'                               => 'vizsla',
        'Weimaraner'                           => 'weimaraner',
        'Beagle'                               => 'beagle',
        'Bloodhound'                           => 'bloodhound',
        'Cocker Spaniel'                       => 'spaniel/cocker',
        'American Cocker Spaniel'              => 'spaniel/cocker',
        'English Cocker Spaniel'               => 'spaniel/cocker',
        'English Springer Spaniel'             => 'spaniel/springer',
        'Cavalier King Charles Spaniel'        => 'spaniel/cavalier',
        'Irish Setter'                         => 'setter/irish',
        'Gordon Setter'                        => 'setter/gordon',
        'English Setter'                       => 'setter/english',
        'Flat-Coated Retriever'                => 'retriever/flatcoated',
        'Chesapeake Bay Retriever'             => 'retriever/chesapeake',
        'Nova Scotia Duck Tolling Retriever'   => 'retriever/duck',
        'German Shorthaired Pointer'           => 'pointer/germanlonghair',
        'Miniature Schnauzer'                  => 'schnauzer/miniature',
        'Giant Schnauzer'                      => 'schnauzer/giant',
        'Standard Schnauzer'                   => 'schnauzer',
        'Cairn Terrier'                        => 'cairn',
        'West Highland White Terrier'          => 'terrier/westhighland',
        'Scottish Terrier'                     => 'terrier/scottish',
        'Bull Terrier'                         => 'terrier/bull',
        'Airedale Terrier'                     => 'airedale',
        'Pomeranian'                           => 'pomeranian',
        'Maltese'                              => 'maltese',
        'Pug'                                  => 'pug',
        'French Bulldog'                       => 'bulldog/french',
        'Boston Terrier'                       => 'boston',
        'Newfoundland'                         => 'newfoundland',
        'Great Pyrenees'                       => 'pyrenees/great',
        'Saint Bernard'                        => 'stbernard',
        'Akita'                                => 'akita',
        'Siberian Husky'                       => 'husky',
        'Alaskan Malamute'                     => 'malamute/alaskan',
        'Samoyed'                              => 'samoyed',
        'Poodle'                               => 'poodle/standard',
        'Dachshund'                            => 'dachshund',
        'Chihuahua'                            => 'chihuahua',
    ];

    return $map[$breedName] ?? null;
}

/**
 * Returns a cached photo URL for $breedName, fetching from Dog CEO on first request.
 * Returns null when: feature disabled, breed not mapped, Dog CEO unavailable,
 * or breed_images table not yet migrated.
 */
function getBreedPhotoUrlCached(PDO $pdo, string $breedName): ?string
{
    if (!function_exists('featureEnabled') || !function_exists('tableExists')) {
        return null;
    }
    if (!featureEnabled($pdo, 'breed_photos_enabled')) {
        return null;
    }
    if (!tableExists($pdo, 'breed_images')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT image_url FROM breed_images WHERE breed_name = ? AND color_variant = \'\' LIMIT 1');
    $stmt->execute([$breedName]);
    $cached = $stmt->fetchColumn();
    if ($cached !== false) {
        return $cached !== '' ? $cached : null;
    }

    $slug = breedPhotoSlug($breedName);
    if ($slug === null) {
        $pdo->prepare('INSERT INTO breed_images (breed_name, color_variant, image_url, source) VALUES (?, \'\', \'\', \'not_mapped\') ON CONFLICT (breed_name, color_variant) DO NOTHING')->execute([$breedName]);
        return null;
    }

    $ch = curl_init('https://dog.ceo/api/breed/' . $slug . '/images/random');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $imageUrl = '';
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        if (isset($data['status'], $data['message']) && $data['status'] === 'success' && is_string($data['message'])) {
            $imageUrl = $data['message'];
        }
    }

    $pdo->prepare('INSERT INTO breed_images (breed_name, color_variant, image_url, source) VALUES (?, \'\', ?, \'dog_ceo\') ON CONFLICT (breed_name, color_variant) DO UPDATE SET image_url = EXCLUDED.image_url, fetched_at = CURRENT_TIMESTAMP')->execute([$breedName, $imageUrl]);

    return $imageUrl !== '' ? $imageUrl : null;
}
