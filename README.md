# Shift8 DB Cache

Shift8 DB Cache is an analysis-first WordPress plugin for identifying expensive database queries and turning only the approved ones into precise cache rules.

The plugin is intentionally conservative:

- capture first, cache later
- admin-started analysis windows
- narrowly targeted query fingerprints
- Redis-only backend direction for active caching
- stale-once refresh behavior for opted-in rules

## What it does

The first phase focuses on visibility. An administrator starts a capture session, the plugin aggregates expensive `SELECT` queries after the request completes, and the admin screen shows normalized query fingerprints, timings, component/source context, and table hints.

The next phase will add active caching for only the rules the administrator approves.

## Installation

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Open `Shift8 > DB Cache`.

## Usage

1. Click `Start capture`.
2. Browse the site normally for a representative period.
3. Stop capture and review the analysis table.
4. Turn the highest-value fingerprints into cache rules once active caching is enabled.

## Security and design principles

- Require `manage_options` for all admin actions.
- Verify nonces before changing capture state or settings.
- Sanitize all settings input before persistence.
- Prefer the smallest possible cache scope.
- Keep the active interception layer minimal when it is introduced.

## Development notes

This repository currently includes the first analysis phase and the test harness that covers the core normalization and aggregation paths. The plugin intentionally avoids broad auto-caching or a large collector framework.

## Testing

The plugin ships with PHPUnit-oriented unit tests for settings sanitization and query aggregation behavior. If your environment has `phpunit` installed, run:

```bash
phpunit --configuration phpunit.xml
```

## Roadmap

- Phase 1: analysis-only capture and rule authoring
- Phase 2: Redis-backed rule execution with stale-once behavior
- Phase 3: operational visibility for hit rate, misses, refreshes, and safe fallback behavior