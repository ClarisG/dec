<?php
// get_ai_suggestions.php - FIXED AI Categorization for ALL Categories
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $conn = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $description = isset($_POST['description']) ? strtolower(trim($_POST['description'])) : '';
        $category_filter = isset($_POST['category']) ? $_POST['category'] : 'incident';
        
        if (empty($description)) {
            echo json_encode(['success' => false, 'message' => 'Walang nilagay na deskripsyon / No description provided']);
            exit;
        }
        
        // Get all report types from database (filtered by category)
        $query = "SELECT * FROM report_types WHERE category = :category AND type_name IS NOT NULL AND type_name != ''";
        $stmt = $conn->prepare($query);
        $stmt->execute([':category' => $category_filter]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // COMPREHENSIVE MULTILINGUAL KEYWORD DATABASE FOR ALL CATEGORIES
        $COMPREHENSIVE_KEYWORDS = [
            // ========== INCIDENT REPORT KEYWORDS ==========
            'incident' => [
                // Theft/Robbery
                'Theft / Pagnanakaw' => ['nanakawan', 'nadukutan', 'stolen', 'missing', 'nawala', 'wallet stolen', 'cellphone stolen', 'kinuha', 'pera nawala', 'ari-arian ninakaw'],
                'Robbery/Hold-up' => ['holdap', 'robbery', 'binunot ng baril', 'armed robbery', 'gunpoint', 'may baril', 'nanloloob', 'akyat-bahay'],
                'Burglary/Akyat-bahay' => ['akyat-bahay', 'pumasok sa bahay', 'break in', 'forced entry', 'ninakawan sa bahay', 'nag-burglary'],
                
                // Assault/Violence
                'Assault / Pag-assalto' => ['sinuntok', 'binugbog', 'sinapak', 'attack', 'assault', 'physical injury', 'hinampas', 'suntukan', 'rumble', 'rambol'],
                'Stabbing/Cutting Incident' => ['sinaksak', 'tinaga', 'stab', 'knife', 'saksak', 'patrolya', 'tinaga', 'punit', 'slash'],
                'Homicide/Murder' => ['pinatay', 'patay', 'murder', 'homicide', 'namatay', 'bangkay', 'dead body', 'sumaksak', 'brutal'],
                
                // Sexual Crimes
                'Sexual Assault/Rape' => ['ginahasa', 'rape', 'sexual assault', 'hinalay', 'panggagahasa', 'na-rape', 'sexual violence'],
                'Sexual Harassment' => ['hinipuan', 'sexual harassment', 'binastos', 'manyak', 'catcalling', 'bastos na lalaki', 'bastos na babae'],
                'Gender-based Incident' => ['vawc', 'violence against women', 'pang-aabuso', 'gender-based', 'abuse against women'],
                
                // Missing Persons
                'Missing Person / Nawawalang Tao' => ['missing person', 'nawawalang tao', 'hindi umuwi', 'taong nawawala', 'anak ko nawala', 'kapatid nawala'],
                'Missing Pet / Nawawalang Alaga' => ['nawawalang aso', 'missing dog', 'nawawalang pusa', 'lost pet', 'alaga nawala', 'asong gala', 'pusang gala'],
                
                // Drugs
                'Drug-related Activity' => ['shabu', 'droga', 'drugs', 'marijuana', 'nagsho-shabu', 'nagtitira', 'drug pusher', 'drug den'],
                'Illegal Gambling / Iligal na Sugal' => ['tupada', 'sugal', 'illegal gambling', 'tong-its', 'jueteng', 'saklaan', 'nagsusugal'],
                
                // Property Crimes
                'Property Damage / Pinsala sa Ari-arian' => ['vandalism', 'sinira ang', 'graffiti', 'sinulatan', 'binasag', 'nasira property'],
                'Trespassing / Pagpasok nang Walang Pahintulot' => ['trespassing', 'pumasok sa bakuran', 'sumampa sa bakod', 'intruder', 'nakapasok nang walang pahintulot'],
                
                // Emergencies
                'Fire Incident / Sunog' => ['sunog', 'fire', 'nasusunog', 'apoy', 'flames', 'smoke', 'may sunog', 'nasunog'],
                'Traffic Accident / Aksidente sa Trapiko' => ['aksidente', 'accident', 'banggaan', 'naaksidente', 'nasagasaan', 'hit and run', 'nabangga'],
                'Natural Disaster / Kalamidad' => ['baha', 'flood', 'lindol', 'earthquake', 'landslide', 'bagyo', 'typhoon', 'storm'],
                'Rescue Operations / Pagsagip' => ['nalulunod', 'drowning', 'trapped', 'na-trap', 'stuck', 'nasa panganib', 'need rescue'],
                
                // Cyber Crimes
                'Online Scam / Panloloko Online' => ['online scam', 'na-scam', 'phishing', 'fake seller', 'bogus buyer', 'scam sa internet', 'budol'],
                'Cyberbullying' => ['cyberbullying', 'online bullying', 'binubully online', 'paninira online', 'fake account', 'hate messages'],
            ],
            
            // ========== COMPLAINT REPORT KEYWORDS ==========
            'complaint' => [
                // Noise Complaints
                'Noise Complaint / Reklamo sa Ingay' => ['maingay', 'noise', 'ingay', 'videoke', 'karaoke', 'loud music', 'tumatahol', 'barking dog', 'maingay na kapitbahay', 'disturbance'],
                'Construction Noise / Ingay sa Konstruksyon' => ['construction noise', 'nagkakanyon', 'drilling', 'martilyo', 'maingay na konstruksyon', 'gawaing bahay'],
                
                // Sanitation/Health
                'Sanitation Issue / Isyu sa Kalinisan' => ['basura', 'garbage', 'trash', 'kalat', 'mabaho', 'foul odor', 'baradong kanal', 'tambak na basura', 'illegal dumping'],
                'Public Health Concern / Alalahanin sa Kalusugan' => ['public health', 'sakit', 'disease', 'outbreak', 'contamination', 'maruming tubig', 'contaminated water'],
                
                // Animal Issues
                'Animal Nuisance / Abala mula sa Hayop' => ['aso', 'stray dog', 'pusa', 'hayop sa kalye', 'animal nuisance', 'barking dogs', 'tumatahol', 'nangangagat'],
                'Rabies Concern / Alalahanin sa Rabies' => ['rabies', 'rabid dog', 'nagka-rabies', 'kagat ng aso', 'kagat ng hayop', 'rabies threat'],
                
                // Public Nuisance
                'Public Nuisance / Istorbong Pampubliko' => ['tambay', 'loitering', 'harang sa daan', 'obstruction', 'sagabal', 'istorbo', 'nagtatambay', 'nakaabala'],
                'Public Drinking / Pag-inom sa Pampubliko' => ['nag-iinom sa kalsada', 'public drinking', 'lasing sa kalye', 'nag-iinom sa labas', 'naglalasing'],
                
                // Ordinance Violations
                'Ordinance Violation / Paglabag sa Ordinansa' => ['illegal parking', 'curfew', 'liquor ban', 'ordinance violation', 'labag sa ordinansa', 'nag-iinom sa bawal na oras'],
                'Smoking Violation / Paglabag sa Paninigarilyo' => ['naninigarilyo sa bawal', 'smoking in public', 'smoking violation', 'usok ng sigarilyo'],
                
                // Consumer Complaints
                'Consumer Complaint / Reklamong Pangkonsyumer' => ['overpriced', 'scam seller', 'fake product', 'panloloko', 'mandaraya', 'overpricing', 'sobrang mahal', 'fake items'],
                'Illegal Vendor / Iligal na Tindera' => ['illegal vendor', 'walang permit na tindahan', 'nagtitinda sa kalsada', 'illegal stall', 'vendor sa kalsada'],
                
                // Business/Construction Issues
                'Illegal Construction / Iligal na Konstruksyon' => ['illegal construction', 'walang permit construction', 'unauthorized construction', 'nagtatayo nang walang permiso'],
                'Business Violation / Paglabag sa Negosyo' => ['illegal business', 'walang permit business', 'nagtitinda bawal', 'unauthorized business'],
                
                // Infrastructure Issues
                'Water Issue / Problema sa Tubig' => ['walang tubig', 'water shortage', 'tubig', 'problema sa tubig', 'maduming tubig', 'low water pressure'],
                'Electricity Issue / Problema sa Kuryente' => ['brownout', 'power outage', 'kuryente', 'problema sa kuryente', 'walang kuryente', 'intermittent power'],
                'Road Issue / Problema sa Kalsada' => ['sira kalsada', 'road damage', 'butas kalsada', 'pothole', 'maputik na daan', 'dangerous road', 'road hazard'],
                
                // Environmental Issues
                'Air Pollution / Polusyon sa Hangin' => ['air pollution', 'usok', 'smoke', 'pollution', 'mausok', 'mabaho ang hangin', 'factory smoke'],
                'Water Pollution / Polusyon sa Tubig' => ['water pollution', 'maruming ilog', 'contaminated water', 'dirty water', 'polluted water'],
            ],
            
            // ========== BLOTTER REPORT KEYWORDS ==========
            'blotter' => [
                // Family/Neighbor Disputes
                'Domestic Dispute / Alitan sa Tahanan' => ['away mag-asawa', 'family dispute', 'domestic problem', 'problema sa pamilya', 'marital problem', 'mag-asawang nag-aaway'],
                'Neighbor Dispute / Alitan ng Kapitbahay' => ['away sa kapitbahay', 'neighbor dispute', 'problema sa kapitbahay', 'kapitbahay', 'kalapit-bahay', 'issue sa kapitbahay'],
                'Family Conflict / Hidwaan sa Pamilya' => ['family conflict', 'away pamilya', 'sibling rivalry', 'away magkapatid', 'problema sa anak', 'anak na suwail'],
                
                // Property/Boundary Issues
                'Property Conflict / Hidwaan sa Ari-arian' => ['boundary dispute', 'lupa away', 'property conflict', 'hangganan', 'lot line', 'encroachment', 'nag-encroach', 'nag-overlap'],
                'Land Dispute / Hidwaan sa Lupa' => ['land dispute', 'lupa issue', 'property dispute', 'hidwaan sa lupa', 'lupa controversy'],
                
                // Debt/Financial Issues
                'Debt-related Issue / Isyu tungkol sa Utang' => ['utang', 'debt', 'ayaw magbayad', 'hindi nagbabayad', 'loan problem', 'pautang', 'nangutang'],
                'Financial Dispute / Hidwaan sa Pera' => ['financial dispute', 'pera away', 'money issue', 'problema sa pera', 'financial conflict'],
                
                // Verbal/Threat Issues
                'Verbal Altercation / Alitan sa Salita' => ['away', 'argument', 'alitan', 'sigawan', 'verbal fight', 'misunderstanding', 'tampuhan', 'away bati'],
                'Threats/Harassment / Pananakot' => ['pinagbabantaan', 'threat', 'banta', 'pananakot', 'harassment', 'nanakot', 'nagbanta', 'intimidation'],
                'Slander / Paninirang Puri' => ['paninirang puri', 'tsismis', 'siniraan', 'defamation', 'libel', 'slander', 'sinisiraan', 'paninirang pangalan'],
                
                // Physical Altercations
                'Physical Altercation / Alitan na Pisikal' => ['alitan na pisikal', 'physical fight', 'nag-suntukan', 'nag-bugbugan', 'physical altercation', 'nag-away pisikal'],
                
                // Contract/Agreement Issues
                'Breach of Contract / Paglabag sa Kontrata' => ['breach of contract', 'violation of agreement', 'nag-violate ng kontrata', 'hindi sinunod ang usapan'],
                'Contract Dispute / Hidwaan sa Kontrata' => ['contract dispute', 'problema sa kontrata', 'issue sa agreement', 'hidwaan sa kasunduan'],
                
                // Documentation Requests
                'Record/Blotter Request / Kahilingan ng Blotter' => ['blotter request', 'documentation', 'record only', 'pang-record', 'dokumento', 'for record lang', 'blotter for documentation'],
                'Barangay Clearance Request' => ['barangay clearance', 'kailangan ng clearance', 'clearance request', 'barangay certification'],
                'Certificate of Indigency' => ['indigency', 'certificate of indigency', 'kailangan ng indigency', 'certificado ng indigency'],
                
                // Special Concerns
                'Child-related Issue / Isyu tungkol sa Bata' => ['bata', 'child', 'minor', 'isyu tungkol sa bata', 'child abuse', 'bata problema', 'problema sa bata'],
                'Elderly Abuse / Pang-aabuso sa Matanda' => ['matanda', 'elderly', 'senior', 'pang-aabuso sa matanda', 'elder abuse', 'abuso sa matanda'],
                'Disability Concern / Alalahanin sa May Kapansanan' => ['disabled', 'may kapansanan', 'PWD', 'disability concern', 'problema sa disabled'],
                
                // Other Blotter Issues
                'Property Dispute / Hidwaan sa Ari-arian' => ['property dispute', 'hidwaan sa ari-arian', 'problema sa property', 'issue sa ari-arian'],
                'Tenant-Landlord Dispute / Hidwaan ng Upahan' => ['tenant landlord', 'away sa upa', 'problema sa upahan', 'hidwaan sa renta', 'away sa renta'],
            ]
        ];
        
        $suggestions = [];
        $total_keywords_found = 0;
        
        foreach ($types as $type) {
            $score = 0;
            $matched_keywords = [];
            $type_name = $type['type_name'];
            
            // ========== METHOD 1: Check Comprehensive Keywords ==========
            if (isset($COMPREHENSIVE_KEYWORDS[$category_filter][$type_name])) {
                $type_keywords = $COMPREHENSIVE_KEYWORDS[$category_filter][$type_name];
                
                foreach ($type_keywords as $keyword) {
                    $keyword_lower = strtolower(trim($keyword));
                    if (empty($keyword_lower)) continue;
                    
                    // Check for EXACT word match (with word boundaries) - HIGHEST SCORE
                    if (preg_match('/\b' . preg_quote($keyword_lower, '/') . '\b/i', $description)) {
                        $score += 20; // Higher score for exact matches
                        if (!in_array($keyword_lower, $matched_keywords)) {
                            $matched_keywords[] = $keyword_lower;
                            $total_keywords_found++;
                        }
                    }
                    // Check for partial match - MEDIUM SCORE
                    elseif (preg_match('/' . preg_quote($keyword_lower, '/') . '/i', $description)) {
                        $score += 10;
                        if (!in_array($keyword_lower, $matched_keywords)) {
                            $matched_keywords[] = $keyword_lower;
                            $total_keywords_found++;
                        }
                    }
                }
            }
            
            // ========== METHOD 2: Check database keywords column ==========
            if (!empty($type['keywords'])) {
                $db_keywords = explode(',', $type['keywords']);
                foreach ($db_keywords as $keyword) {
                    $keyword = strtolower(trim($keyword));
                    if (!empty($keyword)) {
                        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $description)) {
                            $score += 15;
                            if (!in_array($keyword, $matched_keywords)) {
                                $matched_keywords[] = $keyword;
                                $total_keywords_found++;
                            }
                        } elseif (preg_match('/' . preg_quote($keyword, '/') . '/i', $description)) {
                            $score += 8;
                            if (!in_array($keyword, $matched_keywords)) {
                                $matched_keywords[] = $keyword;
                                $total_keywords_found++;
                            }
                        }
                    }
                }
            }
            
            // ========== METHOD 3: Check type name itself ==========
            $type_name_lower = strtolower($type_name);
            $type_name_words = preg_split('/\s+|\/|\(|\)/', $type_name_lower);
            foreach ($type_name_words as $word) {
                $word = trim($word);
                if (strlen($word) > 3) {
                    if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $description)) {
                        $score += 12;
                        if (!in_array($word, $matched_keywords)) {
                            $matched_keywords[] = $word;
                        }
                    }
                }
            }
            
            // ========== METHOD 4: Check category-specific common keywords ==========
            $common_keywords = getCommonKeywordsByCategory($category_filter);
            foreach ($common_keywords as $common_keyword) {
                $common_keyword_lower = strtolower(trim($common_keyword));
                if (preg_match('/\b' . preg_quote($common_keyword_lower, '/') . '\b/i', $description)) {
                    if (strpos($type_name_lower, $common_keyword_lower) !== false) {
                        $score += 18;
                        if (!in_array($common_keyword_lower, $matched_keywords)) {
                            $matched_keywords[] = $common_keyword_lower;
                            $total_keywords_found++;
                        }
                    }
                }
            }
            
            // Only include if we found actual keyword matches AND score is above threshold
            if ($score > 0 && !empty($matched_keywords)) {
                // Calculate confidence percentage (0-100%)
                $confidence = min(($score / 100) * 100, 99);
                $confidence = round($confidence, 1);
                
                // Apply boost for multiple keyword matches
                $keyword_count = count($matched_keywords);
                if ($keyword_count > 1) {
                    $confidence += ($keyword_count * 5);
                    $confidence = min($confidence, 99);
                }
                
                // Lower threshold for complaint and blotter (easier matching)
                $min_confidence = 25;
                if ($category_filter === 'complaint') $min_confidence = 20;
                if ($category_filter === 'blotter') $min_confidence = 20;
                
                if ($confidence < $min_confidence) {
                    continue; // Skip low confidence matches
                }
                
                // Create jurisdiction icon/color
                $jurisdiction_color = ($type['jurisdiction'] === 'police') ? 'red' : 'blue';
                $jurisdiction_icon = ($type['jurisdiction'] === 'police') ? 'fa-shield-alt' : 'fa-home';
                
                $suggestions[] = [
                    'id' => $type['id'],
                    'type_name' => $type['type_name'],
                    'description' => $type['description'] ?? '',
                    'jurisdiction' => $type['jurisdiction'] ?? 'barangay',
                    'severity_level' => $type['severity_level'] ?? 'medium',
                    'score' => $score,
                    'confidence' => $confidence,
                    'matched_keywords' => array_slice(array_unique($matched_keywords), 0, 5),
                    'keyword_count' => $keyword_count,
                    'jurisdiction_color' => $jurisdiction_color,
                    'jurisdiction_icon' => $jurisdiction_icon,
                    'is_exact_match' => $keyword_count >= 2 && $confidence > 70
                ];
            }
        }
        
        // Sort by confidence (highest first), then by score
        usort($suggestions, function($a, $b) {
            if ($b['confidence'] == $a['confidence']) {
                return $b['score'] <=> $a['score'];
            }
            return $b['confidence'] <=> $a['confidence'];
        });
        
        // Take only TOP 3-4 suggestions
        $suggestions = array_slice($suggestions, 0, 4);
        
        // Calculate average confidence for analysis
        $avg_confidence = 0;
        if (!empty($suggestions)) {
            $total_conf = 0;
            foreach ($suggestions as $s) {
                $total_conf += $s['confidence'];
            }
            $avg_confidence = round($total_conf / count($suggestions), 1);
        }
        
        // Check for emergency keywords (mainly for incidents)
        $is_emergency = false;
        if ($category_filter === 'incident') {
            $is_emergency = checkForEmergency($description);
            if ($is_emergency && !empty($suggestions)) {
                // Boost confidence for emergency reports
                foreach ($suggestions as &$suggestion) {
                    $suggestion['confidence'] = min($suggestion['confidence'] + 15, 99);
                    $suggestion['is_emergency'] = true;
                }
            }
        }
        
        // Prepare response
        $response = [
            'success' => true,
            'suggestions' => $suggestions,
            'analysis' => [
                'total_matches' => count($suggestions),
                'total_keywords_found' => $total_keywords_found,
                'avg_confidence' => $avg_confidence,
                'description_length' => strlen($description),
                'category_analyzed' => $category_filter,
                'is_emergency' => $is_emergency,
                'detected_language' => detectLanguage($description)
            ]
        ];
        
        // If no suggestions found for complaint/blotter, provide generic suggestions
        if (empty($suggestions) && ($category_filter === 'complaint' || $category_filter === 'blotter')) {
            $response['fallback_suggestions'] = getFallbackSuggestions($category_filter, $description);
        }
        
        echo json_encode($response);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch(PDOException $e) {
    error_log("AI Suggestions Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
} catch(Exception $e) {
    error_log("AI Suggestions General Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'AI analysis error. Please try again.'
    ]);
}

