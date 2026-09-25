<?php
// Local stand-ins for the functions eramba provides to automations.
// Every call is appended to the file in $ERAMBA_LOG so tests can assert on it.
// ERAMBA_FAIL=edit|upload|comment makes that call return an eramba-style error string.

function __erambaLog(string $line): void
{
    $log = getenv('ERAMBA_LOG') ?: sys_get_temp_dir() . '/eramba-stub.log';
    file_put_contents($log, $line . "\n", FILE_APPEND);
}

function editObjectMacro(array|string $fields, string|int|null $routeOrId = null): string
{
    __erambaLog('EDIT ' . $routeOrId . ' ' . (is_array($fields) ? json_encode($fields) : $fields));
    return getenv('ERAMBA_FAIL') === 'edit' ? 'ERROR: Validation error' : '{"success":true}';
}

function uploadAttachmentMacro(string|int|null $routeOrId, string $attachmentInput, ?string $filename = null): string
{
    __erambaLog('UPLOAD ' . $routeOrId . ' ' . $filename . ' ' . strlen($attachmentInput));
    return getenv('ERAMBA_FAIL') === 'upload' ? 'ERROR: validation.mimes' : 'attachment_' . md5((string) $filename) . '.txt';
}

function addCommentMacro(string|int|null $routeOrId, string $message, array $attachments = []): string
{
    __erambaLog('COMMENT ' . $routeOrId . ' ' . $message . ' ' . json_encode($attachments));
    return getenv('ERAMBA_FAIL') === 'comment' ? 'ERROR: Comment failed' : '{"success":true}';
}

function addObjectMacro(array|string $fields, ?string $route = null): string
{
    __erambaLog('ADD ' . (is_array($fields) ? json_encode($fields) : $fields));
    return '{"success":true}';
}
