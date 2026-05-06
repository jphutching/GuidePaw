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
.gp-dog-name-large {
    display: inline-block;
    font-size: clamp(1.35rem, 5vw, 2rem);
    line-height: 1.12;
    font-weight: 900;
    letter-spacing: -.02em;
}
.gp-active-dog-row {
    border: 2px solid #0d6efd !important;
    background: linear-gradient(90deg, rgba(13,110,253,.14), rgba(34,197,94,.08)) !important;
    box-shadow: 0 8px 20px rgba(13,110,253,.14);
}
.gp-active-dog-row .gp-dog-name-large {
    font-size: clamp(1.65rem, 6.2vw, 2.35rem);
}
.gp-active-dog-badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: 999px;
    padding: .32rem .6rem;
    background: #0d6efd;
    color: #fff;
    font-size: .78rem;
    font-weight: 900;
    letter-spacing: .03em;
    text-transform: uppercase;
    vertical-align: middle;
}
.gp-active-profile-note {
    margin-top: .35rem;
    color: #0f5132;
    font-size: .9rem;
    font-weight: 800;
}
.gp-dog-row-badges {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .45rem;
}
.gp-dog-row-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: .28rem .55rem;
    font-size: .76rem;
    font-weight: 900;
    letter-spacing: .02em;
    border: 1px solid transparent;
}
.gp-dog-row-badge.owner { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.gp-dog-row-badge.shared { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.gp-dog-row-badge.editor { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.gp-dog-row-badge.viewer { background: #f8fafc; color: #475569; border-color: #cbd5e1; }
.gp-dog-row-badge.status { background: #fefce8; color: #854d0e; border-color: #fde68a; }
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

(function () {
    if (!/\/dogs\.php$/.test(window.location.pathname)) return;
    if (document.querySelector(".alert-danger")) return;

    const dogRows = Array.from(document.querySelectorAll(".col-lg-7 .list-group > .list-group-item"));
    const dogCount = dogRows.length;

    function makeBadge(text, kind) {
        const badge = document.createElement("span");
        badge.className = "gp-dog-row-badge " + kind;
        badge.textContent = text;
        return badge;
    }

    dogRows.forEach(function (row) {
        const nameEl = row.querySelector(".fw-semibold");
        const metaEl = row.querySelector(".small.text-muted");
        if (!nameEl) return;
        nameEl.classList.add("gp-dog-name-large");

        const profileLink = row.querySelector("a[href*=\"dog_profile.php?dog_id=\"]");
        const actionWrap = profileLink ? profileLink.parentElement : null;
        if (profileLink && actionWrap && !actionWrap.querySelector("a[href*=\"dog_access.php\"]")) {
            const match = profileLink.getAttribute("href").match(/dog_id=(\d+)/);
            if (match) {
                const accessLink = document.createElement("a");
                accessLink.href = "dog_access.php?dog_id=" + match[1];
                accessLink.className = "btn btn-outline-success btn-sm";
                accessLink.textContent = "Access";
                actionWrap.appendChild(accessLink);
            }
        }

        if (metaEl && !row.querySelector(".gp-dog-row-badges")) {
            const metaText = metaEl.textContent.toLowerCase();
            const badges = document.createElement("div");
            badges.className = "gp-dog-row-badges";

            if (metaText.includes("owner:")) {
                badges.appendChild(makeBadge("Shared with you", "shared"));
            } else {
                badges.appendChild(makeBadge("Owner", "owner"));
            }

            if (metaText.includes("editor")) {
                badges.appendChild(makeBadge("Editor access", "editor"));
            } else if (metaText.includes("viewer")) {
                badges.appendChild(makeBadge("Viewer access", "viewer"));
            } else if (metaText.includes("owner")) {
                badges.appendChild(makeBadge("Full control", "owner"));
            }

            badges.appendChild(makeBadge("Status in Access", "status"));
            metaEl.insertAdjacentElement("afterend", badges);
        }

        const activeBadge = nameEl.querySelector(".badge");
        if (activeBadge && activeBadge.textContent.trim().toLowerCase() === "active") {
            row.classList.add("gp-active-dog-row");
            activeBadge.className = "gp-active-dog-badge";
            activeBadge.textContent = "Active Profile";

            if (!row.querySelector(".gp-active-profile-note")) {
                const note = document.createElement("div");
                note.className = "gp-active-profile-note";
                note.textContent = "GuidePaw is currently using this dog profile.";
                nameEl.insertAdjacentElement("afterend", note);
            }
        }
    });

    const addAction = document.querySelector("form input[name=\"action\"][value=\"add_dog\"]");
    if (!addAction) return;

    const addForm = addAction.closest("form");
    const cardBody = addForm ? addForm.closest(".card-body") : null;
    const cardTitle = cardBody ? cardBody.querySelector(".card-title") : null;

    if (!addForm || !cardBody || !cardTitle || dogCount < 1) return;

    addForm.classList.add("d-none");
    cardTitle.textContent = "Manage Dogs";

    const note = document.createElement("div");
    note.className = "text-muted small mb-3";
    note.textContent = dogCount === 1
        ? "Your existing dog is used as the active dog by default. Open this section only when adding another dog."
        : "Open this section only when adding another dog.";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-outline-primary w-100 mb-3";
    button.textContent = "Add Another Dog";
    button.setAttribute("aria-expanded", "false");

    button.addEventListener("click", function () {
        const isHidden = addForm.classList.toggle("d-none");
        button.textContent = isHidden ? "Add Another Dog" : "Hide Add Dog Form";
        button.setAttribute("aria-expanded", isHidden ? "false" : "true");
        if (!isHidden) {
            const nameField = addForm.querySelector("input[name=\"name\"]");
            if (nameField) nameField.focus();
        }
    });

    cardTitle.insertAdjacentElement("afterend", note);
    note.insertAdjacentElement("afterend", button);
})();
</script>';
    }
}
