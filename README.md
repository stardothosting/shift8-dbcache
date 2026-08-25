# Shift8 DB Cache

Shift8 DB Cache is an analysis-first WordPress plugin for identifying expensive database queries and turning only the approved ones into precise cache rules.

The plugin is intentionally conservative:

- capture first, cache later
- admin-started analysis windows
- narrowly targeted query fingerprints
- Redis-backed active caching for approved fingerprints
- stale-once refresh behavior for opted-in rules

## What it does

The current implementation supports two operating modes:

- analysis mode for capturing and ranking expensive `SELECT` fingerprints
- active caching mode for specific approved fingerprints backed by Redis

The plugin only caches read queries that match an approved normalized SQL fingerprint. Non-`SELECT` queries and `SELECT ... FOR UPDATE` are excluded from caching.

## Installation

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Open `Shift8 > DB Cache`.

## Usage

1. Click `Start capture`.
2. Browse the site normally for a representative period.
3. Stop capture and review the analysis table.
4. Create cache rules from the highest-value fingerprints.
5. Configure Redis and enable active caching.

## Redis configuration

Active caching uses Redis through the `phpredis` extension.

Configure the destination from `Shift8 > DB Cache`:

- `Redis host`: accepts local or remote Redis hosts
- `Redis port`
- `Redis database`
- `Redis key prefix`
- `Default rule TTL`

For secrets, do not store a Redis password in the database. Define it in `wp-config.php` or an environment variable instead:

```php
define( 'SHIFT8_DBCACHE_REDIS_PASSWORD', 'your-redis-password' );
```

Optional runtime overrides are also supported:

```php
define( 'SHIFT8_DBCACHE_REDIS_HOST', 'redis.example.com' );
define( 'SHIFT8_DBCACHE_REDIS_PORT', 6379 );
define( 'SHIFT8_DBCACHE_REDIS_DATABASE', 2 );
define( 'SHIFT8_DBCACHE_REDIS_PREFIX', 'shift8cache' );
define( 'SHIFT8_DBCACHE_REDIS_TIMEOUT', 1.0 );
```

If Redis is unavailable, the plugin falls back to uncached reads and surfaces an admin notice instead of failing closed on frontend requests.

## Rule execution

Approved rules are stored in WordPress options and executed through a minimal `db.php` drop-in. The drop-in only wraps `get_results()`, `get_row()`, `get_var()`, and `get_col()` and only for approved `SELECT` fingerprints.

The plugin will not overwrite a pre-existing third-party `wp-content/db.php` drop-in. If one already exists, active caching remains disabled and the admin screen shows a conflict notice.

## Security and design principles

- Require `manage_options` for all admin actions.
- Verify nonces before changing capture state or settings.
- Sanitize all settings input before persistence.
- Prefer the smallest possible cache scope.
- Keep the active interception layer minimal and limited to explicit approved rules.
- Keep Redis passwords out of normal option storage.
- Refuse to override another database drop-in.

## Development notes

This repository now includes the analysis phase plus a first active-caching phase with explicit rule authoring, a guarded `db.php` drop-in, Redis connectivity checks, and test coverage for rule normalization and runtime helpers. The plugin still intentionally avoids broad auto-caching or a large collector framework.

## Testing

The plugin ships with PHPUnit-oriented unit tests for settings sanitization, query aggregation, rule storage, runtime matching, and drop-in synchronization behavior. If your environment has `phpunit` installed, run:

```bash
phpunit --configuration phpunit.xml
```

## Roadmap

- Phase 1: analysis-only capture and rule authoring
- Phase 2: Redis-backed rule execution with stale-once behavior
- Phase 3: operational visibility for hit rate, misses, refreshes, and safe fallback behavior
- Phase 4: targeted invalidation strategies for approved query scopes