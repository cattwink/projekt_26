<?php
$freeCapacity = 0;
$totalCapacity = 2500;
$freePercent = 0;

try {
    require_once __DIR__ . '/scripts/db-connect.php';
    $pdo = connectDb();
    
    $stmt = $pdo->query('SELECT COALESCE(SUM(kapacita), 0) AS capacity FROM pokoje');
    $totalCapacityRooms = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COALESCE(SUM(JSON_LENGTH(student_ids)), 0) AS count FROM pokoje WHERE student_ids IS NOT NULL');
    $occupiedSpaces = (int) $stmt->fetchColumn();
    
    $freeCapacity = $totalCapacityRooms - $occupiedSpaces;
    $freePercent = $totalCapacity > 0 ? round((1 - ($totalCapacityRooms / $totalCapacity)) * 100) : 100;
    if ($freePercent < 0) {
        $freePercent = 0;
    }
} catch (Exception $e) {
    $freeCapacity = 0;
    $freePercent = 100;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Správa Ubytování | Kolejní Portál</title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="styles.css" />
</head>
<body>
	<header class="top">
		<div class="wrapper">
			<nav class="nav" aria-label="Hlavní navigace">
				<div class="logo">Kolejní <span>Evidence</span></div>
			</nav>

			<section class="hero" aria-labelledby="hero-title">
				<div>
					<h1 id="hero-title">Správa ubytování na koleji</h1>
					<p>Jednoduchý portál pro přihlášení administrátora i studenta a rychlý přehled volných pokojů.</p>
					<div class="actions">
						<a class="btn btn-primary" href="login.php?role=admin">Admin přihlášení</a>
						<a class="btn btn-secondary" href="login.php?role=student">Student přihlášení</a>
					</div>
				</div>

				<aside class="hero-panel" aria-label="Přehled kapacit">
					<h3>Dostupné pokoje</h3>
					<div class="metric">
						<span>Celková kapacita</span>
						<strong><?php echo $totalCapacity; ?></strong>
					</div>
					<div class="metric">
						<span>Volná kapacita</span>
						<strong id="free-capacity"><?php echo $freeCapacity; ?></strong>
					</div>
					<div class="metric">
						<span>Prázdné</span>
						<strong id="free-percent"><?php echo $freePercent; ?>%</strong>
					</div>
					<div class="metric">
						<span>Stav systému</span>
						<strong class="status-ok">Online</strong>
					</div>
				</aside>
			</section>
		</div>
	</header>

	<main>
		<div class="wrapper">
			<section class="intro-grid" aria-label="Student registrace">
				<article class="card">
					<h2>Registrace studenta</h2>
					<p>Vytvořte nového studenta a připravte ho na přidělení pokoje.</p>
					<a class="btn btn-primary" href="student-register.php">Registrovat studenta</a>
				</article>
			</section>

			<footer class="footer" id="kontakt">
				<b>Správa ubytování na koleji</b> | Domovská stránka projektu | <span id="year"></span>
			</footer>
		</div>
	</main>

	<script>
		document.getElementById("year").textContent = new Date().getFullYear();
	</script>
</body>
</html>
