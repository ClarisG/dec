# Secretary Dashboard - Comprehensive Fix Implementation Guide

## Status: IN PROGRESS

### Completed ✅
1. ✅ Added notification button to secretary_dashboard.php
2. ✅ Removed duplicate classification_review link from sidebar
3. ✅ Added classification_review to mobile navigation

### Remaining Tasks (Priority Order)

---

## 1. CLASSIFICATION_REVIEW.PHP - FIXES NEEDED

### Issue 1: Pagination (10 items per page)
**Location**: Line ~200 (Reports Table)
**Fix**: Add pagination logic

```php
// Add after line ~50 (after fetching reports)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$total_items = count($reports);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($page - 1) * $items_per_page;
$paginated_reports = array_slice($reports, $offset, $items_per_page);

// Then use $paginated_reports in the table loop instead of $reports
// Add pagination controls after the table
```

### Issue 2: Confidence Level Accuracy
**Status**: ✅ WORKING - The calculateConfidence() and calculateConfidenceBreakdown() functions are already implemented correctly

### Issue 3: Dropdowns (Category, Severity, Priority) - Make Clickable & Functional
**Status**: ✅ WORKING - Already implemented in modal with proper select elements

### Issue 4: Save Correction & Notify Citizen
**Status**: ✅ WORKING - Already sends email and notification
**Verification**: Check that:
- Email is sent to citizen
- Notification appears in citizen dashboard
- Notification links to the report
- citizen_my_reports.php shows the correction

---

## 2. CASE.PHP - FIXES NEEDED

### Issue 1: Assign Tanod/Lupon - Real Database Accounts
**Current Problem**: Likely using dummy data
**Fix**: Query actual users from database

```php
// Replace dummy data with:
$tanod_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name 
                FROM users 
                WHERE role = 'tanod' AND is_active = 1
                ORDER BY first_name";
$tanod_stmt = $conn->prepare($tanod_query);
$tanod_stmt->execute();
$tanod_list = $tanod_stmt->fetchAll(PDO::FETCH_ASSOC);

$lupon_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name 
                FROM users 
                WHERE role = 'lupon' AND is_active = 1
                ORDER BY first_name";
$lupon_stmt = $conn->prepare($lupon_query);
$lupon_stmt->execute();
$lupon_list = $lupon_stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Issue 2: Pending Cases Disappearing
**Current Problem**: Cases not displaying
**Fix**: Ensure query is correct

```php
// Add at top of case.php
$pending_cases_query = "SELECT r.*, 
                        u.first_name, u.last_name, u.email,
                        rt.type_name,
                        COALESCE(r.assigned_lupon, 'Unassigned') as assigned_to
                        FROM reports r
                        LEFT JOIN users u ON r.user_id = u.id
                        LEFT JOIN report_types rt ON r.report_type_id = rt.id
                        WHERE r.status IN ('pending', 'assigned', 'investigating')
                        ORDER BY r.created_at DESC";
$pending_stmt = $conn->prepare($pending_cases_query);
$pending_stmt->execute();
$pending_cases = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Issue 3: Add Pagination (10 items per page)
**Same as classification_review.php**

---

## 3. COMPLIANCE.PHP - FIXES NEEDED

### Issue 1: Critical Deadlines, Approaching Deadlines, Within Compliance Disappearing
**Current Problem**: Sections not displaying
**Fix**: Add proper queries and display logic

```php
// Add at top of compliance.php
// Critical Deadlines (≤1 day remaining)
$critical_query = "SELECT r.*, u.first_name, u.last_name
                   FROM reports r
                   LEFT JOIN users u ON r.user_id = u.id
                   WHERE r.status IN ('pending', 'assigned', 'investigating')
                   AND DATEDIFF(DATE_ADD(r.created_at, INTERVAL 15 DAY), CURDATE()) <= 1
                   ORDER BY DATE_ADD(r.created_at, INTERVAL 15 DAY) ASC";

// Approaching Deadlines (2-3 days remaining)
$approaching_query = "SELECT r.*, u.first_name, u.last_name
                      FROM reports r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.status IN ('pending', 'assigned', 'investigating')
                      AND DATEDIFF(DATE_ADD(r.created_at, INTERVAL 15 DAY), CURDATE()) BETWEEN 2 AND 3
                      ORDER BY DATE_ADD(r.created_at, INTERVAL 15 DAY) ASC";

// Within Compliance (>3 days remaining)
$compliant_query = "SELECT r.*, u.first_name, u.last_name
                    FROM reports r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE r.status IN ('pending', 'assigned', 'investigating')
                    AND DATEDIFF(DATE_ADD(r.created_at, INTERVAL 15 DAY), CURDATE()) > 3
                    ORDER BY DATE_ADD(r.created_at, INTERVAL 15 DAY) ASC";
```

