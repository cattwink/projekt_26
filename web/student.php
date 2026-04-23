<?php
session_start();
require_once __DIR__ . '/scripts/db-connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php?role=student');
    exit;
}

$studentId = $_SESSION['username'];
$pdo = connectDb();

$stmt = $pdo->prepare('SELECT * FROM zaci WHERE id_zaka = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php?role=student');
    exit;
}
$roommates = [];
$roomInfo = null;
if ($student['id_pokoje']) {
    $stmt = $pdo->prepare('SELECT * FROM pokoje WHERE id_pokoje = ?');
    $stmt->execute([$student['id_pokoje']]);
    $roomInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT id_zaka FROM zaci WHERE id_pokoje = ? AND id_zaka != ? ORDER BY id_zaka');
    $stmt->execute([$student['id_pokoje'], $studentId]);
    $roommates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

if ($_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Můj profil - Správa ubytování</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="top">
        <div class="wrapper">
            <nav class="nav">
                <div class="logo">Správa <span>ubytování</span></div>
                <div class="menu">
                    <span>Přihlášen: <strong><?= htmlspecialchars($studentId) ?></strong></span>
                    <a href="?action=logout" class="logout-btn">Odhlásit se</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="wrapper">
        <div class="student-header">
            <div>
                <h1>Vítejte, <?= htmlspecialchars($student['name']) ?></h1>
                <p class="student-id">ID: <?= htmlspecialchars($studentId) ?></p>
            </div>
        </div>

        <div class="dashboard">
            <div class="card balance">
                <h2>Zůstatek na kontě</h2>
                <div class="card-value">
                    <?= number_format($student['konto'], 2) ?> Kč
                </div>
                <div class="card-subtext">
                    Vaš aktuální zůstatek
                </div>
            </div>

            <div class="card payment">
                <h2>Měsíční splátka</h2>
                <div class="card-value">
                    <?= number_format($student['mesicni_splatka'], 2) ?> Kč
                </div>
                <div class="card-subtext">
                    Vaše měsíční poplatek za ubytování
                </div>
            </div>

            <div class="card room">
                <h2>Pokoj</h2>
                <?php if ($student['id_pokoje']): ?>
                    <div class="room-info">
                        <div class="room-details">
                            <strong>Číslo pokoje:</strong>
                            <p><?= htmlspecialchars($student['id_pokoje']) ?></p>
                        </div>
                        <div class="room-details">
                            <strong>Kapacita pokoje:</strong>
                            <p><?= htmlspecialchars($roomInfo['kapacita']) ?> osob</p>
                        </div>

                        <?php if (!empty($roommates)): ?>
                            <div class="roommates-list">
                                <h3>Spolubydlící:</h3>
                                <?php foreach ($roommates as $roommate): ?>
                                    <div class="roommate-item">
                                        <?= htmlspecialchars($roommate) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-roommates">
                                Vy jste jediným člověkem v tomto pokoji
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-room">
                        <p>Nemáte přiřazen žádný pokoj</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
