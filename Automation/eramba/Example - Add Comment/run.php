<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

$itemId = '%ONLINE_ASSESSMENT_ID%';
$message = 'text of comment';

echo addCommentMacro($itemId, $message);
