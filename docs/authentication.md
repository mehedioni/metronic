# Authentication

Standard Laravel session authentication, written into the application rather
than pulled from a starter kit, so the existing Vue/Inertia frontend was not
overwritten. No new package was added for auth.

## What exists

| Route | Controller | Notes |
| --- | --- | --- |
| `GET /login` | `AuthenticatedSessionController@create` | Inertia page `auth/Login` |
| `POST /login` | `AuthenticatedSessionController@store` | `throttle:6,1` + per-email/IP limiter |
| `POST /logout` | `AuthenticatedSessionController@destroy` | invalidates + regenerates the session |
| `GET/POST /forgot-password` | `PasswordResetLinkController` | broker `users` |
| `GET /reset-password/{token}`, `POST /reset-password` | `NewPasswordController` | |
| `PUT /password` | `PasswordController@update` | signed-in user changes their own password |
| `GET /verify-email`, `GET /verify-email/{id}/{hash}`, `POST /email/verification-notification` | `EmailVerificationController` | |

There is **no registration route**. Accounts are created by an administrator
through the Access module, which is the appropriate model for an admin panel.

## Rules enforced

- **Remember me** — `LoginRequest::authenticate()` passes the `remember`
  boolean to `Auth::attempt()`.
- **Throttling** — `LoginRequest` applies `RateLimiter` keyed on
  email + IP (5 attempts) and fires `Illuminate\Auth\Events\Lockout`, on top of
  the route-level `throttle:6,1`.
- **Deactivated accounts cannot sign in** — `LoginRequest::ensureAccountIsActive()`
  logs the session straight back out and fails validation.
- **Deactivation takes effect immediately** — `EnsureUserIsActive` middleware
  (appended to both the `web` and `api` groups) logs out and aborts 403 on the
  next request of an already-signed-in user who has been deactivated.
- **Session hygiene** — `session()->regenerate()` on login,
  `invalidate()` + `regenerateToken()` on logout.
- **Password policy** — `Password::defaults()` is configured in
  `AppServiceProvider` (min 8, letters, numbers) and used by every password
  rule.
- **Password reset link** — `ResetPassword::createUrlUsing()` points at the
  Inertia page route, not the API route.

`User` implements `MustVerifyEmail` so the notification works, but **no route
is wrapped in the `verified` middleware**: administrators create accounts, so
blocking a new user until they click a link would be a lockout risk with mail
set to the `log` driver. Add `->middleware('verified')` to a route group if the
business later requires it.

## Inertia sharing

`HandleInertiaRequests::share()` exposes:

```php
'auth' => ['user' => …, 'roles' => [...], 'permissions' => [...]],
'flash' => ['success' => …, 'error' => …],
```

The frontend reads permissions through `resources/js/composables/usePermissions.ts`
(`can()` / `canAny()`), which only hides controls — the server still authorizes
every action.
