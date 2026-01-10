<?php
// captain/modules/hearing.php
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Mediation & Hearing Scheduler</h3>
                <p class="text-gray-600 text-sm">Manage calendar for conciliation hearings and send automated reminders</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-4">
                <button onclick="openSchedulerModal()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i> Schedule Hearing
                </button>
                <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-print mr-2"></i> Print Schedule
                </button>
            </div>
        </div>
        
        <!-- Calendar Navigation -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
                <button class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h4 class="text-lg font-bold text-gray-800"><?php echo date('F Y'); ?></h4>
                <button class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Today
                </button>
            </div>
            <div class="flex space-x-2">
                <button class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg font-medium">Month</button>
                <button class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Week</button>
                <button class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">Day</button>
            </div>
        </div>
        
        <!-- Calendar -->
        <div class="bg-white rounded-lg border">
            <div class="grid grid-cols-7 border-b">
                <?php 
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($days as $day): 
                ?>
                    <div class="py-3 text-center text-sm font-medium text-gray-500">
                        <?php echo $day; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="grid grid-cols-7">
                <?php
                $firstDay = date('w', strtotime(date('Y-m-01')));
                $daysInMonth = date('t');
                $currentDay = 1;
                
                for ($i = 0; $i < 42; $i++): 
                    if ($i >= $firstDay && $currentDay <= $daysInMonth):
                        $date = date('Y-m-' . str_pad($currentDay, 2, '0', STR_PAD_LEFT));
                        $isToday = $date == date('Y-m-d');
                        $hasHearing = false; // In real system, check if date has hearings
                ?>
                    <div class="min-h-24 border p-2 <?php echo $isToday ? 'bg-blue-50' : ''; ?>">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium <?php echo $isToday ? 'text-blue-600' : 'text-gray-700'; ?>">
                                <?php echo $currentDay; ?>
                            </span>
                            <?php if ($hasHearing): ?>
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasHearing): ?>
                            <div class="mt-2 space-y-1">
                                <div class="text-xs p-1 bg-green-100 text-green-800 rounded">
                                    10:00 AM - Case #123
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php
                        $currentDay++;
                    else:
                ?>
                    <div class="min-h-24 border p-2 bg-gray-50"></div>
                <?php
                    endif;
                endfor; 
                ?>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cases Ready for Hearing -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h4 class="text-lg font-bold text-gray-800">Cases Ready for Hearing</h4>
                    <p class="text-gray-600 text-sm">Assign hearing dates for pending conciliation cases</p>
                </div>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg text-sm">
                    <?php echo count($hearing_cases); ?> cases
                </span>
            </div>
            
            <div class="space-y-4">
                <?php if (!empty($hearing_cases)): ?>
                    <?php foreach ($hearing_cases as $case): ?>
                        <div class="p-4 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($case['report_number']); ?></span>
                                        <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Mediation</span>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($case['title']); ?></p>
                                </div>
                                <button onclick="openSchedulerModal(<?php echo $case['id']; ?>)" 
                                        class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                                    Schedule
                                </button>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-user mr-2"></i>
                                <span><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></span>
                                <span class="mx-2">•</span>
                                <i class="fas fa-clock mr-2"></i>
                                <span><?php echo $case['days_pending']; ?> days pending</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                        <p class="text-gray-600">All cases have scheduled hearings</p>
                        <p class="text-sm text-gray-500 mt-1">Great job keeping up with the schedule!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scheduled Hearings -->
        <div class="glass-card rounded-xl p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h4 class="text-lg font-bold text-gray-800">Scheduled Hearings</h4>
                    <p class="text-gray-600 text-sm">Upcoming conciliation hearings</p>
                </div>
                <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View Calendar
                </a>
            </div>
            
            <div class="space-y-4">
                <?php if (!empty($scheduled_hearings)): ?>
                    <?php foreach ($scheduled_hearings as $hearing): ?>
                        <div class="p-4 rounded-lg border border-gray-200">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($hearing['report_number']); ?></span>
                                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">Scheduled</span>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($hearing['title']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-800"><?php echo date('M d', strtotime($hearing['hearing_date'])); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo date('g:i A', strtotime($hearing['hearing_time'])); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="text-gray-500">
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($hearing['complainant_fname'] . ' ' . $hearing['complainant_lname']); ?>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="sendReminder(<?php echo $hearing['id']; ?>)" 
                                            class="px-3 py-1 bg-green-100 text-green-800 rounded hover:bg-green-200 text-sm">
                                        <i class="fas fa-bell mr-1"></i> Remind
                                    </button>
                                    <button onclick="editHearing(<?php echo $hearing['id']; ?>)" 
                                            class="px-3 py-1 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 text-sm">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-alt text-gray-400 text-3xl mb-3"></i>
                        <p class="text-gray-600">No hearings scheduled</p>
                        <p class="text-sm text-gray-500 mt-1">Schedule your first hearing using the button above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reminder Settings -->
    <div class="glass-card rounded-xl p-6">
        <div class="flex items-center space-x-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                <i class="fas fa-bell text-green-600"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-800">Automated Reminder Settings</h4>
                <p class="text-sm text-gray-600">Configure automated SMS & Email reminders</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-4 border rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-medium text-gray-700">24-Hour Reminder</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <p class="text-sm text-gray-600">Send reminders 24 hours before hearing</p>
            </div>
            
            <div class="p-4 border rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-medium text-gray-700">3-Day Reminder</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <p class="text-sm text-gray-600">Send reminders 3 days before hearing</p>
            </div>
            
            <div class="p-4 border rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-medium text-gray-700">SMS Notifications</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <p class="text-sm text-gray-600">Enable SMS notifications for participants</p>
            </div>
        </div>
        
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <div class="flex items-center space-x-3">
                <i class="fas fa-info-circle text-blue-600"></i>
                <p class="text-sm text-blue-800">
                    Reminders are automatically sent to all involved parties (complainant, respondent, Lupon members) based on their contact information in the case file.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Scheduler Modal -->
