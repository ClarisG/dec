<?php
// ai/jurisdiction_predictor.php - AI Model for Auto-fill
class JurisdictionPredictor {
    private $model_path = __DIR__ . '/models/jurisdiction_model.pkl';
    private $keywords = [
        'barangay' => [
            'noise', 'neighbor', 'dispute', 'argument', 'verbal', 'pet', 'animal',
            'garbage', 'sanitation', 'boundary', 'parking', 'minor', 'youth',
            'ordinance', 'water', 'electricity', 'construction', 'business',
            'kapitbahay', 'away', 'alitan', 'basura', 'tambay', 'maingay',
            'videoke', 'karaoke', 'barangay', 'tanod', 'kagawad', 'captain',
            'community', 'neighborhood', 'local', 'ordinance', 'mediation'
        ],
        'police' => [
            'assault', 'robbery', 'theft', 'drug', 'weapon', 'violence', 'physical',
            'injury', 'sexual', 'fraud', 'scam', 'kidnap', 'fire', 'accident',
            'shooting', 'missing', 'murder', 'homicide', 'rape', 'molestation',
            'police', 'pulis', 'station', 'arrest', 'detain', 'investigate',
            'criminal', 'crime', 'felony', 'warrant', 'suspect', 'offender',
            'illegal', 'contraband', 'prohibited', 'baril', 'saksak', 'bugbog',
            'holdap', 'nanakaw', 'nadukutan', 'carnapping', 'ginahasa', 'pinatay'
        ]
    ];
    
