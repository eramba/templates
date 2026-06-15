# Add Attachment

Uploads one attachment.

## Change

- `$itemId`: replace `'%ONLINE_ASSESSMENT_ID%'` with the Eramba macro.
- `$base64Attachment`: replace the sample text file with your file content.
- `example-attachment.txt`: replace with the filename you want to show.

The sample payload is `some data` encoded as base64:

```text
data:text/plain;base64,c29tZSBkYXRh
```

## Call

```php
echo uploadAttachmentMacro($itemId, $base64Attachment, 'example-attachment.txt');
```

## Notes

This example only tests the upload function. If you want to link the uploaded file to a comment, use the value returned by this function in a separate comment example.
