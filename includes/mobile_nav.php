<?php
// Shared GuidePaw mobile/menu navigation.

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

if (!function_exists('gpNavUnreadNotificationCount')) {
    function gpNavUnreadNotificationCount(): int
    {
        if (!isset($_SESSION['user_id']) || !isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return 0;
        }

        if (!function_exists('gpUnreadNotificationCount')) {
            $notificationHelper = __DIR__ . '/notifications.php';
            if (is_file($notificationHelper)) {
                require_once $notificationHelper;
            }
        }

        return function_exists('gpUnreadNotificationCount') ? gpUnreadNotificationCount($GLOBALS['pdo'], (int) $_SESSION['user_id']) : 0;
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
    .gp-bottom-nav { pointer-events: auto; max-width: 760px; margin: 0 auto; background: rgba(255,255,255,.97); backdrop-filter: blur(14px); border: 1px solid rgba(15,23,42,.12); border-bottom: 0; border-radius: 22px 22px 0 0; box-shadow: 0 -10px 28px rgba(15,23,42,.18); padding: .55rem .65rem calc(.55rem + env(safe-area-inset-bottom)); display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .35rem; }
    .gp-bottom-nav a, .gp-bottom-nav button { border: 0; background: transparent; color: #1f2937; text-decoration: none; font-size: .72rem; line-height: 1.1; font-weight: 750; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .18rem; border-radius: 14px; min-height: 48px; min-width:0; overflow:hidden; text-overflow:ellipsis; }
    .gp-bottom-nav a.active, .gp-bottom-nav button.active, .gp-bottom-nav a:focus, .gp-bottom-nav button:focus { background: #e8f1ff; color: #0d6efd; outline: none; }
    .gp-bottom-nav .ico { font-size: 1.22rem; line-height: 1; }
    .gp-menu-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.48); z-index: 1050; }
    .gp-menu-panel { display: none; position: fixed; left: 50%; bottom: 0; transform: translateX(-50%); z-index: 1051; width: min(760px, 100%); max-width: 100vw; max-height: min(84vh, 760px); overflow: auto; overscroll-behavior: contain; background: #f8fafc; border-radius: 24px 24px 0 0; box-shadow: 0 -18px 42px rgba(15,23,42,.28); padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom)); }
    body.gp-menu-open .gp-menu-backdrop, body.gp-menu-open .gp-menu-panel { display: block; }
    body.gp-menu-open { overflow: hidden; }
    .gp-menu-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; flex-wrap:wrap; }
    .gp-menu-title { font-weight: 900; font-size: 1.2rem; color: #111827; }
    .gp-menu-close { border: 0; background: #e5e7eb; color: #111827; border-radius: 999px; padding: .45rem .75rem; font-weight: 850; }
    .gp-menu-root-actions { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:.6rem; margin-bottom:.7rem; }
    .gp-menu-root-actions a { display:flex; align-items:center; justify-content:center; gap:.45rem; border-radius:16px; padding:.8rem; font-weight:850; text-decoration:none; min-width:0; overflow-wrap:anywhere; }
    .gp-menu-root-actions .settings { background:#e8f1ff; color:#0d6efd; border:1px solid #bfdbfe; }
    .gp-menu-root-actions .logout { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
    .gp-menu-search { display:flex; align-items:center; gap:.5rem; margin:0 0 .85rem; }
    .gp-menu-search input { flex:1; border:1px solid rgba(15,23,42,.16); border-radius:14px; padding:.72rem .85rem; font-weight:700; min-width:0; }
    .gp-menu-search button { border:0; border-radius:14px; padding:.72rem .9rem; font-weight:850; background:#e5e7eb; color:#111827; }
    .gp-menu-section { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 18px; margin-bottom: .75rem; overflow: hidden; }
    .gp-menu-section summary { cursor: pointer; list-style: none; padding: .9rem 1rem .35rem; font-weight: 900; color: #111827; display: flex; align-items: center; justify-content: space-between; gap:.75rem; }
    .gp-menu-section summary::-webkit-details-marker { display: none; }
    .gp-menu-section summary::after { content: '⌄'; color: #6b7280; font-size: 1.15rem; transition: transform .15s ease; flex:0 0 auto; }
    .gp-menu-section[open] summary::after { transform: rotate(180deg); }
    .gp-menu-description { color: #6b7280; font-size: .82rem; padding: 0 1rem .75rem; margin-top: -.15rem; overflow-wrap:anywhere; }
    .gp-menu-link-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; padding: 0 .85rem .9rem; }
    .gp-menu-link { display: flex; align-items: center; gap: .55rem; text-decoration: none; color: #1f2937; background: #f8fafc; border: 1px solid rgba(15,23,42,.08); border-radius: 14px; padding: .75rem; font-weight: 780; min-height: 48px; min-width:0; overflow-wrap:anywhere; }
    .gp-menu-link.active { background: #e8f1ff; border-color: #bfdbfe; color: #0d6efd; }
    .gp-menu-icon { font-size: 1.2rem; width: 1.4rem; text-align: center; flex:0 0 auto; }
    .gp-nav-icon-wrap { position: relative; display: inline-flex; align-items: center; justify-content: center; }
    .gp-nav-badge { position: absolute; top: -6px; right: -10px; min-width: 1.15rem; height: 1.15rem; padding: 0 .28rem; border-radius: 999px; background: #0d6efd; color: #fff; font-size: .64rem; line-height: 1; font-weight: 900; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 0 0 2px #fff; }
    .gp-nav-badge.is-zero { background: #94a3b8; }
    @media (max-width: 420px) { .gp-menu-root-actions, .gp-menu-link-grid { grid-template-columns: 1fr; } .gp-bottom-nav a, .gp-bottom-nav button { font-size:.66rem; } }
    @media (min-width: 720px) { .gp-menu-link-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 900px) {
        html, body { max-width: 100%; overflow-x: hidden; }
        body { overflow-wrap: anywhere; word-break: normal; }
        main, .container, .container-fluid, .wrap, .card, .card-body, .cardx, form, fieldset, .row, [class*="col-"] { min-width: 0; max-width: 100%; }
        img, video, canvas, iframe, svg { max-width: 100%; height: auto; }
        input, textarea, select, button, .btn { max-width: 100%; min-width: 0; white-space: normal; overflow-wrap: anywhere; }
        a { overflow-wrap: anywhere; word-break: break-word; }
        .d-flex, .row, .top, .filters, .nav { min-width: 0; flex-wrap: wrap; }
        .list-group-item { min-width: 0; flex-wrap: wrap; }
        table { width: 100% !important; max-width: 100% !important; min-width: 0 !important; table-layout: fixed; border-collapse: collapse; }
        th, td { max-width: 100%; min-width: 0 !important; white-space: normal !important; overflow-wrap: anywhere; word-break: break-word; }
        td form, td .btn, td button { width: 100%; margin-top: .25rem; }
        [style*="min-width"] { min-width: 0 !important; }
    }
    @media (max-width: 900px) {
        table, thead, tbody, tfoot, tr, th, td { display: block; width: 100% !important; }
        thead tr { border-bottom: 2px solid rgba(15,23,42,.18); }
        tr { border-bottom: 1px solid rgba(15,23,42,.14); padding: .35rem 0; }
        th, td { border-left: 0 !important; border-right: 0 !important; }
        td form, td .d-flex, td .row { display: flex; flex-direction: column; gap: .35rem; align-items: stretch !important; }
        td input, td textarea, td select, td button, td .btn { width: 100% !important; }
    }
    @media print { .gp-mobile-nav-shell, .gp-menu-backdrop, .gp-menu-panel { display: none !important; } }
</style>

<?php $gpUnreadCount = gpNavUnreadNotificationCount(); ?>
<div class="gp-mobile-nav-shell"><nav class="gp-bottom-nav" aria-label="Primary navigation"><a href="index.php" class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' ? 'active' : '' ?>"><span class="ico">🏠</span><span>Home</span></a><a href="quick_log.php" class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'quick_log.php' ? 'active' : '' ?>"><span class="ico">⚡</span><span>Log</span></a><a href="view_logs.php" class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'view_logs.php' ? 'active' : '' ?>"><span class="ico">📋</span><span>History</span></a><a href="notifications.php" class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'notifications.php' ? 'active' : '' ?>"><span class="gp-nav-icon-wrap"><span class="ico">🔔</span><span class="gp-nav-badge<?= $gpUnreadCount === 0 ? ' is-zero' : '' ?>"><?= (int) $gpUnreadCount ?></span></span><span>Alerts</span></a><button type="button" id="gpMenuOpen" aria-controls="gpAppMenu" aria-expanded="false"><span class="ico">☰</span><span>Menu</span></button></nav></div>

<div class="gp-menu-backdrop" id="gpMenuBackdrop" aria-hidden="true"></div>
<aside class="gp-menu-panel" id="gpAppMenu" aria-label="GuidePaw menu">
    <div class="gp-menu-header"><div><div class="gp-menu-title">GuidePaw Menu</div><div class="text-muted small">Tools grouped by job, not by feature list.</div></div><button type="button" class="gp-menu-close" id="gpMenuClose">Close</button></div>
    <div class="gp-menu-search"><input type="search" id="gpMenuSearch" placeholder="Search pages, tools, or training tracks" aria-label="Search menu"><button type="button" id="gpMenuSearchClear">Clear</button></div>
    <details class="gp-menu-section" open data-default-open="1"><summary>🐾 Dog</summary><div class="gp-menu-description">Manage the active dog, handler profile, dog details, and progress snapshot.</div><div class="gp-menu-link-grid"><?php gpNavLink('index.php', '🏠', 'Dashboard'); ?><?php gpNavLink('handler_profile.php', '👤', 'Handler Profile'); ?><?php gpNavLink('dogs.php', '🐕', 'Dogs'); ?><?php gpNavLink('dog_profile.php', '🪪', 'Dog Profile'); ?><?php gpNavLink('dog_access.php', '🤝', 'Dog Access'); ?><?php gpNavLink('dog_access_audit.php', '🧾', 'Dog Audit'); ?><?php gpNavLink('stats.php', '📊', 'Stats'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>🔔 Notifications</summary><div class="gp-menu-description">In-app alerts for transfers, shared access, found-dog reports, reminders, and account notices.</div><div class="gp-menu-link-grid"><?php gpNavLink('notifications.php', '🔔', 'Notification Center'); ?><?php gpNavLink('alerts.php', '🧠', 'Smart Alerts', 'alerts_enabled'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>📝 Logs</summary><div class="gp-menu-description">Daily handler notes, sessions, media, and history.</div><div class="gp-menu-link-grid"><?php gpNavLink('quick_log.php', '⚡', 'Quick Session', 'quick_session_enabled'); ?><?php gpNavLink('log_entry.php', '📝', 'Detailed Log', 'detailed_log_enabled'); ?><?php gpNavLink('view_logs.php', '📋', 'History'); ?><?php gpNavLink('media_review.php', '🎥', 'Media Review', 'media_reviews_enabled'); ?><?php gpNavLink('video_review.php', '🎞️', 'Video Review', 'video_reviews_enabled'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>🎓 Training</summary><div class="gp-menu-description">Plan, repair, assess, and track training progress.</div><div class="gp-menu-link-grid"><?php gpNavLink('training_program.php', '🎓', 'Training Program', 'training_program_enabled'); ?><?php gpNavLink('candidate_assessment.php', '🐾', 'Candidate Assessment', 'candidate_scoring_enabled'); ?><?php gpNavLink('candidate_comparison.php', '🔎', 'Candidate Comparison', 'candidate_comparison_enabled'); ?><?php gpNavLink('behavior_risk_scoring.php', '⚠️', 'Behavior Risk', 'behavior_risk_scoring_enabled'); ?><?php gpNavLink('regression_engine.php', '♻️', 'Regression Engine', 'regression_engine_enabled'); ?><?php gpNavLink('goal_builder.php', '🧩', 'Goal Builder', 'goal_builder_enabled'); ?><?php gpNavLink('community_challenges.php', '🏅', 'Community Challenges', 'community_challenges_enabled'); ?><?php gpNavLink('trucking_mode.php', '🚚', 'Trucking Mode', 'trucking_mode_enabled'); ?><?php gpNavLink('training_goal_intake.php', '🎯', 'Goal Intake', 'goal_intake_enabled'); ?><?php gpNavLink('habit_repair.php', '🛠️', 'Habit Repair', 'habit_repair_enabled'); ?><?php gpNavLink('training_session_log.php', '✅', 'Session Log', 'training_progression_enabled'); ?><?php gpNavLink('training_history.php', '📚', 'Training History', 'training_progression_enabled'); ?><?php gpNavLink('coach_review.php', '🧭', 'Coach Review', 'coach_review_enabled'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>🩺 Care</summary><div class="gp-menu-description">Health documents, appointments, medications, wearable syncs, and active care alerts.</div><div class="gp-menu-link-grid"><?php gpNavLink('dog_health.php', '🩺', 'Health Docs', 'health_docs_enabled'); ?><?php gpNavLink('appointments.php', '📅', 'Vet Appointments', 'vet_appointments_enabled'); ?><?php gpNavLink('medications.php', '💊', 'Medications', 'medications_enabled'); ?><?php gpNavLink('wearable_integrations.php', '⌚', 'Wearable Sync', 'wearable_integrations_enabled'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>🪪 Access</summary><div class="gp-menu-description">Public-access references, ADA notes, flight rules, and certification tools.</div><div class="gp-menu-link-grid"><?php gpNavLink('ada_access_card.php', '🪪', 'ADA Access Card', 'ada_wallet_enabled'); ?><?php gpNavLink('service_dog_rights.php', '⚖️', 'Detailed ADA Notes'); ?><?php gpNavLink('air_travel_rights.php', '✈️', 'Air Travel Rights'); ?><?php gpNavLink('certification.php', '✅', 'Certification', 'certification_enabled'); ?></div></details>
    <details class="gp-menu-section" data-default-open="1"><summary>💬 Support</summary><div class="gp-menu-description">Send feedback, compare breed ideas, review beta testing notes, and find trainer contacts already in your account.</div><div class="gp-menu-link-grid"><?php gpNavLink('feedback.php', '💬', 'Feedback / Bug Report'); ?><?php gpNavLink('breed_questionnaire.php', '❓', 'Breed Questionnaire'); ?><?php gpNavLink('trainer_marketplace.php', '🤝', 'Trainer Marketplace', 'trainer_marketplace_enabled'); ?><?php gpNavLink('ai_training_assistant.php', '🤖', 'AI Training Assistant', 'ai_training_assistant_enabled'); ?><?php gpNavLink('beta_qa_checklist.php', '✅', 'Beta QA Checklist'); ?></div></details>
    <?php if (gpNavIsAdmin()): ?><details class="gp-menu-section"><summary>🛠️ Admin</summary><div class="gp-menu-description">Beta access, found-dog reports, system health, feature flags, and backups.</div><div class="gp-menu-link-grid"><?php gpNavLink('admin.php', '🛠️', 'Admin Home'); ?><?php gpNavLink('admin_beta_requests.php', '📨', 'Beta Requests'); ?><?php gpNavLink('admin_found_dog_reports.php', '📍', 'Found Dog Reports'); ?><?php gpNavLink('admin_notification_test.php', '🔔', 'Notification Test'); ?><?php gpNavLink('admin_profile_completion.php', '👤', 'Profile Completion'); ?><?php gpNavLink('admin_feedback.php', '💬', 'Feedback Reports'); ?><?php gpNavLink('admin_users.php', '👥', 'User Management'); ?><?php gpNavLink('admin_feature_roadmap.php', '🗺️', 'Feature Roadmap'); ?><?php gpNavLink('api_tokens.php', '🔐', 'API Tokens'); ?><?php gpNavLink('db_status.php', '🩺', 'System Health'); ?><?php gpNavLink('backup.php', '💾', 'Backup Tools', 'backup_tools_enabled'); ?></div></details><?php endif; ?>
</aside>
<script>(function(){var openBtn=document.getElementById('gpMenuOpen'),closeBtn=document.getElementById('gpMenuClose'),backdrop=document.getElementById('gpMenuBackdrop'),search=document.getElementById('gpMenuSearch'),clearBtn=document.getElementById('gpMenuSearchClear'),sections=Array.from(document.querySelectorAll('.gp-menu-section'));function openMenu(){document.body.classList.add('gp-menu-open');if(openBtn)openBtn.setAttribute('aria-expanded','true');if(search)search.focus()}function closeMenu(){document.body.classList.remove('gp-menu-open');if(openBtn)openBtn.setAttribute('aria-expanded','false')}function applyFilter(){var query=(search&&search.value?search.value.trim().toLowerCase():'');sections.forEach(function(section){var links=Array.from(section.querySelectorAll('.gp-menu-link'));var summary=(section.querySelector('summary')&&section.querySelector('summary').textContent?section.querySelector('summary').textContent:'').toLowerCase();var description=(section.querySelector('.gp-menu-description')&&section.querySelector('.gp-menu-description').textContent?section.querySelector('.gp-menu-description').textContent:'').toLowerCase();var anyMatch=!query||summary.includes(query)||description.includes(query);links.forEach(function(link){var match=!query||link.textContent.toLowerCase().includes(query);link.hidden=!match;if(match)anyMatch=true});section.hidden=query&&!anyMatch;if(query){section.open=anyMatch}else if(section.dataset.defaultOpen==='1'){section.open=true}})}if(openBtn)openBtn.addEventListener('click',openMenu);if(closeBtn)closeBtn.addEventListener('click',closeMenu);if(backdrop)backdrop.addEventListener('click',closeMenu);if(search)search.addEventListener('input',applyFilter);if(clearBtn)clearBtn.addEventListener('click',function(){if(search)search.value='';applyFilter();if(search)search.focus()});document.addEventListener('keydown',function(event){if(event.key==='Escape')closeMenu();});applyFilter();})();</script>
