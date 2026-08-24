# Shift8 DB Cache Internal Plan

## Purpose

Build a precision database caching plugin that helps administrators identify expensive query patterns before caching anything.

## Non-goals

- No broad carpet-bomb caching
- No multi-dispatch reporting framework
- No heavy bootstrap-time logic
- No automatic caching of every query

## Current phase

Phase 1 is complete enough to demonstrate the analysis model:

- plugin bootstrap and Shift8 menu integration
- capture session start/stop flow
- normalized SQL fingerprint aggregation
- basic report rendering
- minimal PHPUnit coverage for settings and collector logic

## Key implementation rules

- Keep request-time overhead low when capture is not active.
- Keep all admin actions nonce-protected and capability-gated.
- Store only the minimum data needed for analysis and later rule authoring.
- Favor ordinary plugin classes over large framework layers.
- Keep future active caching logic in a tiny interception layer and move real logic into normal classes.

## Open engineering questions for the next phase

- Should active caching use a minimal `db.php` drop-in or a similarly small `wpdb` interception layer?
- Should persistence remain in `wp_options` or move to a dedicated table once rule storage becomes more complex?
- Which invalidation hooks are justified for the first Redis-backed ruleset?

## Verification expectations

- Activation and deactivation should be clean.
- Capture off should have near-zero impact.
- Capture on should only persist bounded aggregate data.
- Settings validation should reject invalid values and clamp unsafe ranges.
- Report data should be derived, not manually curated.