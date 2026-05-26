<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/seo.php';
// Public page — no login required
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php guidepawSeoHead([
        'title' => 'Service Dog State Access Laws — All 50 States + DC | GuidePaw',
        'description' => 'State-by-state guide to service dog in training (SDIT) public access rights, misrepresentation laws, and key statutes for all 50 states and Washington DC.',
        'robots' => 'index,follow',
        'type'   => 'article',
        'image'  => '/assets/brand/guidepaw-logo.png',
        'json_ld' => [[
            '@context'   => 'https://schema.org',
            '@type'      => 'Article',
            'headline'   => 'Service Dog State Access Laws — All 50 States + DC',
            'description'=> 'SDIT public access rights, misrepresentation laws, and key statutes for every US state and DC.',
            'author'     => ['@type' => 'Organization', 'name' => appShortName()],
            'publisher'  => ['@type' => 'Organization', 'name' => appShortName(), 'logo' => ['@type' => 'ImageObject', 'url' => guidepawSeoAbsoluteUrl('/assets/brand/guidepaw-logo.png')]],
        ]],
    ]); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .sal-hero { background: linear-gradient(135deg, #0f766e, #0d6efd); color: #fff; border-radius: 24px; padding: 2rem; }
        .sal-card { border-radius: 16px; border: 1px solid rgba(15,23,42,.08); background: #fff; overflow: hidden; transition: box-shadow .15s; }
        .sal-card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.12); }
        .sal-accent { height: 4px; }
        .sal-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
        .sal-abbr { font-size: 1.5rem; font-weight: 800; opacity: .18; float: right; line-height: 1; }
        .sal-stat { font-size: .78rem; color: #64748b; }
        #stateSearch { border-radius: 12px; border: 1px solid #cbd5e1; padding: .65rem 1rem; font-size: 1rem; }
        #stateSearch:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
        .sal-empty { display: none; }
        .sal-filter-btn { border-radius: 999px; padding: .3rem .85rem; font-size: .85rem; font-weight: 600; border: 2px solid transparent; cursor: pointer; }
        .sal-filter-btn.active { border-color: currentColor; }
    </style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>

<div class="container py-4" style="max-width: 1100px;">

    <div class="sal-hero mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="small opacity-75 text-uppercase fw-semibold mb-2">Public reference · No account needed</div>
                <h1 class="h2 fw-bold mb-2">Service Dog State Access Laws</h1>
                <p class="mb-3 opacity-90">Public access rights for service dogs in training (SDITs), misrepresentation laws, and key statutes — all 50 states and Washington DC. Federal ADA is the floor; state law can go higher.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="service_dog_rights.php" class="btn btn-light btn-sm fw-semibold">ADA Quick Ref</a>
                    <a href="air_travel_rights.php" class="btn btn-outline-light btn-sm fw-semibold">Air Travel Rights</a>
                    <a href="housing_access_faq.php" class="btn btn-outline-light btn-sm fw-semibold">Housing FAQ</a>
                    <a href="service_dog_esa_legal_info.php" class="btn btn-outline-light btn-sm fw-semibold">ESA Legal Info</a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bg-white bg-opacity-10 rounded-3 p-3 text-white">
                    <div class="fw-bold mb-1">What is an SDIT?</div>
                    <div class="small opacity-90">A service dog in training — a dog actively being trained to perform tasks for a person with a disability. Under federal law, SDITs do not have the same public access rights as fully trained service dogs. Many states go further and grant SDITs access when working with a trainer.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning rounded-3 d-flex gap-2 align-items-start mb-4">
        <span style="font-size:1.1rem">⚠️</span>
        <div><strong>Reference only — not legal advice.</strong> Laws change. Verify current statutes before making decisions, especially for states marked "Check current statutes." A local attorney familiar with disability law is the authoritative source.</div>
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
        <input type="search" id="stateSearch" class="flex-grow-1" placeholder="Search state…" aria-label="Search states" oninput="filterStates()">
        <div class="d-flex gap-2 flex-wrap" id="filterBtns">
            <button class="sal-filter-btn active" style="color:#0f766e;background:#f0fdf4" onclick="setFilter('all',this)">All states</button>
            <button class="sal-filter-btn" style="color:#0f766e;background:#f0fdf4" onclick="setFilter('sdit_yes',this)">SDIT access</button>
            <button class="sal-filter-btn" style="color:#0d6efd;background:#eff6ff" onclick="setFilter('misrep',this)">Has misrep law</button>
            <button class="sal-filter-btn" style="color:#92400e;background:#fef9c3" onclick="setFilter('check',this)">Check statutes</button>
        </div>
    </div>

    <div class="row g-3" id="stateGrid">
<?php
$states = [
    ['Alabama',              'AL', 'Check current statutes',   true,  'Code of Ala. § 21-7-8; misdemeanor',                           'Largely follows federal ADA baseline.'],
    ['Alaska',               'AK', 'Yes — with trainer',       true,  'AS § 18.80.395; class B misdemeanor',                          'Guide dog laws in AS § 18.80.395.'],
    ['Arizona',              'AZ', 'Yes — with trainer',       true,  'A.R.S. § 11-1024; class 3 misdemeanor',                        'SDITs granted access when accompanied by trainer. Strong misrep penalties.'],
    ['Arkansas',             'AR', 'Check current statutes',   true,  'Ark. Code § 20-14-305; Class A misdemeanor',                   'Misrepresentation is a Class A misdemeanor.'],
    ['California',           'CA', 'Yes — same as trained SD', true,  'Penal Code § 365.7; misdemeanor, fine up to $1,000',           'Civil Code § 54.2 grants SDITs the same public access as fully trained service dogs. Broadest state protections in the US.'],
    ['Colorado',             'CO', 'Yes — with trainer',       true,  'C.R.S. § 24-34-803.5; petty offense',                         'SDITs allowed with trainer. Misrepresentation is a petty offense. One of the stronger state frameworks.'],
    ['Connecticut',          'CT', 'Yes — with trainer',       true,  'CGS § 1-1f; infraction',                                      'SDITs allowed with trainer. Misrepresentation is an infraction.'],
    ['Delaware',             'DE', 'Yes — with trainer',       true,  'Del. Code tit. 6 § 4504A; fine up to $1,000',                 'SDITs allowed with trainer.'],
    ['District of Columbia', 'DC', 'Yes — with trainer',       true,  'D.C. Code § 7-1009; misdemeanor',                             'Misrepresentation is a misdemeanor.'],
    ['Florida',              'FL', 'Yes — with trainer/handler',true, 'F.S. § 413.081; second-degree misdemeanor',                   'F.S. § 413.08 grants SDIT access with trainer or handler. Strong framework.'],
    ['Georgia',              'GA', 'Yes — with trainer',       true,  'O.C.G.A. § 30-4-2; misdemeanor',                              'SDITs allowed with trainer.'],
    ['Hawaii',               'HI', 'Yes — with trainer',       true,  'HRS § 347-13; misdemeanor',                                   'SDITs allowed with trainer.'],
    ['Idaho',                'ID', 'Check current statutes',   true,  'Idaho Code § 56-704; fine up to $1,000',                      'Check current statutes for SDIT-specific access.'],
    ['Illinois',             'IL', 'Yes — with trainer',       true,  '775 ILCS 5/5-102.2; petty offense/Class B misdemeanor',       'SDITs granted access when accompanied by trainer or handler. Well-established framework.'],
    ['Indiana',              'IN', 'Check current statutes',   true,  'IC § 16-32-3; Class C infraction',                            'Check current statutes for SDIT-specific access.'],
    ['Iowa',                 'IA', 'Yes — with trainer',       true,  'Iowa Code § 216C.11; simple misdemeanor',                     'SDITs allowed with trainer.'],
    ['Kansas',               'KS', 'Check current statutes',   null,  'K.S.A. § 39-1109; verify current statutes',                   'Largely follows federal ADA baseline.'],
    ['Kentucky',             'KY', 'Check current statutes',   true,  'KRS § 258.500; fine',                                         'Check current statutes for SDIT-specific access.'],
    ['Louisiana',            'LA', 'Yes — with trainer',       true,  'La. R.S. § 46:1958; misdemeanor',                             'SDITs allowed with trainer.'],
    ['Maine',                'ME', 'Yes — with trainer',       true,  '5 M.R.S.A. § 4553; civil violation',                          'SDITs allowed with trainer.'],
    ['Maryland',             'MD', 'Yes — with trainer',       true,  'Md. Code Art. 49B § 15; misdemeanor',                         'SDITs allowed with trainer.'],
    ['Massachusetts',        'MA', 'Yes — with trainer',       true,  'M.G.L. c. 272 § 98A; fine up to $500',                        'SDITs allowed with trainer.'],
    ['Michigan',             'MI', 'Yes — with trainer',       true,  'MCL § 750.502b; misdemeanor',                                 'SDITs allowed with trainer. Misrepresentation is a misdemeanor.'],
    ['Minnesota',            'MN', 'Yes — with trainer',       true,  'Minn. Stat. § 256C.02; misdemeanor',                          'SDITs allowed with trainer.'],
    ['Mississippi',          'MS', 'Check current statutes',   null,  'Miss. Code § 43-6-155; verify current statutes',               'Largely follows federal baseline.'],
    ['Missouri',             'MO', 'Check current statutes',   true,  'Mo. Rev. Stat. § 209.150; class D misdemeanor',               'Check current statutes for SDIT-specific access.'],
    ['Montana',              'MT', 'Check current statutes',   null,  'MCA § 49-4-214; verify current statutes',                     'Largely follows federal baseline.'],
    ['Nebraska',             'NE', 'Check current statutes',   true,  'Neb. Rev. Stat. § 20-128; fine',                              'Check current statutes for SDIT-specific access.'],
    ['Nevada',               'NV', 'Yes — with trainer',       true,  'NRS § 651.075; misdemeanor',                                  'SDITs allowed with trainer.'],
    ['New Hampshire',        'NH', 'Yes — with trainer',       null,  'RSA § 167-D:2; verify current statutes',                      'RSA § 167-D:2 covers guide dogs.'],
    ['New Jersey',           'NJ', 'Yes — with trainer',       true,  'N.J.S.A. § 10:5-29.2; disorderly persons offense',            'SDITs allowed with trainer.'],
    ['New Mexico',           'NM', 'Yes — with trainer',       true,  'NMSA § 28-11-2; misdemeanor',                                 'SDITs allowed with trainer.'],
    ['New York',             'NY', 'Yes — with trainer',       true,  'Civil Rights Law § 47-b; violation/fine',                     'Civil Rights Law § 47-b grants SDIT access with trainer.'],
    ['North Carolina',       'NC', 'Yes — with trainer',       true,  'N.C.G.S. § 168-4.2; Class 3 misdemeanor',                    'SDITs allowed with trainer.'],
    ['North Dakota',         'ND', 'Check current statutes',   null,  'N.D. Cent. Code § 25-13-01; verify current statutes',         'Largely follows federal.'],
    ['Ohio',                 'OH', 'Yes — with trainer',       true,  'Ohio Rev. Code § 955.011; fine up to $1,000',                 'SDITs allowed with trainer.'],
    ['Oklahoma',             'OK', 'Check current statutes',   true,  '7 Okl. St. § 19.1; misdemeanor',                             'Check current statutes for SDIT-specific access.'],
    ['Oregon',               'OR', 'Yes — with trainer',       true,  'ORS § 659A.143; violation',                                   'SDITs allowed with trainer.'],
    ['Pennsylvania',         'PA', 'Yes — with trainer',       true,  '43 P.S. § 953; summary offense/fine',                        'SDITs allowed with trainer.'],
    ['Rhode Island',         'RI', 'Yes — with trainer',       true,  'RIGL § 40-9.1-1; fine',                                      'SDITs allowed with trainer.'],
    ['South Carolina',       'SC', 'Check current statutes',   true,  'S.C. Code § 43-33-20; fine up to $500',                      'Check current statutes for SDIT-specific access.'],
    ['South Dakota',         'SD', 'Check current statutes',   null,  'SDCL § 20-13-23.2; verify current statutes',                  'Largely follows federal.'],
    ['Tennessee',            'TN', 'Check current statutes',   true,  'Tenn. Code § 62-7-112; Class B misdemeanor',                 'Check current statutes for SDIT-specific access.'],
    ['Texas',                'TX', 'Yes — with trainer',       true,  'Tex. Human Resources Code § 121.006; Class A misdemeanor, fine up to $300', 'Tex. Human Resources Code § 121.003 grants SDIT access with trainer.'],
    ['Utah',                 'UT', 'Check current statutes',   true,  'Utah Code § 62A-5b-102; fine',                               'Check current statutes for SDIT-specific access.'],
    ['Vermont',              'VT', 'Yes — with trainer',       null,  '9 V.S.A. § 4502; verify current statutes',                   '9 V.S.A. § 4502 covers service animals.'],
    ['Virginia',             'VA', 'Yes — with trainer',       true,  'Va. Code § 51.5-44; Class 4 misdemeanor',                    'SDITs allowed with trainer.'],
    ['Washington',           'WA', 'Yes — with trainer',       true,  'RCW § 49.60.215; civil infraction, fine up to $250',          'SDITs allowed with trainer.'],
    ['West Virginia',        'WV', 'Check current statutes',   null,  'W. Va. Code § 5-15-4; verify current statutes',               'Largely follows federal.'],
    ['Wisconsin',            'WI', 'Yes — with trainer',       true,  'Wis. Stat. § 174.056; forfeiture/fine',                      'SDITs allowed with trainer.'],
    ['Wyoming',              'WY', 'Check current statutes',   null,  'Wyo. Stat. § 35-9-305; verify current statutes',              'Largely follows federal.'],
];

foreach ($states as [$name, $abbr, $sdit, $misrep, $misrepNote, $keyNote]):
    $isSameAsSD  = str_contains($sdit, 'same as trained');
    $isYes       = str_starts_with($sdit, 'Yes');
    $isCheck     = str_starts_with($sdit, 'Check');
    $accentColor = $isSameAsSD ? '#22c55e' : ($isYes ? '#0d9488' : '#f59e0b');
    $sditClass   = $isSameAsSD ? 'bg-success text-white' : ($isYes ? 'bg-info text-dark' : 'bg-warning text-dark');
    $misrepClass = $misrep ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
    $misrepText  = $misrep ? 'Has misrep law' : 'Verify statutes';
    $dataFilter  = ($isYes ? 'sdit_yes ' : '') . ($misrep ? 'misrep ' : '') . ($isCheck ? 'check ' : '');
?>
        <div class="col-sm-6 col-lg-4 sal-state-card" data-name="<?= e(strtolower($name . ' ' . $abbr)) ?>" data-filter="<?= e(trim($dataFilter)) ?>">
            <div class="sal-card h-100">
                <div class="sal-accent" style="background:<?= $accentColor ?>"></div>
                <div class="p-3">
                    <div class="mb-2">
                        <span class="sal-abbr"><?= e($abbr) ?></span>
                        <div class="fw-bold"><?= e($name) ?></div>
                    </div>
                    <div class="mb-2 d-flex flex-wrap gap-1">
                        <span class="sal-badge <?= $sditClass ?>"><?= e($sdit) ?></span>
                        <span class="sal-badge <?= $misrepClass ?>"><?= e($misrepText) ?></span>
                    </div>
                    <div class="sal-stat mb-1"><strong>Statute:</strong> <?= e($misrepNote) ?></div>
                    <?php if ($keyNote): ?>
                    <div class="sal-stat text-muted"><?= e($keyNote) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endforeach; ?>
    </div>

    <div class="sal-empty text-center text-muted py-5" id="noResults">
        <div style="font-size:2rem">🔍</div>
        <div class="mt-2">No states match your search.</div>
    </div>

    <div class="mt-5 p-4 rounded-3 bg-white border">
        <h2 class="h5 fw-bold mb-3">Legend</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex gap-2 align-items-start">
                    <span class="sal-badge bg-success text-white mt-1">Yes — same as trained SD</span>
                    <div class="small text-muted">State grants SDITs identical public access rights as fully trained service dogs.</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 align-items-start">
                    <span class="sal-badge bg-info text-dark mt-1">Yes — with trainer</span>
                    <div class="small text-muted">SDIT may access public places when accompanied by a trainer or handler working the dog.</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 align-items-start">
                    <span class="sal-badge bg-warning text-dark mt-1">Check current statutes</span>
                    <div class="small text-muted">State may follow only the federal ADA baseline for SDITs, or statutes are ambiguous. Verify before relying on state-level access.</div>
                </div>
            </div>
        </div>
        <hr class="my-3">
        <div class="small text-muted">
            <strong>Federal baseline:</strong> Under the ADA, only fully trained service dogs performing tasks for a person with a disability are guaranteed public access. SDITs do not have federal public access rights. States set their own floor above the federal minimum.
            <strong class="d-block mt-2">Sources:</strong> ADA.gov service animal guidance, DOJ regulations 28 C.F.R. § 36.302, and state-level statutes as noted. Always verify against current official state statutes.
        </div>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2 justify-content-center">
        <a href="service_dog_rights.php" class="btn btn-outline-primary">ADA Handler Quick Ref</a>
        <a href="air_travel_rights.php" class="btn btn-outline-primary">Air Travel Rights</a>
        <a href="housing_access_faq.php" class="btn btn-outline-primary">Housing &amp; Access FAQ</a>
        <a href="service_dog_esa_legal_info.php" class="btn btn-outline-primary">ESA Legal Info</a>
        <a href="index.php" class="btn btn-primary">GuidePaw Home</a>
    </div>
</div>

<script>
let activeFilter = 'all';

function filterStates() {
    const q = document.getElementById('stateSearch').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.sal-state-card');
    let visible = 0;
    cards.forEach(card => {
        const nameMatch = !q || card.dataset.name.includes(q);
        const filterMatch = activeFilter === 'all' || card.dataset.filter.includes(activeFilter);
        const show = nameMatch && filterMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
}

function setFilter(filter, btn) {
    activeFilter = filter;
    document.querySelectorAll('.sal-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterStates();
}
</script>
</body>
</html>
