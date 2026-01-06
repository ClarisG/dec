<?php
// create_directories.php
$directories = [
    'uploads',
    'uploads/profile_pictures',
    'uploads/reports',
    'uploads/ids',
    'uploads/guardian_ids',
    'uploads/announcements'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        echo "Created directory: $dir<br>";
    } else {
        echo "Directory exists: $dir<br>";
    }
}
?>