// Helper function to get common keywords by category
// Helper function to get common keywords by category
function getCommonKeywordsByCategory($category) {
    $keywords_by_category = [
        /* =========================
           INCIDENT REPORT
           Crimes, emergencies, accidents
        ========================== */
        'incident' => [
            // General / Emergency
            'incident', 'crime', 'criminal', 'emergency', 'urgent', 'help', 'saklolo',
            'danger', 'delikado', 'life threatening', 'critical', 'responde',
            '911', 'pulis', 'police', 'tanod',

            // Theft / Loss
            'stolen', 'ninakaw', 'nakaw', 'holdap', 'robbery',
            'missing', 'nawala', 'lost', 'wala yung bag', 'wala yung phone',
            'snatch', 'snatching', 'carnap', 'carnapping',

            // Violence / Physical Harm
            'attack', 'inatake', 'assault', 'sinaktan', 'binugbog',
            'violence', 'karahasan', 'saksak', 'baril', 'shooting',
            'injured', 'nasugatan', 'dugo', 'patay', 'dead',
            'murder', 'killing', 'homicide',

            // Sexual / Serious Crimes
            'rape', 'ginahasa', 'sexual assault', 'molest',
            'kidnap', 'kidnapping', 'dinukot', 'abduction',

            // Accidents / Disasters
            'accident', 'aksidente', 'banggaan', 'car accident',
            'fire', 'sunog', 'nasusunog', 'smoke',
            'flood', 'baha', 'landslide', 'bagyo',
            'disaster', 'sakuna', 'earthquake', 'lindol'
        ],

        /* =========================
           COMPLAINT
           Issues, nuisances, violations
        ========================== */
        'complaint' => [
            // General Complaint
            'complaint', 'reklamo', 'issue', 'problem', 'problema',
            'concern', 'hinaing', 'report', 'ireport',

            // Noise / Disturbance
            'noise', 'noisy', 'ingay', 'maingay', 'loud',
            'videoke', 'karaoke', 'party', 'inuman',
            'disturbance', 'istorbo', 'gulo',

            // Sanitation / Environment
            'garbage', 'basura', 'trash', 'kalat',
            'dirty', 'marumi', 'mabaho', 'sanitation',
            'sewer', 'imburnal', 'baradong kanal',

            // Animals
            'animal', 'hayop', 'aso', 'pusa',
            'stray', 'gala', 'askal', 'pusakal',
            'kagat', 'bite', 'rabies',

            // Traffic / Parking / Violations
            'parking', 'illegal parking', 'double parking',
            'violation', 'labag', 'bawal', 'obstruction',
            'traffic', 'trapik', 'road block',

            // Utilities
            'water', 'tubig', 'walang tubig',
            'electricity', 'kuryente', 'brownout',
            'power outage', 'meralco',

            // Roads / Construction
            'road', 'kalsada', 'daan', 'street',
            'construction', 'hukay', 'sira ang daan',
            'bukas na kanal'
        ],

        /* =========================
           BLOTTER
           Disputes, conflicts, documentation
        ========================== */
        'blotter' => [
            // General Blotter
            'blotter', 'ipablotter', 'record', 'irecord',
            'documentation', 'dokumento',

            // Disputes / Arguments
            'dispute', 'alitan', 'away', 'nag-away',
            'argument', 'pagtatalo', 'conflict',
            'misunderstanding', 'di pagkakaunawaan',

            // Neighbors / Family
            'neighbor', 'neighbour', 'kapitbahay',
            'family', 'pamilya', 'kamag-anak',
            'asawa', 'mag-asawa', 'partner',

            // Property / Land
            'property', 'ari-arian', 'lupa', 'land',
            'boundary', 'hangganan', 'bakod',
            'encroachment', 'trespassing',

            // Money / Debt
            'debt', 'utang', 'loan', 'pautang',
            'money', 'pera', 'hindi nagbayad',
            'paniningil',

            // Threats / Harassment
            'threat', 'banta', 'pananakot',
            'harassment', 'pangha-harass',
            'intimidation', 'pang-iintimidate',

            // Verbal / Non-Physical
            'verbal', 'salita', 'mura', 'panlalait',
            'insult', 'defamation', 'paninira',

            // Certificates / Records
            'clearance', 'barangay clearance',
            'certificate', 'certification',
            'rekord', 'kasulatan', 'affidavit'
        ]
    ];

    return $keywords_by_category[$category] ?? [];
}