<div id="schedulerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="sticky top-0 bg-white border-b px-6 py-4">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Schedule Hearing</h3>
                <button onclick="closeSchedulerModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <form id="schedulerForm" method="POST" action="">
                <input type="hidden" name="case_id" id="scheduleCaseId">
                <input type="hidden" name="schedule_hearing" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hearing Date</label>
                        <input type="date" name="hearing_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hearing Time</label>
                        <input type="time" name="hearing_time" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="09:00" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                    <input type="text" name="location" 
                           placeholder="Barangay Hall, Conference Room A"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Participants</label>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-gray-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Complainant</p>
                                    <p class="text-xs text-gray-500">Required attendance</p>
                                </div>
                            </div>
                            <input type="checkbox" name="participants[]" value="complainant" checked class="w-5 h-5 text-blue-600">
                        </div>
                        
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-gray-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Respondent</p>
                                    <p class="text-xs text-gray-500">Required attendance</p>
                                </div>
                            </div>
                            <input type="checkbox" name="participants[]" value="respondent" checked class="w-5 h-5 text-blue-600">
                        </div>
                        
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-users text-gray-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Lupon Members</p>
                                    <p class="text-xs text-gray-500">Minimum 3 members required</p>
                                </div>
                            </div>
                            <input type="checkbox" name="participants[]" value="lupon" checked class="w-5 h-5 text-blue-600">
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes / Instructions</label>
                    <textarea name="notes" rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Any special instructions or requirements..."></textarea>
                </div>
                
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-bell text-blue-600 mt-1"></i>
                        <div>
                            <p class="text-sm font-medium text-blue-800">Automatic Reminders</p>
                            <p class="text-sm text-blue-700">
                                All selected participants will receive SMS and email reminders 24 hours and 3 days before the hearing.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeSchedulerModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-calendar-plus mr-2"></i> Schedule Hearing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSchedulerModal(caseId = '') {
    document.getElementById('scheduleCaseId').value = caseId;
    document.getElementById('schedulerModal').classList.remove('hidden');
    document.getElementById('schedulerModal').classList.add('flex');
    
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.querySelector('input[name="hearing_date"]').min = tomorrow.toISOString().split('T')[0];
}

function closeSchedulerModal() {
    document.getElementById('schedulerModal').classList.add('hidden');
    document.getElementById('schedulerModal').classList.remove('flex');
    document.getElementById('schedulerForm').reset();
}

function sendReminder(hearingId) {
    if (confirm('Send reminder to all participants?')) {
        // AJAX call to send reminders
        fetch(`../handlers/send_reminder.php?id=${hearingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reminders sent successfully!');
                } else {
                    alert('Failed to send reminders: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error sending reminders: ' + error.message);
            });
    }
}

function editHearing(hearingId) {
    // Load hearing data and open editor
    alert('Edit hearing functionality would load here for ID: ' + hearingId);
}
</script>