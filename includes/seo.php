<?php
require_once __DIR__ . '/app_config.php';

if (!function_exists('guidepawSeoBaseUrl')) {
    function guidepawSeoBaseUrl(): string
    {
        $base = trim((string) appUrl());
        return $base !== '' ? rtrim($base, '/') : 'https://guidepaw.app';
    }
}

if (!function_exists('guidepawSeoAbsoluteUrl')) {
    function guidepawSeoAbsoluteUrl(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '') {
            return guidepawSeoBaseUrl();
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        return rtrim(guidepawSeoBaseUrl(), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('guidepawSeoMeasurementId')) {
    function guidepawSeoMeasurementId(): string
    {
        foreach (['GUIDEPAW_GA4_MEASUREMENT_ID', 'GOOGLE_ANALYTICS_MEASUREMENT_ID', 'GA4_MEASUREMENT_ID'] as $key) {
            $value = trim((string) gpEnv($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('guidepawSeoHead')) {
    function guidepawSeoHead(array $options = []): void
    {
        $title = trim((string) ($options['title'] ?? appShortName()));
        $description = trim((string) ($options['description'] ?? ''));
        $robots = trim((string) ($options['robots'] ?? 'index,follow'));
        $canonical = trim((string) ($options['canonical'] ?? ''));
        $type = trim((string) ($options['type'] ?? 'website')) ?: 'website';
        $siteName = trim((string) ($options['site_name'] ?? appShortName())) ?: appShortName();
        $image = trim((string) ($options['image'] ?? ''));
        $measurementId = guidepawSeoMeasurementId();
        $jsonLd = $options['json_ld'] ?? [];
        $skipCanonical = stripos($robots, 'noindex') !== false || stripos($robots, 'none') !== false;

        if ($title !== '') {
            echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . PHP_EOL;
        }
        if ($description !== '') {
            echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        if ($robots !== '') {
            echo '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        if (!$skipCanonical) {
            if ($canonical === '') {
                $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
                $path = $requestUri !== '' ? parse_url($requestUri, PHP_URL_PATH) : '';
                $canonical = guidepawSeoAbsoluteUrl(is_string($path) && $path !== '' ? $path : '');
            }
            if ($canonical !== '') {
                echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
            }
        }

        echo '<meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        echo '<meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        if ($title !== '') {
            echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        if ($description !== '') {
            echo '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        if (!$skipCanonical) {
            $ogUrl = $canonical !== '' ? $canonical : guidepawSeoAbsoluteUrl((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
            if ($ogUrl !== '') {
                echo '<meta property="og:url" content="' . htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
            }
        }
        if ($image !== '') {
            echo '<meta property="og:image" content="' . htmlspecialchars(guidepawSeoAbsoluteUrl($image), ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
            echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
            echo '<meta name="twitter:image" content="' . htmlspecialchars(guidepawSeoAbsoluteUrl($image), ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        } else {
            echo '<meta name="twitter:card" content="summary">' . PHP_EOL;
        }
        if ($title !== '') {
            echo '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        if ($description !== '') {
            echo '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }

        if (is_array($jsonLd) && $jsonLd !== []) {
            $blocks = isset($jsonLd[0]) && is_array($jsonLd[0]) ? $jsonLd : [$jsonLd];
            foreach ($blocks as $block) {
                if (!is_array($block) || $block === []) {
                    continue;
                }
                $encoded = json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                if ($encoded !== false) {
                    echo '<script type="application/ld+json">' . $encoded . '</script>' . PHP_EOL;
                }
            }
        }

        // ── Search engine site verification tags ──────────────────────────────
        // Set these env vars once you verify ownership in each webmaster console.
        // Bing Webmaster Tools → https://www.bing.com/webmasters (covers Yahoo, DuckDuckGo, Ecosia)
        $bingVerification = trim((string) gpEnv('GUIDEPAW_BING_VERIFICATION', ''));
        if ($bingVerification !== '') {
            echo '<meta name="msvalidate.01" content="' . htmlspecialchars($bingVerification, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        // Yandex Webmaster → https://webmaster.yandex.com
        $yandexVerification = trim((string) gpEnv('GUIDEPAW_YANDEX_VERIFICATION', ''));
        if ($yandexVerification !== '') {
            echo '<meta name="yandex-verification" content="' . htmlspecialchars($yandexVerification, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
        // Brave Search → https://search.brave.com/webmaster (uses HTML file upload, not a meta tag)

        if ($measurementId !== '') {
            $gaId = htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8');
            echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $gaId . '"></script>' . PHP_EOL;
            echo "<script>\n";
            echo "window.dataLayer = window.dataLayer || [];\n";
            echo "function gtag(){dataLayer.push(arguments);}\n";
            echo "gtag('js', new Date());\n";
            echo "gtag('config', '{$gaId}', { send_page_view: true });\n";
            echo "</script>\n";
        }
    }
}
