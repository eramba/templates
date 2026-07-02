# Add Item

Creates a new Online Assessment item.

## Change

- `$data['VendorAssessmentQuestionnaires']`: replace `1` with an existing questionnaire ID.
- `$data['report_id']`: keep `null` while `questions_download` is `0`; use an integer report ID when `questions_download` is `1`.
- Association arrays such as `BusinessUnits`, `ThirdParties`, and `Assets`: replace `1` with IDs from your instance.
- Dates must stay in `YYYY-MM-DD`.
- `User-1` and `Group-13`: replace with users or groups that exist in your instance.
- `recurrence_period_type`: `1` day, `2` week, `3` month, `4` year.

## Call

```php
echo addObjectMacro($data);
```

## Notes

The payload is complete on purpose. Do not remove fields unless you have tested the reduced payload in Eramba.

If Eramba returns `Call to a member function getMaxScore() on null`, check `VendorAssessmentQuestionnaires`: the questionnaire ID does not point to a valid questionnaire in that instance.
