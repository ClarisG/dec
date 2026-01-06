<?php
// ajax/analyze_report.php - Simplified Analysis
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    $category = $input['category'] ?? 'incident';
    
    try {
        $conn = getDbConnection();
        
        // Get report types for analysis
        $types_query = "SELECT * FROM report_types WHERE category = :category";
        $types_stmt = $conn->prepare($types_query);
        $types_stmt->bindParam(':category', $category);
        $types_stmt->execute();
        $types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Simple keyword matching
        $text_lower = strtolower($text);
        $matches = [];
        
        foreach ($types as $type) {
            $score = 0;
            $keywords = explode(',', strtolower($type['keywords'] ?? ''));
            
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    if (strpos($text_lower, $keyword) !== false) {
                        $score += 2;
                    }
                    if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $text_lower)) {
                        $score += 3;
                    }
                }
            }
            
            // Check type name
            $type_name_lower = strtolower($type['type_name']);
            if (strpos($text_lower, $type_name_lower) !== false) {
                $score += 1;
            }
            
            if ($score > 0) {
                $matches[] = [
                    'id' => $type['id'],
                    'type_name' => $type['type_name'],
                    'description' => $type['description'],
                    'jurisdiction' => $type['jurisdiction'],
                    'score' => $score,
                    'confidence' => min($score / 10, 1.0)
                ];
            }
        }
        
        // Sort by score
        usort($matches, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $top_match = !empty($matches) ? $matches[0] : null;
        
        echo json_encode([
            'success' => true,
            'predicted_category' => $category,
            'predicted_type' => $top_match ? $top_match['type_name'] : 'General ' . ucfirst($category),
            'confidence' => $top_match ? $top_match['confidence'] : 0.1,
            'suggestions' => array_slice($matches, 0, 3),
            'total_matches' => count($matches)
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>