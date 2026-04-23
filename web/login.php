<?php
require_once __DIR__ . '/scripts/db-connect.php';

$allowedRoles = ['admin' => 'administrátor', 'student' => 'student'];
$role = 'student';
if (!empty($_GET['role']) && array_key_exists($_GET['role'], $allowedRoles)) {
    $role = $_GET['role'];
}

$roleLabel = $allowedRoles[$role];
$error = '';
$submittedUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedRole = $_POST['role'] ?? 'student';
    if (!array_key_exists($submittedRole, $allowedRoles)) {
        $submittedRole = 'student';
    }
    $submittedUsername = trim($_POST['username'] ?? '');
    $submittedPassword = trim($_POST['password'] ?? '');

    if ($submittedUsername === '' || $submittedPassword === '') {
        $error = 'Vyplňte prosím uživatelské jméno i heslo.';
    } else {
        $role = $submittedRole;
        $roleLabel = $allowedRoles[$role];

        try {
            $pdo = connectDb();
            if ($role === 'admin') {
                $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE username = ?');
            } else {
                $stmt = $pdo->prepare('SELECT password_hash FROM zaci WHERE id_zaka = ?');
            }
            $stmt->execute([$submittedUsername]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($submittedPassword, $user['password_hash'])) {
                $successMessage = sprintf('Přihlášení pro %s proběhlo úspěšně.', $roleLabel);
                session_start();
                $_SESSION['username'] = $submittedUsername;
                $_SESSION['role'] = $role;
                header('Location: ' . ($role === 'admin' ? 'admin.php' : 'student.php'));
                exit;
            } else {
                $error = 'Neplatné uživatelské jméno nebo heslo.';
            }
        } catch (Exception $e) {
            $error = 'Chyba při přihlašování. Zkuste to znovu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Přihlášení | Správa Ubytování</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <main class="login-page">
        <div class="wrapper">
            <section class="login-card">
                <h1>Přihlášení <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Zadejte své přihlašovací údaje pro role <strong><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php elseif (!empty($successMessage)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" action="login.php?role=<?php echo urlencode($role); ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" />

                    <label for="username"><?php echo $role === 'student' ? 'ID žáka' : 'Uživatelské jméno'; ?></label>
                    <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($submittedUsername, ENT_QUOTES, 'UTF-8'); ?>" required />

                    <label for="password">Heslo</label>
                    <input id="password" name="password" type="password" required />

                    <button type="submit" class="btn btn-primary">Přihlásit se</button>
                </form>

                <div class="login-switch">
                    <a href="login.php?role=admin"<?php if ($role === 'admin') echo ' class="active"'; ?>>Admin</a>
                    <a href="login.php?role=student"<?php if ($role === 'student') echo ' class="active"'; ?>>Student</a>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
