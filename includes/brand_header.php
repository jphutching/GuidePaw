<?php
if (!function_exists('guidepawBrandHeader')) {
    function guidepawBrandHeader(): void
    {
        echo '
<style>
.gp-shared-brand-wrap {
    background: linear-gradient(135deg, #0d6efd 0%, #2856c8 100%);
    border-bottom-left-radius: 28px;
    border-bottom-right-radius: 28px;
    box-shadow: 0 8px 18px rgba(15, 40, 90, .18);
    padding: clamp(28px, 7vw, 54px) 18px clamp(24px, 6vw, 42px);
    margin: 0 0 clamp(20px, 5vw, 34px);
}
.gp-shared-brand-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(14px, 4vw, 30px);
    flex-wrap: wrap;
}
.gp-shared-brand-logo {
    width: clamp(170px, 42vw, 280px);
    height: auto;
    border-radius: 16px;
    display: block;
    box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
}
.gp-shared-brand-tagline {
    max-width: 360px;
    font-family: "Trebuchet MS", "Arial Rounded MT Bold", system-ui, sans-serif;
    font-size: clamp(.9rem, 3.2vw, 1.3rem);
    font-weight: 900;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #ffffff;
    text-align: center;
    line-height: 1.15;
    text-shadow: 0 3px 10px rgba(0,0,0,.28);
}
@media (min-width: 760px) {
    .gp-shared-brand-inner {
        flex-wrap: nowrap;
    }
    .gp-shared-brand-tagline {
        text-align: left;
    }
}
</style>
<div class="gp-shared-brand-wrap">
    <div class="gp-shared-brand-inner">
        <img class="gp-shared-brand-logo" src="/assets/brand/guidepaw-logo.png" alt="GuidePaw Training Trust for the Journey">
        <div class="gp-shared-brand-tagline">Training Trust for the Journey</div>
    </div>
</div>';
    }
}
