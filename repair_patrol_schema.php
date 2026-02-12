<?php
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    echo "Starting database schema repair for patrol_routes...\n";

    // Check existing columns
    $stmt = $conn->query("SHOW COLUMNS FROM patrol_routes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Existing columns: " . implode(", ", $columns) . "\n";

    // 1. Fix 'description' -> 'route_description'
    if (in_array('description', $columns) && !in_array('route_description', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes CHANGE COLUMN description route_description TEXT");
        echo "Renamed 'description' to 'route_description'.\n";
    } elseif (!in_array('route_description', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN route_description TEXT AFTER route_name");
        echo "Added 'route_description' column.\n";
    }

    // 2. Fix 'estimated_time' -> 'estimated_duration'
    if (in_array('estimated_time', $columns) && !in_array('estimated_duration', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes CHANGE COLUMN estimated_time estimated_duration INT");
        echo "Renamed 'estimated_time' to 'estimated_duration'.\n";
    } elseif (!in_array('estimated_duration', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN estimated_duration INT AFTER route_description");
        echo "Added 'estimated_duration' column.\n";
    }

    // 3. Fix 'waypoints' -> 'route_coordinates'
    if (in_array('waypoints', $columns) && !in_array('route_coordinates', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes CHANGE COLUMN waypoints route_coordinates LONGTEXT");
        echo "Renamed 'waypoints' to 'route_coordinates'.\n";
    } elseif (!in_array('route_coordinates', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN route_coordinates LONGTEXT");
        echo "Added 'route_coordinates' column.\n";
    }

    // 4. Add 'checkpoint_count'
    if (!in_array('checkpoint_count', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN checkpoint_count INT DEFAULT 0 AFTER estimated_duration");
        echo "Added 'checkpoint_count' column.\n";
    }
    
    // 5. Add 'updated_at'
    if (!in_array('updated_at', $columns)) {
        $conn->exec("ALTER TABLE patrol_routes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added 'updated_at' column.\n";
    }

    echo "Schema repair completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>