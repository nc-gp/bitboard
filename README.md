<h1 align="center">
  <img src="https://i.imgur.com/COz9BVy.png" alt="Bitboard logo">
  <br>
  BitBoard
</h1>

<div align="center">
  <img src="https://img.shields.io/badge/License-GPL_3.0-green" alt="License: GPL 3.0" />
  <img src="https://img.shields.io/badge/PHP-8.0%2B-blue" alt="PHP 8.0+" />
  <img src="https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange" alt="MySQL / MariaDB" />
  <img src="https://img.shields.io/badge/Status-early%20development-red" alt="Early development" />
</div>

<h4 align="center">A lightweight, self-hosted forum engine written in PHP.</h4>

> **BitBoard is in early development.** It is not production-ready yet. Expect unfinished
> features, breaking changes, and missing security hardening. See the
> [roadmap](#roadmap) and [TODO.md](TODO.md) for what is being worked on.

<p align="center">
  <a href="#features">Features</a> •
  <a href="#requirements">Requirements</a> •
  <a href="#installation">Installation</a> •
  <a href="#project-structure">Project Structure</a> •
  <a href="#roadmap">Roadmap</a> •
  <a href="#contributing">Contributing</a> •
  <a href="#license">License</a>
</p>

## Features

### Working today

- Six-step web installer (database, admin account, forum settings).
- Page/controller/widget/template architecture with a bundled default theme.
- Categories → forums → subforums browsing.
- Thread and post viewing with relative timestamps.
- User login/logout with bcrypt password hashes, username or email login, and bitmask-based
  ranks/permissions.
- Members list with online/offline state.
- Admin panel shell with forum settings and prefix management (edit/delete).
- Theme system with the bundled `default` theme and light/dark/auto switcher.
- Offline mode, force-login mode, banned page, and a 404 page.
- Output escaping for user-controlled text and allowlist sanitization for rich thread/post HTML.

### Incomplete or not implemented yet

- **Admin JSON API** — session auth, CSRF, route allowlisting, granular permission checks,
  and the settings and prefix edit/delete endpoints work. There is no prefix creation UI yet.
- **Registration** — the UI links to `/register`, but the page does not exist yet.
- **Creating, replying to, editing, or deleting threads/posts** — no write UI yet.
- **Profiles** — `ProfilePage` renders an empty template and does not load a user.
- **Account settings** — the user menu links to `/settings`, which is not implemented.
- **Admin CRUD** — categories, forums, subforums, users, and ranks are not manageable yet.
- **Prefix creation** — existing custom prefixes can be edited/deleted, but there is no create UI yet.
- **Likes, reports, moderation tools, search, avatars/uploads.**
- **Email verification and password reset.**
- **Login rate limiting and session idle/absolute timeouts.**
- **Tests, CI, Composer, migrations, and a documented public API.**

## Requirements

| Component | Version / notes |
| --- | --- |
| PHP | 8.0+ (8.1+ recommended), with the `mysqli` extension |
| Database | MySQL 5.7+ or MariaDB 10.2+ |
| Web server | Apache 2.4+ with `mod_rewrite`, or Nginx with an equivalent rewrite rule |
| Filesystem | Write access to `app/` for `config.php` and the installer state files |

### Platform note

BitBoard runs on Windows and Linux. The autoloader maps namespaces to directories with
forward slashes, and `UrlManager::GetPath()` returns a normalized base URL with exactly one
trailing slash, so root and subdirectory installs both work. Apache with `mod_rewrite` is the
supported setup.

## Installation

1. Download or clone the repository into your web root.
2. Make sure Apache has `AllowOverride All` (or the equivalent) so `.htaccess` is honored.
3. Make `app/` writable by the web server. The installer writes `app/config.php` and
   `app/install/lock` / `app/install/step` there.
4. Open the site in your browser and follow the six installer steps.
5. When installation finishes, the installer creates `app/install/lock`.
6. Tighten permissions again and keep `app/` out of public reach if your server does not
   honor the bundled `app/.htaccess`.

The installer also creates a default category, forum, welcome thread, three ranks, two
prefixes, and the `BitBot` system account.

### Reinstalling

Delete the following files and reload the site:

```text
app/install/lock
app/install/step
app/config.php
```

Only do this on a development installation. Deleting the lock does **not** drop the
database, so existing tables and the seeded rows may conflict with a fresh install.

### Manual configuration

The installer generates `app/config.php`. If you prefer to create it by hand:

```php
<?php

$config['host'] = 'localhost';
$config['user'] = 'bitboard';
$config['pass'] = 'change-me';
$config['name'] = 'bitboard';
```

`app/config.php` is git-ignored. Never commit real credentials.

### Nginx

Apache is the supported server. If you use Nginx, add rewrites equivalent to `.htaccess`:

```nginx
location / {
    try_files $uri $uri/ @bitboard;
}

location @bitboard {
    # Apache passes the action without a leading slash; match that behavior.
    rewrite ^/(.*)$ /index.php?action=$1 last;
}

location ^~ /api/ {
    # ApiMain expects the "api/" prefix in data, for example data=api/settings
    # or data=api/prefixes/edit/3.
    rewrite ^/api/(.*)$ /api.php?data=api/$1 last;
}

location ^~ /app/ {
    deny all;
}
```

## Project Structure

```text
index.php                 Front controller (all forum pages)
api.php                   JSON API front controller
auto_loader.php           Class autoloader (PSR-4-style prefix mapping)
.htaccess                 Pretty URLs and API routing
app/
  BitBoard.php            Bootstrap: install check, config, DB, routing
  Classes/                Database, Template, permissions, sessions, utilities
  forum/
    controllers/          Data access / domain helpers
    pages/                Page classes rendered by PageFactory
    structs/              Lightweight data objects
    widgets/              Reusable page fragments (header, footer, pagination…)
  install/                Web installer steps and HTML templates
  interfaces/             Page and installer step contracts
Api/
  Classes/                API bootstrap, response, status codes
  POST/                   POST endpoints (settings, prefixes)
assets/                   Global CSS/JS and installer assets
themes/default/           Templates, styles, and images for the default theme
```

### Request flow

```text
.htaccess ──> index.php ──> BitBoard::Run()
                               ├─ install not finished? → App\Install\Setup
                               ├─ load app/config.php + settings
                               ├─ session / online / banned checks
                               └─ PageFactory → App\Forum\Pages\*Page::Do()
                                                    └─ Template + widgets → HTML
```

`api.php` follows a parallel path through `Api\Classes\ApiMain` and `Api\POST\*`, requiring
a logged-in session with `ADMIN_PANEL_ACCESS`.

## Roadmap

The full, file-level backlog lives in [TODO.md](TODO.md). The short version:

### Now — correctness and security

- [x] Replace the autoloader with PSR-4-style mapping and use forward slashes in URLs/paths (Linux support).
- [x] Fix the page lifecycle so pages are not rendered twice (`PageFactory` + `IndexPage`).
- [x] Add CSRF protection to login and admin API requests.
- [x] Escape user-controlled output and sanitize rich post content.
- [x] Harden sessions: regenerate ID on login, cookie flags, proper logout.
- [ ] Add an idle/absolute session timeout.
- [x] Fix installer validation and escape values written to `app/config.php`.
- [x] Fix the API database connection.
- [x] Validate API route/class names against an explicit allowlist.
- [x] Add granular API permissions and finish settings/prefix validation.
- [ ] Fix pagination bugs (threads, members, subforums, previous pages).
- [x] Restore notifications; remove references to missing Toastify assets.
- [x] Use generic login errors and support username or email login; remove the ignored "remember me" option.
- [ ] Add login rate limiting and failure logging.

### Next — a usable forum

- [ ] Registration, account settings, and real user profiles.
- [ ] Create/reply/edit/delete threads and posts.
- [ ] Likes for threads and posts (there is already a TODO in `ThreadPage`).
- [ ] Admin CRUD for categories, forums, subforums, prefixes, users, and ranks.
- [ ] Search, moderation tools (pin/lock/delete), and reports.
- [ ] Avatar uploads and profile customization.
- [ ] Email verification and password reset.
- [ ] Stable, documented API (v1) with token auth.

### Later — polish and ecosystem

- [ ] Composer, PHPUnit, PHPStan/Psalm, PHP-CS-Fixer.
- [ ] GitHub Actions CI (lint + tests) and a Docker development environment.
- [ ] Database migrations instead of installer-only schema creation.
- [ ] i18n, timezones, accessibility pass, and SEO metadata.
- [ ] Theme manager and an optional "Powered by BitBoard" credit setting.
- [ ] Plugin/hook system and a public extension API.

## Contributing

Contributions are welcome. To keep changes reviewable:

1. Pick an item from [TODO.md](TODO.md) or open an issue to discuss a new feature.
2. Fork the repository and create a focused branch.
3. Follow the existing page/controller/widget/template structure and code style.
4. Test manually on a fresh installation and include before/after screenshots for UI changes.
5. Open a pull request describing the problem, the change, and how you tested it.

There is no automated test suite yet, so manual verification steps in pull requests are
especially important. Good first issues are marked in [TODO.md](TODO.md).

## Security

BitBoard is **not production-ready**. Do not expose it to untrusted users until the P0 items
in [TODO.md](TODO.md) are resolved.

Please report security issues privately through
[GitHub Security Advisories](https://github.com/bit-paper/bitboard/security/advisories/new)
instead of opening a public issue with exploit details.

## Credits

<a href="https://github.com/bit-paper/bitboard/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=bit-paper/bitboard" alt="Contributors" />
</a>

Built with PHP, MySQLi, and a small vanilla JavaScript layer. Third-party front-end
resources include Tabler Icons and Microtip.

This README counts roughly **8,052 lines across 175 counted source files**. The count comes
from `python cntlines.py -v`; re-run it after large changes. (The script still needs a small
update before its `-m` README updater matches this layout — see [TODO.md](TODO.md).)

## License

BitBoard is licensed under the [GNU General Public License v3.0](LICENSE).

## Note

BitBoard is currently in the early stages of development. APIs, database schemas, template
variables, and directory layout may change without notice.
