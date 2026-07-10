# Task #10.1 — Publication Date in Delivery Messages

## Summary

Delivery cards now show two separate timestamps:

| Field | Source | Missing value |
|-------|--------|---------------|
| 🕒 Publication Date | `mentions.published_at` (normalized across all providers) | `Unknown` |
| ⚙️ Processed At | Delivery context processing time | N/A (always set) |

Format: `d.m.Y H:i T` in `config('app.timezone')` (e.g. `10.07.2026 14:35 UTC`).

## Changed Files

- `app/DTO/DeliveryCardDTO.php` — added `publishedAt`, renamed `timestamp` → `processedAt`, added `fromPayload()`
- `app/Services/Delivery/DeliveryCardBuilder.php` — template updated with both fields
- `app/Services/Delivery/DigestEngine.php` — uses `DeliveryCardDTO::fromPayload()` for digest items
- `tests/Concerns/CreatesDeliverableMention.php` — sets `published_at` on test mentions
- `tests/Unit/Services/Delivery/DeliveryCardBuilderTest.php` — new tests
- `tests/Unit/Services/Delivery/DeliveryEngineTest.php` — assertion updates
- `tests/Feature/Delivery/DeliveryPipelineTest.php` — assertion updates
- `tests/Feature/Delivery/DigestGenerationCommandTest.php` — payload field update

## Verification

```
Tests: 239 passed (1008 assertions)
```

| Scenario | Test | Result |
|----------|------|--------|
| Publication date shown | `DeliveryCardBuilderTest::it_displays_publication_date_and_processed_at` | PASS |
| Missing publication date | `DeliveryCardBuilderTest::it_displays_unknown_when_publication_date_is_missing` | PASS |
| Timezone formatting | `DeliveryCardBuilderTest::it_formats_timestamps_in_configured_timezone` | PASS |
| Both timestamps in digest payload | `DeliveryCardBuilderTest::it_restores_both_timestamps_from_card_payload` | PASS |
| Approve → delivery pipeline | `DeliveryPipelineTest::it_delivers_approved_mention_to_delivery_bot` | PASS |

No routing or business logic changes.
