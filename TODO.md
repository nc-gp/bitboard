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
  `require_once`. The autoloader stays dependency-free.
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
  only settings, avatar, and prefix routes, so there is no forum-content API surface yet;
  future forum endpoints must apply the same check.
- [x] **Use granular API permissions.** `ApiMain` now resolves a route before applying its
  declared permission. Settings, avatar, and prefix requests require both `ADMIN_PANEL_ACCESS`
  and `MANAGING_SETTINGS`; guests receive `401`, authenticated users without the required
  permission receive `403`.
- [x] **Harden the installer and remove it from the public path after setup.** The installer
  now rejects posted steps that do not match the current step, verifies a CSRF token, and
  refuses to run once `app/install/lock` exists. Root `.htaccess`, `app/.htaccess`, and
  `app/install/.htaccess` deny direct `/app/...` access; the Nginx guide does the same. The
  README now documents removing write access from `app/install` after setup and treating
  `lock` / `step` as sensitive control files.
- [x] **Allow a configurable admin panel path during installation.** Installer step 5
  normalizes and validates the secret admin segment (3-64 letters, numbers, `_`, or `-`,
  lowercased and excluding existing routes), stores it as `bit_settings.admin_path`, defaults
  existing installs to `admin`, and routes only that segment to `AdminPage`. All admin links,
  redirects, pagination, settings tabs, header menu, and the privacy fallback use the configured
  path.

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
  exposes `api/settings`, `api/avatars`, plus the prefix edit/delete routes. New endpoints must
  be added to the allowlist.
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

- [x] **Handle `select_db()` failures.** `Database::SelectDatabase()` now checks the result and
  throws a `DatabaseException` with the database name and driver message.
- [x] **Adopt MySQLi exceptions consistently.** `Database` calls
  `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`, wraps connection, prepare, and
  execute failures in `DatabaseException`, and keeps the original driver code for callers that
  handle lock/deadlock cases.
- [x] **Protect against nested queries.** The single-active-statement restriction is documented
  on `Database`, and fetching without an active result throws a clear `DatabaseException`.
- [x] **Add the remaining indexes and column defaults.** Migration 15 adds unique indexes
  on account username/email, indexes for last activity, thread forums/users, and post
  threads/users, plus defaults for thread flags/prefix/like counters and post like counters.
  Fresh installs receive the same definitions in their `CREATE TABLE` statements.
- [x] **Add foreign keys with deliberate `ON DELETE` behavior.** Migration 16 cleans orphaned
  rows, normalizes referenced ID columns to unsigned integers, and adds foreign keys for accounts,
  categories, forums, subforums, threads, posts, likes, edit history, subscriptions, read
  positions, reports, and notifications. Content and dependent rows use cascades where safe;
  authored content, ranks, categories, and forums use restrictive rules. Manual deletes remain
  only for notification/report targets that use `0` as a sentinel.
- [x] **Add a migration system.** `app/migrations/` contains numbered migrations and
  `Migrator` records applied versions in `bit_migrations`. Pending upgrades run once on the
  next request, guarded by a MySQL advisory lock; fresh installs run the same migrations after
  the installer creates the baseline schema. The per-request `EnsureSchema()` checks and
  `Schema::EnsureCore()` have been removed.
- [ ] **Remove magic row IDs.** `bit_settings.id = 0` and the implicit admin rank ID `1` are
  brittle. Use a key/value settings table or a single-row constraint, and look up ranks by
  name/slug.

### Pagination and page bugs

- [x] **Fix thread pagination.** `ThreadPage` now counts posts for the thread, passes the
  real total to `PaginationWidget`, and redirects out-of-range pages to the last page.
- [x] **Fix subforum links.** `SubforumPage` now redirects and builds pagination links with
  `subforum/{id}/page/...`.
- [x] **Fix prefix rendering in `SubforumPage`.** Prefix widgets now use the same explicit
  string/object handling as `ForumPage`, so threads without a matching prefix render safely.
- [x] **Fix the members zero-result loop.** Zero regular members render a dedicated empty
  state instead of redirecting to `/members/page/0`; out-of-range pages only redirect when
  members exist.
