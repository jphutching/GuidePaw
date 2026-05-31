<?php
/**
 * Maps GuidePaw breed catalog names to Dog CEO API slugs.
 * Returns null for breeds not covered — the caller falls back to Wikipedia.
 * Dog CEO API: https://dog.ceo/dog-api/ (CC0, no key required)
 */
function breedPhotoSlug(string $breedName): ?string
{
    static $map = [
        // ── Retrievers ───────────────────────────────────────────────────────
        'Labrador Retriever'                 => 'labrador',
        'Golden Retriever'                   => 'retriever/golden',
        'Chesapeake Bay Retriever'           => 'retriever/chesapeake',
        'Flat-Coated Retriever'             => 'retriever/flatcoated',
        'Curly-Coated Retriever'            => 'retriever/curly',
        'Nova Scotia Duck Tolling Retriever' => 'retriever/duck',

        // ── Poodles ──────────────────────────────────────────────────────────
        'Poodle'                             => 'poodle/standard',
        'Standard Poodle'                    => 'poodle/standard',
        'Miniature Poodle'                   => 'poodle/miniature',
        'Toy Poodle'                         => 'poodle/toy',

        // ── Shepherds / Herding ──────────────────────────────────────────────
        'German Shepherd Dog'                => 'german/shepherd',
        'Australian Shepherd'                => 'australian/shepherd',
        'Miniature American Shepherd'        => 'australian/shepherd',
        'Border Collie'                      => 'collie/border',
        'Rough Collie'                       => 'rough/collie',
        'Collie'                             => 'rough/collie',
        'Belgian Malinois'                   => 'malinois',
        'Belgian Tervuren'                   => 'tervuren',
        'Belgian Sheepdog'                   => 'groenendael',
        'Bouvier des Flandres'               => 'bouvier',
        'Briard'                             => 'briard',
        'Old English Sheepdog'               => 'sheepdog/english',
        'Shetland Sheepdog'                  => 'sheepdog/shetland',
        'Australian Cattle Dog'              => 'cattledog/australian',
        'Australian Stumpy Tail Cattle Dog'  => 'cattledog/australian',
        'Australian Kelpie'                  => 'australian/kelpie',

        // ── Corgis ───────────────────────────────────────────────────────────
        'Pembroke Welsh Corgi'               => 'pembroke',
        'Cardigan Welsh Corgi'               => 'corgi/cardigan',
        'Corgi'                              => 'pembroke',

        // ── Working ──────────────────────────────────────────────────────────
        'Boxer'                              => 'boxer',
        'Doberman Pinscher'                  => 'doberman',
        'Rottweiler'                         => 'rottweiler',
        'Bernese Mountain Dog'               => 'mountain/bernese',
        'Greater Swiss Mountain Dog'         => 'mountain/swiss',
        'Great Dane'                         => 'dane/great',
        'Newfoundland'                       => 'newfoundland',
        'Saint Bernard'                      => 'stbernard',
        'Great Pyrenees'                     => 'pyrenees',
        'Akita'                              => 'akita',
        'Siberian Husky'                     => 'husky',
        'Alaskan Malamute'                   => 'malamute',
        'Samoyed'                            => 'samoyed',
        'Kuvasz'                             => 'kuvasz',
        'Komondor'                           => 'komondor',
        'Leonberger'                         => 'leonberg',
        'Bullmastiff'                        => 'mastiff/bull',
        'Mastiff'                            => 'mastiff/english',
        'Tibetan Mastiff'                    => 'mastiff/tibetan',
        'Boerboel'                           => 'mastiff/english',
        'Caucasian Shepherd Dog'             => 'ovcharka/caucasian',
        'Central Asian Shepherd Dog'         => 'ovcharka/caucasian',
        'Dutch Shepherd'                     => 'german/shepherd',
        'Black Russian Terrier'              => 'schnauzer/giant',
        'Bohemian Shepherd'                  => 'german/shepherd',
        'Entlebucher Mountain Dog'           => 'entlebucher',
        'Appenzeller Sennenhund'             => 'appenzeller',

        // ── Spaniels ─────────────────────────────────────────────────────────
        'Cocker Spaniel'                     => 'spaniel/cocker',
        'American Cocker Spaniel'            => 'spaniel/cocker',
        'English Cocker Spaniel'             => 'spaniel/cocker',
        'English Springer Spaniel'           => 'springer/english',
        'Welsh Springer Spaniel'             => 'spaniel/welsh',
        'Irish Water Spaniel'                => 'spaniel/irish',
        'Clumber Spaniel'                    => 'clumber',
        'Sussex Spaniel'                     => 'spaniel/sussex',
        'Brittany'                           => 'spaniel/brittany',
        'English Toy Spaniel'                => 'spaniel/blenheim',
        'King Charles Spaniel'               => 'spaniel/blenheim',
        'Japanese Chin'                      => 'spaniel/japanese',
        'Boykin Spaniel'                     => 'spaniel/cocker',
        'Field Spaniel'                      => 'spaniel/cocker',

        // ── Setters / Pointers ───────────────────────────────────────────────
        'Irish Setter'                       => 'setter/irish',
        'Gordon Setter'                      => 'setter/gordon',
        'English Setter'                     => 'setter/english',
        'German Shorthaired Pointer'         => 'pointer/german',
        'German Longhaired Pointer'          => 'pointer/germanlonghair',

        // ── Hounds ───────────────────────────────────────────────────────────
        'Beagle'                             => 'beagle',
        'Bloodhound'                         => 'hound/blood',
        'Basset Hound'                       => 'hound/basset',
        'Afghan Hound'                       => 'hound/afghan',
        'Ibizan Hound'                       => 'hound/ibizan',
        'Rhodesian Ridgeback'                => 'ridgeback/rhodesian',
        'Greyhound'                          => 'greyhound/italian',
        'Italian Greyhound'                  => 'greyhound/italian',
        'Whippet'                            => 'whippet',
        'Borzoi'                             => 'borzoi',
        'Saluki'                             => 'saluki',
        'Irish Wolfhound'                    => 'wolfhound/irish',
        'Scottish Deerhound'                 => 'deerhound/scottish',
        'Norwegian Elkhound'                 => 'elkhound/norwegian',
        'Plott Hound'                        => 'hound/plott',
        'Redbone Coonhound'                  => 'redbone',
        'Bluetick Coonhound'                 => 'bluetick',
        'Treeing Walker Coonhound'           => 'hound/walker',
        'American English Coonhound'         => 'coonhound',
        'English Foxhound'                   => 'hound/english',
        'Basenji'                            => 'basenji',
        'Vizsla'                             => 'vizsla',
        'Weimaraner'                         => 'weimaraner',
        'Pointer'                            => 'pointer',
        'Harrier'                            => 'hound',
        'Hamiltonstovare'                    => 'hound',

        // ── Terriers ─────────────────────────────────────────────────────────
        'Yorkshire Terrier'                  => 'terrier/yorkshire',
        'Airedale Terrier'                   => 'airedale',
        'Cairn Terrier'                      => 'terrier/cairn',
        'West Highland White Terrier'        => 'terrier/westhighland',
        'Scottish Terrier'                   => 'terrier/scottish',
        'Bull Terrier'                       => 'terrier/fox',
        'Staffordshire Bull Terrier'         => 'bullterrier/staffordshire',
        'American Staffordshire Terrier'     => 'terrier/american',
        'Miniature Bull Terrier'             => 'bullterrier/staffordshire',
        'Miniature Schnauzer'                => 'schnauzer/miniature',
        'Giant Schnauzer'                    => 'schnauzer/giant',
        'Standard Schnauzer'                 => 'schnauzer',
        'Border Terrier'                     => 'terrier/border',
        'Australian Terrier'                 => 'terrier/australian',
        'Irish Terrier'                      => 'terrier/irish',
        'Kerry Blue Terrier'                 => 'terrier/kerryblue',
        'Welsh Terrier'                      => 'terrier/welsh',
        'Lakeland Terrier'                   => 'terrier/lakeland',
        'Norfolk Terrier'                    => 'terrier/norfolk',
        'Norwich Terrier'                    => 'terrier/norwich',
        'Bedlington Terrier'                 => 'terrier/bedlington',
        'Dandie Dinmont Terrier'             => 'terrier/dandie',
        'Smooth Fox Terrier'                 => 'terrier/fox',
        'Wire Fox Terrier'                   => 'terrier/fox',
        'Parson Russell Terrier'             => 'terrier/russell',
        'Russell Terrier'                    => 'terrier/russell',
        'Silky Terrier'                      => 'terrier/silky',
        'Soft Coated Wheaten Terrier'        => 'terrier/wheaten',
        'Tibetan Terrier'                    => 'terrier/tibetan',
        'Sealyham Terrier'                   => 'terrier/sealyham',
        'Toy Fox Terrier'                    => 'terrier/toy',

        // ── Toy / Companion ──────────────────────────────────────────────────
        'Chihuahua'                          => 'chihuahua',
        'Pomeranian'                         => 'pomeranian',
        'Maltese'                            => 'maltese',
        'Pug'                                => 'pug',
        'Pekingese'                          => 'pekinese',
        'Bichon Frise'                       => 'frise/bichon',
        'Papillon'                           => 'papillon',
        'Miniature Pinscher'                 => 'pinscher/miniature',
        'Affenpinscher'                      => 'affenpinscher',
        'Brussels Griffon'                   => 'brabancon',
        'Havanese'                           => 'havanese',

        // ── Non-Sporting / Misc ──────────────────────────────────────────────
        'French Bulldog'                     => 'bulldog/french',
        'Bulldog'                            => 'bulldog/english',
        'Boston Terrier'                     => 'bulldog/boston',
        'Dalmatian'                          => 'dalmatian',
        'Chow Chow'                          => 'chow',
        'Lhasa Apso'                         => 'lhasa',
        'Shih Tzu'                           => 'shihtzu',
        'Chinese Shar-Pei'                   => 'sharpei',
        'Keeshond'                           => 'keeshond',
        'Schipperke'                         => 'schipperke',
        'Xoloitzcuintli'                     => 'mexicanhairless',
        'American Eskimo Dog'                => 'eskimo',
        'Coton de Tulear'                    => 'cotondetulear',
        'Finnish Lapphund'                   => 'finnish/lapphund',
        'Norwegian Buhund'                   => 'buhund/norwegian',
        'Shiba Inu'                          => 'shiba',
        'Dachshund'                          => 'dachshund',
        'Otterhound'                         => 'otterhound',
        'Spanish Water Dog'                  => 'waterdog/spanish',
        'Danish-Swedish Farmdog'             => 'danish/swedish',

        // ── Doodles in Dog CEO ───────────────────────────────────────────────
        'Labradoodle'                        => 'labradoodle',
        'Cockapoo'                           => 'cockapoo',
        'Cavapoo'                            => 'cavapoo',
        'Puggle'                             => 'puggle',

        // ── Mixed / catch-all ────────────────────────────────────────────────
        'Mixed Breed'                        => 'mix',
        'Mixed Breed - Large'                => 'mix',
        'Mixed Breed - Medium'               => 'mix',
        'Mixed Breed - Small'                => 'mix',
    ];

    return $map[$breedName] ?? null;
}

