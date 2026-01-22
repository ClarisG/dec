<?php
// setup_database.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Setup</h2>";

// Database credentials
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server<br>";
    
    // Create database if not exists
    $dbname = 'leir_db';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '$dbname' created or already exists<br>";
    
    // Use the database
    $pdo->exec("USE `$dbname`");
    
    // Create users table
    $users_table = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `contact_number` VARCHAR(20),
        `address` TEXT,
        `barangay` VARCHAR(100),
        `role` ENUM('citizen', 'tanod', 'lupon', 'secretary', 'captain', 'admin') DEFAULT 'citizen',
        `status` ENUM('active', 'inactive', 'pending') DEFAULT 'active',
        `profile_picture` VARCHAR(255),
        `is_active` TINYINT(1) DEFAULT 1,
        `is_online` TINYINT(1) DEFAULT 0,
        `is_chairman` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($users_table);
    echo "✓ Users table created<br>";
    
    // Create reports table
    $reports_table = "
    CREATE TABLE IF NOT EXISTS `reports` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11),
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `category` VARCHAR(100),
        `status` ENUM('pending', 'assigned', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
        `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `location` VARCHAR(255),
        `incident_date` DATE,
        `assigned_lupon` INT(11),
        `assigned_lupon_chairman` INT(11),
        `assigned_tanod` INT(11),
        `actions_taken` TEXT,
        `blotter_number` VARCHAR(50),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`assigned_lupon`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`assigned_lupon_chairman`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`assigned_tanod`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($reports_table);
    echo "✓ Reports table created<br>";
    
    // Create report_attachments table
    $attachments_table = "
    CREATE TABLE IF NOT EXISTS `report_attachments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `report_id` INT(11),
        `filename` VARCHAR(255) NOT NULL,
        `filepath` VARCHAR(500) NOT NULL,
        `filetype` VARCHAR(50),
        `filesize` INT(11),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($attachments_table);
    echo "✓ Report attachments table created<br>";
    
    // Create notifications table
    $notifications_table = "
    CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11),
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT,
        `type` VARCHAR(50),
        `is_read` TINYINT(1) DEFAULT 0,
        `related_id` INT(11),
        `related_type` VARCHAR(50),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($notifications_table);
    echo "✓ Notifications table created<br>";
    
    // Create report_status_history table
    $history_table = "
    CREATE TABLE IF NOT EXISTS `report_status_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `report_id` INT(11),
        `status` VARCHAR(50),
        `updated_by` INT(11),
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($history_table);
    echo "✓ Report status history table created<br>";
    
    // Create blotter_records table
    $blotter_table = "
    CREATE TABLE IF NOT EXISTS `blotter_records` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `blotter_number` VARCHAR(50) NOT NULL UNIQUE,
        `complainant_name` VARCHAR(255) NOT NULL,
        `complainant_contact` VARCHAR(20),
        `case_category` VARCHAR(100),
        `incident_date` DATE,
        `description` TEXT,
        `initial_action` TEXT,
        `assigned_to` INT(11),
        `status` ENUM('active', 'closed', 'archived') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($blotter_table);
    echo "✓ Blotter records table created<br>";
    
    // Create case_notes table
    $case_notes_table = "
    CREATE TABLE IF NOT EXISTS `case_notes` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `report_id` INT(11),
        `user_id` INT(11),
        `note` TEXT NOT NULL,
        `type` ENUM('update', 'hearing', 'resolution', 'other') DEFAULT 'update',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($case_notes_table);
    echo "✓ Case notes table created<br>";
    
    // Insert sample data if tables are empty
    echo "<h3>Checking for sample data...</h3>";
    
    // Check if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Insert sample users
        $sample_users = [
            "('Juan', 'Dela Cruz', 'juan@email.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', '09123456789', '123 Main St', 'Barangay 1', 'secretary', 'active', NULL, 1, 0, 0)",
            '("Maria", "Santos", "maria@email.com", "' . password_hash('password123', PASSWORD_DEFAULT) . '", "09123456788", "456 Elm St", "Barangay 2", "lupon", "active", NULL, 1, 0, 0)',
            '("Pedro", "Gomez", "pedro@email.com", "' . password_hash('password123', PASSWORD_DEFAULT) . '", "09123456787", "789 Oak St", "Barangay 3", "lupon_chairman", "active", NULL, 1, 0, 1)',
            '("Luis", "Reyes", "luis@email.com", "' . password_hash('password123', PASSWORD_DEFAULT) . '", "09123456786", "321 Pine St", "Barangay 4", "tanod", "active", NULL, 1, 0, 0)',
            '("Ana", "Torres", "ana@email.com", "' . password_hash('password123', PASSWORD_DEFAULT) . '", "09123456785", "654 Cedar St", "Barangay 5", "citizen", "active", NULL, 1, 0, 0)'
        ];
        
        foreach ($sample_users as $user) {
            $pdo->exec("INSERT INTO users (first_name, last_name, email, password, contact_number, address, barangay, role, status, profile_picture, is_active, is_online, is_chairman) VALUES $user");
        }
        
        echo "✓ Sample users added<br>";
    } else {
        echo "Users table already has data<br>";
    }
    
    // Check if reports table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Insert sample reports
        $sample_reports = [
            "(1, 'Noise Complaint', 'Excessive noise from neighbors party late at night', 'Barangay Matter', 'pending', 'medium', '123 Main St', '2024-01-15', NULL, NULL, NULL, 'Reported to authorities')",
            "(5, 'Property Dispute', 'Boundary dispute between neighbors', 'Civil', 'pending', 'high', '654 Cedar St', '2024-01-10', NULL, NULL, NULL, 'Initial assessment done')",
            "(5, 'Theft Incident', 'Stolen bicycle from garage', 'Criminal', 'assigned', 'high', '654 Cedar St', '2024-01-05', 2, NULL, NULL, 'Police notified')",
            "(5, 'Public Disturbance', 'Fight in public area', 'Criminal', 'in_progress', 'high', 'Main Road', '2024-01-03', NULL, NULL, 4, 'Tanod dispatched')",
            "(1, 'Child Abuse Report', 'Suspected child abuse case', 'Minor', 'resolved', 'high', '123 Main St', '2023-12-20', 3, NULL, NULL, 'Case resolved through mediation')"
        ];
        
        foreach ($sample_reports as $report) {
            $pdo->exec("INSERT INTO reports (user_id, title, description, category, status, priority, location, incident_date, assigned_lupon, assigned_lupon_chairman, assigned_tanod, actions_taken) VALUES $report");
        }
        
        echo "✓ Sample reports added<br>";
    } else {
        echo "Reports table already has data<br>";
    }
    
    echo "<h3 style='color:green;'>✓ Database setup completed successfully!</h3>";
    echo "<p>You can now:</p>";
    echo "<ol>";
    echo "<li><a href='test_db.php'>Test Database Connection</a></li>";
    echo "<li><a href='login.php'>Go to Login Page</a></li>";
    echo "<li><a href='index.php'>Go to Home Page</a></li>";
    echo "</ol>";
    
} catch(PDOException $e) {
    echo "<span style='color:red;'>Error: " . $e->getMessage() . "</span><br>";
    echo "<p>Make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL is running (XAMPP/WAMP)</li>";
    echo "<li>MySQL username/password are correct</li>";
    echo "<li>You have permission to create databases</li>";
    echo "</ul>";
}
?>