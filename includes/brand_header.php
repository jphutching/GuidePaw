<?php
if (!function_exists('guidepawBrandHeader')) {
    function guidepawBrandHeader(): void
    {
        echo '
<style>
.gp-shared-brand-wrap {
    background: linear-gradient(135deg, #0d6efd 0%, #2856c8 100%);
    border-bottom-left-radius: 16px;
    border-bottom-right-radius: 16px;
    box-shadow: 0 4px 12px rgba(15, 40, 90, .18);
    padding: clamp(10px, 2.5vw, 16px) 16px clamp(12px, 2.5vw, 16px);
    margin: 0 0 clamp(12px, 3vw, 20px);
}
.gp-shared-brand-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    flex-wrap: nowrap;
}
.gp-shared-brand-logo {
    width: clamp(120px, 26vw, 190px);
    height: auto;
    border-radius: 10px;
    display: block;
    box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
}
.gp-shared-brand-tagline {
    color: rgba(255,255,255,.65);
    font-size: clamp(9px, 1.6vw, 11px);
    font-weight: 600;
    letter-spacing: .1em;
    text-align: center;
    margin-top: 5px;
    text-transform: uppercase;
}
</style>
<div class="gp-shared-brand-wrap">
    <div class="gp-shared-brand-inner">
        <img class="gp-shared-brand-logo" src="/assets/brand/guidepaw-logo.png" alt="GuidePaw logo">
    </div>
    <div class="gp-shared-brand-tagline">Training Trust For The Journey</div>
</div>';
    }
}
