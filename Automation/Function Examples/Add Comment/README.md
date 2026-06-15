# Add Comment

Adds a comment to an Online Assessment item.

## Change

- `$itemId`: replace `'%ONLINE_ASSESSMENT_ID%'` with the Eramba macro.
- `$message`: replace the sample text.

## Call

```php
echo addCommentMacro($itemId, $message);
```

## Notes

Run this in Eramba. Local PHP does not define `addCommentMacro()`.
