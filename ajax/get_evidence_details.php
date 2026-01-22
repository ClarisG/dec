<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tanod') {
    die('Unauthorized');
}

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

$conn = getDbConnection();

try {
    $stmt = $conn->prepare("
        SELECT eh.*, 
               u_tanod.first_name as tanod_first, u_tanod.last_name as tanod_last,
               u_recipient.first_name as recipient_first, u_recipient.last_name as recipient_last,
               u_recipient.role as recipient_role,
               u_recipient.barangay_position as recipient_position
        FROM evidence_handovers eh
        JOIN users u_tanod ON eh.tanod_id = u_tanod.id
        JOIN users u_recipient ON eh.handover_to = u_recipient.id
        WHERE eh.id = ? AND eh.tanod_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $evidence = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evidence) die('Evidence not found');
    
    // Output HTML
    ?>
    <div class="space-y-6">
        <!-- Evidence Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-gray-500 mb-1">Evidence ID</div>
                <div class="font-bold text-lg">EVID-<?php echo str_pad($evidence['id'], 5, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-gray-500 mb-1">Type</div>
                <div class="font-medium"><?php echo htmlspecialchars(ucfirst($evidence['item_type'])); ?></div>
            </div>
        </div>
        
        <!-- Description -->
        <div class="p-4 bg-blue-50 rounded-lg">
            <div class="text-sm text-blue-700 font-medium mb-2">Evidence Description</div>
            <div class="text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($evidence['item_description']); ?></div>
        </div>
        
        <!-- Chain of Custody -->
        <?php if (!empty($evidence['chain_of_custody'])): ?>
        <div class="p-4 bg-yellow-50 rounded-lg">
            <div class="text-sm text-yellow-700 font-medium mb-2">Chain of Custody Notes</div>
            <div class="text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($evidence['chain_of_custody']); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- People Involved -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-green-50 rounded-lg">
                <div class="text-sm text-green-700 font-medium mb-2">Handed Over By (Tanod)</div>
                <div class="font-medium"><?php echo htmlspecialchars($evidence['tanod_first'] . ' ' . $evidence['tanod_last']); ?></div>
                <div class="text-sm text-gray-600">Tanod Member</div>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg">
                <div class="text-sm text-purple-700 font-medium mb-2">Recipient</div>
                <div class="font-medium"><?php echo htmlspecialchars($evidence['recipient_first'] . ' ' . $evidence['recipient_last']); ?></div>
                <div class="text-sm text-gray-600">
                    <?php echo ucfirst($evidence['recipient_role']); ?>
                    <?php if (!empty($evidence['recipient_position'])): ?>
                        - <?php echo htmlspecialchars($evidence['recipient_position']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="text-sm text-gray-700 font-medium mb-3">Timeline</div>
            <div class="space-y-3">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                    <div class="flex-1">
                        <div class="font-medium">Handover Submitted</div>
                        <div class="text-sm text-gray-500"><?php echo date('F j, Y, g:i A', strtotime($evidence['handover_date'])); ?></div>
                    </div>
                </div>
                
                <?php if ($evidence['recipient_acknowledged']): ?>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                    <div class="flex-1">
                        <div class="font-medium">Recipient Acknowledged Receipt</div>
                        <div class="text-sm text-gray-500"><?php echo date('F j, Y, g:i A', strtotime($evidence['acknowledged_at'])); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                    <div class="flex-1">
                        <div class="font-medium">Awaiting Acknowledgement</div>
                        <div class="text-sm text-gray-500">Pending recipient confirmation</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status -->
        <div class="p-4 rounded-lg <?php echo $evidence['recipient_acknowledged'] ? 'bg-green-100 border border-green-200' : 'bg-yellow-100 border border-yellow-200'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $evidence['recipient_acknowledged'] ? 'fa-check-circle text-green-600' : 'fa-clock text-yellow-600'; ?> text-xl mr-3"></i>
                <div>
                    <div class="font-bold <?php echo $evidence['recipient_acknowledged'] ? 'text-green-800' : 'text-yellow-800'; ?>">
                        <?php echo $evidence['recipient_acknowledged'] ? 'RECEIPT ACKNOWLEDGED' : 'PENDING ACKNOWLEDGEMENT'; ?>
                    </div>
                    <div class="text-sm <?php echo $evidence['recipient_acknowledged'] ? 'text-green-700' : 'text-yellow-700'; ?>">
                        <?php echo $evidence['recipient_acknowledged'] 
                            ? 'Recipient has confirmed physical receipt of this evidence'
                            : 'Waiting for recipient to confirm physical receipt'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    
} catch (PDOException $e) {
    echo '<div class="text-red-500 p-4">Error loading evidence details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>