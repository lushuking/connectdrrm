<?php
$dir = __DIR__;
$search = 'confirmActionModal';

function search_dir($directory, $query) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'])) {
            $content = file_get_contents($file->getPathname());
            if (strpos($content, $query) !== false) {
                echo "Found '$query' in " . $file->getPathname() . "\n";
                // Print lines
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    if (strpos($line, $query) !== false) {
                        echo "  Line " . ($i + 1) . ": " . trim($line) . "\n";
                    }
                }
            }
        }
    }
}

search_dir($dir, $search);
search_dir($dir, 'confirmActionBtn');
?>
