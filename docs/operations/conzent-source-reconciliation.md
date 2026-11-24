# Conzent source reconciliation

## Scope and acceptance

Reconcile the deployed OCI corrections onto Coolify's configured source revision
`de3b40f5fb09f6c01460b6531cc00f047c6dcd84`, preserving the existing ARM64 scanner
and Resend implementation. This is a source candidate only. It does not authorize
an OCI deployment, database migration, override deletion or image retirement.

Work proceeds in three slices: image/context and shared generated files; PHP
company lookup, additive schema and request handlers; consent reopen/localization.
Acceptance requires narrow source changes, regression tests, PHP/JavaScript syntax,
Twig parsing, Compose rendering and an independent leader review. Test commands
and observed results are recorded below when verification is complete.

## Existing corrections

- Pin the PHP base to the immutable PHP 8.3.36 Alpine 3.24 multi-platform index.
- Exclude environment/credential files, generated Compose files, runtime/site data,
  backups and local dependencies from the image build context.
- Mount the logical `app-sites-data` volume read-only into Nginx. PHP and the workers
  already share that volume; Nginx must see the generated consent scripts too.
- Inject the existing user repository into the self-hosted plan repository so
  company profiles remain available without the optional billing module.
- Include the official additive `suspended_reason` migration. It does not drop the
  column on rollback because cloud installations already own that column.
- Read cookie/category route IDs from the router's direct `id` request attribute.
- Authenticate script generation through the existing `user` request attribute and
  verify site ownership before generation.
- Reopen the existing consent box instead of initializing a duplicate banner.
- Preserve the deployed Romanian/English retention markup in four layouts and CSS.

The deployed Nginx `X-Conzent-Instance: oci` diagnostic header is environment
specific and is deliberately omitted from generic source. Generated Coolify
Compose, scanner secret ARG additions, credentials and site data are excluded.

## External overrides and future retirement gate

All five live overrides and the running `conzent-php:8.3.36-20261022-r2` image stay
in place. A source merge by itself does not replace them:

| Override | Source replacement | Retirement proof |
| --- | --- | --- |
| `redirect-fix.yml` | `NullPlanRepository` company lookup | New image includes the fix; company/profile UI works in every affected PHP service. |
| `php-8.3.36.yml` | Immutable PHP base and embedded source fixes | Reviewed image digest runs PHP 8.3.36 across all four services; no fallback to floating PHP. |
| `sites-data.yml` | Logical Nginx `app-sites-data` mount | Rendered Coolify definition preserves the actual existing volume identity, read-only in Nginx; both public scripts load. |
| `route-handlers.yml` | Four cookie/category handlers plus owned-site script generation | Authenticated edits and script generation succeed; unauthenticated and cross-account requests fail. |
| `layout-localization.yml` | Four layouts plus banner CSS | Both generated scripts show the correct language; reject/reopen/accept/withdrawal work with one banner. |

Before a future redeploy, merge/review the candidate in the authoritative Coolify
branch, build a traceable ARM64 image, compare its embedded files to these fixes,
and inspect the generated deployment definition without exposing environment
values. Confirm existing DB/Redis/runtime/sites-data volume identities, networks,
all PHP service images and the custom start wrapper. Coolify may overwrite its
retained checkout before invoking that wrapper.

Retire each override only after its replacement image/configuration passes the
corresponding checks and restart persistence. Update the external wrapper and
Coolify custom-start command together under a separately reviewed deployment;
keep an exact rollback image/configuration available. Do not infer that the image
has replaced a bind-mounted fix while that old mount is still masking its file.
Retain the current image until all gates pass. Never copy generated deployment
files or secret-bearing scanner build arguments into this repository.

## Source verification — 2026-10-23

Local verification used task-owned `--rm` Podman containers running PHP 8.3.36
with no network and a read-only candidate mount. Locked Composer development
dependencies were installed into `/tmp/conzent-reconcile-deps-20261023`; they are
not part of this source candidate and no lockfile was changed. No OCI state was
changed. PHP/Composer executables are absent from the host.

```sh
# With locked development dependencies available at vendor/:
php vendor/bin/phpunit --testsuite Unit --no-coverage --cache-directory /tmp/phpunit-cache
node --check resources/consent/js/conzent.script.js
node --test --test-isolation=none tests/js/consent-reinit.test.mjs
podman-compose -f docker-compose.yml config
git diff --check
```

- Regression-first PHP run reproduced the original company lookup, route ID and
  generation authentication failures. After reconciliation, the complete unit
  suite passed: 27 tests / 113 assertions, including the unchanged Resend tests.
- All modern consent layouts parsed in Twig; both retention languages remained
  present in each of the four changed layouts.
- The standalone JavaScript regression passed: reinit calls the existing box-open
  function once and does not invoke initialization. JavaScript syntax passed.
- PHP 8.3.36 syntax checks passed for the affected handlers, repository, migration
  and new PHP tests.
- Local Podman Compose rendering passed; a parsed-definition assertion confirmed
  the shared sites-data volume is writable in app and read-only in Nginx.
- Whitespace/diff checks passed. The scanner Dockerfile and mail implementation
  are unchanged from the configured source revision.

This proves source behavior only. A fresh ARM64 image build, authenticated browser
acceptance, database migration execution, Coolify deployment/restart persistence
and override retirement remain future deployment gates. No image was built or
published, and no migration was executed.
