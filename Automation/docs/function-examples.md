# Function Examples

PHP 8.4 examples for Eramba automation functions on Online Assessments.

The Add/Edit/Comment/Attachment examples are Online Assessment examples. Their item IDs, payload fields, and macros are not generic examples for every Eramba section.

## Examples

| Example | Function |
| --- | --- |
| [Edit Item](../eramba/Example%20-%20Edit%20Item/) | `editObjectMacro()` |
| [Add Item](../eramba/Example%20-%20Add%20Item/) | `addObjectMacro()` |
| [Add Comment](../eramba/Example%20-%20Add%20Comment/) | `addCommentMacro()` |
| [Add Attachment](../eramba/Example%20-%20Add%20Attachment/) | `uploadAttachmentMacro()` |
| [File Write](../eramba/Example%20-%20File%20Write/) | `file_put_contents()` + `file_get_contents()` |

## Notes

- Replace `'%ONLINE_ASSESSMENT_ID%'` with the macro selected in Eramba.
- Numeric values such as `1` are IDs from your Eramba instance.
- User and group values use the `User-1` / `Group-13` format.
- `recurrence_period_type` is an integer enum: `1` day, `2` week, `3` month, `4` year.
- Keep Add/Edit payloads complete unless you have tested a smaller payload.
- Use `php -l run.php` for syntax checks. Eramba macros are not available in local PHP.
