# BitBoard TODO

Prioritized backlog for BitBoard. This list is based on a review of the current `main`
branch. Items are ordered by risk and dependency: finish **P0** before exposing the forum to
real users, then work through **P1** and **P2**.

Legend: `P0` blocker/security · `P1` correctness · `P2` features · `P3` developer experience.
Items marked **good first issue** are small and self-contained.

---

## P0 — Security and blockers

### Platform and bootstrap

- [x] **Replace the autoloader with a PSR-4-style implementation.** `auto_loader.php` now maps
  explicit namespace prefixes (`App\Classes\`, `App\Forum\*`, `App\Install\`, `App\Interfaces\`,
  `Api\Classes\`, `Api\POST\`) to directories with `__DIR__`, converts `\` to `/`, and uses
  `require_once`. Composer autoloading remains a P3 item.
- [x] **Remove remaining backslash paths in URLs.** Avatar, footer script, admin script, and
  `ELEMENTS.html` asset URLs now use forward slashes on all platforms.
- [x] **Normalize the application base URL.** `UrlManager::GetPath()` uses `SCRIPT_NAME`, converts
  backslashes, and always returns exactly one trailing slash, so root and subdirectory installs
  concatenate routes correctly.
- [x] **Create the missing avatar directory or stop linking to it.**
  `AvatarUtils::GetPath()` now rejects path components, allows only common image extensions,
  and falls back to the theme default when the avatar file or directory is missing.
- [x] **Fix the page lifecycle.** `IndexPage::__construct()` no longer calls `Do()`; `PageFactory`
  is now the single caller, so `/` and `/index` render once.
- [x] **Guard the empty-URL path in `BitBoard::Do()`.** `app/BitBoard.php` now derives the
  first segment with a null-safe fallback and no longer reads `$SplitedURL[0]` when the URL is
  empty. Manually verified `/`, `/offline`, `/login`, and `/forum/1` in online, offline, and
  force-login modes.
- [x] **Fix `BannedPage` for guests.** `BannedPage` now checks `SessionManager::IsLogged()`
  and the session user before reading permissions. Guests are redirected home instead of
  hitting an undefined session user.
- [x] **Fix the installer validation bug.** `Step_5.php` now trims values, rejects empty and
  short input with `mb_strlen()`, and enforces the 64/256-character column limits.
- [x] **Escape values written to `app/config.php`.** `Step_3.php` now validates POST values,
  restricts the database name to `^[A-Za-z0-9_]+$`, quotes it in `CREATE DATABASE`, and writes
  every config value with `var_export()`.
- [x] **Stop logging the session during installation.** Removed the `$_SESSION` console log
  from `Setup` and the unused `Console` import.

### Authentication, sessions, and authorization

- [x] **Add CSRF protection** to the login form, admin settings, and every `Api/POST/*`
  endpoint. A per-session token is rendered in the page meta tag and forms, sent as
  `X-CSRF-Token` for API calls, rotated after login, and verified server-side.
- [x] **Harden sessions.**
  - [x] Regenerate the session ID after login (`session_regenerate_id(true)`).
  - [x] Set `HttpOnly`, `SameSite=Lax/Strict`, and `Secure` (when HTTPS) cookie flags before
    `session_start()`.
  - [x] Destroy the session and cookie on logout.
  - [x] Add a 30-minute idle timeout and a 12-hour absolute timeout. Expired sessions are
    cleared, their IDs regenerated, and the login page shows a notice.
- [x] **Use generic login errors and support username/email login.** `LoginPage` now trims
  the identifier, verifies the password before loading permissions, and returns the same
  error for unknown users and wrong passwords. `GetAccountByLogin()` matches username or
  email.
- [x] **Remove the ignored "remember me" option.** The checkbox was removed from the login
  form; persistent login tokens are not implemented.
- [x] **Add login rate limiting and failure logging.** `LoginThrottle` records failed
  attempts in `bit_login_attempts` and blocks after 5 failures per account/identifier or
  10 per IP in a 15-minute sliding window. Successful logins clear that account's failures,
  and rows older than 7 days are pruned as new failures are recorded. New installs create
  the table in step 3; existing installs create it on the first login attempt.
- [x] **Enforce locked forum/subforum permissions.** `is_locked` is now checked in
  `ForumPage`, `SubforumPage`, and `ThreadPage`. Users without
  `Permissions::VIEWING_FORUM_LOCKED` are redirected to the forum index, and a locked
  parent forum also protects its subforums and their threads. The current API exposes
  only settings/prefix routes, so there is no forum-content API surface yet; future
  forum endpoints must apply the same check.
- [x] **Use granular API permissions.** `ApiMain` now resolves a route before applying its
  declared permission. Settings and prefix updates require both `ADMIN_PANEL_ACCESS` and
  `MANAGING_SETTINGS`; guests receive `401`, authenticated users without the required
  permission receive `403`.
- [x] **Harden the installer and remove it from the public path after setup.** The installer
  now rejects posted steps that do not match the current step, verifies a CSRF token, and
  refuses to run once `app/install/lock` exists. Root `.htaccess`, `app/.htaccess`, and
  `app/install/.htaccess` deny direct `/app/...` access; the Nginx guide does the same. The
  README now documents removing write access from `app/install` after setup and treating
  `lock` / `step` as sensitive control files.

### Output, input, and API

- [x] **Introduce an escaping strategy for templates.** `Template` now has explicit
  `AddEscapedEntry()`/`AddJsEntry()` helpers. Plain text and attribute values use
  `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`; HTML fragments still use
  `AddEntry()` intentionally. `UsernameUtils::Format()` escapes the username before inserting
  it into `rank_format`, which remains an explicit admin-only HTML field. Rich thread/post
  content is sanitized with the new `HtmlSanitizer` allowlist (scripts, event handlers, unsafe
  URL schemes, and unsupported tags/attributes are removed; basic formatting, links, images,
  and tables are kept). JavaScript contexts (login error, notifications, installer errors) use
  JSON encoding instead of raw string interpolation. `UrlManager::GetPath()` also restricts the
  client-controlled `Host` header to safe hostname/IPv6/host:port characters before it is used
  in `{server_url}`.
- [x] **Fix the API database connection.** `ApiMain` now constructs the connection without
  passing the database name as the charset and calls `SelectDatabase($config['name'])`.
- [x] **Validate API routes.** `ApiMain::HandleURL()` now uses an explicit method/route/class
  allowlist, rejects non-string or unknown routes before `class_exists()`/autoloading, and
  exposes `api/settings` plus the prefix edit/delete routes. New endpoints must be added to
  the allowlist.
- [x] **Fix `Api/POST/Prefixes.php`.** The file now declares `class Prefixes` and implements
  edit/delete with route-authoritative IDs, prepared statements, name/class length and CSS
  class validation, built-in-prefix protection, not-found handling, and a conflict check when
  a prefix is still used by threads.
- [x] **Tighten `Api/POST/Settings.php`.** The endpoint now validates field presence and types,
  enforces the database column limits, accepts an empty offline message, parses boolean flags
  explicitly, uses prepared statements, and returns field-level errors for bad input.
- [x] **Restrict CORS and response details.** `api.php` no longer sends wildcard CORS
  headers. Invalid JSON returns a generic message, and endpoint/database exceptions are
  logged and returned as a generic 500 instead of leaking details.
- [x] **Remove `eval()` from the modal helper.** `modal()` now accepts a callback function, and
  declarative `bb-action="modal"` buttons dispatch a `bb:modal-confirm` event with the trigger,
  `data-confirm-action`, and `data-confirm-value`. Attribute values are passed as data only and
  are never executed as code.
- [x] **Escape console output.** `Console::Log()`/`Console::Error()` now encode values with
  `json_encode()` and `JSON_HEX_*` flags, so quotes and `</script>` cannot break out of the
  generated script tag. Removed the leftover `ThreadPage` debug log.

---

## P1 — Correctness and core behavior

### Database layer

- [ ] **Handle `select_db()` failures.** `Database::SelectDatabase()` ignores the return
  value, so a typo in the database name results in confusing "no database selected" errors.
  Throw a clear exception.
- [ ] **Adopt MySQLi exceptions consistently.** On PHP 8.1+, `mysqli` throws by default, so
  checks like `connect_error` and `$query->errno` may never run. Configure
  `mysqli_report()` explicitly and translate errors into a project exception.
- [ ] **Protect against nested queries.** `Database` keeps a single active statement and
  closes the previous one on every `Query()`. Either document the restriction or return a
  separate result object.
- [ ] **Add schema constraints and indexes.**
  - Foreign keys with deliberate `ON DELETE` behavior.
  - Unique indexes on `bit_accounts.username` and `bit_accounts.email`.
  - Indexes on `bit_threads(forum_id, subforum_id)`, `bit_posts(thread_id)`,
    `bit_posts(user_id)`, `bit_threads(user_id)`, and `bit_accounts(last_active)`.
  - Defaults for `is_closed`, `is_pinned`, `prefix_id`, and like counters.
- [ ] **Add a migration system.** The installer creates the schema only once. Add numbered
  migrations (Phinx, Doctrine Migrations, or a small in-house runner) so existing forums can
  upgrade without reinstalling.
- [ ] **Remove magic row IDs.** `bit_settings.id = 0` and the implicit admin rank ID `1` are
  brittle. Use a key/value settings table or a single-row constraint, and look up ranks by
  name/slug.

### Pagination and page bugs

- [ ] **Fix thread pagination.** `ThreadPage::$postsCount` is never assigned, so pagination
  is always hidden (`ThreadPage.php:26-27,141-146`). Count posts for the thread and pass the
  real total to `PaginationWidget`.
- [ ] **Fix subforum links.** `SubforumPage.php:63` and `:146` redirect/page to
  `forum/{id}/page/...`; they must use `subforum/{id}/page/...`.
- [ ] **Fix prefix rendering in `SubforumPage`.** Lines 102-119 access `->template` on a
  string when no prefix matches. Mirror the explicit `$threadPrefix = ''` handling used in
  `ForumPage`.
- [ ] **Fix the members zero-result loop.** With zero regular members,
  `MembersPage.php:50-53` redirects to `/members/page/0`, which redirects back to page 1.
  Show an empty state when the total is zero.
- [ ] **Fix `PaginationWidget`.**
  - `$pagesub2` is overwritten by the `page_m1` block (`PaginationWidget.php:80-82`), so the
    "page - 2" link never renders.
  - Next-page buttons reuse `page_m1.html`; add a dedicated template/parameter.
  - Rename `$totalPages` to `$totalItems` (or pass the actual page count) to match its use.
  - Add tests for first page, middle page, last page, and a single page.
- [ ] **Return real 404s for missing IDs.** Forum/thread/subforum handlers currently
  redirect invalid IDs to hardcoded IDs such as `forum/1/page/1`. Render `_404Page` with an
  HTTP 404 status instead.
- [x] **Fix `/index` duplicating output** (also listed under P0); a routing test is still pending.
- [ ] **Remove leftover template placeholders.** `themes/default/templates/index/index.html`
  contains `{top_stats}`, which `IndexPage` never replaces.
- [ ] **Reduce query counts.**
  - `ThreadPage::fetchPosts()` runs three queries per post (author + thread count + post
    count). Fetch authors and counts in the posts query or with one aggregate query.
  - `IndexPage` runs one last-post query per forum and uses `SELECT * ... NumRows()` for
    statistics; use `COUNT(*)` and a single aggregate.
  - `ForumPage`/`SubforumPage` repeat the same thread-list query with small differences;
    extract a shared controller method.
- [ ] **Cache last-post lookups.** `ForumController::GetLastPost()`,
  `SubForumController::GetLastPost()`, and `ThreadController::GetLastPost()` can be cached
  per request or replaced with a join.

### Templates, pages, and assets

- [x] **Fix `Template` initialization and `AddEntries()`.** `$variables`/`$replaces` now
  initialize to `[]`; `AddEntries()` compares the two `count()` values.
- [ ] **Simplify `Template::Load()`.** Use `file_get_contents()` with an explicit error
  instead of `fopen`/`flock`/`filesize`; handle empty files and missing templates.
- [ ] **Move `{server_url}` replacement before page content injection.** Currently global
  widgets and page content both rely on a second replacement pass. Document or centralize
  this to avoid double-replacement surprises.
- [ ] **Append the global JS inside `<body>`.** `PageBase::CommonSetup()` appends
  `footer/end.html` after `</html>`, producing invalid markup. Add a `{scripts}` placeholder
  to every layout, or append before the closing body tag.
- [x] **Restore notifications.** Restored `assets/javascripts/toastify.js` and
  `assets/stylesheets/toastify.css`; the local `toast()` wrapper uses the existing Toastify
  CDN and provides a small local fallback. Admin settings save was verified end-to-end.
- [ ] **Remove the old toast include from the login page** if it is not used
  (`themes/default/templates/login/login.html:29`).
- [ ] **Align requirement with the client validator.** `install/form.js` requires an
  uppercase letter, but `PasswordUtils` only checks length, a digit, and a letter. Decide on
  one policy and enforce it server-side.
- [ ] **Remove development pages from navigation.** `header/nav/menu.html:8` links to
  `/ELEMENTS.html` in production. Move it behind a debug flag or delete it.
- [ ] **Fix dead links.** `header/nav/user_menu.html:6` points to `/settings` (no page), and
  `login.html:11` + `login_register.html:6` point to `/register` (no page). Add the pages or
  hide the links until they exist.
- [ ] **Finish the footer.** `footer/footer.html` contains four `{#}` "Test" links; remove
  them or make them configurable.
- [ ] **Make the credits optional.** `FooterWidget.php:22` has a TODO to add the
  "Powered by BitBoard" toggle to forum settings.
- [ ] **Pin front-end dependencies.** `@tabler/icons-webfont@latest` and `unpkg.com/microtip`
  are unpinned CDN URLs. Pin versions and add integrity attributes, or vendor the assets.
- [ ] **Remove duplicate request helper.** `request.js` duplicates `req()` from `main.js`;
  keep one implementation.
- [ ] **Fix empty/placeholder CSS.** `themes/default/styles/profile.css` is empty and
  `profile.html` says "soon". Build the profile styles with the profile page.

---

## P2 — Features

### Accounts and authentication

- [ ] Registration page with unique username/email validation, password policy, terms, and
  rate limiting. The UI already links to it.
- [ ] Email verification with resend and expiration.
- [ ] Password reset via signed, expiring tokens.
- [ ] Account settings: email, password, avatar, bio, signature, timezone, notification
  preferences.
- [ ] Optional two-factor authentication (TOTP) after the core is stable.
- [ ] Ban/suspension records with reasons and expiry instead of only the "Banned" rank.

### Threads and posts

- [ ] Create thread/reply forms using the existing controllers; enforce
  `CREATING_THREADS`, `CREATING_POSTS`, and locked variants.
- [ ] Edit and delete with `EDITING_*`/`DELETING_*` permissions, edit history, and
  soft-delete/restore.
- [ ] Likes for threads and posts (existing TODO in `ThreadPage.php:87`), with one-like-per-
  user constraints and counter caching.
- [ ] Quote, multi-quote, mentions, and notifications.
- [ ] Rich editor with preview, drafts, and a Markdown/HTML policy.
- [ ] Attachments/uploads with extension/MIME/size validation and private storage.
- [ ] Polls, thread subscriptions, and "watch forum" notifications.
- [ ] Pagination-preserving anchors and "jump to last unread".

### Moderation and admin

- [ ] Pin, lock, move, merge, and split threads.
- [ ] Report queue with statuses, notes, and `VIEWING_REPORTS`.
- [ ] Admin CRUD for categories, forums, subforums, and prefixes, including drag-and-drop
  ordering and locked toggles. The admin nav links already exist.
- [ ] User management: search, rank assignment, ban/unban, reputation adjustments.
- [ ] Rank/permission matrix editor with readable labels for every bit flag.
- [ ] Audit log for admin actions and moderator actions.
- [ ] Admin dashboard statistics and recent activity.
- [x] Finish the prefix edit/delete API and add validation.

### Discovery and profiles

- [ ] Search threads/posts with MySQL `FULLTEXT`, filters (forum, author, date), and
  pagination.
- [ ] Public profile page with avatar, rank, reputation, join date, recent posts/threads,
  and online state.
- [ ] Member directory filters/sorting.
- [ ] Online users list and "who is viewing" (optional).
- [ ] SEO-friendly thread slugs and canonical URLs.
- [ ] OpenGraph/Twitter metadata, sitemap, RSS/Atom feeds.

### API and integrations

- [ ] API v1 with explicit routes, versioning, token auth, scopes/rate limits, and OpenAPI
  documentation.
- [ ] Read endpoints for forums, threads, posts, members, and stats.
- [ ] Write endpoints for posting/editing with the same permission checks as the UI.
- [ ] Webhooks or an event/hook system for plugins.
- [ ] Web push/email notification channel.

### Themes and UX

- [ ] Theme manager (install, activate, preview, delete).
- [ ] Child themes and a documented template variable reference.
- [ ] Consistent dark/light/auto handling (some layouts hardcode `data-theme="dark"`).
- [ ] Responsive audit on mobile and tablet.
- [ ] Accessibility pass: landmarks, labels, focus states, contrast, keyboard navigation.
- [ ] Loading, empty, and error states for every page.
- [ ] i18n/locale files, timezone-aware dates, and RTL support.
- [ ] Replace hardcoded English strings in pages/widgets/templates.

---

## P3 — Developer experience and operations

### Tooling

- [ ] Add `composer.json` with PSR-4 autoloading, dev dependencies, and scripts.
- [ ] Add PHPUnit tests for `PasswordUtils`, `EmailUtils`, `UsernameUtils`,
  `RelativeTime`, `Permissions`, `Template`, and `PaginationWidget`.
- [ ] Add integration tests for installer, login, permissions, routing, and API auth.
- [ ] Add PHPStan/Psalm at a sensible level and PHP-CS-Fixer/PHP_CodeSniffer.
- [ ] Add GitHub Actions: lint on PHP 8.0-8.3, static analysis, unit tests, and a smoke test
  against MySQL/MariaDB.
- [ ] Add a `docker-compose.yml` for Apache + PHP + MariaDB and seed a demo forum.
- [ ] Add `config.example.php` and `.env.example`, and support environment variables.
- [ ] Add structured logging (PSR-3) and an error handler that renders a friendly 500 page.
- [ ] Add database backup/restore and health-check commands.
- [ ] Add a CLI installer for automated deployments.
- [ ] Add `CHANGELOG.md`, semantic version tags, and release notes.

### Code quality

- [ ] Add `declare(strict_types=1);` and consistent typing across classes.
- [ ] Normalize code style (tabs vs spaces, brace placement, `static public` vs
  `public static`).
- [ ] Remove unused imports and dead code (`App\BB`, `App\Classes\Avatar`, etc.).
- [ ] Add structured request/response objects instead of passing `$_GET`, `$_POST`, and
  `$_SESSION` through page constructors.
- [ ] Extract a router and shared URL validation; the `UrlHandler()` logic is duplicated in
  every page.
- [ ] Add `NotFound`/`Forbidden`/`BadRequest` HTTP exceptions and map them centrally.
- [ ] Add pagination and query-count tests; expose `Database::$query_count` in debug mode.
- [ ] Add validation for struct construction (reject unknown keys, avoid dynamic properties
  on PHP 8.2+).

### Repository hygiene

- [ ] Extend `.gitignore` with `.env`, IDE/OS files, uploads, logs, and local dumps.
- [ ] Add `CONTRIBUTING.md`, `SECURITY.md`, issue templates, and a pull-request template.
- [ ] Add a `CODE_OF_CONDUCT.md` if the project wants one.
- [ ] Document the database schema (ER diagram) and template variables.
- [ ] Add screenshots/GIFs to the README and a short "request flow" diagram.
- [ ] Keep the README feature list honest: mark WIP features explicitly (see README).
- [ ] Update `cntlines.py -m` to match the new README layout (or replace it with a small
  script that updates a dedicated line-count marker).
- [ ] Add a `make`/npm-free task runner for lint, test, and line-count commands.

---

## Good first issues

- [x] Restore the missing Toastify assets instead of switching admin notifications to `spell_alert`.
- [ ] Remove the "CSS ELEMENTS" link from the production navigation.
- [ ] Fix backslashes in `AvatarUtils`, `footer/end.html`, and `admin.html`.
- [x] Fix `Template::AddEntries()` count comparison and initialize arrays.
- [x] Fix `Step_5` `strlen($value <= 0)` validation.
- [ ] Fix `RankController::Create()` default flags (`0000000000` is octal zero; use `0`).
- [ ] Remove the leftover `{top_stats}` placeholder.
- [ ] Add `config.example.php`.
- [ ] Replace the four "Test" footer links.
- [x] Remove `Console::Log()` debug calls from `ThreadPage` and `Setup`.

---

## Suggested release gate

Before calling a release "stable":

- [ ] All P0 items closed, with manual security review of login and admin API.
- [ ] Registration, login, logout, thread creation, replies, and moderation work end-to-end.
- [ ] Automated tests pass on PHP 8.0, 8.1, 8.2, and 8.3 with MySQL and MariaDB.
- [ ] Fresh install and upgrade migration both verified on Linux and Windows.
- [ ] No PHP warnings/notices with `error_reporting(E_ALL)`.
- [ ] README, installation guide, and API docs match the shipped behavior.