// Get fallback suggestions when no specific matches found
function getFallbackSuggestions($category, $description) {
    $description_lower = strtolower($description);
    $fallbacks = [];
    
    if ($category === 'complaint') {
        // Common complaint patterns
        if (preg_match('/(maingay|ingay|noise|loud|videoke|karaoke)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Noise Complaint / Reklamo sa Ingay', 'confidence' => 65];
        }
        if (preg_match('/(basura|garbage|trash|kalat|dirty|marumi)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Sanitation Issue / Isyu sa Kalinisan', 'confidence' => 70];
        }
        if (preg_match('/(aso|pusa|animal|hayop|stray|gala)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Animal Nuisance / Abala mula sa Hayop', 'confidence' => 60];
        }
        if (preg_match('/(tubig|water|kuryente|electricity|power)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Water Issue / Problema sa Tubig', 'confidence' => 55];
        }
        
        // If no specific patterns, suggest general complaint
        if (empty($fallbacks)) {
            $fallbacks[] = ['type_name' => 'General Complaint / Pangkalahatang Reklamo', 'confidence' => 40];
        }
    }
    
    if ($category === 'blotter') {
        // Common blotter patterns
        if (preg_match('/(kapitbahay|neighbor|kalapit)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Neighbor Dispute / Alitan ng Kapitbahay', 'confidence' => 70];
        }
        if (preg_match('/(asawa|pamilya|family|mag-asawa)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Domestic Dispute / Alitan sa Tahanan', 'confidence' => 65];
        }
        if (preg_match('/(utang|debt|loan|pera|money)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Debt-related Issue / Isyu tungkol sa Utang', 'confidence' => 60];
        }
        if (preg_match('/(away|argument|alitan|dispute|conflict)/i', $description_lower)) {
            $fallbacks[] = ['type_name' => 'Verbal Altercation / Alitan sa Salita', 'confidence' => 55];
        }
        
        // If no specific patterns, suggest general blotter
        if (empty($fallbacks)) {
            $fallbacks[] = ['type_name' => 'Record/Blotter Request / Kahilingan ng Blotter', 'confidence' => 45];
        }
    }
    
    return $fallbacks;
}

