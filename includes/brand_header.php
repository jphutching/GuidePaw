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
    padding: clamp(22px, 6vw, 42px) 18px;
    margin: 0 0 clamp(20px, 5vw, 34px);
}
.gp-shared-brand-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    flex-wrap: nowrap;
}
.gp-shared-brand-logo {
    width: clamp(210px, 44vw, 360px);
    height: auto;
    border-radius: 18px;
    display: block;
    box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
}
.gp-shared-brand-tagline {
    color: rgba(255,255,255,.78);
    font-size: clamp(11px, 2.2vw, 14px);
    font-weight: 600;
    letter-spacing: .12em;
    text-align: center;
    margin-top: 10px;
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
