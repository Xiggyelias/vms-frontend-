<?php
$dir = __DIR__;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;
foreach ($files as $file) {
    if ($file->getExtension() === 'php' && $file->getFilename() !== 'refactor_db.php' && $file->getFilename() !== 'init.php' && $file->getFilename() !== 'db_test.php') {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, 'function getDBConnection()') !== false) {
            
            // Try to brutally remove the exact block
            $regex = '/function\s+getDBConnection\s*\(\)\s*\{\s*\$conn\s*=\s*new\s*mysqli\([^;]+;\s*if\s*\(\$conn->connect_error\)\s*\{\s*die\([^;]+;\s*\}\s*return\s*\$conn;\s*\}/s';
            
            $new_content = preg_replace($regex, '', $content);
            $new_content = preg_replace('/(?<!function )getDBConnection\(\)/', 'getLegacyDatabaseConnection()', $new_content);
            
            if ($content !== $new_content) {
                file_put_contents($file->getPathname(), $new_content);
                echo 'Refactored: ' . $file->getFilename() . "\n";
                $count++;
            }
        }
    }
}
echo 'Total files refactored: ' . $count . "\n";
?>