function checkForEmergency($text) {
    $emergency_keywords = [
        'emergency', 'urgent', 'help', 'saklolo', 'tulong', '911', 'emergency',
        'patay', 'dead', 'dying', 'namamatay', 'biktima', 'victim',
        'sunog', 'fire', 'burning', 'flames', 'smoke', 'nasusunog',
        'aksidente', 'accident', 'hospital', 'ambulance', 'nasugatan', 'injured',
        'holdap', 'robbery', 'baril', 'gun', 'shoot', 'shot', 'pumuputok',
        'ginahasa', 'rape', 'assault', 'sexual assault', 'panggagahasa',
        'missing', 'nawawala', 'lost', 'kidnap', 'abduct', 'dinukot',
        'suicide', 'magpapakamatay', 'jumping', 'overdose', 'self-harm',
        'heart attack', 'atake sa puso', 'stroke', 'natumba', 'unconscious',
        // Add your new emergency keywords
        'life threatening', 'delikado', 'critical', 'responde',
        'dugo', 'blood', 'nasugatan', 'wounded',
        'baha', 'flood', 'landslide', 'bagyo', 'typhoon',
        'lindol', 'earthquake', 'quake',
        'gas leak', 'chemical spill', 'nakuryente', 'electric shock',
        'poison', 'lason', 'food poisoning', 'intoxicated'
    ];
    
    foreach ($emergency_keywords as $keyword) {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $text)) {
            return true;
        }
    }
    
    return false;
}

