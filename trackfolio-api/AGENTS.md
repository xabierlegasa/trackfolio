# Trackfolio API — agent instructions

Laravel 12, PHP 8.4, API-only. Organize new code as **DDD by bounded context**, not by Laravel default `Http/Models` dump.

When changing this app, follow `.cursor/rules/` in this directory. Prefer **small, named types** over growing controllers.

## Bounded contexts (`app/`)

| Context | Owns |
|---|---|
| `Auth` | Login/register HTTP + application services |
| `User` | User account |
| `AccountStatement` | Degiro account statement CSV, EUR cash as-of |
| `DegiroTransaction` | CSV upload, trades, holdings as-of date |
| `Portfolio` | Daily snapshots, stats-as-of view, evolution |
| `Isin` | Quotes, ticker resolution, market calendar, providers |
| `ExchangeRate` | USD→EUR persistence and resolution |
| `TaxReturn` | FIFO tax year reports |
| `GlobalConfig` | App-wide `global_config` key/value kill switches |
| `Admin` | Admin users + internal admin panel APIs |
| `Dummy` | Internal/debug only |

Do **not** create a new top-level folder unless it is a real bounded context.

## Layers (inside a context)

```
app/{Context}/
  Domain/
    Entity/          # Eloquent models / aggregates (persistence details leak is OK here)
    Service/         # Business rules; no HTTP Request/JsonResponse
    DTO/             # Immutable input/output values
    Exception/       # Domain failures
  Application/
    UseCase/         # One use case per class: orchestrate domain services
    Job/             # ShouldQueue wrappers; handle() calls a UseCase; queue_one via RabbitMQ
  Infrastructure/
    Controllers/     # HTTP only: validate, auth, status codes, JSON
    Repository/      # Queries and persistence; the only layer that builds Eloquent queries for that context
```

**Dependency rule:** Infrastructure → Application → Domain. Domain must not import Controllers, `Illuminate\Http\*`, or other contexts’ Infrastructure.

## Gold path (copy this)

`UploadDegiroTransactionController` → `UploadDegiroTransactionsUseCase` → domain services + DTO result.

New write/query flows should look the same: thin controller, use case or domain service, repository for SQL.

## Persistence

- Money: integer **min units** (cents). Never `float` for amounts.
- Degiro `date` column is `DD-MM-YYYY` text; compare with `STR_TO_DATE` or parse in PHP.
- Historical “as of” views: cache in `portfolio_daily_snapshots.view_payload` + Laravel cache. Rebuild when `closes_complete` is false and exact `isin_quotes` for that as-of later appear; new quotes invalidate payloads for that closing date.
