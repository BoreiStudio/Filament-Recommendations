# Changelog

All notable changes to `boreistudio/filament-recommendations` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `FeatureProvider` contract + `FeatureResolver` — lets other plugins (e.g. `filament-store`) expose content-based features for models that do not implement `Recommendable`, so content-based works without modifying the model.
- `RecommendationSettingsPage` now pre-populates the repeater from `recommendations.models`, so every recommendable model is always visible with its strategy selector (Co-occurrence / Content-based / Collaborative filtering).

### Fixed

- `RecommendationService::resolveTenantId()` no longer throws `TenantResolverNotConfigured` when no custom resolver is bound in multi-tenant mode; it safely returns `null`.
- `ContentBasedStrategy` accepts any Eloquent model (via `FeatureProvider`) instead of requiring `Recommendable`.
- PHPStan/Larastan: `Recommendation` and `RecommendationEvent` now declare their column `@property` types, and `recommendationIds()` uses an explicit loop instead of `Collection::map()->unique()` (which failed type inference on some Larastan versions).
- In-panel **Documentation page** (`docs/*/index.md` modules) with a sidebar, markdown rendering and copy-to-clipboard, toggled via `RecommendationsPlugin::make()->documentation(true|false)`.
- **Configuration cluster** — the settings page and documentation are grouped under a single `Recommendations` cluster (`RecommendationsConfigCluster`), matching the pattern of the other Borei Studio plugins.
- **Own compiled CSS** (`resources/dist/filament-recommendations.css`) registered through `FilamentAsset`, plus `tailwind.config.js` and `package.json` build scripts.
- `RecommendationSettingsPage` rewritten as a proper Filament form (strategy select + result limit per model) instead of raw HTML.
- `SuperAdminRecommendationsPage` restyled with Filament components (stat cards, tenant list with avatars and recalculate buttons).
- `SECURITY.md` with a vulnerability reporting policy.

### Changed

- Translations moved to `resources/lang/{lang}/messages.php` and referenced via the correct `filament-recommendations::` namespace (fixes the literal `recommendations::widgets.recommendations.empty` key in the widget).
- Widget empty state uses a proper icon + translated copy.

### Fixed

- `RecalculateRecommendationsJob` no longer requires the subject to implement `Recommendable` — it processes any Eloquent model via `RecommendationService`, so plugins like `filament-store` work out of the box.
- Strategies (`CoOccurrenceStrategy`, `ContentBasedStrategy`, `CollaborativeFilteringStrategy`) accept any Eloquent model as subject; content-based returns an empty result for models without `getRecommendationFeatures()`.
- MySQL compatibility: index names and composite indexes now fit within the 64-char name / 3072-byte key limits (previously migration failed on MySQL 9.x with `Specified key was too long`).
- `RecommendationsWidget` used an invalid icon name (`heroicon-o-lightbulb`) — replaced with the correct `heroicon-o-light-bulb` so the widget renders without a missing-icon error.
- `RecommendationCache` no longer serializes Eloquent models (which corrupted in persistent cache stores); it stores scalar `[type, id]` pairs and rehydrates models on read.
- `post-autoload-dump` script now resolves the host project root correctly (`dirname(__DIR__, 3)`), so `filament:assets` runs on install.

### Added

- First release. See the [README](README.md) for the full feature set:

  - **Tenancy contracts** — `TenantResolver` and `TenantProvider` so the plugin never assumes a specific tenancy package (single-tenant and multi-tenant ready).
  - **`HasRecommendations` trait** — `recommend()`, `recordInteraction()`, and the `recommendations()` relation with automatic tenant scoping.
  - **Strategies** — `CoOccurrenceStrategy` (SQL-aggregated, "who also viewed"), `ContentBasedStrategy` (weighted Jaccard + normalized numerics), and `CollaborativeFilteringStrategy` (advanced/optional, cosine over a user-item matrix).
  - **Background recalculation** — `RecalculateRecommendationsJob` and the `recommendations:rebuild` command (per model, per tenant, or all tenants via `TenantProvider`).
  - **Result caching** — `recommend()` results are cached per subject and tenant (`cache.enabled` / `cache.ttl`), invalidated automatically on recalculation.
  - **Filament integration** — `RecommendationsWidget`, `RecommendationSettingsPage` (per-tenant), `SuperAdminRecommendationsPage` (health + recalculate), and a `RecommendationsPlugin` for any panel.
  - **Operational tooling** — `recommendations:install` (interactive), `recommendations:prune-events` (retention policy), clear exceptions for common misconfigurations, and an isolation audit test suite.