function detectLanguage($text) {
    $tagalog_words = ['ang', 'ng', 'sa', 'na', 'ako', 'ko', 'mo', 'siya', 'namin', 'ninyo', 'sila',
                     'ito', 'iyan', 'iyon', 'dito', 'doon', 'kung', 'pero', 'at', 'o', 'tayo',
                     'kayo', 'sila', 'akin', 'amin', 'inyo', 'kanila', 'bakit', 'paano', 'kailan',
                     'saan', 'sino', 'ano', 'alin', 'gaano', 'ilan', 'magkano'];
    
    $english_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with',
                     'by', 'from', 'of', 'about', 'into', 'through', 'during', 'before', 'after',
                     'above', 'below', 'between', 'under', 'over', 'again', 'further', 'then', 'once'];
    
    $tagalog_count = 0;
    $english_count = 0;
    
    $words = str_word_count($text, 1);
    
    foreach ($words as $word) {
        $word_lower = strtolower($word);
        if (in_array($word_lower, $tagalog_words)) {
            $tagalog_count++;
        }
        if (in_array($word_lower, $english_words)) {
            $english_count++;
        }
    }
    
    if ($tagalog_count > $english_count) {
        return 'tagalog';
    } elseif ($english_count > $tagalog_count) {
        return 'english';
    } else {
        return 'mixed';
    }
}
?>