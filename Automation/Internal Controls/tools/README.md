# Test harness for eramba automations

Runs an automation locally, without eramba and without touching the real target system.

| File | Purpose |
|---|---|
| `eramba-stubs.php` | Local versions of the eramba functions (`editObjectMacro`, `uploadAttachmentMacro`, `addCommentMacro`, `addObjectMacro`). Calls are written to `$ERAMBA_LOG`. `ERAMBA_FAIL=edit`, `upload` or `comment` simulates an eramba error. |
| `prepare.php` | Prepares `run.php` the way eramba does (macros, includes after `declare(strict_types=1);`, variable overrides). |
| `examples/aws-backup-jobs-restore-tests/` | Complete scenario suite for the reference automation: fake AWS endpoint + test runner. |

Quick run of any automation (from this folder):

```bash
php prepare.php "../_TEMPLATE/run.php" /tmp/t.php - --secret example_api_token=test
ERAMBA_LOG=/tmp/eramba.log php /tmp/t.php; echo "exit=$?"; cat /tmp/eramba.log
```

Scenario suite of the AWS reference automation (needs `aws/aws-sdk-php` installed locally):

```bash
cd examples/aws-backup-jobs-restore-tests
php run-tests.php "../../../AWS/aws-backup-jobs-restore-tests/run.php" /path/to/vendor/autoload.php
```

A local run does not replace testing in eramba and against the real system: see [`docs/writing-automations.md`](../docs/writing-automations.md) §6.
