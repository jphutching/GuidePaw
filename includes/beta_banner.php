<?php
require_once __DIR__ . '/feature_flags.php';
if (isset($pdo) && !featureEnabled($pdo, 'beta_banner_enabled')) { return; }
if (!function_exists('renderBetaBanner')) {
    function renderBetaBanner(): void {
        if (!betaBannerEnabled()) {
            return;
        }
        if (!empty($_GET['dismiss_beta_banner'])) {
            $_SESSION['hide_beta_banner'] = 1;
            $qs = $_GET;
            unset($qs['dismiss_beta_banner']);
            $target = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
            if ($qs) {
                $target .= '?' . http_build_query($qs);
            }
            header('Location: ' . ($target ?: 'index.php'));
            exit;
        }
        if (!empty($_SESSION['hide_beta_banner'])) {
            return;
        }
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $separator = (strpos($requestUri, '?') === false) ? '?' : '&';
        $dismissUrl = $requestUri . $separator . 'dismiss_beta_banner=1';
        ?>
        <div class="beta-banner alert alert-warning alert-dismissible fade show rounded-0 mb-0 border-0" role="alert">
            <div class="container-fluid d-flex flex-wrap align-items-center gap-2 small">
                <strong><?= e(betaBannerLabel()) ?></strong>
                <span>This build is for development or beta use. Data loss, bugs, and breaking changes can happen.</span>
                <a href="feedback.php" class="btn btn-sm btn-dark ms-auto">Report bug / request feature</a>
                <a href="<?= e($dismissUrl) ?>" class="btn-close ms-1" aria-label="Dismiss"></a>
            </div>
        </div>
        <?php
    }
}
renderBetaBanner();
?>
