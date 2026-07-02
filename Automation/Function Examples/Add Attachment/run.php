<?php

declare(strict_types=1);

$itemId = '%ONLINE_ASSESSMENT_ID%';
$base64Attachment = 'data:text/plain;base64,c29tZSBkYXRh';

echo uploadAttachmentMacro($itemId, $base64Attachment, 'example-attachment.txt');
