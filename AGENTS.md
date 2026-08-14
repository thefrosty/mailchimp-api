# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `drewm/mailchimp-api` — a minimal-abstraction PHP wrapper for the MailChimp API v3. It uses raw cURL under the hood with very little encapsulation, so the code maps directly to the official MailChimp API docs.

**Namespace:** `DrewM\MailChimp` (PSR-4, `src/`)
**Minimum PHP:** 8.3
**License:** MIT

## Source Structure

```
src/
  MailChimp.php   # Core HTTP client — manages API key, endpoint discovery, all HTTP verbs (get/post/put/patch/delete), SSL config, error handling
  Batch.php       # Batch operations — queues multiple HTTP requests and executes them via the `/batches` endpoint
  Webhook.php     # Webhook handler — subscribes callbacks to MailChimp webhook events (subscribe, unsubscribe, campaign, etc.)
```

`MailChimp` is the central class. It parses the data center from the API key, constructs the endpoint URL, and exposes `get()`, `post()`, `put()`, `patch()`, `delete()` methods that all funnel through a private `makeRequest()` using cURL. The `Batch` class holds a reference to the parent `MailChimp` instance and queues operations to be submitted as a single batch. `Webhook` is a static utility class with `subscribe()` and `receive()` methods for handling incoming MailChimp webhook POST data.

## Development Commands

All commands via Composer scripts in `composer.json`:

| Command | Description |
|---|---|
| `composer phpcs` | Run PHPCS linter on `src/` using `phpcs-ruleset.xml` (PSR-12 based) |
| `composer phpcs:fix` | Auto-fix PHPCS issues via phpabf |
| `composer phpunit` | Run PHPUnit test suite (`tests/`) with colors, sorted by defects |
| `composer phpunit:coverage` | Run tests with HTML coverage report + enforce minimum coverage threshold via `tests/clover-results.php` |
| `composer refactor` | Run Rector auto-refactoring on `src/` and `tests/` (PHP 8.3 level + InlineConstructorDefaultToProperty) |
| `composer refactor:dry` | Dry-run mode for Rector (preview changes without applying) |

### Running tests

Tests require the `MC_API_KEY` environment variable (loaded from `.env.test` via the bootstrap). To run a single test file:

```bash
vendor/bin/phpunit tests/MailChimpTest.php
vendor/bin/phpunit tests/BatchTest.php
```

The phpunit config uses `executionOrder="depends,defects"`, requires coverage metadata (`requireCoverageMetadata`), and enforces strict coverage (`beStrictAboutCoverageMetadata`, `failOnWarning`). All source in `src/` is subject to coverage.

## Architecture Notes

- **No abstraction layer** — methods like `get('lists')` map directly to API paths `https://{dc}.api.mailchimp.com/3.0/lists`. This is by design.
- **cURL-based HTTP** — `MailChimp::makeRequest()` handles all HTTP verbs, sets `Authorization: apikey {key}` headers, uses `application/vnd.api+json` content type, and parses the HTTP status from curl info.
- **SSL verification** is on by default (`$verify_ssl = true`). Do not disable without cert path diagnosis.
- **Error handling** — use `$MailChimp->getLastError()` for the last error string, `$MailChimp->getLastResponse()` for response headers+body, `$MailChimp->getLastRequest()` for the outgoing request details.
- **Batch workflow** — create via `$MailChimp->newBatch($id)`, queue operations with `get/post/put/patch/delete($opId, $method, $args)`, execute with `->execute()`, check status with `->checkStatus($batchId)`.
- **Webhook workflow** — subscribe callbacks via `Webhook::subscribe($event, $callback)`, receive/process incoming webhooks via `Webhook::receive()`.

## CI/CD

GitHub Actions (`/.github/workflows/main.yml`) runs on pull requests (excluded from `release` branches) with two jobs on Ubuntu, PHP 8.3:
1. **run-phpcs** — uses custom `thefrosty/ci-setup` action for environment setup, runs `composer phpcs`, posts results to PR via `cs2pr`.
2. **run-phpunit** — same setup action, runs `composer phpunit:coverage` (enforces code coverage threshold).

Scrutinizer QA (`/.scrutinizer.yml`) filters to `src/` only.

## Configuration Files

- `composer.json` — project metadata, dependencies (php 8.3+, curl, json), dev tools (phpunit 11, phpcs, rector, phpdotenv), scripts
- `phpunit.xml` — test suite config, bootstrap, coverage output (clover + html), strict mode flags
- `phpcs-ruleset.xml` — PSR-12 based linting rules with a few exclusions
- `rector.php` — Rector config (PHP 8.3 + InlineConstructorDefaultToPropertyRector)
- `.env.test` — placeholder env vars (`MC_API_KEY`, `MC_LIST_ID`)

## Testing Strategy

Tests use real API calls against MailChimp (with a fake API key), relying on failure states to verify error handling paths. Use PHPUnit's `#[CoversClass]` attribute to declare class-level coverage. The `BatchTest` uses ReflectionClass to inspect private properties.

### Conventions
- Test methods are prefixed with `test` and use PHPDoc annotations for descriptions.
- All source code under `src/` must have test coverage.