/**
 * Returns the Wikipedia article title to use for breeds not in Dog CEO.
 * Returns null for categories that have no useful Wikipedia photo
 * (mixed breeds, generics, placeholders).
 */
function breedWikipediaTitle(string $breedName): ?string
{
    static $skip = [
        'Unknown Breed', 'Unknown / Rescue Mix', 'Other / Custom', 'Other / Not Listed',
        'Custom Designer Breed / Hybrid', 'Poodle Cross / Doodle Mix',
        'Mixed Breed', 'Mixed Breed - Large', 'Mixed Breed - Medium', 'Mixed Breed - Small',
    ];
    if (in_array($breedName, $skip, true)) {
        return null;
    }

    // Overrides where the Wikipedia article title differs from our catalog name
    static $overrides = [
        'Poodle (Standard)'                           => 'Standard Poodle',
        'Poodle (Miniature)'                          => 'Miniature Poodle',
        'Poodle (Toy)'                                => 'Toy Poodle',
        'Labrador Retriever / Golden Retriever Cross' => 'Goldador',
        'Aussiedoodle'                                => 'Aussiedoodle',
        'Bernedoodle'                                 => 'Bernedoodle',
        'Cavachon'                                    => 'Cavachon',
        'Cavador'                                     => 'Cavador',
        'Double Doodle'                               => 'Double Doodle',
        'Doxiepoo'                                    => 'Doxiepoo',
        'Frenchton'                                   => 'Frenchton',
        'Gerberian Shepsky'                           => 'Gerberian Shepsky',
        'Goldendoodle'                                => 'Goldendoodle',
        'Golden Mountain Doodle'                      => 'Golden Mountain Doodle',
        'Golden Shepherd'                             => 'Golden Shepherd',
        'Huskydoodle'                                 => 'Huskydoodle',
        'Irish Doodle'                                => 'Irish Doodle',
        'Maltipoo'                                    => 'Maltipoo',
        'Miniature Goldendoodle'                      => 'Miniature Goldendoodle',
        'Miniature Labradoodle'                       => 'Miniature Labradoodle',
        'Morkie'                                      => 'Morkie',
        'Newfypoo'                                    => 'Newfypoo',
        'Pitsky'                                      => 'Pitsky',
        'Pomapoo'                                     => 'Pomapoo',
        'Pomchi'                                      => 'Pomchi',
        'Pomsky'                                      => 'Pomsky',
        'Portuguese Water Dog'                        => 'Portuguese Water Dog',
        'Rottle'                                      => 'Rottle',
        'Saint Berdoodle'                             => 'Saint Berdoodle',
        'Schnoodle'                                   => 'Schnoodle',
        'Sheepadoodle'                                => 'Sheepadoodle',
        'Shih-Poo'                                    => 'Shih-Poo',
        'Shorkie'                                     => 'Shorkie',
        'Springerdoodle'                              => 'Springerdoodle',
        'Vizsladoodle'                                => 'Vizsladoodle',
        'Weimardoodle'                                => 'Weimardoodle',
        'Whoodle'                                     => 'Whoodle',
        'Yorkipoo'                                    => 'Yorkipoo',
        'Beaglier'                                    => 'Beaglier',
        'Bichpoo'                                     => 'Bichoopoo',
        'Bordoodle'                                   => 'Border Collie',
        'Boxador'                                     => 'Boxador',
        'Chiweenie'                                   => 'Chiweenie',
        'Chorkie'                                     => 'Chorkie',
        'Cockalier'                                   => 'Cockalier',
        'Corgipoo'                                    => 'Corgipoo',
        'Danoodle'                                    => 'Great Dane',
        'Doxiepoo'                                    => 'Doxiepoo',
        'Havapoo'                                     => 'Havapoo',
        'Jackapoo'                                    => 'Jackapoo',
        'Labrastaff'                                  => 'Labrastaff',
        'Poochon'                                     => 'Poochon',
        'Peekapoo'                                    => 'Peekapoo',
        'Aussalier'                                   => 'Australian Shepherd',
    ];

    return $overrides[$breedName] ?? $breedName;
}

