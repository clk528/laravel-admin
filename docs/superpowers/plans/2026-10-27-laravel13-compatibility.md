# Laravel 13 Compatibility Implementation Plan

**Goal:** Upgrade clk528/laravel-admin for the consuming Laravel 13 application.

**Architecture:** Preserve Encore\\Admin APIs and existing configuration. Replace removed framework APIs with native schema inspection. Test using Testbench and SQLite rather than the consuming application's database.

**Tech Stack:** PHP 8.3+, Laravel 13, Symfony 7.4/8, Testbench 11, PHPUnit 11/12.

## Constraints

- Sync verified changes to the local package loaded by backend's junction.
- Preserve existing backend changes and all business data.
- Keep old BrowserKit tests as historical fixtures; run isolated compatibility tests by default.
- No publishing, backend database migrations, or Git commits are required.

## Tasks

- [x] Update Composer and test dependencies and install the isolated test environment.
- [x] Reproduce schema generation failures (prefixed tables, types, escaped defaults); replace Doctrine access.
- [x] Reproduce PJAX failures with Symfony responses and missing exceptions; fix response handling and entity decoding.
- [x] Round-trip notification helpers through JSON sessions; accept arrays and legacy MessageBag payloads in views.
- [x] Load all classes on PHP 8.5; replace implicit nullable parameters and fix actual runtime failures.
- [x] Exercise migrations, authentication, permissions, menus, grids, forms, details, uploads and pagination on SQLite.
- [x] Validate dependencies, lint, run package/backend checks and document limitations.

## Validation

Run Composer validation, package PHPUnit with `--display-all-issues`, backend unit tests and `artisan route:list --path=admin`.

Validated on PHP 8.5.12 / Laravel 13.38.0. Backend's full existing suite passes (16 tests / 28 assertions), and 247 admin routes register. All 312 PHP files in src, config, database and the compatibility suite pass syntax/deprecation lint. The consuming project's composer.json and AppServiceProvider.php retain their pre-task hashes. Its lock file updates only the local admin package and DomCrawler and removes three obsolete Doctrine packages.
