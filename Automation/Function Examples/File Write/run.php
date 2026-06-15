<?php

declare(strict_types=1);

$filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eramba-automation-example.txt';
$text = 'some data';

file_put_contents($filePath, $text);

echo $filePath . "\n";
echo file_get_contents($filePath);
