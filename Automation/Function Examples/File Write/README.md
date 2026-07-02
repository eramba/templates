# File Write

Writes text to a file and reads it back.

## Change

- `$filePath`: change the target path if needed.
- `$text`: change the text to write.

## Calls

```php
file_put_contents($filePath, $text);
echo file_get_contents($filePath);
```

## Output

The script prints the file path and the stored text.
