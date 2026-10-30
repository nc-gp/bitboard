<h1 align="center">
  <img src="https://i.imgur.com/COz9BVy.png" alt="Bitboard logo">
  <br>
  BitBoard
</h1>

<div align="center">
  <img src="https://img.shields.io/badge/License-GPL_3.0-green" alt="License: GPL 3.0" />
  <img src="https://img.shields.io/badge/PHP-8.0%2B-blue" alt="PHP 8.0+" />
  <img src="https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange" alt="MySQL / MariaDB" />
  <img src="https://img.shields.io/badge/Dependencies-none-brightgreen" alt="No dependencies" />
  <img src="https://img.shields.io/badge/Status-early%20development-red" alt="Early development" />
</div>

<h4 align="center">A lightweight, self-hosted forum engine in plain PHP.</h4>

> **BitBoard is in early development.** It is not production-ready yet. Expect unfinished
> features, breaking changes, and missing hardening. See [TODO.md](TODO.md) for the backlog.

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

BitBoard is dependency-free: plain PHP, MySQLi, and vanilla JavaScript. There is no Composer,
npm, or build step. Tabler Icons is the only external front-end asset and is pinned via CDN.

- Six-step web installer (database, admin account, forum settings).
- Categories → forums → subforums, with locked-forum permissions.
- Threads and replies with a Markdown editor, live preview, local drafts, CSRF protection,
  edit history, and permanent deletion.
- Inline fast reply with AJAX append and a normal POST fallback.
- Reactions, quotes, multi-quote, `@mentions`, and a header notification bell.
- Thread subscriptions and forum/subforum watch notifications.
- Per-user read tracking, pagination-preserving last-post links, and “who is viewing”.
- Custom thread prefixes, pin/lock, and move-between-forums moderation.
- Thread and reply reporting with a staff queue (open, resolved, dismissed).
- Search with forum, prefix, author, and date filters.
- Member directory, online list, and public profiles with avatar, bio, and signature.
- Account settings for email, password, bio, signature, and avatar uploads with admin limits.
- Admin panel: dashboard charts, tabbed settings, categories, forums, prefixes, ranks with a
  permission matrix, user management, and an audit log.
- Theme system with the bundled default theme and a light/dark/auto switcher.
- SEO slugs and canonical URLs, OpenGraph/Twitter metadata, `/sitemap.xml`, and `/feed.xml`.
- Responsive layouts for desktop, tablet, and phone.

Email verification, password reset, anti-bot registration, attachments, thread merges/splits,
the public API, and a theme manager are not implemented yet. See [TODO.md](TODO.md) for details.

### Content format

Posts use a safe Markdown subset: headings, bold, italic, strikethrough, inline and fenced code,
quotes, lists, links, and images. Raw HTML is escaped, and the rendered result is sanitized before
it is stored. Drafts stay in the browser’s local storage.

## Requirements

| Component | Version / notes |
| --- | --- |
| PHP | 8.0+ (8.1+ recommended), with the `mysqli` extension |
| Database | MySQL 5.7+ or MariaDB 10.2+ |
| Web server | Apache 2.4+ with `mod_rewrite`, or Nginx with an equivalent rewrite rule |
| Filesystem | Temporary write access to `app/` during installation for `config.php` and installer state files |

BitBoard runs on Windows and Linux. Root and subdirectory installs are both supported.

## Installation

1. Download or clone the repository into your web root. Prefer keeping `app/` outside the
   document root when your host supports it.
2. Enable `.htaccess` (for Apache, `AllowOverride All`).
3. Make `app/` writable by the web server **only during installation**.
4. Open the site in your browser and complete the six installer steps.
5. After installation, remove write access again. The installer writes `app/config.php` and
   `app/install/lock` / `app/install/step`:

   ```bash
   chmod -R a-w app/install
   chmod a-w app/config.php
   ```

6. Keep `app/` out of public reach. The bundled root `.htaccess` blocks `/app/...`; use the
   Nginx rules below or move `app/` outside the document root if your server ignores it.

`app/install/lock` and `app/install/step` are sensitive control files. Do not expose, edit, or
delete them except when intentionally reinstalling on a development copy.

### Reinstalling

Restore write access, delete the installer state and config, then reload the site:

```bash
chmod -R u+w app/install
chmod u+w app/config.php
rm app/install/lock app/install/step app/config.php
```

The database is **not** dropped, so existing tables and seeded rows may conflict with a fresh
install. Only reinstall on a development copy.

### Manual configuration

The installer generates `app/config.php`. To create it by hand:

```php
<?php

$config['host'] = 'localhost';
$config['user'] = 'bitboard';
$config['pass'] = 'change-me';
$config['name'] = 'bitboard';
```

`app/config.php` is git-ignored. Never commit real credentials.

### Nginx

Apache is the supported server. For Nginx, mirror the `.htaccess` behavior:

```nginx
location / {
    try_files $uri $uri/ @bitboard;
}

location @bitboard {
    # Apache passes the action without a leading slash; match that behavior.
    rewrite ^/(.*)$ /index.php?action=$1 last;
}

location ^~ /api/ {
    # ApiMain expects the "api/" prefix in data, e.g. data=api/settings.
    rewrite ^/api/(.*)$ /api.php?data=api/$1 last;
}

location ^~ /app/ {
    deny all;
}
```

## Project Structure

```text
index.php                 Front controller (forum pages)
api.php                   JSON API front controller
auto_loader.php           Dependency-free PSR-4-style class loader
app/
  BitBoard.php            Bootstrap: install check, config, DB, routing
  Classes/                Database, Template, permissions, sessions, utilities
  forum/                  Controllers, pages, structs, and widgets
  install/                Web installer and access guard
  interfaces/             Page and installer step contracts
Api/                      API bootstrap and POST endpoints
assets/                   Global CSS/JS and installer assets
themes/default/           Templates, styles, and images for the default theme
```

## Roadmap

The full backlog lives in [TODO.md](TODO.md). Next up:

- Configurable admin panel path.
- Email verification, password reset, and anti-bot registration.
- Rich editor, attachments, and thread merge/split.
- Public API v1 and an event/hook system.
- Accessibility and i18n passes.

## Contributing

1. Pick an item from [TODO.md](TODO.md) or open an issue first.
2. Keep the project dependency-free: plain PHP, MySQLi, and vanilla JavaScript. Do not add
   Composer or npm packages.
3. Follow the existing controller/page/widget/template structure.
4. Test on a fresh installation and describe the manual verification steps in your pull request.
   There is no automated test suite yet.

## Security

BitBoard is **not production-ready**. Do not expose it to untrusted users until the P0 items in
[TODO.md](TODO.md) are resolved.

Report security issues privately through
[GitHub Security Advisories](https://github.com/bit-paper/bitboard/security/advisories/new)
instead of opening a public issue with exploit details.

## Credits

<a href="https://github.com/bit-paper/bitboard/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=bit-paper/bitboard" alt="Contributors" />
</a>

Built with PHP, MySQLi, and vanilla JavaScript. [Tabler Icons](https://tabler.io/icons) 3.49.0
is the only external front-end asset; tooltips, notifications, and the editor helpers are local.

## License

BitBoard is licensed under the [GNU General Public License v3.0](LICENSE).
