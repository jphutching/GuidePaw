<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = strtolower(trim($_POST['username'] ?? ''));
    $dog  = cleanText($_POST['dog_name'] ?? '', 80);
    $breed = cleanText($_POST['breed'] ?? '', 120);
    $chip = cleanText($_POST['chip_number'] ?? '', 80);
    $passRaw = (string) ($_POST['password'] ?? '');
    $pass = password_hash($passRaw, PASSWORD_DEFAULT);
    $recovery = strtoupper(bin2hex(random_bytes(5)));

    try {
        $pdo->beginTransaction();
        $newUserId = insertAndGetId($pdo, "INSERT INTO users (username, password_hash, dog_name, breed, chip_number, recovery_key) VALUES (?, ?, ?, ?, ?, ?)", [$user, $pass, $dog, $breed, $chip, $recovery]);

        $newDogId = insertAndGetId($pdo, "INSERT INTO dogs (owner_user_id, name, breed, chip_number) VALUES (?, ?, ?, ?)", [$newUserId, $dog, $breed, $chip]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $user;
        $_SESSION['dog_name'] = $dog;
        $_SESSION['active_dog_id'] = $newDogId;
        header('Location: index.php?msg=welcome');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Username already exists or setup failed.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light p-4">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
    <div class="card p-4 mx-auto shadow" style="max-width:400px;">
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'setup_required'): ?><div class="alert alert-info">Create your first handler account to initialize the app.</div><?php endif; ?>
            <h3 class="text-center mb-4">🐾 Handler Boarding</h3>
            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="username" class="form-control mb-2" placeholder="Username" required>
                <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                <hr>
                <h6>First Dog Profile</h6>
                <input type="text" name="dog_name" class="form-control mb-2" placeholder="Dog Name (e.g. Otis)" required>
                <input type="text" name="breed" class="form-control mb-2" placeholder="Breed (e.g. Cavalier)">
                <input type="text" name="chip_number" class="form-control mb-3" placeholder="Microchip #">
                <button class="btn btn-primary w-100">Initialize Profile</button>
            </form>
    </div>
</body>
</html>
