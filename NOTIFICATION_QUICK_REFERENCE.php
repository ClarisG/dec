<?php
/**
 * QUICK REFERENCE: How to Add Notification Button to Any Dashboard
 * 
 * This file shows the exact code needed to add the unified notification button
 * to Lupon and Super Admin dashboards (or any other dashboard).
 */
?>

<!-- STEP 1: In the header section where notifications should appear, add this code: -->

<!-- Notification Button Component -->
<?php include '../components/notification_button.php'; ?>

<!-- STEP 2: Make sure these variables are available in your dashboard: -->
<?php
// These should already be set in your dashboard:
// $user_id = $_SESSION['user_id'];
// $conn = getDbConnection();
?>

<!-- STEP 3: The component will automatically: -->
<!-- - Display unread notification count -->
<!-- - Show dropdown with latest notifications -->
<!-- - Handle mark as read functionality -->
<!-- - Auto-refresh every 60 seconds -->

<!-- EXAMPLE: For Lupon Dashboard -->
<!-- 
Location in lupon/lupon_dashboard.php:
Find the header section with user dropdown, and add the notification component before it:

<div class="flex items-center space-x-4">
    <!-- Notifications Component -->
    <?php include '../components/notification_button.php'; ?>
    
    <!-- User Dropdown -->
    <div class="relative">
        ...existing user dropdown code...
    </div>
</div>
-->

<!-- EXAMPLE: For Super Admin Dashboard -->
<!-- 
Location in super_admin/super_admin_dashboard.php:
Find the header section with user dropdown, and add the notification component before it:

<div class="flex items-center space-x-4">
    <!-- Notifications Component -->
    <?php include '../components/notification_button.php'; ?>
    
    <!-- User Dropdown -->
    <div class="relative">
        ...existing user dropdown code...
    </div>
</div>
-->

<!-- STYLING: The component uses Tailwind CSS classes and is compatible with: -->
<!-- - All existing dashboard styles -->
<!-- - Mobile responsive design -->
<!-- - Dark/light theme compatibility -->
<!-- - Font Awesome icons -->

<!-- CUSTOMIZATION: If you need to customize the component: -->
<!-- 1. Copy components/notification_button.php -->
<!-- 2. Modify the styling classes as needed -->
<!-- 3. Adjust the limit parameter in get_user_notifications.php -->
<!-- 4. Change auto-refresh interval (currently 60000ms = 60 seconds) -->

<!-- TROUBLESHOOTING: -->
<!-- 
If notifications don't appear:
1. Check that $user_id is set correctly
2. Verify $conn is a valid database connection
3. Ensure notifications table exists in database
4. Check browser console for JavaScript errors
5. Verify AJAX endpoints are accessible

If badge count doesn't update:
1. Check that notifications are being created in the database
2. Verify is_read field is being updated correctly
3. Check auto-refresh interval is working
4. Clear browser cache and reload
-->
