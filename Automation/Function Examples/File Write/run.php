<?php

declare(strict_types=1);

// Unique file readable only by this run (a fixed name in the shared temp folder could be pre-created by others).
$filePath = tempnam(sys_get_temp_dir(), 'eramba-automation-');
$text = 'some data';

file_put_contents($filePath, $text);

echo $filePath . "\n";
echo file_get_contents($filePath);
unlink($filePath);
