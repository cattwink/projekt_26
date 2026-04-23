<?php
session_start();
require_once __DIR__ . '/scripts/db-connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?role=admin');
    exit;
}

$adminUsername = $_SESSION['username'];
$pdo = connectDb();
$message = '';
$error = '';

// Handle logout
if ($_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

function updateRoomStudentIds($pdo, $room_id) {
    if ($room_id) {
        $stmt = $pdo->prepare('SELECT id_zaka FROM zaci WHERE id_pokoje = ? ORDER BY id_zaka');
        $stmt->execute([$room_id]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $student_ids_json = json_encode($students);
        $update_stmt = $pdo->prepare('UPDATE pokoje SET student_ids = ? WHERE id_pokoje = ?');
        $update_stmt->execute([$student_ids_json, $room_id]);
    }
}

// Add new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_student') {
    $id_zaka = trim($_POST['id_zaka'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $mesicni_splatka = floatval($_POST['mesicni_splatka'] ?? 0);
    $konto = floatval($_POST['konto'] ?? 0);
    $id_pokoje = !empty($_POST['id_pokoje']) ? intval($_POST['id_pokoje']) : null;
    $password = trim($_POST['password'] ?? '');

    if ($id_zaka && $name && $password) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO zaci (id_zaka, name, password_hash, mesicni_splatka, konto, id_pokoje) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id_zaka, $name, $password_hash, $mesicni_splatka, $konto, $id_pokoje]);
            updateRoomStudentIds($pdo, $id_pokoje);
            $message = 'Žák úspěšně přidán.';
        } catch (PDOException $e) {
            $error = 'Chyba při přidávání žáka: ' . $e->getMessage();
        }
    } else {
        $error = 'ID žáka, jméno a heslo jsou povinné.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_student') {
    $id_zaka = trim($_POST['id_zaka'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $mesicni_splatka = floatval($_POST['mesicni_splatka'] ?? 0);
    $konto = floatval($_POST['konto'] ?? 0);
    $new_id_pokoje = !empty($_POST['id_pokoje']) ? intval($_POST['id_pokoje']) : null;

    if ($id_zaka && $name) {
        try {
            $get_stmt = $pdo->prepare('SELECT id_pokoje FROM zaci WHERE id_zaka = ?');
            $get_stmt->execute([$id_zaka]);
            $old_room = $get_stmt->fetchColumn();

            $stmt = $pdo->prepare('UPDATE zaci SET name = ?, mesicni_splatka = ?, konto = ?, id_pokoje = ? WHERE id_zaka = ?');
            $stmt->execute([$name, $mesicni_splatka, $konto, $new_id_pokoje, $id_zaka]);
            
            if ($old_room !== $new_id_pokoje) {
                updateRoomStudentIds($pdo, $old_room);
                updateRoomStudentIds($pdo, $new_id_pokoje);
            } else {
                updateRoomStudentIds($pdo, $new_id_pokoje);
            }
            
            $message = 'Žák úspěšně aktualizován.';
        } catch (PDOException $e) {
            $error = 'Chyba při aktualizaci žáka: ' . $e->getMessage();
        }
    } else {
        $error = 'ID žáka a jméno jsou povinné.';
    }
}

if ($_GET['action'] === 'delete_student' && $_GET['id_zaka']) {
    try {
        $get_stmt = $pdo->prepare('SELECT id_pokoje FROM zaci WHERE id_zaka = ?');
        $get_stmt->execute([$_GET['id_zaka']]);
        $room_id = $get_stmt->fetchColumn();
        
        $stmt = $pdo->prepare('DELETE FROM zaci WHERE id_zaka = ?');
        $stmt->execute([$_GET['id_zaka']]);
        
        updateRoomStudentIds($pdo, $room_id);
        
        $message = 'Žák úspěšně odstraněn.';
    } catch (PDOException $e) {
        $error = 'Chyba při odstraňování žáka: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_room') {
    $kapacita = intval($_POST['kapacita'] ?? 0);

    if ($kapacita > 0) {
        try {
            $stmt = $pdo->prepare('INSERT INTO pokoje (kapacita) VALUES (?)');
            $stmt->execute([$kapacita]);
            $message = 'Pokoj úspěšně přidán.';
        } catch (PDOException $e) {
            $error = 'Chyba při přidávání pokoje: ' . $e->getMessage();
        }
    } else {
        $error = 'Kapacita musí být větší než 0.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_room') {
    $id_pokoje = intval($_POST['id_pokoje'] ?? 0);
    $kapacita = intval($_POST['kapacita'] ?? 0);

    if ($id_pokoje && $kapacita > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE pokoje SET kapacita = ? WHERE id_pokoje = ?');
            $stmt->execute([$kapacita, $id_pokoje]);
            $message = 'Pokoj úspěšně aktualizován.';
        } catch (PDOException $e) {
            $error = 'Chyba při aktualizaci pokoje: ' . $e->getMessage();
        }
    }
}

if ($_GET['action'] === 'delete_room' && $_GET['id_pokoje']) {
    try {
        $room_id = intval($_GET['id_pokoje']);
        $stmt = $pdo->prepare('DELETE FROM pokoje WHERE id_pokoje = ?');
        $stmt->execute([$room_id]);
        $message = 'Pokoj úspěšně odstraněn.';
    } catch (PDOException $e) {
        $error = 'Chyba při odstraňování pokoje: ' . $e->getMessage();
    }
}

$students = $pdo->query('SELECT z.*, p.kapacita FROM zaci z LEFT JOIN pokoje p ON z.id_pokoje = p.id_pokoje ORDER BY z.id_zaka')->fetchAll();
$rooms = $pdo->query('SELECT p.*, COUNT(z.id_zaka) as pocet_zaku FROM pokoje p LEFT JOIN zaci z ON p.id_pokoje = z.id_pokoje GROUP BY p.id_pokoje ORDER BY p.id_pokoje')->fetchAll();
$available_rooms = $pdo->query('SELECT * FROM pokoje ORDER BY id_pokoje')->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa ubytování - Admin panel</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="top">
        <div class="wrapper">
            <nav class="nav">
                <div class="logo">Správa <span>ubytování</span></div>
                <div class="menu">
                    <span>Přihlášen: <strong><?= htmlspecialchars($adminUsername) ?></strong></span>
                    <a href="?action=logout" class="logout-btn">Odhlásit se</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="wrapper">
        <div class="admin-header">
            <h1>Admin panel</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>📋 Správa pokojů</h2>

            <button class="toggle-form" onclick="toggleForm('addRoomForm')">+ Přidat nový pokoj</button>

            <form method="POST" class="form-content" id="addRoomForm">
                <input type="hidden" name="action" value="add_room">
                <div class="form-group">
                    <label for="kapacita">Kapacita pokoje:</label>
                    <input type="number" id="kapacita" name="kapacita" min="1" required>
                </div>
                <button type="submit">Přidat pokoj</button>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID Pokoje</th>
                            <th>Kapacita</th>
                            <th>Počet žáků</th>
                            <th>IDs žáků</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rooms): ?>
                            <?php foreach ($rooms as $room): 
                                $student_ids = $room['student_ids'] ? json_decode($room['student_ids'], true) : [];
                                $student_ids_display = !empty($student_ids) ? implode(', ', $student_ids) : '—';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($room['id_pokoje']) ?></td>
                                    <td><?= htmlspecialchars($room['kapacita']) ?></td>
                                    <td><?= htmlspecialchars($room['pocet_zaku'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($student_ids_display) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-small btn-edit" onclick="editRoom(<?= $room['id_pokoje'] ?>, <?= $room['kapacita'] ?>)">Upravit</button>
                                            <a href="?action=delete_room&id_pokoje=<?= $room['id_pokoje'] ?>" class="btn-small btn-danger" onclick="return confirm('Opravdu chcete odstranit pokoj?')">Smazat</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Žádné pokoje nejsou k dispozici</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" id="editRoomForm" style="display: none; margin-top: 1.5rem; background: var(--bg); padding: 1rem; border-radius: 4px;">
                <input type="hidden" name="action" value="edit_room">
                <input type="hidden" id="edit_id_pokoje" name="id_pokoje">
                <div class="form-group">
                    <label for="edit_kapacita">Kapacita pokoje:</label>
                    <input type="number" id="edit_kapacita" name="kapacita" min="1" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit">Uložit změny</button>
                    <button type="button" class="secondary" onclick="cancelEditRoom()">Zrušit</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>👥 Správa žáků</h2>

            <button class="toggle-form" onclick="toggleForm('addStudentForm')">+ Přidat nového žáka</button>

            <form method="POST" class="form-content" id="addStudentForm">
                <input type="hidden" name="action" value="add_student">
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_zaka">ID žáka:</label>
                        <input type="text" id="id_zaka" name="id_zaka" required>
                    </div>
                    <div class="form-group">
                        <label for="name">Jméno:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Heslo:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mesicni_splatka">Měsíční splátka:</label>
                        <input type="number" id="mesicni_splatka" name="mesicni_splatka" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="konto">Konto:</label>
                        <input type="number" id="konto" name="konto" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="id_pokoje">Pokoj:</label>
                        <select id="id_pokoje" name="id_pokoje">
                            <option value="">- Bez pokoje -</option>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?= $room['id_pokoje'] ?>">Pokoj <?= htmlspecialchars($room['id_pokoje']) ?> (kapacita: <?= htmlspecialchars($room['kapacita']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit">Přidat žáka</button>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID Žáka</th>
                            <th>Jméno</th>
                            <th>Pokoj</th>
                            <th>Měsíční splátka</th>
                            <th>Konto</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['id_zaka']) ?></td>
                                    <td><?= htmlspecialchars($student['name'] ?? 'N/A') ?></td>
                                    <td><?= $student['id_pokoje'] ? 'Pokoj ' . htmlspecialchars($student['id_pokoje']) : 'Bez pokoje' ?></td>
                                    <td><?= number_format($student['mesicni_splatka'], 2) ?> Kč</td>
                                    <td><?= number_format($student['konto'], 2) ?> Kč</td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-small btn-edit" onclick="editStudent('<?= htmlspecialchars($student['id_zaka']) ?>', '<?= htmlspecialchars($student['name']) ?>', <?= htmlspecialchars($student['mesicni_splatka']) ?>, <?= htmlspecialchars($student['konto']) ?>, <?= $student['id_pokoje'] ?? 'null' ?>)">Upravit</button>
                                            <a href="?action=delete_student&id_zaka=<?= urlencode($student['id_zaka']) ?>" class="btn-small btn-danger" onclick="return confirm('Opravdu chcete odstranit žáka?')">Smazat</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">Žádní žáci nejsou k dispozici</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Student Form -->
            <form method="POST" id="editStudentForm" style="display: none; margin-top: 1.5rem; background: var(--bg); padding: 1rem; border-radius: 4px;">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" id="edit_id_zaka" name="id_zaka">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Jméno:</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_mesicni_splatka">Měsíční splátka:</label>
                        <input type="number" id="edit_mesicni_splatka" name="mesicni_splatka" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_konto">Konto:</label>
                        <input type="number" id="edit_konto" name="konto" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_id_pokoje">Pokoj:</label>
                        <select id="edit_id_pokoje" name="id_pokoje">
                            <option value="">- Bez pokoje -</option>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?= $room['id_pokoje'] ?>">Pokoj <?= htmlspecialchars($room['id_pokoje']) ?> (kapacita: <?= htmlspecialchars($room['kapacita']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit">Uložit změny</button>
                    <button type="button" class="secondary" onclick="cancelEditStudent()">Zrušit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.classList.toggle('show');
        }

        function editStudent(id_zaka, name, mesicni_splatka, konto, id_pokoje) {
            document.getElementById('edit_id_zaka').value = id_zaka;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_mesicni_splatka').value = mesicni_splatka;
            document.getElementById('edit_konto').value = konto;
            document.getElementById('edit_id_pokoje').value = id_pokoje || '';
            document.getElementById('editStudentForm').style.display = 'block';
            window.scrollTo({ top: document.getElementById('editStudentForm').offsetTop - 100, behavior: 'smooth' });
        }

        function cancelEditStudent() {
            document.getElementById('editStudentForm').style.display = 'none';
        }

        function editRoom(id_pokoje, kapacita) {
            document.getElementById('edit_id_pokoje').value = id_pokoje;
            document.getElementById('edit_kapacita').value = kapacita;
            document.getElementById('editRoomForm').style.display = 'block';
            window.scrollTo({ top: document.getElementById('editRoomForm').offsetTop - 100, behavior: 'smooth' });
        }

        function cancelEditRoom() {
            document.getElementById('editRoomForm').style.display = 'none';
        }
    </script>
</body>
</html>
