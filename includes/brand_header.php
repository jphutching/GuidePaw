<?php
if (!function_exists('guidepawBrandHeader')) {
    function guidepawBrandHeader(): void
    {
        echo '
<style>
.gp-brand-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(12px, 4vw, 28px);
    margin: clamp(12px, 3vw, 22px) auto;
    flex-wrap: wrap;
}
.gp-brand-logo {
    width: clamp(120px, 34vw, 175px);
    height: auto;
    border-radius: 16px;
    display: block;
}
.gp-brand-tagline {
    font-family: "Trebuchet MS", "Arial Rounded MT Bold", system-ui, sans-serif;
    font-size: clamp(.86rem, 3.1vw, 1.08rem);
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #ffffff;
    text-align: center;
    text-shadow: 0 2px 8px rgba(0,0,0,.28);
}
</style>
<div class="gp-brand-hero">
    <img class="gp-brand-logo" src="/assets/brand/guidepaw-logo.png" alt="GuidePaw">
    <div class="gp-brand-tagline">Training Trust for the Journey</div>
</div>';
    }
}
