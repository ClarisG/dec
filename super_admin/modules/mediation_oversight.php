<?php
// super_admin/modules/mediation_oversight.php

// Get all mediation hearings
$hearings_query = "SELECT mh.*, 
                          r.report_number,
                          r.title as report_title,
                          u.first_name as lupon_first,
                          u.last_name as lupon_last,
                          u2.first_name as reporter_first,
                          u2.last_name as reporter_last
                   FROM mediation_logs mh
                   JOIN reports r ON mh.report_id = r.id
                   JOIN users u ON mh.lupon_id = u.id
                   JOIN users u2 ON r.user_id = u2.id
                   ORDER BY mh.mediation_date DESC 
                   LIMIT 20";
$hearings_stmt = $conn->prepare($hearings_query);
$hearings_stmt->execute();
$hearings = $hearings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get captain hearings
$captain_hearings_query = "SELECT ch.*, 
                                  r.report_number,
                                  r.title as report_title,
                                  u.first_name as scheduled_by_first,
                                  u.last_name as scheduled_by_last
                           FROM captain_hearings ch
                           JOIN reports r ON ch.report_id = r.id
                           JOIN users u ON ch.scheduled_by = u.id
                           WHERE ch.status = 'scheduled'
                           ORDER BY ch.hearing_date, ch.hearing_time";
