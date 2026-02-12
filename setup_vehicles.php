<?php
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    echo "Setting up vehicle tracking tables...\n";

    // 1. Create patrol_vehicles table
    $sql = "CREATE TABLE IF NOT EXISTS patrol_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_name VARCHAR(50) NOT NULL,
        plate_number VARCHAR(20),
        type VARCHAR(20) DEFAULT 'car',
        status ENUM('Active', 'Maintenance', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Created patrol_vehicles table.\n";

    // 2. Insert default vehicles if empty
    $stmt = $conn->query("SELECT COUNT(*) FROM patrol_vehicles");
    if ($stmt->fetchColumn() == 0) {
        $sql = "INSERT INTO patrol_vehicles (vehicle_name, plate_number, type) VALUES 
            ('Mobile 1', 'SJA-123', 'car'),
            ('Mobile 2', 'SJA-456', 'car'),
            ('Motor 1', 'MC-789', 'motorcycle'),
            ('Motor 2', 'MC-101', 'motorcycle'),
            ('Mobile 3', 'SJA-777', 'car')";
        $conn->exec($sql);
        echo "Inserted default vehicles.\n";
    }

    // 3. Update tanod_schedules to include vehicle_id
    // Check if column exists first
    $stmt = $conn->query("SHOW COLUMNS FROM tanod_schedules LIKE 'vehicle_id'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE tanod_schedules ADD COLUMN vehicle_id INT NULL AFTER user_id";
        $conn->exec($sql);
        echo "Added vehicle_id to tanod_schedules.\n";
    }

    // 4. Create vehicle_location_logs if not exists (for tracking)
    $sql = "CREATE TABLE IF NOT EXISTS vehicle_gps_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        speed FLOAT DEFAULT 0,
        status VARCHAR(20),
        logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (vehicle_id),
        INDEX (logged_at)
    )";
    $conn->exec($sql);
    echo "Created vehicle_gps_logs table.\n";

    echo "Setup complete successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