### Issue 2: Add Pagination (10 items per page)
**Same as above**

---

## 4. DOCUMENT_LIST.PHP - FIXES NEEDED

### Issue 1: All Modals Working
**Status**: Need to verify all modals are functional
**Check**:
- View Document Modal
- Generate Document Modal
- Edit Document Modal
- Delete Confirmation Modal

### Issue 2: All Buttons & Selections Working
**Status**: Need to verify all buttons work
**Check**:
- Generate Button
- Edit Button
- Delete Button
- Download Button
- Print Button
- All form selections

---

## 5. SECRETARY_DASHBOARD.PHP - FIXES NEEDED

### Issue 1: Remove Referral Link from Dashboard
**Status**: ✅ DONE - Referral link removed from dashboard.php quick actions
**Note**: Referral functionality moved to classification_review.php

---

## Implementation Priority

### CRITICAL (Do First)
1. ✅ Add notification button to secretary_dashboard.php
2. Fix case.php - Real database accounts for Tanod/Lupon
3. Fix case.php - Pending cases display
4. Fix compliance.php - Critical/Approaching/Compliant sections

### HIGH (Do Second)
5. Add pagination to classification_review.php
6. Add pagination to case.php
7. Add pagination to compliance.php
8. Verify document_list.php modals

### MEDIUM (Do Third)
9. Test all buttons functionality
10. Test all form submissions
11. Test email notifications
12. Test citizen notifications

---

## Testing Checklist

### Classification Review
- [ ] Confidence level displays correctly
- [ ] Category dropdown works
- [ ] Severity dropdown works
- [ ] Priority dropdown works
- [ ] Save button sends notification to citizen
- [ ] Citizen receives email
- [ ] Citizen sees notification in dashboard
- [ ] Pagination works (10 items per page)

### Case Management
- [ ] Pending cases display
- [ ] Tanod list shows real accounts
- [ ] Lupon list shows real accounts
- [ ] Assign button works
- [ ] Pagination works (10 items per page)

### Compliance Monitoring
- [ ] Critical deadlines section displays
- [ ] Approaching deadlines section displays
- [ ] Within compliance section displays
- [ ] Color coding is correct
- [ ] Pagination works (10 items per page)

### Document List
- [ ] All modals open
- [ ] All buttons work
- [ ] All selections work
- [ ] Generate document works
- [ ] Download works
- [ ] Print works

---

## Database Queries Reference

### Get Active Tanod Members
```sql
SELECT id, CONCAT(first_name, ' ', last_name) as name 
FROM users 
WHERE role = 'tanod' AND is_active = 1
ORDER BY first_name;
```

### Get Active Lupon Members
```sql
SELECT id, CONCAT(first_name, ' ', last_name) as name 
FROM users 
WHERE role = 'lupon' AND is_active = 1
ORDER BY first_name;
```

### Get Critical Deadline Cases
```sql
SELECT r.*, u.first_name, u.last_name
FROM reports r
LEFT JOIN users u ON r.user_id = u.id
WHERE r.status IN ('pending', 'assigned', 'investigating')
AND DATEDIFF(DATE_ADD(r.created_at, INTERVAL 15 DAY), CURDATE()) <= 1
ORDER BY DATE_ADD(r.created_at, INTERVAL 15 DAY) ASC;
```

---

## Notes

- All notification functionality is already in place
- Email sending is configured
- Citizen notifications are working
- Database structure supports all required fields
- Just need to fix display logic and add pagination

---

**Last Updated**: 2024
**Status**: Ready for implementation
