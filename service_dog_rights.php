<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed ADA Notes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .card-soft { border-radius: 18px; }
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(8px);
            border-top: 1px solid #dee2e6;
        }
        .mini-badge {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <div class="bg-primary text-white px-4 py-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="small opacity-75">Detailed notes</div>
                <h3 class="mb-0">ADA Service Dog Rights</h3>
            </div>
            <a href="index.php" class="btn btn-light btn-sm">Home</a>
        </div>
    </div>

    <div class="container py-4" style="max-width: 760px;">
        <div class="alert alert-warning shadow-sm card-soft">
            <strong>Use this as a calm reference, not legal advice.</strong><br>
            This card reflects general federal ADA guidance and a basic HIPAA clarification.
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <div class="mini-badge bg-success-subtle text-success-emphasis mb-2">Fast script</div>
                <p class="mb-0 fs-5">
                    “I’m protected under the ADA. This is my service dog. You may ask only whether the dog is required because of a disability and what work or task the dog has been trained to perform.”
                </p>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>What staff may ask</h5>
                <ol class="mb-0">
                    <li>Is the dog a service animal required because of a disability?</li>
                    <li>What work or task has the dog been trained to perform?</li>
                </ol>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>What staff may <span class="text-danger">not</span> ask</h5>
                <ul class="mb-0">
                    <li>You to disclose your diagnosis or details of your disability</li>
                    <li>For medical records or doctor letters as a condition of entry</li>
                    <li>For certification, registration, ID card, or proof of training</li>
                    <li>That the dog demonstrate the task on demand</li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>When a business can ask the dog to leave</h5>
                <ul class="mb-0">
                    <li>The dog is out of control and the handler does not take effective action to control it</li>
                    <li>The dog is not housebroken</li>
                </ul>
                <div class="small text-muted mt-2">Fear of dogs or allergies alone are not valid reasons to deny access.</div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>Handler responsibilities</h5>
                <ul class="mb-0">
                    <li>Keep the dog under control with leash, harness, or voice control when appropriate</li>
                    <li>Maintain public-access behavior</li>
                    <li>Pay for actual damage caused by the dog if the business normally charges customers for damage</li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>HIPAA clarification</h5>
                <p class="mb-2">
                    HIPAA applies to covered health care entities and related business associates, not to ordinary stores, restaurants, truck stops, or most other businesses you enter.
                </p>
                <p class="mb-0">
                    That means people often say “HIPAA” in access disputes, but the more accurate point is this: <strong>you are generally not required under the ADA to show medical documentation to a business to enter with a service dog.</strong>
                </p>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3 card-soft">
            <div class="card-body">
                <h5>If challenged</h5>
                <ul class="mb-0">
                    <li>Stay calm and keep your voice even</li>
                    <li>Answer only the two ADA questions</li>
                    <li>Ask for a manager if the employee is unsure</li>
                    <li>Offer to let them review ADA Department of Justice guidance</li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4 card-soft">
            <div class="card-body">
                <h5>DOJ ADA Information Line</h5>
                <div><strong>Voice:</strong> 800-514-0301</div>
                <div><strong>TTY:</strong> 800-514-0383</div>
                <div class="small text-muted mt-2">For official federal guidance, see ADA.gov service animal resources.</div>
            </div>
        </div>
    </div>

    <div class="sticky-actions p-3">
        <div class="container" style="max-width: 760px;">
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
                <a href="ada_access_card.php" class="btn btn-outline-dark">ADA Access Card</a>
                <button class="btn btn-outline-secondary" onclick="window.print()">Print / Save PDF</button>
                <a href="index.php" class="btn btn-primary">Done</a>
            </div>
        </div>
    </div>
</body>
</html>
