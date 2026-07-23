# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-07-23

### Changed

- Upgraded to Laravel 13 (`laravel/framework` ^13.0, `laravel/tinker` ^3.0). PHP 8.5 is now the minimum.
- Set `serializable_classes` to `false` in `config/cache.php`, so a leaked `APP_KEY` cannot drive an object gadget chain through the cache.
- Upgraded the frontend toolchain to match the Laravel 13 skeleton: Vite 8, `laravel-vite-plugin` 3, Tailwind CSS 4.3.3, DaisyUI 5.7, Alpine.js 3.15.12 and concurrently 10.

### Fixed

- Adding a second batch of files to a share left the uploader stuck on "Processing files…" forever, with the drop zone and the "Create Share Link" button permanently disabled. The uploading state is now cleared by a `files-processed` event dispatched on every batch, instead of a one-off `x-init` that only ran the first time the file list appeared.
- Dropping files on the drop zone showed no upload progress at all, because `uploadMultiple()` was called without progress callbacks.
- Dropping a second folder onto an existing selection replaced the collected relative paths instead of appending them, which shifted every earlier file's path onto the wrong file.

### Removed

- Dropped the unused `axios` dependency and the stale `@rollup/rollup-linux-x64-gnu` optional pin (Vite 8 builds with rolldown).

### Security

- Forced `shell-quote` to a patched release via an npm override, clearing GHSA-395f-4hp3-45gv (quadratic complexity DoS). `npm audit` reports 0 vulnerabilities, down from 3.

## [1.0.1] - 2026-02-25

### Fixed

- ZIP downloads returned 0-byte archives under FrankenPHP. ZipStream writes through `fwrite(php://output)`, which FrankenPHP silently drops; downloads are now built with native `ZipArchive` and served as a file response.
- Docker image was missing the PHP `zip` extension required by `ZipArchive`.
- Stale `bootstrap/cache/*.php` from the build context could load dev-only service providers in the production image.
- `DB_DATABASE` now defaults to `/app/database/database.sqlite` in `docker-compose.yml`, so the `env()` fallback resolves correctly.
- The unlock button on the password-protected share page rendered outside the form and did nothing. Share page action buttons are now consistently full width.

### Removed

- `maennchen/zipstream-php` dependency.

## [1.0.0] - 2026-02-13

### Added

- Initial release.
- File uploading via drag & drop or browse, supporting multiple files and folders with real-time progress.
- Shareable links, one unique link per upload.
- AES-256-GCM encryption at rest, chunked and streaming, with PBKDF2-SHA256 key derivation.
- Optional password protection per share.
- Configurable expiration from 1 hour to 30 days, and per-share download limits.
- ZIP download of all files in a share.
- Hourly auto-cleanup of expired shares and their files.
- Admin dashboard and settings for upload limits, storage quotas and branding.
- Site branding: custom logo, title and description.
- Optional system password gate restricting upload access.
- User authentication (login, registration, password reset, email verification) and TOTP two-factor authentication via Laravel Fortify.
- First-run setup wizard for creating the initial admin account.
- Dark themed UI built with Livewire, Alpine.js, Tailwind CSS and DaisyUI.
- Docker images published to `ghcr.io/surtic86/sealshare`, served by FrankenPHP via Laravel Octane.

[Unreleased]: https://github.com/surtic86/SealShare/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/surtic86/SealShare/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/surtic86/SealShare/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/surtic86/SealShare/releases/tag/v1.0.0
