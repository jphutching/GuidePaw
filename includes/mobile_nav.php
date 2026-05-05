<?php
// Shared GuidePaw mobile/menu navigation.
// Keep this include lightweight because many app pages load it after db_connect.php.

if (!function_exists('gpNavFeatureEnabled')) {
    function gpNavFeatureEnabled(string $flagKey): bool
    {
        if (!function_exists('featureEnabled')) {
            $featureFile = __DIR__ . '/feature_flags.php';
            if (is_file($featureFile)) {
                require_once $featureFile;
            }
        }

        if (function_exists('featureEnabled') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return featureEnabled($GLOBALS['pdo'], $flagKey);
        }

        return true;
    }
}

if (!function_exists('gpNavIsAdmin')) {
    function gpNavIsAdmin(): bool
    {
        return function_exists('currentUserIsAdmin') && currentUserIsAdmin();
    }
}

if (!function_exists('gpNavLink')) {
    function gpNavLink(string $href, string $icon, string $label, ?string $flagKey = null): void
    {
        if ($flagKey !== null && !gpNavFeatureEnabled($flagKey)) {
            return;
        }
        $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $target = basename(parse_url($href, PHP_URL_PATH) ?: $href);
        $active = $current === $target ? ' active' : '';
        echo '<a class="gp-menu-link' . $active . '" href="' . e($href) . '"><span class="gp-menu-icon">' . e($icon) . '</span><span>' . e($label) . '</span></a>';
    }
}
?>
<style>
    .gp-mobile-nav-shell { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1040; pointer-events: none; }
    .gp-bottom-nav { pointer-events: auto; max-width: 760px; margin: 0 auto; background: rgba(255,255,255,.96); backdrop-filter: blur(14px); border: 1px solid rgba(15,23,42,.12); border-bottom: 0; border-radius: 22px 22px 0 0; box-shadow: 0 -10px 28px rgba(15,23,42,.18); padding: .55rem .65rem calc(.55rem + env(safe-area-inset-bottom)); display: grid; grid-template-columns: repeat(5, 1fr); gap: .35rem; }
    .gp-bottom-nav a, .gp-bottom-nav button { border: 0; background: transparent; color: #1f2937; text-decoration: none; font-size: .72rem; line-height: 1.1; font-weight: 700; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .18rem; border-radius: 14px; min-height: 48px; }
    .gp-bottom-nav a.active, .gp-bottom-nav button.active, .gp-bottom-nav a:focus, .gp-bottom-nav button:focus { background: #e8f1ff; color: #0d6efd; outline: none; }
    .gp-bottom-nav .ico { font-size: 1.22rem; line-height: 1; }
    .gp-menu-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.48); z-index: 1050; }
    .gp-menu-panel { display: none; position: fixed; left: 50%; bottom: 0; transform: translateX(-50%); z-index: 1051; width: min(720px, 100%); max-height: min(82vh, 720px); overflow: auto; background: #f8fafc; border-radius: 24px 24px 0 0; box-shadow: 0 -18px 42px rgba(15,23,42,.28); padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom)); }
    body.gp-menu-open .gp-menu-backdrop, body.gp-menu-open .gp-menu-panel { display: block; }
    body.gp-menu-open { overflow: hidden; }
    .gp-menu-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; }
    .gp-menu-title { font-weight: 850; font-size: 1.15rem; color: #111827; }
    .gp-menu-close { border: 0; background: #e5e7eb; color: #111827; border-radius: 999px; padding: .45rem .75rem; font-weight: 800; }
    .gp-menu-section { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 18px; margin-bottom: .75rem; overflow: hidden; }
    .gp-menu-section summary { cursor: pointer; list-style: none; padding: .9rem 1rem; font-weight: 850; color: #111827; display: flex; align-items: center; justify-content: space-between; }
    .gp-menu-section summary::-webkit-details-marker { display: none; }
    .gp-menu-section summary::after { content: '⌄'; color: #6b7280; font-size: 1.15rem; transition: transform .15s ease; }
    .gp-menu-section[open] summary::after { transform: rotate(180deg); }
    .gp-menu-link-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem; padding: 0 .85rem .9rem; }
    .gp-menu-link { display: flex; align-items: center; gap: .55rem; text-decoration: none; color: #1f2937; background: #f8fafc; border: 1px solid rgba(15,23,42,.08); border-radius: 14px; padding: .75rem; font-weight: 750; min-height: 48px; }
    .gp-menu-link.active { background: #e8f1ff; border-color: #bfdbfe; color: #0d6efd; }
    .gp-menu-icon { font-size: 1.2rem; width: 1.4rem; text-align: center; }
    @media (min-width: 720px) { .gp-menu-link-grid { grid-template-columns: repeat(3, 1fr); } }
    @media print { .gp-mobile-nav-shell, .gp-menu-backdrop, .gp-menu-panel { display: none !important; } }
</style>

<div class="gp-mobile-nav-shell">
    <nav class="gp-bottom-nav" aria-label="Primary navigation">
        <a href="index.php" class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' ? 'active' : '' ?>"><span class="ico">🏠</span><span>Home</span></a>
        <a href="quick_log.php"><span class="ico">⚡</span><span>Log</span></a>
        <a href="view_logs.php"><span class="ico">📋</span><span>History</span></a>
        <a href="alerts.php"><span class="ico">🧠</span><span>Alerts</span></a>
        <button type="button" id="gpMenuOpen" aria-controls="gpAppMenu" aria-expanded="false"><span class="ico">☰</span><span>Menu</span></button>
    </nav>
</div>

<div class="gp-menu-backdrop" id="gpMenuBackdrop" aria-hidden="true"></div>
<aside class="gp-menu-panel" id="gpAppMenu" aria-label="GuidePaw menu">
    <div class="gp-menu-header">
        <div>
            <div class="gp-menu-title">GuidePaw Menu</div>
            <div class="text-muted small">Grouped tools and settings</div>
        </div>
        <button type="button" class="gp-menu-close" id="gpMenuClose">Close</button>
    </div>

    <details class="gp-menu-section" open>
        <summary>🐾 Dogs & Dashboard</summary>
        <div class="gp-menu-link-grid">
            <?php gpNavLink('index.php', '🏠', 'Dashboard'); ?>
            <?php gpNavLink('dogs.php', '🐕', 'Dogs'); ?>
            <?php gpNavLink('dog_profile.php', '🪪', 'Dog Profile'); ?>
            <?php gpNavLink('stats.php', '📊', 'Stats'); ?>
            <?php gpNavLink('settings.php', '⚙️', 'Settings'); ?>
            <?php gpNavLink('feedback.php', '💬', 'Feedback / Bug Report'); ?>
        </div>
    </details>

    <details class="gp-menu-section">
        <summary>🎓 Training & Logs</summary>
        <div class="gp-menu-link-grid">
            <?php gpNavLink('quick_log.php', '⚡', 'Quick Session', 'quick_session_enabled'); ?>
            <?php gpNavLink('log_entry.php', '📝', 'Detailed Log', 'detailed_log_enabled'); ?>
            <?php gpNavLink('view_logs.php', '📋', 'History'); ?>
            <?php gpNavLink('training_program.php', '🎓', 'Training', 'training_program_enabled'); ?>
            <?php gpNavLink('candidate_assessment.php', '🐾', 'Candidate Assessment', 'candidate_scoring_enabled'); ?>
            <?php gpNavLink('training_goal_intake.php', '🎯', 'Goal Intake', 'goal_intake_enabled'); ?>
            <?php gpNavLink('habit_repair.php', '🛠️', 'Habit Repair', 'habit_repair_enabled'); ?>
            <?php gpNavLink('training_session_log.php', '✅', 'Session Log', 'training_progression_enabled'); ?>
            <?php gpNavLink('training_history.php', '📚', 'Training History', 'training_progression_enabled'); ?>
        </div>
    </details>

    <details class="gp-menu-section">
        <summary>🩺 Health & Care</summary>
        <div class="gp-menu-link-grid">
            <?php gpNavLink('dog_health.php', '🩺', 'Health Docs', 'health_docs_enabled'); ?>
            <?php gpNavLink('appointments.php', '📅', 'Vet Appointments', 'vet_appointments_enabled'); ?>
            <?php gpNavLink('medications.php', '💊', 'Medications', 'medications_enabled'); ?>
            <?php gpNavLink('alerts.php', '🧠', 'Smart Alerts', 'alerts_enabled'); ?>
        </div>
    </details>

    <details class="gp-menu-section">
        <summary>🪪 Access & Certification</summary>
        <div class="gp-menu-link-grid">
            <?php gpNavLink('ada_access_card.php', '🪪', 'ADA Access Card', 'ada_wallet_enabled'); ?>
            <?php gpNavLink('service_dog_rights.php', '⚖️', 'Detailed ADA Notes'); ?>
            <?php gpNavLink('certification.php', '✅', 'Certification', 'certification_enabled'); ?>
        </div>
    </details>

    <?php if (gpNavIsAdmin()): ?>
        <details class="gp-menu-section">
            <summary>🛠️ Admin</summary>
            <div class="gp-menu-link-grid">
                <?php gpNavLink('admin.php', '🛠️', 'Admin Home'); ?>
                <?php gpNavLink('admin_beta_requests.php', '📨', 'Beta Requests'); ?>
                <?php gpNavLink('admin_notification_test.php', '🔔', 'Notification Test'); ?>
                <?php gpNavLink('admin_feedback.php', '💬', 'Feedback Reports'); ?>
                <?php gpNavLink('admin_users.php', '👥', 'User Management'); ?>
                <?php gpNavLink('admin_feature_roadmap.php', '🗺️', 'Feature Roadmap'); ?>
                <?php gpNavLink('api_tokens.php', '🔐', 'API Tokens'); ?>
                <?php gpNavLink('db_status.php', '🩺', 'System Health'); ?>
                <?php gpNavLink('backup.php', '💾', 'Backup Tools', 'backup_tools_enabled'); ?>
            </div>
        </details>
    <?php endif; ?>
</aside>

<script>
(function () {
    var openBtn = document.getElementById('gpMenuOpen');
    var closeBtn = document.getElementById('gpMenuClose');
    var backdrop = document.getElementById('gpMenuBackdrop');

    function openMenu() {
        document.body.classList.add('gp-menu-open');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        document.body.classList.remove('gp-menu-open');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
    }

    if (openBtn) openBtn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });
})();
</script>
