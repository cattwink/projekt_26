<?php
function vytvorenitabulek() {
$host = 'localhost';
$dbname = 'ubytovani';
$username = 'root';
$password = 'admin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pokoje (
            id_pokoje INT NOT NULL AUTO_INCREMENT,
            kapacita  INT NOT NULL,
            student_ids JSON DEFAULT NULL,
            PRIMARY KEY (id_pokoje)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'pokoje' created successfully.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zaci (
            id_zaka         VARCHAR(50)    NOT NULL,
            name            VARCHAR(255)   NOT NULL,
            password_hash   VARCHAR(255)   NOT NULL,
            mesicni_splatka DECIMAL(10,2) NOT NULL,
            konto           DECIMAL(10,2) NOT NULL,
            id_pokoje       INT           NULL,
            PRIMARY KEY (id_zaka),
            CONSTRAINT fk_zaci_pokoje
                FOREIGN KEY (id_pokoje)
                REFERENCES pokoje (id_pokoje)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'zaci' created successfully.<br>";

    $checkColumn = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='zaci' AND COLUMN_NAME='password_hash' AND TABLE_SCHEMA='{$dbname}'");
    if ($checkColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE zaci ADD COLUMN password_hash VARCHAR(255) NOT NULL AFTER id_zaka");
        echo "Column 'password_hash' added to 'zaci' table.<br>";
    }

    $checkName = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='zaci' AND COLUMN_NAME='name' AND TABLE_SCHEMA='{$dbname}'");
    if ($checkName->rowCount() === 0) {
        $pdo->exec("ALTER TABLE zaci ADD COLUMN name VARCHAR(255) NOT NULL AFTER id_zaka");
        echo "Column 'name' added to 'zaci' table.<br>";
    }

    $checkStudentIds = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pokoje' AND COLUMN_NAME='student_ids' AND TABLE_SCHEMA='{$dbname}'");
    if ($checkStudentIds->rowCount() === 0) {
        $pdo->exec("ALTER TABLE pokoje ADD COLUMN student_ids JSON DEFAULT NULL AFTER kapacita");
        echo "Column 'student_ids' added to 'pokoje' table.<br>";
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id_admin       INT NOT NULL AUTO_INCREMENT,
            username       VARCHAR(50) NOT NULL UNIQUE,
            password_hash  VARCHAR(255) NOT NULL,
            PRIMARY KEY (id_admin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'admin_users' created successfully.<br>";

    $defaultAdmin = 'admin';
    $defaultPass = password_hash('admin', PASSWORD_DEFAULT); 
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (username, password_hash) VALUES (:username, :password_hash)");
    $stmt->execute([
        ':username' => $defaultAdmin,
        ':password_hash' => $defaultPass
    ]);
    echo "Default admin user added successfully.<br>";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
}
vytvorenitabulek();
?>