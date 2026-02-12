<?php
require_once 'config/database.php';
try {
    $conn = getDbConnection();
    // Add route_description column
    $conn->exec("ALTER TABLE patrol_routes ADD COLUMN route_description TEXT AFTER route_name");
    echo "Added route_description column.\n";
    
    // Add estimated_duration column if not exists (check first)
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes LIKE 'estimated_duration'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN estimated_duration INT AFTER route_description");
        echo "Added estimated_duration column.\n";
    }
    
    // Add checkpoint_count column if not exists
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes LIKE 'checkpoint_count'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN checkpoint_count INT AFTER estimated_duration");
        echo "Added checkpoint_count column.\n";
    }

    // Add route_coordinates column if not exists
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes LIKE 'route_coordinates'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN route_coordinates LONGTEXT AFTER checkpoint_count");
        echo "Added route_coordinates column.\n";
    }
    
    // Add updated_at column if not exists
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added updated_at column.\n";
    }

    echo "Table schema update complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>