# File Write

Writes text to a file and reads it back.

## Change

- `$filePath`: a unique temporary file (`tempnam`), deleted at the end. Avoid fixed names in the shared temp folder: another process could create or read that file first.
- `$text`: change the text to write.

## Calls

```php
$filePath = tempnam(sys_get_temp_dir(), 'eramba-automation-');
file_put_contents($filePath, $text);
echo file_get_contents($filePath);
unlink($filePath);
```

## Output

The script prints the file path and the stored text.
