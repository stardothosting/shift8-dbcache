# Shift8 DB Cache Internal Plan

## Purpose

Build a precision database caching plugin that helps administrators identify expensive query patterns before caching anything.

## Non-goals

- No broad carpet-bomb caching
- No multi-dispatch reporting framework
- No heavy bootstrap-time logic
- No automatic caching of every query

## Current phase

Phase 2 now exists as a minimal functional slice:

- plugin bootstrap and Shift8 menu integration
- capture session start/stop flow
- normalized SQL fingerprint aggregation
- rule storage for approved fingerprints
- guarded Redis-backed execution through a minimal `db.php` drop-in
- admin configuration for Redis host, port, database, prefix, and default TTL
- minimal PHPUnit coverage for settings, collector logic, rule storage, runtime matching, and drop-in synchronization

## Key implementation rules

- Keep request-time overhead low when capture is not active.
- Keep all admin actions nonce-protected and capability-gated.
- Store only the minimum data needed for analysis and later rule authoring.
- Favor ordinary plugin classes over large framework layers.
- Keep future active caching logic in a tiny interception layer and move real logic into normal classes.
- Never store Redis passwords in standard options.
- Never override an existing non-Shift8 `db.php` drop-in.
- Limit active caching to explicit approved `SELECT` fingerprints.

## Open engineering questions for the next phase

- Should rule persistence remain in `wp_options` or move to a dedicated table once rule storage becomes more complex?
- Which invalidation hooks are justified for the first Redis-backed ruleset?
- Should hit/miss/stale counters stay in options or move to transient or Redis-side metrics?

## Verification expectations

- Activation and deactivation should be clean.
- Capture off should have near-zero impact.
- Capture on should only persist bounded aggregate data.
- Settings validation should reject invalid values and clamp unsafe ranges.
- Report data should be derived, not manually curated.
- Active caching should safely degrade to uncached reads when Redis is unavailable.
- The plugin should refuse to override another `db.php` drop-in.
- Only approved fingerprints should be eligible for caching.