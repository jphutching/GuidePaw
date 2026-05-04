<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$activeDogName = $_SESSION['active_dog_name'] ?? ($_SESSION['dog_name'] ?? '');

if (!function_exists('featureEnabled')) {
    require_once __DIR__ . '/feature_flags.php';
}

$navItems = [
    ['href' => 'index.php', 'label' => 'Home', 'emoji' => '🏠', 'match' => ['index.php']],
    ['href' => 'dogs.php', 'label' => 'Dogs', 'emoji' => '🐕', 'match' => ['dogs.php','dog_profile.php','profile.php','collaboration.php']],
    ['href' => 'quick_log.php', 'label' => 'Log', 'emoji' => '⚡', 'match' => ['quick_log.php','log_entry.php','view_logs.php','stats.php']],
    ['href' => 'training_program.php', 'label' => 'Training', 'emoji' => '🎓', 'match' => ['training_program.php','training_goal_intake.php','training_session_log.php','candidate_assessment.php','habit_repair.php','training_history.php']],
    ['href' => 'dog_health.php', 'label' => 'Health', 'emoji' => '🩺', 'match' => ['dog_health.php','appointments.php','medications.php','alerts.php','certification.php']],
    ['href' => 'settings.php', 'label' => 'Settings', 'emoji' => '⚙️', 'match' => ['settings.php','edit_profile.php','setup_2fa.php']],
];

if (function_exists('featureEnabled') && isset($pdo) && !featureEnabled($pdo, 'quick_session_enabled')) {
    $navItems = array_values(array_filter($navItems, fn($item) => ($item['href'] ?? '') !== 'quick_log.php'));
}

if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()) {
    if (
        function_exists('featureEnabled') &&
        isset($pdo) &&
        featureEnabled($pdo, 'backup_tools_enabled')
    ) {
        $navItems[] = ['href' => 'backup.php', 'label' => 'Backup', 'emoji' => '💾', 'match' => ['backup.php','import_backup.php','export_backup.php']];
    }

    $navItems[] = ['href' => 'admin.php', 'label' => 'Admin', 'emoji' => '🛠️', 'match' => ['admin.php','admin_feedback.php','admin_feature_roadmap.php','admin_audit_log.php','admin_beta_requests.php','admin_users.php','db_status.php','api_tokens.php']];
}

    ['href' => 'feedback.php', 'label' => 'Feedback', 'emoji' => '💬', 'match' => ['feedback.php']],

$navItems[] = ['href' => 'logout.php', 'label' => 'Logout', 'emoji' => '🚪', 'match' => ['logout.php']];

$primaryTabs = [
    ['href' => 'index.php', 'label' => 'Home', 'emoji' => '🏠', 'match' => ['index.php']],
    ['href' => 'dogs.php', 'label' => 'Dogs', 'emoji' => '🐕', 'match' => ['dogs.php','dog_profile.php','profile.php','collaboration.php']],
    ['href' => 'training_program.php', 'label' => 'Training', 'emoji' => '🎓', 'match' => ['training_program.php','training_goal_intake.php','training_session_log.php','candidate_assessment.php','habit_repair.php','training_history.php']],
];

$primaryActive = false;
foreach ($primaryTabs as $tab) {
    if (in_array($currentPage, $tab['match'], true)) {
        $primaryActive = true;
        break;
    }
}
?>


<div class="gp-mobile-nav-shell">
    <div class="gp-offcanvas-backdrop" data-gp-menu-close hidden></div>
    <aside class="gp-offcanvas" aria-hidden="true" data-gp-menu-panel>
        <div class="gp-offcanvas-header">
            <div>
                <div class="gp-offcanvas-title">GuidePaw Menu</div>
                <div class="gp-offcanvas-subtitle">
                    <?= htmlspecialchars($activeDogName !== '' ? ('Active dog: ' . $activeDogName) : 'Ready to navigate', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-gp-menu-close>Close</button>
        </div>
        <nav class="gp-offcanvas-links">
            <?php foreach ($navItems as $item): $isActive = in_array($currentPage, $item['match'], true); $isDisabled = !empty($item['disabled']); ?>
                <a href="<?= $isDisabled ? '#' : htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="gp-offcanvas-link <?= $isActive ? 'active' : '' ?> <?= $isDisabled ? 'disabled opacity-75' : '' ?>" <?= $isDisabled ? 'aria-disabled="true" onclick="return false;"' : '' ?>>
                    <span class="gp-link-emoji"><?= htmlspecialchars($item['emoji'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?><?= $isDisabled ? '<br><small>Coming soon</small>' : '' ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <nav class="gp-bottom-nav" aria-label="Primary">
        <?php foreach ($primaryTabs as $tab): $isActive = in_array($currentPage, $tab['match'], true); $isDisabled = !empty($tab['disabled']); ?>
            <a href="<?= $isDisabled ? '#' : htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') ?>" class="gp-bottom-link <?= $isActive ? 'active' : '' ?> <?= $isDisabled ? 'disabled opacity-75' : '' ?>" <?= $isDisabled ? 'aria-disabled="true" onclick="return false;"' : '' ?>>
                <span class="gp-link-emoji"><?= htmlspecialchars($tab['emoji'], ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?><?= $isDisabled ? '<br><small>Soon</small>' : '' ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" class="gp-bottom-link <?= $primaryActive ? '' : 'active' ?>" data-gp-menu-open>
            <span class="gp-link-emoji">☰</span>
            <span>More</span>
        </button>
    </nav>
</div>
<script>
(function () {
    var panel = document.querySelector('[data-gp-menu-panel]');
    var backdrop = document.querySelector('.gp-offcanvas-backdrop');
    if (!panel || !backdrop) return;
    function openMenu() {
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        backdrop.hidden = false;
        document.body.classList.add('gp-menu-open');
    }
    function closeMenu() {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        backdrop.hidden = true;
        document.body.classList.remove('gp-menu-open');
    }
    document.querySelectorAll('[data-gp-menu-open]').forEach(function (el) {
        el.addEventListener('click', openMenu);
    });
    document.querySelectorAll('[data-gp-menu-close]').forEach(function (el) {
        el.addEventListener('click', closeMenu);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });
})();
</script>
