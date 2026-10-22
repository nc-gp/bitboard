# BitBoard TODO

Prioritized backlog for BitBoard. This list is based on a review of the current `main`
branch. Items are ordered by risk and dependency: finish **P0** before exposing the forum to
real users, then work through **P1** and **P2**.

Legend: `P0` blocker/security · `P1` correctness · `P2` features · `P3` developer experience.
Items marked **good first issue** are small and self-contained.

---

## P0 — Security and blockers

### Platform and bootstrap

- [ ] **Replace the autoloader with a PSR-4 implementation.** `auto_loader.php` builds paths
  from the raw namespaced class name, which only works with Windows path separators.
  On Linux the first `App\BitBoard` lookup fails. Map `App\` → `app/`, `Api\` → `Api/`, and
  convert `\` to `/`. Add Composer autoloading later.
- [ ] **Remove remaining backslash paths in URLs.** See `app/Classes/AvatarUtils.php:23-25`,
  `themes/default/templates/footer/end.html:11`, and
  `themes/default/templates/admin/admin.html:50`. Browsers and Linux servers expect `/`.
- [ ] **Create the missing avatar directory or stop linking to it.**
  `AvatarUtils::GetPath()` returns `assets/images/avatars/...`, but `assets/images` does not
  exist. Use a theme fallback and validate the stored filename.
- [ ] **Fix the page lifecycle.** `PageFactory::CreatePage()` calls `Do()`, while
  `IndexPage::__construct()` already calls `Do()`, so `/index` renders the page twice.
  Standardize on one contract: constructor prepares, `Do()` renders, factory calls it once.
- [ ] **Guard the empty-URL path in `BitBoard::Do()`.** `app/BitBoard.php:60-65` reads
  `$SplitedURL[0]` when the array is empty. Restructure the offline/force-login checks and
  add tests for `/`, `/offline`, and `/login`.
- [ ] **Fix `BannedPage` for guests.** `BannedPage.php:23` dereferences
  `$_SESSION['bitboard_user']` without checking `SessionManager::IsLogged()`. Guests should
  be redirected home/login, not crash.
- [ ] **Fix the installer validation bug.** In `Step_5.php:43-67`,
  `strlen($forumName <= 0)` evaluates a boolean, so empty/short values are not rejected
  reliably. Use `empty(trim($value))` and `mb_strlen($value) < 2`.
- [ ] **Escape values written to `app/config.php`.** `Step_3.php:78-84` concatenates raw
  `$_POST` values into PHP source. A quote in the password breaks the file and can inject
  code. Use `var_export()` and validate the database name against
  `^[A-Za-z0-9_]+$`.
- [ ] **Stop logging the session during installation.** `Setup.php:17` prints `$_SESSION`
  to the browser console. Remove it before release.

### Authentication, sessions, and authorization

- [ ] **Add CSRF protection** to the login form, admin settings, and every `Api/POST/*`
  endpoint. Use a per-session token, verify it server-side, and reject non-matching requests.
- [ ] **Harden sessions.**
  - Regenerate the session ID after login (`session_regenerate_id(true)`).
  - Set `HttpOnly`, `SameSite=Lax/Strict`, and `Secure` (when HTTPS) cookie flags before
    `session_start()`.
  - Destroy the session and cookie on logout; `SessionManager::Delete()` currently only
    unsets two keys.
  - Add an idle/absolute timeout.
- [ ] **Add login rate limiting and generic errors.** `LoginPage` reveals whether a username
  exists and allows unlimited password attempts. Use one generic error message, per-IP and
  per-account throttling, and log failures.
- [ ] **Finish or remove the "remember me" option.** The login form has a checkbox that is
  ignored. Implement hashed persistent tokens or remove the checkbox.
- [ ] **Add server-side login validation.** The form advertises email login but
  `AccountController::GetAccountByName()` only matches usernames. Support email, trim input,
  and test both.
- [ ] **Enforce locked forum/subforum permissions.** `is_locked` exists on forums and
  subforums but is never checked. Apply `Permissions::VIEWING_FORUM_LOCKED` in
  `ForumPage`, `SubforumPage`, `ThreadPage`, and the API.
- [ ] **Use granular API permissions.** `ApiMain::IsAuthorizated()` only checks
  `ADMIN_PANEL_ACCESS`; settings updates should require `MANAGING_SETTINGS`, prefix changes
  the matching permission, and so on.
- [ ] **Remove the installer from the public path after setup.** `app/install/lock` is the
  only guard. Ensure the installer directory is not writable by the app in production and
  document the lock/step files as sensitive.

### Output, input, and API

- [ ] **Introduce an escaping strategy for templates.** The template engine performs raw
  `str_replace`, so every value is inserted as HTML. At minimum:
  - Escape usernames, thread titles, forum/category names, and descriptions with
    `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` at the rendering boundary.
  - Keep `rank_format` as an explicit, admin-only HTML field.
  - Sanitize rich post content with an allowlist (for example HTMLPurifier) or render
    Markdown after escaping.
- [ ] **Fix the API database connection.** `ApiMain.php:38` passes `$config['name']` as the
  fourth `Database` argument, but that argument is the charset. The database is never
  selected afterwards. Construct with the charset or call `SelectDatabase($config['name'])`.
- [ ] **Validate API routes.** `ApiMain::HandleURL()` derives a class name from
  `$_GET['data']` with no allowlist. Restrict endpoints to explicit route names and reject
  anything else before `class_exists()`/autoloading.
- [ ] **Fix `Api/POST/Prefixes.php`.** The file declares `class Settings`, so
  `ApiMain` can never find `Api\POST\Prefixes`. Rename the class and implement edit/delete
  with authorization, validation, and prepared statements.
- [ ] **Tighten `Api/POST/Settings.php`.** `empty($data['forumonline'])` rejects a valid empty
  offline message; there are no length/type checks; and all responses return HTTP 200.
  Validate input, use `isset()`, set real HTTP status codes, and return structured errors.
- [ ] **Restrict CORS and response details.** `api.php` sends
  `Access-Control-Allow-Origin: *`; remove it unless a cross-origin client is supported.
  Do not leak exception messages or SQL errors to clients.
- [ ] **Remove `eval()` from the modal helper.** `assets/javascripts/main.js:132` executes the
  `onconfirm` attribute. Accept a callback or dispatch a custom event instead.
- [ ] **Escape console output.** `Console::Log()`/`Console::Error()` inject raw strings into
  `<script>`; an exception message containing a quote breaks the page. Use
  `json_encode()` or remove debug output entirely.

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
- [ ] **Fix `/index` duplicating output** (also listed under P0) and add a routing test.
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

- [ ] **Fix `Template` initialization and `AddEntries()`.** `$variables`/`$replaces` are
  uninitialized until the first `AddEntry()`; `AddEntries()` compares an array to an int
  (`Template.php:85`), so it always throws. Initialize to `[]` and compare `count()`.
- [ ] **Simplify `Template::Load()`.** Use `file_get_contents()` with an explicit error
  instead of `fopen`/`flock`/`filesize`; handle empty files and missing templates.
- [ ] **Move `{server_url}` replacement before page content injection.** Currently global
  widgets and page content both rely on a second replacement pass. Document or centralize
  this to avoid double-replacement surprises.
- [ ] **Append the global JS inside `<body>`.** `PageBase::CommonSetup()` appends
  `footer/end.html` after `</html>`, producing invalid markup. Add a `{scripts}` placeholder
  to every layout, or append before the closing body tag.
- [ ] **Restore notifications.** Templates call `toast()`, but `main.js` no longer defines it;
  `assets/stylesheets/toastify.css` and `assets/javascripts/toastify.js` are missing while
  `head.html` and `admin.html` still reference them. Either restore Toastify or migrate the
  admin pages to `spell_alert`.
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
- [ ] Finish the prefix edit/delete API and add validation.

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

- [ ] Remove the broken Toastify includes and switch admin notifications to `spell_alert`.
- [ ] Remove the "CSS ELEMENTS" link from the production navigation.
- [ ] Fix backslashes in `AvatarUtils`, `footer/end.html`, and `admin.html`.
- [ ] Fix `Template::AddEntries()` count comparison and initialize arrays.
- [ ] Fix `Step_5` `strlen($value <= 0)` validation.
- [ ] Fix `RankController::Create()` default flags (`0000000000` is octal zero; use `0`).
- [ ] Remove the leftover `{top_stats}` placeholder.
- [ ] Add `config.example.php`.
- [ ] Replace the four "Test" footer links.
- [ ] Remove `Console::Log()` debug calls from `ThreadPage` and `Setup`.

---

## Suggested release gate

Before calling a release "stable":

- [ ] All P0 items closed, with manual security review of login and admin API.
- [ ] Registration, login, logout, thread creation, replies, and moderation work end-to-end.
- [ ] Automated tests pass on PHP 8.0, 8.1, 8.2, and 8.3 with MySQL and MariaDB.
- [ ] Fresh install and upgrade migration both verified on Linux and Windows.
- [ ] No PHP warnings/notices with `error_reporting(E_ALL)`.
- [ ] README, installation guide, and API docs match the shipped behavior.
