<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Air Travel Rights</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .card-soft { border-radius: 18px; }
        .mini-badge {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
        }
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(8px);
            border-top: 1px solid #dee2e6;
        }
        .note-list li + li { margin-top: .45rem; }
    </style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="container py-4" style="max-width: 820px;">
    <div class="bg-primary text-white px-4 py-3 shadow-sm rounded-4 mb-3">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">Air carrier access</div>
                <h1 class="h3 mb-0">Air Travel Rights</h1>
            </div>
            <a href="ada_access_card.php" class="btn btn-light btn-sm">ADA Access Card</a>
        </div>
    </div>

    <div class="alert alert-warning shadow-sm card-soft">
        <strong>Use this as a practical reference, not legal advice.</strong><br>
        Air travel is covered by the Air Carrier Access Act, not the ADA.
    </div>

    <section class="card shadow-sm border-0 mb-3 card-soft">
        <div class="card-body">
            <div class="mini-badge bg-success-subtle text-success-emphasis mb-2">Service dogs</div>
            <h2 class="h5">Service dogs are covered on flights</h2>
            <p class="mb-2">
                Under DOT air-travel rules, airlines must recognize dogs that are individually trained to do work or perform tasks for a person with a disability.
            </p>
            <ul class="note-list mb-0">
                <li>Airlines must allow a covered service dog in the cabin on flights to, within, and from the United States.</li>
                <li>Airlines may require DOT service animal forms and may ask the two travel-specific questions.</li>
                <li>The dog must fit in the handler’s foot space or under the seat in front of the handler when required by the airline.</li>
            </ul>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-3 card-soft">
        <div class="card-body">
            <div class="mini-badge bg-primary-subtle text-primary-emphasis mb-2">Travel basics</div>
            <h2 class="h5">What airlines can ask or require</h2>
            <ul class="note-list mb-0">
                <li>A U.S. DOT Service Animal Air Transportation Form for health, behavior, and training.</li>
                <li>A U.S. DOT Relief Attestation Form for flights of 8 hours or more.</li>
                <li>Advance submission if the reservation was made before the 48-hour deadline.</li>
                <li>Harness, leash, or tether control in the airport and on the aircraft when required by the airline.</li>
            </ul>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-3 card-soft">
        <div class="card-body">
            <div class="mini-badge bg-danger-subtle text-danger-emphasis mb-2">When travel can be denied</div>
            <h2 class="h5">Airlines can refuse transport if the dog</h2>
            <ul class="note-list mb-0">
                <li>Is too large or heavy to fit safely in the cabin space</li>
                <li>Poses a direct threat to the health or safety of others</li>
                <li>Causes a significant disruption in the cabin or gate area</li>
                <li>Fails required health or destination-entry rules</li>
            </ul>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-3 card-soft">
        <div class="card-body">
            <div class="mini-badge bg-dark-subtle text-dark-emphasis mb-2">Service dogs in training</div>
            <h2 class="h5">SDIT travel is different</h2>
            <p class="mb-2">
                DOT’s air-travel rules do not treat service dogs in training as service animals for flights.
            </p>
            <p class="mb-0">
                If you are traveling with an SDIT, check the airline’s current animal policy before you book. The carrier may treat the dog as a pet or may have its own handling rules, and destination rules can also matter.
            </p>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4 card-soft">
        <div class="card-body">
            <h2 class="h5">Practical reminders</h2>
            <ul class="note-list mb-0">
                <li>Call the airline before you travel if you need a specific seating or relief-area plan.</li>
                <li>Keep a copy of the DOT forms and reservation confirmation with you.</li>
                <li>Check destination-country rules for international travel, because foreign entry rules can override the U.S. baseline.</li>
                <li>Ask for a Complaints Resolution Official if you believe your rights under air-travel disability rules are being denied.</li>
            </ul>
        </div>
    </section>

    <div class="small text-muted mb-5">
        Sources: U.S. Department of Transportation service-animal guidance, final rule on traveling by air with service animals, and the Air Carrier Access Act summary.
    </div>
</main>

<div class="sticky-actions p-3">
    <div class="container" style="max-width: 820px;">
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
            <a href="service_dog_rights.php" class="btn btn-outline-dark">Detailed ADA Notes</a>
            <a href="ada_access_card.php" class="btn btn-outline-secondary">ADA Access Card</a>
            <a href="index.php" class="btn btn-primary">Done</a>
        </div>
    </div>
</div>
</body>
</html>
