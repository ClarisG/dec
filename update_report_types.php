<?php
// update_report_types.php
// Run this script once to add the new report types to your database
// Place this file in your project root directory

// Find the config directory by going up one level from current directory
require_once __DIR__ . '/config/database.php';

try {
    $conn = getDbConnection();
    
    echo "<h2>Starting database update for new report types...</h2><br>";
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'report_types'");
    if ($checkTable->rowCount() == 0) {
        die("ERROR: report_types table does not exist!");
    }
    
    // List of new report types to add
    $newTypes = [
        // Property Crime
        [
            'type_name' => 'Property Crime / Krimen sa Ari-arian',
            'category' => 'incident',
            'jurisdiction' => 'police',
            'severity_level' => 'medium',
            'keywords' => 'lost,missing,stolen,nawala,ninakaw,kinuha,ari-arian,gamit,kagamitan',
            'description' => 'Reports of lost or stolen personal property'
        ],
        [
            'type_name' => 'Missing Person / Nawawalang Tao',
            'category' => 'incident',
            'jurisdiction' => 'police',
            'severity_level' => 'medium',
            'keywords' => 'missing person,nawawalang tao,nawala,hindi umuwi,lost person',
            'description' => 'Reports of missing individuals'
        ],
        [
            'type_name' => 'Missing Pet / Nawawalang Alaga',
            'category' => 'incident',
            'jurisdiction' => 'barangay',
            'severity_level' => 'low',
            'keywords' => 'missing pet,nawawalang aso,nawawalang pusa,lost animal,alaga nawala',
            'description' => 'Reports of lost or missing pets'
        ],
        // Complaint types
        [
            'type_name' => 'Public Nuisance / Istorbong Pampubliko',
            'category' => 'complaint',
            'jurisdiction' => 'barangay',
            'severity_level' => 'low',
            'keywords' => 'tambay,loitering,harang,obstruction,sagabal,istorbo',
            'description' => 'Public disturbances and obstructions'
        ],
        [
            'type_name' => 'Ordinance Violation / Paglabag sa Ordinansa',
            'category' => 'complaint',
            'jurisdiction' => 'barangay',
            'severity_level' => 'medium',
            'keywords' => 'ordinance violation,bawal,labag,curfew,liquor ban,illegal parking',
            'description' => 'Violations of barangay or municipal ordinances'
        ],
        [
            'type_name' => 'Consumer Complaint / Reklamong Pangkonsyumer',
            'category' => 'complaint',
            'jurisdiction' => 'barangay',
            'severity_level' => 'medium',
            'keywords' => 'overpriced,scam,panloloko,fake product,mandaraya,overpricing',
            'description' => 'Consumer protection and business complaints'
        ],
        // Blotter types
        [
            'type_name' => 'Domestic Dispute / Alitan sa Tahanan',
            'category' => 'blotter',
            'jurisdiction' => 'barangay',
            'severity_level' => 'medium',
            'keywords' => 'family dispute,away mag-asawa,domestic problem,problema sa pamilya',
            'description' => 'Family conflicts and domestic issues'
        ],
        [
            'type_name' => 'Property Conflict / Hidwaan sa Ari-arian',
            'category' => 'blotter',
            'jurisdiction' => 'barangay',
            'severity_level' => 'medium',
            'keywords' => 'boundary dispute,lupa away,property conflict,hangganan,lot line',
            'description' => 'Property and boundary disputes'
        ],
        [
            'type_name' => 'Record/Blotter Request / Kahilingan ng Blotter',
            'category' => 'blotter',
            'jurisdiction' => 'barangay',
            'severity_level' => 'low',
            'keywords' => 'blotter request,documentation,record only,pang-record,dokumento',
            'description' => 'Requests for documentation and record-keeping only'
        ]
    ];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($newTypes as $type) {
        // Check if type already exists
        $checkSql = "SELECT id FROM report_types WHERE type_name = :type_name";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':type_name' => $type['type_name']]);
        
        if ($checkStmt->rowCount() > 0) {
            echo "Skipping '{$type['type_name']}' - already exists<br>";
            $skippedCount++;
            continue;
        }
        
        // Insert new type
        $insertSql = "INSERT INTO report_types (type_name, category, jurisdiction, severity_level, keywords, description) 
                     VALUES (:type_name, :category, :jurisdiction, :severity_level, :keywords, :description)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([
            ':type_name' => $type['type_name'],
            ':category' => $type['category'],
            ':jurisdiction' => $type['jurisdiction'],
            ':severity_level' => $type['severity_level'],
            ':keywords' => $type['keywords'],
            ':description' => $type['description']
        ]);
        
        echo "Added: {$type['type_name']}<br>";
        $addedCount++;
    }
    
    // Update existing types with new keywords
    echo "<br><h3>Updating existing types with new keywords...</h3><br>";
    
    $updateKeywords = [
        ['Theft / Pagnanakaw', 'lost,missing,nawala,nanakawan,kinuha,ninakaw,stolen,ari-arian,gamit,kagamitan,property crime'],
        ['Missing Person / Nawawalang Tao', 'missing person,nawawalang tao,nawala,hindi umuwi,lost person,taong nawawala,missing case'],
        ['Sexual Assault/Rape', 'sexual harassment,hinipuan,binastos,ginahasa,manyak,catcalling,gender-based,vawc,sexual crime'],
        ['Public Disturbance / Gulo sa Publiko', 'tambay,loitering,harang,obstruction,sagabal,istorbo,public nuisance'],
        ['Neighbor Dispute / Alitan ng Kapitbahay', 'family dispute,away mag-asawa,domestic problem,problema sa pamilya,alitan sa tahanan'],
        ['Boundary Dispute / Hidwaan sa Hangganan', 'boundary dispute,lupa away,property conflict,hangganan,lot line,ari-arian,property conflict']
    ];
    
    foreach ($updateKeywords as $update) {
        $updateSql = "UPDATE report_types SET keywords = :keywords WHERE type_name = :type_name";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([
            ':keywords' => $update[1],
            ':type_name' => $update[0]
        ]);
        
        echo "Updated keywords for: {$update[0]}<br>";
    }
    
    echo "<br><h2>Update Complete!</h2>";
    echo "<p><strong>Added:</strong> {$addedCount} new report types</p>";
    echo "<p><strong>Skipped:</strong> {$skippedCount} existing types</p>";
    echo "<p><strong>Updated:</strong> " . count($updateKeywords) . " existing types with new keywords</p>";
    
    echo "<br><h3>Verification:</h3>";
    echo "<p><a href='?module=citizen-new-report'>Go to New Report page to test AI suggestions</a></p>";
    
} catch(PDOException $e) {
    echo "<h3>Database Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>