- [x] **Fix `PaginationWidget`.**
  - The "page - 2" link is no longer overwritten by the "page - 1" block.
  - Next-page buttons use the dedicated `page_next.html` template.
  - The `totalPages` constructor/property name is now `totalItems` to match its meaning.
  - First, middle, last, and single-page cases were verified manually; automated tests remain
    part of the testing roadmap.
- [x] **Return real 404s for missing IDs.** Forum/thread/subforum handlers now throw a
  `PageNotFoundException`; `PageFactory` renders `_404Page`, which sends an HTTP 404 status.
  Unknown routes and malformed forum/thread/subforum URLs use the same path.
- [x] **Fix `/index` duplicating output** (also listed under P0); a routing test is still pending.
- [x] **Remove leftover template placeholders.** Removed the unused `{top_stats}` slot and
  its styles from the index page. Latest-thread/post stats can return through the future
  plugin/event system.
- [x] **Reduce query counts.**
  - `ThreadPage` joins post authors and ranks directly and fetches thread/post totals once
    per user on the page.
  - `IndexPage` uses one aggregate query for statistics and a batched last-post lookup for
    all forums.
  - Forum/subforum thread-list SQL is centralized in `ThreadController::GetThreads()` and
    `CountThreads()`.
- [x] **Cache last-post lookups.** Forum, subforum, and thread last-post data is now
  fetched in one batch per listing page. The single-ID controller methods reuse the same
  batch implementation.

### Templates, pages, and assets

- [x] **Fix `Template` initialization and `AddEntries()`.** `$variables`/`$replaces` now
  initialize to `[]`; `AddEntries()` compares the two `count()` values.
- [x] **Simplify `Template::Load()`.** Uses `file_get_contents()` with explicit errors for
  missing/unreadable files; empty template files load with their start/end markers.
- [x] **Move `{server_url}` replacement before page content injection.** Template files now
  resolve the base URL at load time. Pagination uses a separate `{pagination_url}` prefix,
  and injected user content is no longer affected by a global server-URL replacement pass.
- [x] **Append the global JS inside `<body>`.** Every page layout now has a `{scripts}`
  placeholder before `</body>`, and `PageBase` renders `footer/end.html` through it.
- [x] **Replace Toastify with local notifications.** Removed the Toastify CDN/assets; the
  `toast()` wrapper now uses the local SpellAlert library, with success/warning/error styles.
- [x] **Remove the old toast include from the login page.** Login errors now use the shared
  SpellAlert `toast()` wrapper.
- [x] **Align requirement with the client validator.** Client and server now both require
  at least 8 characters, one digit, one lowercase letter, and one uppercase letter.
- [x] **Replace the theme element demo link.** The default theme's header now links to
  search instead of the old `/ELEMENTS.html` placeholder.
- [x] **Fix dead links.** Added `/register` and `/settings` pages with CSRF protection,
  validation, password policy, auto-login after registration, and email/password updates.
- [x] **Finish the footer.** Replaced the placeholder links with a responsive brand, Explore,
  and GitHub footer for the default theme.
- [x] **Make the credits optional.** Added the `forum_credits` setting to the installer,
  admin form/API, and footer widget.
- [x] **Pin the single front-end asset dependency.** Tabler Icons 3.49.0 is pinned on jsDelivr
  with a SHA-384 integrity hash. Everything else is local and dependency-free.
- [x] **Remove Microtip.** Replaced it with local `tooltip.css` using
  `data-tooltip-position`.
- [x] **Remove duplicate request helper.** Removed the unused `request.js`; `main.js` keeps
  the single `req()` implementation.
- [x] **Fix empty/placeholder CSS.** Built the public profile page and populated
  `themes/default/styles/profile.css`.

---

## P2 — Features

### Accounts and authentication

- [x] Registration page with unique username/email validation and password policy.
- [ ] Registration terms acceptance and rate limiting.
- [ ] Email activation before login/posting, with resend and expiration.
- [ ] Anti-bot registration protection: per-IP throttling, a honeypot/minimum submit delay, and an optional challenge.
- [ ] Configurable email-domain allowlist/blocklist for registration, seeded with common provider domains.
- [ ] Password reset via signed, expiring tokens.
- [x] Account settings for email and password.
- [x] Account settings for bio and post signature, with profile/post display, plain-text escaping,
  and length limits.
- [x] Account settings for avatar uploads with image type/size validation, custom avatar storage,
  preview, and removal.
