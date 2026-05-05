<?php
// Backward-compatible route. The user-facing feature is now ADA Access Card.
$state = isset($_GET['state']) ? '?state=' . rawurlencode((string) $_GET['state']) : '';
header('Location: ada_access_card.php' . $state, true, 302);
exit;