/**
 * Fetch a breed photo from Wikipedia/Wikimedia (free, no key required).
 * Tries three sources in order and returns the first usable URL:
 *   1. MediaWiki API (pageimages) — more reliable than REST
 *   2. Wikipedia REST summary — fast fallback
 *   3. Wikimedia Commons image search — covers hybrids/rare breeds
 */
function fetchWikipediaPhoto(string $articleTitle): string
{
    // ── Source 1: MediaWiki pageimages API ───────────────────────────────────
    // Request 640px — Wikimedia returns whatever thumbnail size it has cached
    // (often larger, e.g. 960px). Use the URL as-is; do NOT force /640px- on
    // the path because many images only exist at their native cached size.
    $apiUrl = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'titles'      => $articleTitle,
        'prop'        => 'pageimages',
        'pithumbsize' => 640,
        'format'      => 'json',
        'redirects'   => '1',
    ]);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app; breed photo lookup)',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        foreach (($data['query']['pages'] ?? []) as $page) {
            $title = (string) ($page['title'] ?? '');
            $src   = (string) ($page['thumbnail']['source'] ?? '');
            // Reject list/disambiguation pages — they return a generic image
            // shared across all breeds that lack their own article (e.g. the
            // "List of dog crossbreeds" page whose thumbnail is a Labradoodle
            // assistance dog photo, wrongly applied to dozens of breeds).
            if (preg_match('/^(List of|Lists of|Crossbreed|Disambiguation)/i', $title)) {
                return '';
            }
            // Only use thumbnail URLs (contain /thumb/) — originals return 403
            if ($src !== '' && strpos($src, '/thumb/') !== false) {
                return $src;
            }
        }
    }

    // ── Source 2: Wikipedia REST summary API — always returns a real thumbnail ──
    $restUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($articleTitle);
    $ch = curl_init($restUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app; breed photo lookup)',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        // Reject if the REST summary also resolved to a list/disambiguation page
        $resolvedTitle = (string) ($data['title'] ?? '');
        if (!preg_match('/^(List of|Lists of|Crossbreed|Disambiguation)/i', $resolvedTitle)) {
            $src = (string) ($data['thumbnail']['source'] ?? '');
            if ($src !== '') {
                return $src;
            }
        }
    }

    // ── Source 3: Wikimedia Commons image search ──────────────────────────────
    $commonsUrl = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'       => 'query',
        'generator'    => 'search',
        'gsrsearch'    => $articleTitle . ' dog breed',
        'gsrnamespace' => 6,
        'gsrlimit'     => 5,
        'prop'         => 'imageinfo',
        'iiprop'       => 'url',
        'iiurlwidth'   => 640,
        'format'       => 'json',
    ]);
    $ch = curl_init($commonsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app; breed photo lookup)',
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        foreach (($data['query']['pages'] ?? []) as $page) {
            $thumb = (string) ($page['imageinfo'][0]['thumburl'] ?? '');
            if ($thumb !== '') {
                return $thumb;
            }
        }
    }

    return '';
}

