<?php
require_once __DIR__ . '/scripts/db-connect.php';

$message = '';
$error = '';
$submitted_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_zaka = trim($_POST['id_zaka'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $mesicni_splatka = !empty($_POST['mesicni_splatka']) ? floatval($_POST['mesicni_splatka']) : 0;
    $konto = !empty($_POST['konto']) ? floatval($_POST['konto']) : 0;
    $id_pokoje = !empty($_POST['id_pokoje']) ? intval($_POST['id_pokoje']) : null;

    $submitted_data = [
        'id_zaka' => $id_zaka,
        'name' => $name,
        'mesicni_splatka' => $mesicni_splatka,
        'konto' => $konto,
        'id_pokoje' => $id_pokoje
    ];

    if (!$id_zaka || !$name || !$password) {
        $error = 'ID žáka, jméno a heslo jsou povinné.';
    } elseif ($password !== $password_confirm) {
        $error = 'Hesla se neshodují.';
    } elseif (strlen($password) < 6) {
        $error = 'Heslo musí mít alespoň 6 znaků.';
    } elseif ($id_pokoje) {
        try {
            $pdo = connectDb();
            $room_stmt = $pdo->prepare('SELECT kapacita, JSON_LENGTH(student_ids) as occupied FROM pokoje WHERE id_pokoje = ?');
            $room_stmt->execute([$id_pokoje]);
            $room_data = $room_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room_data) {
                $error = 'Vybraný pokoj neexistuje.';
            } elseif (($room_data['occupied'] ?? 0) >= $room_data['kapacita']) {
                $error = 'Vybraný pokoj je plne osbazén. Vyberte jiný pokoj.';
                $id_pokoje = null; 
            } else {
                $error = '';
            }
        } catch (Exception $e) {
            $error = 'Chyba při kontrole dostupnosti pokoje.';
        }
    }

    if (!$error) {
        try {
            $pdo = connectDb();
            
            $check_stmt = $pdo->prepare('SELECT id_zaka FROM zaci WHERE id_zaka = ?');
            $check_stmt->execute([$id_zaka]);
            if ($check_stmt->fetch()) {
                $error = 'Toto ID žáka je již registrováno.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare('INSERT INTO zaci (id_zaka, name, password_hash, mesicni_splatka, konto, id_pokoje) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$id_zaka, $name, $password_hash, $mesicni_splatka, $konto, $id_pokoje]);
                
                if ($id_pokoje) {
                    $student_stmt = $pdo->prepare('SELECT id_zaka FROM zaci WHERE id_pokoje = ? ORDER BY id_zaka');
                    $student_stmt->execute([$id_pokoje]);
                    $students = $student_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    $student_ids_json = json_encode($students);
                    $room_stmt = $pdo->prepare('UPDATE pokoje SET student_ids = ? WHERE id_pokoje = ?');
                    $room_stmt->execute([$student_ids_json, $id_pokoje]);
                }
                
                $message = 'Registrace proběhla úspěšně! Nyní se můžete přihlásit.';
                $submitted_data = [];
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'Toto ID žáka je již registrováno.';
            } else {
                $error = 'Chyba při registraci: ' . $e->getMessage();
            }
        }
    }
}

$pdo = connectDb();
$available_rooms = $pdo->query('SELECT id_pokoje, kapacita, COALESCE(JSON_LENGTH(student_ids), 0) as occupied FROM pokoje ORDER BY id_pokoje')->fetchAll();

foreach ($available_rooms as &$room) {
    $room['available'] = $room['kapacita'] - $room['occupied'];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrace studenta - Správa ubytování</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="top">
        <div class="wrapper">
            <nav class="nav">
                <div class="logo">Správa <span>ubytování</span></div>
            </nav>
        </div>
    </div>

    <div class="wrapper">
        <div class="register-container">
            <div class="register-card">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <div class="success-message">
                            <strong>✓ <?= htmlspecialchars($message) ?></strong>
                            <a href="login.php?role=student">Přihlásit se</a>
                        </div>
                    </div>
                <?php else: ?>
                    <h1>Registrace studenta</h1>
                    <p>Vyplňte formulář pro vytvoření vašeho účtu</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_zaka">ID žáka *</label>
                                <input type="text" id="id_zaka" name="id_zaka" value="<?= htmlspecialchars($submitted_data['id_zaka'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="name">Jméno *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($submitted_data['name'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Heslo *</label>
                                <input type="password" id="password" name="password" required>
                                <div class="password-info">Minimálně 6 znaků</div>
                            </div>
                            <div class="form-group">
                                <label for="password_confirm">Potvrzení hesla *</label>
                                <input type="password" id="password_confirm" name="password_confirm" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mesicni_splatka">Měsíční splátka (Kč)</label>
                                <input type="number" id="mesicni_splatka" name="mesicni_splatka" step="0.01" min="0" value="<?= htmlspecialchars($submitted_data['mesicni_splatka'] ?? 0) ?>">
                            </div>
                            <div class="form-group">
                                <label for="konto">Konto (Kč)</label>
                                <input type="number" id="konto" name="konto" step="0.01" min="0" value="<?= htmlspecialchars($submitted_data['konto'] ?? 0) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="id_pokoje">Pokoj</label>
                            <select id="id_pokoje" name="id_pokoje">
                                <option value="">- Bez pokoje -</option>
                                <?php foreach ($available_rooms as $room): ?>
                                    <option value="<?= $room['id_pokoje'] ?>" <?= ($submitted_data['id_pokoje'] ?? null) == $room['id_pokoje'] ? 'selected' : '' ?>>
                                        Pokoj <?= htmlspecialchars($room['id_pokoje']) ?> (dostupné: <?= htmlspecialchars($room['available']) ?>/<?= htmlspecialchars($room['kapacita']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit">Zaregistrovat se</button>
                            <button type="reset" class="secondary">Vymazat</button>
                        </div>
                    </form>

                    <div class="login-link">
                        <p>Již máte účet?</p>
                        <a href="login.php?role=student">Přihlásit se</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
