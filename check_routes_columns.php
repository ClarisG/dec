<?php
require_once 'config/database.php';
try {
    $conn = getDbConnection();
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>