<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

// Unique file readable only by this run (a fixed name in the shared temp folder could be pre-created by others).
$filePath = tempnam(sys_get_temp_dir(), 'eramba-automation-');
if ($filePath === false) {
    throw new RuntimeException('Could not create a temporary file.');
}
$text = 'some data';

file_put_contents($filePath, $text);

echo $filePath . "\n";
echo file_get_contents($filePath);
unlink($filePath);