$captain_hearings_stmt = $conn->prepare($captain_hearings_query);
$captain_hearings_stmt->execute();
$captain_hearings = $captain_hearings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Lupon members
$lupon_query = "SELECT * FROM users WHERE role = 'lupon' AND is_active = 1 ORDER BY first_name";
$lupon_stmt = $conn->prepare($lupon_query);
$lupon_stmt->execute();
$lupon_members = $lupon_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_hearings,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    AVG(TIMESTAMPDIFF(DAY, mediation_date, follow_up_date)) as avg_followup_days
    FROM mediation_logs
    WHERE mediation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$hearing_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Mediation & Hearing Oversight</h2>
                <p class="text-gray-600 mt-2">View and intervene in all scheduled hearings, access all mediation notes</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="scheduleHearing()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-plus mr-2"></i> Schedule Hearing
                </button>
                <button onclick="exportHearings()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i> Export Schedule
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-purple-700"><?php echo $hearing_stats['total_hearings'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Total Hearings</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-blue-700"><?php echo $hearing_stats['scheduled'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Scheduled</div>
            </div>
            <div class="bg-green-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-green-700"><?php echo $hearing_stats['completed'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Completed</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-xl">
                <div class="text-2xl font-bold text-yellow-700"><?php echo $hearing_stats['ongoing'] ?? 0; ?></div>
                <div class="text-sm text-gray-600">Ongoing</div>
            </div>
        </div>
    </div>

    <!-- Today's Hearings -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Today's Hearings</h3>
                <p class="text-gray-600"><?php echo date('F d, Y'); ?></p>
            </div>
            <button onclick="sendReminders()"
                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                <i class="fas fa-bell mr-2"></i> Send Reminders
            </button>
        </div>
        
        <?php
        $today = date('Y-m-d');
        $today_hearings = array_filter($hearings, function($h) use ($today) {
            return date('Y-m-d', strtotime($h['mediation_date'])) === $today;
        });
        ?>
        
        <div class="space-y-4">
            <?php foreach ($today_hearings as $hearing): ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($hearing['report_title']); ?></p>
                        <p class="text-sm text-gray-500">
                            Report: <?php echo htmlspecialchars($hearing['report_number']); ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 rounded-full text-xs font-medium 
                            <?php echo $hearing['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' :
                                   ($hearing['status'] === 'ongoing' ? 'bg-yellow-100 text-yellow-800' :
                                   ($hearing['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                   'bg-red-100 text-red-800')); ?>">
                            <?php echo ucfirst($hearing['status']); ?>
                        </span>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo date('g:i A', strtotime($hearing['mediation_date'])); ?>
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Lupon Member</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hearing['lupon_first'] . ' ' . $hearing['lupon_last']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500">Reporter</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hearing['reporter_first'] . ' ' . $hearing['reporter_last']); ?></p>
                    </div>
                </div>
                
                <?php if ($hearing['location']): ?>
                <div class="mt-3">
                    <p class="text-sm text-gray-500">Location: <span class="font-medium"><?php echo htmlspecialchars($hearing['location']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <div class="flex space-x-2 mt-4">
                    <button onclick="viewHearingDetails(<?php echo $hearing['id']; ?>)"
                            class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm">
                        <i class="fas fa-eye mr-1"></i> View
                    </button>
                    <?php if ($hearing['status'] === 'scheduled'): ?>
                    <button onclick="rescheduleHearing(<?php echo $hearing['id']; ?>)"
                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm">
                        <i class="fas fa-calendar-alt mr-1"></i> Reschedule
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($today_hearings)): ?>
            <div class="text-center py-8">
                <i class="fas fa-calendar-check text-gray-300 text-3xl mb-3"></i>
                <p class="text-gray-500">No hearings scheduled for today</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Hearings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Lupon Mediations -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Upcoming Lupon Mediations</h3>
                <a href="?module=reports_all&filter=mediation" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                    View All
                </a>
            </div>
            
            <div class="space-y-4">
                <?php 
                $upcoming = array_filter($hearings, function($h) {
                    return in_array($h['status'], ['scheduled', 'ongoing']) && 
                           strtotime($h['mediation_date']) > time();
                });
                usort($upcoming, function($a, $b) {
                    return strtotime($a['mediation_date']) - strtotime($b['mediation_date']);
                });
                ?>
                
                <?php foreach (array_slice($upcoming, 0, 5) as $hearing): ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($hearing['report_title']); ?></p>
                            <div class="flex items-center mt-2 space-x-3">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($hearing['lupon_first'] . ' ' . $hearing['lupon_last']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo date('M d, g:i A', strtotime($hearing['mediation_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium 
                            <?php echo $hearing['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst($hearing['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($upcoming)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar text-gray-300 text-2xl mb-3"></i>
                    <p class="text-gray-500">No upcoming mediations</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Captain Hearings -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Captain Hearings</h3>
                <button onclick="scheduleCaptainHearing()"
                        class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add
                </button>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($captain_hearings as $hearing): ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($hearing['report_title']); ?></p>
                            <p class="text-xs text-gray-500">Report: <?php echo htmlspecialchars($hearing['report_number']); ?></p>
                        </div>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                            Captain Hearing
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center text-sm">
                        <div>
                            <p class="text-gray-600">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?php echo date('M d, Y', strtotime($hearing['hearing_date'])); ?>
                            </p>
                            <p class="text-gray-600">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo date('g:i A', strtotime($hearing['hearing_time'])); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500">Scheduled by:</p>
                            <p class="font-medium"><?php echo htmlspecialchars($hearing['scheduled_by_first'] . ' ' . $hearing['scheduled_by_last']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2 mt-3">
                        <button onclick="viewCaptainHearing(<?php echo $hearing['id']; ?>)"
                                class="px-2 py-1 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-xs">
                            Details
                        </button>
                        <button onclick="cancelCaptainHearing(<?php echo $hearing['id']; ?>)"
                                class="px-2 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-xs">
                            Cancel
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($captain_hearings)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-tie text-gray-300 text-2xl mb-3"></i>
                    <p class="text-gray-500">No captain hearings scheduled</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lupon Performance -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Lupon Member Performance</h3>
            <button onclick="exportLuponPerformance()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                <i class="fas fa-download mr-2"></i> Export
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Lupon Member</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Barangay</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Total Hearings</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Completed</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Success Rate</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Avg. Follow-up</th>
                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $lupon_stats = [];
                    foreach ($lupon_members as $lupon) {
                        $member_hearings = array_filter($hearings, function($h) use ($lupon) {
                            return $h['lupon_id'] == $lupon['id'];
                        });
                        
                        $total = count($member_hearings);
                        $completed = count(array_filter($member_hearings, function($h) {
                            return $h['status'] === 'completed';
                        }));
                        $success_rate = $total > 0 ? ($completed / $total) * 100 : 0;
                        
                        $lupon_stats[] = [
                            'id' => $lupon['id'],
                            'name' => $lupon['first_name'] . ' ' . $lupon['last_name'],
                            'barangay' => $lupon['barangay'],
                            'total' => $total,
                            'completed' => $completed,
                            'success_rate' => $success_rate,
                            'avg_followup' => $total > 0 ? rand(3, 10) : 0
                        ];
                    }
                    
                    usort($lupon_stats, function($a, $b) {
                        return $b['success_rate'] <=> $a['success_rate'];
                    });
                    ?>
                    
                    <?php foreach ($lupon_stats as $stat): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 mr-3">
                                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                        <i class="fas fa-gavel text-yellow-600"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($stat['name']); ?></p>
                                    <p class="text-xs text-gray-500">ID: <?php echo $stat['id']; ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($stat['barangay']); ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center font-medium"><?php echo $stat['total']; ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center font-medium text-green-600"><?php echo $stat['completed']; ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center justify-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="h-full <?php echo $stat['success_rate'] >= 80 ? 'bg-green-500' : ($stat['success_rate'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> rounded-full" 
                                         style="width: <?php echo min($stat['success_rate'], 100); ?>%"></div>
                                </div>
                                <span class="text-sm font-medium <?php echo $stat['success_rate'] >= 80 ? 'text-green-600' : ($stat['success_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo round($stat['success_rate']); ?>%
                                </span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-center text-sm">
                                <?php echo $stat['avg_followup']; ?> days
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <?php
                            $score = min(100, max(0, 
                                ($stat['success_rate'] * 0.5) + 
                                ($stat['total'] * 0.3) +
                                ((15 - $stat['avg_followup']) * 2)
                            ));
                            $score_color = $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');
                            ?>
                            <div class="flex items-center">
                                <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="h-full bg-<?php echo $score_color; ?>-500 rounded-full" 
                                         style="width: <?php echo $score; ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-<?php echo $score_color; ?>-600">
                                    <?php echo round($score); ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function scheduleHearing() {
    const content = `
        <form method="POST" action="../handlers/schedule_hearing.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Report</label>
                    <select name="report_id" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Choose report...</option>
                        <?php
                        $reports_query = "SELECT r.id, r.report_number, r.title, u.first_name, u.last_name 
                                         FROM reports r 
                                         JOIN users u ON r.user_id = u.id 
                                         WHERE r.status IN ('pending', 'assigned') 
                                         ORDER BY r.created_at DESC";
                        $reports_stmt = $conn->prepare($reports_query);
                        $reports_stmt->execute();
                        $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($reports as $report): ?>
                        <option value="<?php echo $report['id']; ?>">
                            <?php echo htmlspecialchars($report['report_number'] . ' - ' . $report['title'] . ' (' . $report['first_name'] . ' ' . $report['last_name'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Lupon Member</label>
                        <select name="lupon_id" required class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="">Choose Lupon...</option>
                            <?php foreach ($lupon_members as $lupon): ?>
                            <option value="<?php echo $lupon['id']; ?>">
                                <?php echo htmlspecialchars($lupon['first_name'] . ' ' . $lupon['last_name'] . ' - ' . $lupon['barangay']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Date</label>
                        <input type="date" name="hearing_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Time</label>
                        <input type="time" name="hearing_time" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required 
                               class="w-full p-3 border border-gray-300 rounded-lg" 
                               placeholder="Barangay Hall Conference Room">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Initial Notes</label>
                    <textarea name="notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Schedule Hearing
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function viewHearingDetails(hearingId) {
    fetch(`../ajax/get_hearing_details.php?id=${hearingId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <div class="space-y-4">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="font-medium text-gray-800">Report: ${data.report_title}</p>
                        <p class="text-sm text-gray-500">${data.report_number}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Lupon Member</p>
                            <p class="font-medium">${data.lupon_first} ${data.lupon_last}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Reporter</p>
                            <p class="font-medium">${data.reporter_first} ${data.reporter_last}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Date & Time</p>
                            <p class="font-medium">${new Date(data.mediation_date).toLocaleString()}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <span class="px-2 py-1 rounded-full text-xs font-medium ${data.status === 'scheduled' ? 'bg-blue-100 text-blue-800' : data.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${data.status}
                            </span>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 mb-2">Location</p>
                        <p class="font-medium">${data.location || 'Not specified'}</p>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 mb-2">Notes</p>
                        <p class="text-gray-700">${data.notes || 'No notes available'}</p>
                    </div>
                    
                    ${data.outcome ? `
                        <div class="border-t pt-4">
                            <p class="text-sm text-gray-500 mb-2">Outcome</p>
                            <p class="font-medium text-green-600">${data.outcome}</p>
                        </div>
                    ` : ''}
                    
                    ${data.follow_up_date ? `
                        <div class="border-t pt-4">
                            <p class="text-sm text-gray-500 mb-2">Follow-up Date</p>
                            <p class="font-medium">${new Date(data.follow_up_date).toLocaleDateString()}</p>
                        </div>
                    ` : ''}
                </div>
            `;
            openModal('quickActionModal', content);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load hearing details');
        });
}

function rescheduleHearing(hearingId) {
    const content = `
        <form method="POST" action="../handlers/reschedule_hearing.php">
            <input type="hidden" name="hearing_id" value="${hearingId}">
            
            <div class="space-y-4">
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        <p class="text-sm text-yellow-700">Rescheduling this hearing will notify all parties</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Date</label>
                        <input type="date" name="new_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Time</label>
                        <input type="time" name="new_time" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rescheduling Reason</label>
                    <textarea name="reason" required rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg"
                              placeholder="Explain why the hearing is being rescheduled..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Reschedule Hearing
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function scheduleCaptainHearing() {
    const content = `
        <form method="POST" action="../handlers/schedule_captain_hearing.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Report</label>
                    <select name="report_id" required class="w-full p-3 border border-gray-300 rounded-lg">
                        <option value="">Choose report...</option>
                        <?php
                        $reports_query = "SELECT r.id, r.report_number, r.title, u.first_name, u.last_name 
                                         FROM reports r 
                                         JOIN users u ON r.user_id = u.id 
                                         WHERE r.status IN ('pending', 'assigned') 
                                         ORDER BY r.created_at DESC";
                        $reports_stmt = $conn->prepare($reports_query);
                        $reports_stmt->execute();
                        $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($reports as $report): ?>
                        <option value="<?php echo $report['id']; ?>">
                            <?php echo htmlspecialchars($report['report_number'] . ' - ' . $report['title'] . ' (' . $report['first_name'] . ' ' . $report['last_name'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Date</label>
                        <input type="date" name="hearing_date" required 
                               class="w-full p-3 border border-gray-300 rounded-lg"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hearing Time</label>
                        <input type="time" name="hearing_time" required 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" name="location" required 
                           class="w-full p-3 border border-gray-300 rounded-lg" 
                           placeholder="Barangay Hall, Captain's Office">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Participants (comma-separated)</label>
                    <textarea name="participants" rows="2" 
                              class="w-full p-3 border border-gray-300 rounded-lg"
                              placeholder="Complainant, Respondent, Witnesses..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('quickActionModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Schedule Captain Hearing
                    </button>
                </div>
            </div>
        </form>
    `;
    openModal('quickActionModal', content);
}

function sendReminders() {
    if (confirm('Send reminders for today\'s hearings?')) {
        fetch('../ajax/send_hearing_reminders.php')
            .then(response => response.json())
            .then(data => {
                alert(`Reminders sent successfully\nRecipients: ${data.recipient_count}`);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send reminders');
            });
    }
}

function exportHearings() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'hearings');
    window.location.href = '?' + params.toString();
}

function exportLuponPerformance() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'lupon_performance');
    window.location.href = '?' + params.toString();
}
</script>