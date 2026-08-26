# Settings

Two things share one drawer, and they are not the same kind of thing.

| Tab | Whose | Guard |
| --- | --- | --- |
| General | The store: name, logo, currency | `settings.manage` permission |
| Profile | The signed-in user: name, password | None — everyone has an account |

Settings is a sheet over whichever page the user is on, not a destination, so
there is no page to visit and no route to guard for reading. The two write
routes are all there is:

```
PUT settings/general    → permission:settings.manage
PUT settings/profile    → auth
PUT password            → auth (already existed)
```

The tab strip only offers General to someone who can save it, and the route
refuses it regardless — the hidden tab is tidiness, the middleware is the rule.

## Where a value comes from

Each setting falls back to configuration, so a fresh install works before
anything has been saved:

```
company name   settings table → config('app.name')
currency       settings table → config('currencies.default')
logo           settings table → none (a placeholder renders)
```

That is also what keeps the earlier rule true: the project name comes from
config and env until an operator deliberately overrides it here.

`App\Core\Services\SettingsService` reads the whole table as one cached blob —
these are a handful of scalars read on every request — and drops the cache on
write. Key/value rather than a column per setting, so adding one is not a
migration.

## Currency

One store, one currency. It is a setting, and every amount in the application
is written in it.

```php
config('currencies.available')   // USD, EUR, GBP, BDT — the choices
Currency::code()                 // what the store actually uses
Currency::format('1499.99')      // ৳1,499.99
```

`config/currencies.php` carries the symbol, position and decimal places, so an
amount reads identically everywhere rather than depending on the viewer's
locale. Adding a currency is configuration, not code; the form offers whatever
the file lists, and validation accepts exactly that set.

**Orders and expenses do not store a currency.** They used to, which implied
records could differ when they cannot, and left reports summing across a
distinction that never existed. The column is dropped
(`2026_08_26_000300_drop_currency_columns`), and figures are written in the
store's currency at the moment they are read.

On the frontend, `money()` reads a module-level ref set from the shared props
on boot and after every visit:

```
settings.currency (shared prop) → setCurrency() → money()
```

Because it is a ref, every template calling `money()` re-renders when the
setting is saved — no reload, and no component knows where the currency came
from.

## Profile

`name` only. The email address identifies the account and is deliberately not
accepted by the request, so a stray field cannot let someone move their own
login — an administrator changes it through the Access module. Passwords reuse
the existing `PUT /password`, which requires the current password.