- [x] Forum Settings uses General/Avatars tabs with active states, a shared tab template, and
  concise per-field help text linked with `aria-describedby`.
- [x] Avatar upload limits are configurable from the Avatars tab: uploads enabled/disabled,
  maximum file size, allowed formats, and optional dimension limits, enforced in account
  settings.
- [ ] Optional two-factor authentication (TOTP) after the core is stable.
- [ ] Ban/suspension records with reasons and expiry instead of only the "Banned" rank.

### Threads and posts

- [x] Create thread/reply forms using the existing controllers; enforce
  `CREATING_THREADS`, `CREATING_POSTS`, and locked variants. A shared `/post` form creates
  threads in forums/subforums or replies to threads, with CSRF, validation, and redirects.
  Thread pages also include an inline fast-reply form, and replies render oldest to newest.
- [x] Allow moderators/administrators to create threads and replies in locked forums/subforums
  and closed threads. Locked forums still honor `CREATING_*_LOCKED`; staff can now reply in
  closed threads, with a clear staff-only hint in the fast-reply form.
- [x] Edit and delete with `EDITING_*`/`DELETING_*` permissions and edit history. Authors can
  manage their own content when permitted; staff can manage any content. Deletion is
  permanent, and deleting a thread also removes all posts, reactions, and edit history
  belonging to it.
- [x] Likes for threads and posts with one-like-per-user constraints and cached counters.
  Thread and post like buttons toggle through `/like`, with AJAX updates and a normal POST
  fallback.
- [x] Reactions for threads and posts: heart, sad, funny, what?, and angry. One reaction
  per user per target, counted as one like, with a combined total indicator,
  reaction-specific icons/colors, right-aligned controls, and a vertical hover/click picker.
  New reactions also notify the thread/post author (except self-reactions); changing a
  reaction does not notify again, and withdrawing it removes the notification.
- [x] Quote and multi-quote. Quote buttons insert a plain-text `> author wrote:` block into
  the inline fast reply or prefill the full reply page without JavaScript; multi-quote
  selections persist per thread in the browser session. Quotes render as `<blockquote>` and
  survive a round trip through the plain-text editor.
- [x] Mentions and notifications. `@username` mentions and new thread/post reactions create
  persistent notifications. A header bell shows the unread count and opens an AJAX panel
  with the latest items, unread highlighting, per-item read tracking, "mark all as read",
  and 15-second polling. New items play a soft chime, with a per-browser sound
  toggle. Mentions inside quotes are ignored, and deleting content removes its
  notifications.
- [x] Show custom thread prefixes on the thread page and let thread editors choose them when
  creating or editing a thread. Pin/lock statuses stay separate from custom prefixes.
- [x] Rich Markdown editor with a formatting toolbar, live AJAX preview, and browser-local
  drafts. Policy: input is a Markdown subset, raw HTML is escaped, and rendered output is
  sanitized before storage. Existing HTML posts are converted back to Markdown when edited.
- [ ] Attachments/uploads with extension/MIME/size validation and private storage.
- [x] Thread subscriptions: subscribe/unsubscribe from the thread page, with a reply
  notification (including a direct post anchor) for every new reply by another member.
- [x] "Watch forum" notifications for forums and subforums, with watch/unwatch buttons, a
  `forum_thread` notification for new threads, deduplication when watching both targets, and
  cleanup when a target is deleted.
- [x] Pagination-preserving last-post links that jump to the correct page and post anchor, plus
  a per-user read-position table and “jump to first unread” links in forum/subforum listings.
- [x] Submit replies from the inline fast reply via AJAX, append the new post in place, scroll to it,
  and keep the normal POST fallback. Validation, permission, and CSRF errors return JSON and are
  shown as toasts; replies that overflow the page redirect to the new post.

### Moderation and admin

- [x] Pin and lock threads from the thread page with `PINNING_THREADS`/`LOCKING_THREADS`.
  Pinned threads sort first and show the pinned prefix; locked threads show the closed prefix
  and keep staff posting exceptions.
- [x] Move threads between forums and subforums from the moderation actions.
- [x] Report queue with statuses, notes, and `VIEWING_REPORTS`. Users can report threads or
  replies; staff get a filtered queue with resolve/dismiss actions and resolution notes.
