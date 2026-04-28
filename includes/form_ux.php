<?php
if (!function_exists('guidepawFormUx')) {
    function guidepawFormUx(): void
    {
        $messages = [
            'saved' => 'Saved successfully.',
            'updated' => 'Updated successfully.',
            'deleted' => 'Deleted successfully.',
            'cancelled' => 'Changes cancelled.',
            'roadmap_updated' => 'Roadmap item updated successfully.',
        ];

        $msgKey = $_GET['msg'] ?? '';
        $message = $messages[$msgKey] ?? '';

        echo '
<style>
.gp-form-toast {
    position: fixed;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    margin: 0 auto;
    width: calc(100% - 28px);
    max-width: 920px;
    padding: 14px 16px;
    border-radius: 12px;
    background: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
    font-weight: 900;
    box-shadow: 0 8px 18px rgba(0,0,0,.10);
}
.gp-unsaved-pill {
    display: none;
    position: fixed;
    right: 14px;
    bottom: 14px;
    z-index: 9999;
    padding: 10px 12px;
    border-radius: 999px;
    background: #fff3cd;
    border: 1px solid #ffecb5;
    color: #664d03;
    font-weight: 800;
    box-shadow: 0 8px 18px rgba(0,0,0,.12);
}
</style>';

        if ($message !== '') {
            echo '<div class="gp-form-toast">✅ ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo '
<div class="gp-unsaved-pill" id="gpUnsavedPill">⚠️ Unsaved changes</div>
<script>
(function () {
    let dirty = false;
    let submitted = false;
    const pill = document.getElementById("gpUnsavedPill");

    function markDirty() {
        if (submitted) return;
        dirty = true;
        if (pill) pill.style.display = "block";
    }

    function clearDirty() {
        dirty = false;
        submitted = true;
        if (pill) pill.style.display = "none";
    }

    document.querySelectorAll("form").forEach(function (form) {
        if (form.dataset.dirtyWatch === "off") return;

        form.querySelectorAll("input, textarea, select").forEach(function (field) {
            if (field.type === "hidden") return;
            field.addEventListener("change", markDirty);
            field.addEventListener("input", markDirty);
        });

        form.addEventListener("submit", clearDirty);
    });

    document.querySelectorAll("a[href]").forEach(function (link) {
        link.addEventListener("click", function (event) {
            if (!dirty || submitted) return;
            const ok = confirm("You have unsaved changes. Leave this page without saving?");
            if (!ok) {
                event.preventDefault();
            }
        });
    });

    window.addEventListener("beforeunload", function (event) {
        if (!dirty || submitted) return;
        event.preventDefault();
        event.returnValue = "";
    });
})();
</script>';
    }
}