    public function predict($text) {
        $lower_text = strtolower($text);
        
        $barangay_score = 0;
        $police_score = 0;
        
        foreach ($this->keywords['barangay'] as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $lower_text)) {
                $barangay_score += 2;
            } elseif (strpos($lower_text, $keyword) !== false) {
                $barangay_score += 1;
            }
        }
        
        foreach ($this->keywords['police'] as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $lower_text)) {
                $police_score += 2;
            } elseif (strpos($lower_text, $keyword) !== false) {
                $police_score += 1;
            }
        }
        
        // Add weight for urgency indicators
        if (strpos($lower_text, 'emergency') !== false || 
            strpos($lower_text, 'urgent') !== false ||
            strpos($lower_text, 'immediate') !== false ||
            strpos($lower_text, 'critical') !== false) {
            $police_score += 2;
        }
        
        $total = $barangay_score + $police_score;
        if ($total === 0) return ['jurisdiction' => 'uncertain', 'confidence' => 0];
        
        if ($police_score > $barangay_score) {
            $confidence = ($police_score / $total) * 100;
            return ['jurisdiction' => 'police', 'confidence' => $confidence];
        } else {
            $confidence = ($barangay_score / $total) * 100;
            return ['jurisdiction' => 'barangay', 'confidence' => $confidence];
        }
    }
    
    public function extract_keywords($text) {
        $lower_text = strtolower($text);
        $all_keywords = array_merge($this->keywords['barangay'], $this->keywords['police']);
        $found_keywords = [];
        
        foreach ($all_keywords as $keyword) {
            if (strpos($lower_text, $keyword) !== false && !in_array($keyword, $found_keywords)) {
                $found_keywords[] = $keyword;
            }
        }
        
        return array_slice($found_keywords, 0, 10); // Return top 10 keywords
    }
    
    public function suggest_report_type($text, $category) {
        $lower_text = strtolower($text);
        
        // Enhanced mapping of keywords to report types
        $suggestions = [
            // Incident mappings
            'nanakawan' => [7, 'Theft / Pagnanakaw'],
            'nadukutan' => [7, 'Theft / Pagnanakaw'],
            'theft' => [7, 'Theft / Pagnanakaw'],
            'robbery' => [7, 'Theft / Pagnanakaw'],
            'holdap' => [51, 'Robbery/Hold-up'],
            'assault' => [8, 'Assault / Pag-assalto'],
            'attack' => [8, 'Assault / Pag-assalto'],
            'bugbog' => [8, 'Assault / Pag-assalto'],
            'sinaksak' => [72, 'Stabbing/Cutting Incident'],
            'drug' => [4, 'Drug-related Activity'],
            'shabu' => [4, 'Drug-related Activity'],
            'marijuana' => [4, 'Drug-related Activity'],
            'sunog' => [6, 'Fire Incident / Sunog'],
            'fire' => [6, 'Fire Incident / Sunog'],
            'aksidente' => [5, 'Traffic Accident / Aksidente sa Trapiko'],
            'banggaan' => [5, 'Traffic Accident / Aksidente sa Trapiko'],
            'missing' => [9, 'Missing Person / Nawawalang Tao'],
            'nawawala' => [9, 'Missing Person / Nawawalang Tao'],
            'ginahasa' => [57, 'Sexual Assault/Rape'],
            'rape' => [57, 'Sexual Assault/Rape'],
            'pinatay' => [56, 'Homicide/Murder'],
            'murder' => [56, 'Homicide/Murder'],
            'kidnap' => [59, 'Abduction/Kidnapping'],
            'dinukot' => [59, 'Abduction/Kidnapping'],
            
            // Complaint mappings
            'noise' => [11, 'Noise Complaint / Reklamo sa Ingay'],
            'maingay' => [11, 'Noise Complaint / Reklamo sa Ingay'],
            'videoke' => [11, 'Noise Complaint / Reklamo sa Ingay'],
            'karaoke' => [11, 'Noise Complaint / Reklamo sa Ingay'],
            'garbage' => [12, 'Sanitation Issue / Isyu sa Kalinisan'],
            'basura' => [12, 'Sanitation Issue / Isyu sa Kalinisan'],
            'neighbor' => [15, 'Neighbor Dispute / Alitan ng Kapitbahay'],
            'kapitbahay' => [15, 'Neighbor Dispute / Alitan ng Kapitbahay'],
            'animal' => [13, 'Animal Nuisance / Abala mula sa Hayop'],
            'aso' => [13, 'Animal Nuisance / Abala mula sa Hayop'],
            'parking' => [14, 'Boundary Dispute / Hidwaan sa Hangganan'],
            'water' => [18, 'Water Issue / Problema sa Tubig'],
            'electricity' => [19, 'Electricity Issue / Problema sa Kuryente'],
            'kuryente' => [19, 'Electricity Issue / Problema sa Kuryente'],
            'dispute' => [21, 'Verbal Altercation / Alitan sa Salita'],
            'argument' => [21, 'Verbal Altercation / Alitan sa Salita'],
            'away' => [21, 'Verbal Altercation / Alitan sa Salita'],
            'harassment' => [23, 'Harassment / Pangha-harass'],
            'trespassing' => [24, 'Trespassing / Pagpasok nang Walang Pahintulot'],
            'child' => [26, 'Child-related Issue / Isyu tungkol sa Bata'],
            'bata' => [26, 'Child-related Issue / Isyu tungkol sa Bata'],
            'elderly' => [27, 'Elderly Abuse / Pang-aabuso sa Matanda'],
            'matanda' => [27, 'Elderly Abuse / Pang-aabuso sa Matanda'],
            'slander' => [28, 'Slander / Paninirang Puri'],
            'paninirang puri' => [28, 'Slander / Paninirang Puri']
        ];
        
        foreach ($suggestions as $keyword => $suggestion) {
            if (strpos($lower_text, $keyword) !== false) {
                return $suggestion;
            }
        }
        
        // Default suggestions by category
        $defaults = [
            'incident' => [1, 'Public Disturbance / Gulo sa Publiko'],
            'complaint' => [11, 'Noise Complaint / Reklamo sa Ingay'],
            'blotter' => [21, 'Verbal Altercation / Alitan sa Salita']
        ];
        
        return $defaults[$category] ?? $defaults['incident'];
    }
}

// Initialize predictor
$ai_predictor = new JurisdictionPredictor();
?>