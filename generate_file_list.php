<?php
function listFiles($dir, &$results = []) {
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
        } else if ($value != "." && $value != "..") {
            listFiles($path, $results);
            $results[] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $path) . DIRECTORY_SEPARATOR;
        }
    }
    return $results;
}

$files = listFiles(__DIR__);
sort($files);
file_put_contents('directory_structure.txt', implode(PHP_EOL, $files));
echo "File list generated in directory_structure.txt";
?>