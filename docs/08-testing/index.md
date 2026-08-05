# Testing

## Package test suite

```bash
composer install
composer test                                 # single-tenant
RECOMMENDATIONS_TENANCY=multi composer test   # multi-tenant
```

## Code style & static analysis

```bash
composer format    # Laravel Pint
composer analyse   # PHPStan
```

## Isolation audit

The `tests/Security` directory contains an exhaustive audit that runs every package query in multi-tenant mode and asserts no query can return another tenant's data. This is critical — not just for performance, but for security.

## Benchmarks

Co-occurrence computation is fully aggregated in SQL. On SQLite via testbench:

- ~10k events across 5 tenants: co-occurrence compute in **~0.2s** (20 subjects).
- A tenant-scoped count over 5,000 events: **~0.4s** including row inserts.