- [ ] Merge and split threads (deferred; candidate for a future moderation plugin).
- [x] Admin category management: list, create, edit, delete, and manual ordering.
- [x] Admin forum and subforum management: create, edit, delete, category assignment,
  manual ordering, and locked toggles. Forums cannot be deleted while they still contain
  threads; deleting an empty forum also removes its subforums.
- [x] Admin custom prefix creation: name plus CSS class, duplicate-name protection, and
  64-character limits; built-in prefixes are left untouched.
- [x] Admin rank management: create, edit, and delete ranks, with built-in rank protection,
  user-count deletion guards, a readable permission matrix, and rank positions for hierarchy.
- [x] Admin user management: searchable and rank-filtered user list, pagination, rank
  assignment, ban/unban, reputation adjustments, and rank-hierarchy enforcement so lower
  ranks cannot modify users or ranks at or above their own position.
- [x] Rank/permission matrix editor: grouped, labelled checkboxes generated from
  `Permissions::GROUPS`, with built-in rank protections and disabled states for read-only ranks.
- [x] Audit log for admin and moderator actions: persistent `bit_audit_log`, a permission-gated
  admin page with actor/action filters and pagination, and entries for settings, categories,
  forums, subforums, prefixes, ranks, users, thread pin/lock/move/edit/delete, post edits/deletes,
  and report resolutions.
- [x] Expanded the admin dashboard with forum statistics, recent threads, newest members, and
  lightweight SVG activity charts for new threads/posts/members, with selectable 7/14/30-day
  periods and previous-period trend labels.
- [x] Offline-mode notice for moderators/administrators: staff see a persistent banner with the
  offline message while browsing; everyone else is redirected to the offline page.
- [x] Finish the prefix edit/delete API and add validation.

### Discovery and profiles

- [x] Search threads/posts with MySQL `FULLTEXT`, filters (forum, author, date), and
  pagination. Falls back to `LIKE` when the database user cannot add the indexes.
- [x] Search by thread prefix/tag, preserving the selected prefix in the filter form and
  pagination links.
- [x] Public profile page with avatar, rank, reputation, join date, recent posts/threads,
  and online state.
- [x] Member directory filters/sorting. Search by username; sort by newest, oldest,
  username, reputation, posts, or threads; pagination preserves the filters.
- [x] Online members list with a 15-minute activity window and an index preview.
- [x] "Who is viewing" indicators on forums, subforums, and threads, using the existing
  online window and a per-member viewing location.
- [x] SEO-friendly thread slugs and canonical URLs. Thread URLs use
  `/thread/{id}/{slug}/page/{page}`, old URLs redirect with a 301, and thread pages
  expose a canonical link.
- [x] OpenGraph/Twitter metadata, sitemap, RSS/Atom feeds. Pages expose social metadata,
  `/sitemap.xml` lists public content, and `/feed.xml` provides an Atom feed of recent
  threads.

### API and integrations

- [ ] API v1 with explicit routes, versioning, token auth, scopes/rate limits, and OpenAPI
  documentation.
- [ ] Read endpoints for forums, threads, posts, members, and stats.
- [ ] Write endpoints for posting/editing with the same permission checks as the UI.
- [ ] Webhooks or an event/hook system for plugins. Optional features such as polls should
  live here rather than in the core.
- [ ] Web push/email notification channel.

### Themes and UX

- [x] Redesign the offline page as a standalone theme-aware card with no header/footer chrome and
  a context-aware action (staff login, view forum, or log out).
- [ ] Redesign the installation wizard to match the default theme layout and styling.
- [x] Pin the admin panel to the default theme so custom forum themes cannot break its
  templates, header, footer, or assets.
- [ ] Build a dedicated admin theme/layout independent of the default theme.
- [x] Keep admin dashboard and shared admin controls in templates (stat cards, charts, badges,
  options, buttons, rank/user controls) so admin themes can override them.
- [x] Theme manager: upload a theme ZIP, create a new theme by copying the default theme, preview
  it in the current admin session, activate it, and delete non-active themes. Includes metadata
  cards, screenshot support, template fallback, theme-relative `{theme_url}` assets, and archive
  path/extension validation.
