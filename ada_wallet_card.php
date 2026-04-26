<?php
require_once 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'ada_wallet_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$user = getUserRecord($pdo, $userId);

$dogName = $dog['name'] ?? ($_SESSION['dog_name'] ?? 'Your dog');
$handlerName = $user['username'] ?? 'Handler';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ADA Wallet Card</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        :root {
            --wallet-bg-1: #020617;
            --wallet-bg-2: #0f172a;
            --wallet-panel: rgba(15, 23, 42, 0.92);
            --wallet-panel-2: rgba(9, 15, 28, 0.96);
            --wallet-text: #f8fafc;
            --wallet-muted: #cbd5e1;
            --wallet-border: rgba(255,255,255,0.12);
            --wallet-accent: #16a34a;
            --wallet-accent-2: #38bdf8;
            --wallet-soft: rgba(56, 189, 248, 0.1);
            --wallet-shadow: 0 18px 40px rgba(0,0,0,0.28);
        }

        body.wallet-page {
            background: linear-gradient(180deg, var(--wallet-bg-1) 0%, var(--wallet-bg-2) 100%);
            color: var(--wallet-text);
            min-height: 100vh;
        }

        body.wallet-page.wallet-high-contrast {
            --wallet-bg-1: #000;
            --wallet-bg-2: #000;
            --wallet-panel: #000;
            --wallet-panel-2: #000;
            --wallet-text: #fff;
            --wallet-muted: #fff;
            --wallet-border: #fff;
            --wallet-accent: #00ff66;
            --wallet-accent-2: #00e5ff;
            --wallet-soft: rgba(255,255,255,0.08);
            --wallet-shadow: none;
        }

        body.wallet-page.wallet-screenshot {
            --wallet-bg-1: #f8fafc;
            --wallet-bg-2: #eef2f7;
            --wallet-panel: #ffffff;
            --wallet-panel-2: #ffffff;
            --wallet-text: #0f172a;
            --wallet-muted: #475569;
            --wallet-border: rgba(15,23,42,0.12);
            --wallet-accent: #166534;
            --wallet-accent-2: #0f766e;
            --wallet-soft: rgba(15,23,42,0.04);
            --wallet-shadow: 0 10px 24px rgba(15,23,42,0.08);
        }

        .wallet-shell {
            max-width: 820px;
            margin: 0 auto;
            padding: 1rem 1rem calc(8.75rem + env(safe-area-inset-bottom));
        }

        .wallet-card {
            background: var(--wallet-panel);
            border: 1px solid var(--wallet-border);
            border-radius: 24px;
            box-shadow: var(--wallet-shadow);
            padding: 1.1rem;
        }

        .wallet-hero {
            background:
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.14), transparent 32%),
                radial-gradient(circle at top left, rgba(22, 163, 74, 0.14), transparent 28%),
                var(--wallet-panel-2);
        }

        .wallet-kicker {
            font-size: 0.9rem;
            color: var(--wallet-muted);
            margin-bottom: 0.35rem;
        }

        .wallet-title {
            font-size: clamp(2rem, 6vw, 3rem);
            line-height: 1;
            font-weight: 800;
            margin: 0 0 0.45rem 0;
            letter-spacing: -0.03em;
        }

        .wallet-subtitle {
            color: var(--wallet-muted);
            margin: 0;
            max-width: 48rem;
        }

        .wallet-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .wallet-actions .wallet-primary {
            grid-column: 1 / -1;
        }

        .wallet-actions .btn,
        .wallet-utility .btn {
            border-radius: 18px;
            font-weight: 700;
            padding: 0.9rem 1rem;
        }

        .wallet-actions .btn-outline-light,
        .wallet-utility .btn-outline-light {
            border-color: rgba(255,255,255,0.18);
            color: var(--wallet-text);
            background: rgba(255,255,255,0.02);
        }

        .wallet-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }

        .wallet-grid.two-col {
            grid-template-columns: 1fr;
        }

        .wallet-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--wallet-accent-2);
            font-weight: 800;
            margin-bottom: 0.7rem;
        }

        .wallet-statement {
            font-size: clamp(1.35rem, 4.8vw, 2rem);
            line-height: 1.35;
            font-weight: 800;
            margin: 0;
        }

        .wallet-qa {
            margin: 0;
            padding-left: 1.25rem;
            font-size: clamp(1.08rem, 3.8vw, 1.35rem);
            line-height: 1.55;
            font-weight: 700;
        }

        .wallet-qa li + li {
            margin-top: 0.9rem;
        }

        .wallet-list {
            margin: 0;
            padding-left: 1.15rem;
        }

        .wallet-list li + li {
            margin-top: 0.55rem;
        }

        .wallet-note {
            border-left: 4px solid var(--wallet-accent);
            padding: 0.85rem 0.95rem;
            background: var(--wallet-soft);
            border-radius: 16px;
            color: var(--wallet-text);
        }

        .wallet-help-number {
            font-size: clamp(1.6rem, 5vw, 2.3rem);
            font-weight: 800;
            line-height: 1.1;
            margin: 0.25rem 0 0.35rem;
        }

        .wallet-help-meta {
            color: var(--wallet-muted);
            margin: 0;
        }

        .wallet-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.85rem;
            color: var(--wallet-muted);
            font-size: 0.92rem;
        }

        .wallet-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid var(--wallet-border);
            background: rgba(255,255,255,0.03);
            border-radius: 999px;
            padding: 0.4rem 0.8rem;
        }

        .wallet-utility {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        body.wallet-page.wallet-lockscreen .beta-banner,
        body.wallet-page.wallet-lockscreen .gp-mobile-nav-shell,
        body.wallet-page.wallet-lockscreen .wallet-subtitle,
        body.wallet-page.wallet-lockscreen .wallet-utility {
            display: none !important;
        }

        body.wallet-page.wallet-lockscreen .wallet-shell {
            max-width: 100%;
            padding: calc(1rem + env(safe-area-inset-top)) 1rem calc(1.5rem + env(safe-area-inset-bottom));
        }

        body.wallet-page.wallet-lockscreen .wallet-card {
            border-radius: 18px;
        }

        body.wallet-page.wallet-screenshot .beta-banner,
        body.wallet-page.wallet-screenshot .gp-mobile-nav-shell {
            display: none !important;
        }

        @media (min-width: 720px) {
            .wallet-grid.two-col {
                grid-template-columns: 1fr 1fr;
            }
            .wallet-utility {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media print {
            .beta-banner,
            .gp-mobile-nav-shell,
            .wallet-actions {
                display: none !important;
            }

            body.wallet-page {
                background: #fff !important;
                color: #000 !important;
            }

            .wallet-shell {
                max-width: 100%;
                padding: 0;
            }

            .wallet-card {
                box-shadow: none !important;
                border-color: #d4d4d8 !important;
                background: #fff !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="wallet-page pb-5">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wallet-shell">
    <section class="wallet-card wallet-hero">
        <div class="wallet-kicker">Ultra-compact access reference</div>
        <h1 class="wallet-title">ADA Wallet Card</h1>
        <p class="wallet-subtitle">
            Fast, readable public-access reminders for <?= e($dogName) ?>.
            Use this page live, in screenshot mode, or as a print-ready reference.
        </p>

        <div class="wallet-actions">
            <button type="button" class="btn btn-success btn-lg wallet-primary" id="lockscreenToggle">Enter lockscreen mode</button>
            <button type="button" class="btn btn-outline-light" id="contrastToggle">High contrast</button>
            <button type="button" class="btn btn-outline-light" id="screenshotToggle">Screenshot-ready</button>
        </div>

        <div class="wallet-meta">
            <span class="wallet-pill">Handler: <?= e($handlerName) ?></span>
            <span class="wallet-pill">Dog: <?= e($dogName) ?></span>
        </div>
    </section>

    <section class="wallet-grid" style="margin-top:1rem;">
        <div class="wallet-card">
            <div class="wallet-label">Calm script</div>
            <p class="wallet-statement">“This is my service dog. You may ask whether the dog is required because of a disability and what work or task the dog is trained to perform.”</p>
        </div>
    </section>

    <section class="wallet-grid two-col">
        <div class="wallet-card">
            <div class="wallet-label">Only two questions</div>
            <ol class="wallet-qa">
                <li>Is the dog required because of a disability?</li>
                <li>What work or task is it trained to perform?</li>
            </ol>
        </div>

        <div class="wallet-card">
            <div class="wallet-label">Not required</div>
            <ul class="wallet-list">
                <li>Certification or registry papers</li>
                <li>A special ID card or proof of training</li>
                <li>Medical records or diagnosis details</li>
                <li>A task demonstration on demand</li>
            </ul>
        </div>
    </section>

    <section class="wallet-grid">
        <div class="wallet-card">
            <div class="wallet-label">Quick reminders</div>
            <div class="wallet-note">
                Access decisions should be based on the dog’s actual behavior and control, not on stereotypes, missing paperwork, or the fact that the dog is not wearing a vest.
            </div>
        </div>

        <div class="wallet-card">
            <div class="wallet-label">Need help now?</div>
            <div class="wallet-help-number">800-514-0301</div>
            <p class="wallet-help-meta">DOJ ADA Information Line · TTY 833-610-1264</p>
        </div>
    </section>

    <section class="wallet-utility">
        <button type="button" class="btn btn-outline-light" id="printBtn">Print / Save PDF</button>
        <a href="service_dog_rights.php" class="btn btn-outline-light">Detailed ADA notes</a>
        <a href="settings.php" class="btn btn-outline-light">Settings</a>
    </section>
</div>

<script>
(function () {
    var body = document.body;
    var lockscreenBtn = document.getElementById('lockscreenToggle');
    var contrastBtn = document.getElementById('contrastToggle');
    var screenshotBtn = document.getElementById('screenshotToggle');
    var printBtn = document.getElementById('printBtn');

    if (lockscreenBtn) {
        lockscreenBtn.addEventListener('click', function () {
            body.classList.toggle('wallet-lockscreen');
            lockscreenBtn.textContent = body.classList.contains('wallet-lockscreen')
                ? 'Exit lockscreen mode'
                : 'Enter lockscreen mode';
        });
    }

    if (contrastBtn) {
        contrastBtn.addEventListener('click', function () {
            body.classList.toggle('wallet-high-contrast');
            contrastBtn.classList.toggle('btn-light');
            contrastBtn.classList.toggle('btn-outline-light');
        });
    }

    if (screenshotBtn) {
        screenshotBtn.addEventListener('click', function () {
            body.classList.toggle('wallet-screenshot');
            screenshotBtn.classList.toggle('btn-light');
            screenshotBtn.classList.toggle('btn-outline-light');
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }
})();
</script>
</body>
</html>