/**
 * Fetch from Unsplash (free tier, requires attribution).
 * Returns ['url' => '...', 'attribution' => 'Photo by NAME on Unsplash'] or empty strings.
 */
function fetchUnsplashPhoto(string $query, string $apiKey): array
{
    $url = 'https://api.unsplash.com/search/photos?' . http_build_query([
        'query'       => $query . ' dog breed',
        'per_page'    => 5,
        'orientation' => 'squarish',
        'client_id'   => $apiKey,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        return ['url' => '', 'attribution' => ''];
    }
    $data = json_decode($raw, true);
    foreach (($data['results'] ?? []) as $photo) {
        $imgUrl = (string) ($photo['urls']['regular'] ?? '');
        if ($imgUrl === '') {
            continue;
        }
        $name  = (string) ($photo['user']['name'] ?? '');
        $attr  = $name !== '' ? 'Photo by ' . $name . ' on Unsplash' : 'Photo on Unsplash';
        return ['url' => $imgUrl, 'attribution' => $attr];
    }
    return ['url' => '', 'attribution' => ''];
}

/**
 * Fetch from Pexels (CC0 — no attribution required).
 * Returns image URL or empty string.
 */
function fetchPexelsPhoto(string $query, string $apiKey): string
{
    $url = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query'    => $query . ' dog',
        'per_page' => 5,
        'size'     => 'medium',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $apiKey],
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        return '';
    }
    $data = json_decode($raw, true);
    foreach (($data['photos'] ?? []) as $photo) {
        $imgUrl = (string) ($photo['src']['large'] ?? $photo['src']['medium'] ?? '');
        if ($imgUrl !== '') {
            return $imgUrl;
        }
    }
    return '';
}

