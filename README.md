# Drupal has two access systems, and they can disagree

A throwaway Drupal 11.4 site that shows a private node returning 403 on its own page while RSS, search, taxonomy pages and views list it to anonymous users, and how to fix it for good.

- **Node page:** `hook_node_access()` decides.
- **Listings** (views, search, feeds, entity queries with access checks): only node grants (`hook_node_access_records()` + `hook_node_grants()`) decide. If no module implements grants, core doesn't filter listing queries at all (`web/core/modules/node/src/Hook/NodeDatabaseHooks.php`).

## The story, one tag per stage

| Tag | What changes | `proof.sh /latest` |
|---|---|---|
| `01-bug` | `secret_note` type + `hook_node_access()` (author and staff only) | node 403, LEAK on RSS, search, term page (`/latest` doesn't exist yet: 404) |
| `02-lockdown` | Config hotfix: search off, feed off, term view filtered. Then a new "latest content" view at `/latest` | PASS everywhere except LEAK on `/latest` |
| `03-grants` | Kernel test first, then `NodeGrantsHooks` (grants), post-update rebuild; lockdown undone | PASS everywhere, search and feed back on |
| `04-pitfall` | New private type `secret_invoice`: access hook updated, grants forgotten | invoice LEAKs on search, term page, `/latest` |
| `05-classified` | Unit test fails on any unclassified node type; classify + rebuild | PASS everywhere |

Diff any two stages: `git diff 02-lockdown 03-grants`.

## Setup

Needs [DDEV](https://ddev.com) and Docker. No PHP on the host required.

```sh
git clone <this repo> drupal-access-demo && cd drupal-access-demo
ddev start
ddev composer install
```

`settings.php` isn't committed. DDEV creates it on first start; add this line **above** the `settings.ddev.php` include near the bottom of `web/sites/default/settings.php`:

```php
$settings['config_sync_directory'] = '../config/sync';
```

Install from the committed config, then seed:

```sh
ddev drush site:install --existing-config -y
ddev drush php:script scripts/seed.php   # users alice/bob, 3 secret notes, 1 invoice, 2 public articles
ddev drush cron                          # index for search
bash scripts/proof.sh /latest
```

## Walking through the stages

Walk forward from a fresh install at `01-bug`. After each checkout: clear caches (new hook classes), sync config, rebuild grants, reseed, prove.

```sh
git checkout 01-bug
ddev drush site:install --existing-config -y

# repeat for 01-bug, 02-lockdown, 03-grants, 04-pitfall, 05-classified
git checkout 02-lockdown
ddev drush cr && ddev drush cim -y
ddev drush php:eval '\Drupal::service(\Drupal\node\NodeAccessRebuild::class)->rebuild();'
ddev drush php:script scripts/seed.php && ddev drush cron
bash scripts/proof.sh /latest
```

Going backward doesn't work: `cim` refuses to delete the `secret_invoice` type while invoices exist. Reinstall at the earlier tag instead.

The rebuild matters because grant rows are stored per node and only change on save or rebuild. On a real site the post-updates in `listing_access_demo.post_update.php` do it once.

`proof.sh` requests each URL as anonymous and prints `PASS`, `LEAK` (a secret title appears in the body) or `FAIL` (the node page isn't 403). Extra paths go as arguments.

## Tests

```sh
ddev exec vendor/bin/phpunit                    # both suites
ddev exec vendor/bin/phpunit --testsuite kernel # ListingAccessTest: who lists what
ddev exec vendor/bin/phpunit --testsuite unit   # BundleClassificationTest: every node type is public or private
```

At `02-lockdown` the Kernel test doesn't exist yet; check out `03-grants`'s test onto `02-lockdown` to watch it fail.

## Files worth reading

- `web/modules/custom/listing_access_demo/src/Hook/NodeAccessHooks.php` — the node page rule.
- `web/modules/custom/listing_access_demo/src/Hook/NodeGrantsHooks.php` — the listing rule (~90 lines with docblocks).
- `web/modules/custom/listing_access_demo/tests/` — the Kernel and Unit tests.
- `scripts/seed.php`, `scripts/proof.sh`.

Local demo only: users get random passwords; log in with `ddev drush uli --name=alice`.
