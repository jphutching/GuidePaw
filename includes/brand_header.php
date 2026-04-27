<?php
if (!function_exists('guidepawBrandHeader')) {
    function guidepawBrandHeader(): void
    {
        echo '
<div class="gp-brand-hero">
    <img class="gp-brand-logo" src="/assets/brand/guidepaw-logo.png" alt="GuidePaw">
    <div class="gp-brand-copy">
        <div class="gp-brand-tagline">Training Trust for the Journey</div>
    </div>
</div>';
    }
}