/**
 * Returns a cached photo URL for $breedName.
 * Source priority: Dog CEO → Wikipedia (3 methods) → Unsplash → Pexels
 * Returns null when feature disabled, table missing, or no photo found anywhere.
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

    // Serve from cache
    $stmt = $pdo->prepare('SELECT image_url FROM breed_images WHERE breed_name = ? AND color_variant = \'\' LIMIT 1');
    $stmt->execute([$breedName]);
    $cached = $stmt->fetchColumn();
    if ($cached !== false) {
        return $cached !== '' ? $cached : null;
    }

    $imageUrl    = '';
    $source      = 'not_mapped';
    $attribution = null;

    // ── Source 1: Wikipedia / Wikimedia ──────────────────────────────────────
    $wikiTitle = breedWikipediaTitle($breedName);
    if ($wikiTitle !== null) {
        $imageUrl = fetchWikipediaPhoto($wikiTitle);
        if ($imageUrl !== '') {
            $source = 'wikipedia';
        }
    }

    // ── Source 2: Dog CEO ─────────────────────────────────────────────────────
    if ($imageUrl === '') {
        $slug = breedPhotoSlug($breedName);
        if ($slug !== null) {
            $ch = curl_init('https://dog.ceo/api/breed/' . $slug . '/images/random');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $raw  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw !== false && $code === 200) {
                $data = json_decode($raw, true);
                if (isset($data['status'], $data['message']) && $data['status'] === 'success' && is_string($data['message'])) {
                    $imageUrl = $data['message'];
                    $source   = 'dog_ceo';
                }
            }
        }
    }

    // ── Source 3: Unsplash ────────────────────────────────────────────────────
    if ($imageUrl === '') {
        $unsplashKey = trim((string) gpEnv('UNSPLASH_ACCESS_KEY', ''));
        if ($unsplashKey !== '') {
            $result = fetchUnsplashPhoto($breedName, $unsplashKey);
            if ($result['url'] !== '') {
                $imageUrl    = $result['url'];
                $source      = 'unsplash';
                $attribution = $result['attribution'] ?: null;
            }
        }
    }

    // ── Source 4: Pexels ──────────────────────────────────────────────────────
    if ($imageUrl === '') {
        $pexelsKey = trim((string) gpEnv('PEXELS_API_KEY', ''));
        if ($pexelsKey !== '') {
            $imageUrl = fetchPexelsPhoto($breedName, $pexelsKey);
            if ($imageUrl !== '') {
                $source = 'pexels';
            }
        }
    }

    // Only cache on success — never write empty results so future requests can retry
    // all sources (important when new API keys are added or sources improve coverage)
    if ($imageUrl !== '') {
        $pdo->prepare(
            'INSERT INTO breed_images (breed_name, color_variant, image_url, source, photo_attribution)
             VALUES (?, \'\', ?, ?, ?)
             ON CONFLICT (breed_name, color_variant)
             DO UPDATE SET image_url = EXCLUDED.image_url, source = EXCLUDED.source,
                           photo_attribution = EXCLUDED.photo_attribution, fetched_at = CURRENT_TIMESTAMP'
        )->execute([$breedName, $imageUrl, $source, $attribution]);
    }

    return $imageUrl !== '' ? $imageUrl : null;
}