- [ ] Child themes and a documented template variable reference.
- [x] Consistent dark/light/auto handling: removed the remaining hardcoded
  `data-theme="dark"` layouts so every page uses the shared theme switcher.
- [x] Responsive pass for mobile and tablet: header/nav wrapping, index forum rows,
  forum/subforum thread rows, thread/post author headers, pagination, viewing chips, and
  login/register spacing at 1000px, 900px, 760px, and 480px breakpoints.
- [ ] Accessibility pass: landmarks, labels, focus states, contrast, keyboard navigation.
- [ ] Loading, empty, and error states for every page.
- [ ] i18n/locale files, timezone-aware dates, and RTL support.
- [ ] Replace hardcoded English strings in pages/widgets/templates.

---

## Deferred — low priority

Not needed for the first public release. Revisit these after the core moderation and UX
work is finished.

- [ ] Account settings for timezone and notification preferences.
- [ ] Account deletion with explicit content reassignment or removal rules.
- [ ] Audit log retention/pruning and CSV export.
- [ ] Optional server-side draft sync across devices (drafts are currently browser-local).

---

## P3 — Developer experience and operations

### Tooling

- [ ] Add dependency-free test scripts for `PasswordUtils`, `EmailUtils`, `UsernameUtils`,
  `RelativeTime`, `Permissions`, `Template`, and `PaginationWidget`.
- [ ] Add a template icon audit that verifies every `ti-*` class exists in the pinned Tabler
  release.
- [ ] Add dependency-free integration scripts for installer, login, permissions, routing, and
  API auth.
- [ ] Add GitHub Actions to lint on PHP 8.0-8.3 and run the smoke scripts against MySQL/MariaDB.
- [ ] Add `config.example.php` (also listed under good first issues) and optional `.env`
  support using `getenv()`, without extra libraries.
- [ ] Add a small logging helper and an error handler that renders a friendly 500 page.
- [ ] Add database backup/restore and health-check commands.
- [ ] Add a CLI installer for automated deployments.
- [ ] Add `CHANGELOG.md`, semantic version tags, and release notes.

### Code quality

- [ ] Add `declare(strict_types=1);` and consistent typing across classes.
- [ ] Normalize code style (tabs vs spaces, brace placement, `static public` vs
  `public static`).
- [ ] Remove unused imports and dead references (for example `use App\BB;` in
  `Template.php` and `use App\Classes\Avatar;` in `ForumPage.php`).
- [ ] Add structured request/response objects instead of passing `$_GET`, `$_POST`, and
  `$_SESSION` through page constructors.
- [ ] Extract a router and shared URL validation; the `UrlHandler()` logic is duplicated in
  every page.
- [ ] Add `NotFound`/`Forbidden`/`BadRequest` HTTP exceptions and map them centrally; the 404
  path exists, but 403 responses are still hand-rolled in several pages.
- [ ] Add pagination and query-count tests; expose `Database::$query_count` in debug mode.
- [ ] Add validation for struct construction (reject unknown keys, avoid dynamic properties
  on PHP 8.2+).

### Repository hygiene

- [ ] Extend `.gitignore` with `.env`, IDE/OS files, and log files (avatars, install
  controls, and `dumps/` are already ignored).
- [ ] Add issue and pull-request templates.
- [ ] Document the database schema (ER diagram) and template variables.
- [ ] Add screenshots or a short GIF to the README.
- [x] Keep the README feature list honest: the README now reflects the implemented features,
  pending work, dependency-free constraint, and current installation flow.
- [ ] Remove the unused `-m` README updater from `cntlines.py`; the README no longer includes
  a generated line-count marker.
- [ ] Add a small script-based task runner for lint, test, and line-count commands.

---

## Good first issues

- [x] Replace Toastify with the local `spell_alert` notification library.
- [x] Remove the "CSS ELEMENTS" link from the production navigation.
- [x] Fix backslashes in `AvatarUtils`, `footer/end.html`, and `admin.html`.
- [x] Fix `Template::AddEntries()` count comparison and initialize arrays.
- [x] Fix `Step_5` `strlen($value <= 0)` validation.
- [x] Fix `RankController::Create()` default flags (`0000000000` is octal zero; use `0`).
- [x] Remove the leftover `{top_stats}` placeholder.
- [ ] Add `config.example.php`.
- [x] Replace the four "Test" footer links.
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
