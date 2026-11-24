# PHP maintenance preparation — 2026-10-22

Source: Conzent OCI v2.7.5, commit `7fe3393c3e450cda5341e5413049a159471f0a44`.
This isolated checkout prepares a local image; deployment and database changes are separate operations.

## PHP image

The shared `base` stage now uses PHP 8.3.36 FPM on Alpine 3.24, pinned to official Docker Hub multi-platform index:

`sha256:81cb692948e6de4275f7e107c50a34e963a440660f2c5b8502d11071f7d30338`

The registry response digest matched SHA-256 of the raw manifest bytes. Its linux/arm64/v8 image is:

`sha256:e7895641feafab60a1897a0a2ed846f23a09a5cb5de530ab37c763bad6f0108b`

Both `dev` and `prod` inherit this base. A local amd64 build proves native application-image construction only; ARM64 execution requires a separate check on the destination architecture. Composer and APK/PECL dependencies retain upstream resolution rules, so pinning PHP alone does not make the complete build reproducible.

Official security release: <https://www.php.net/archive/2026.php#2026-10-21-5>.

## Database compatibility review

There are 80 migration files, including 18 dated after 2026-08-31. The deployed migration ledger must be compared with file names before any execution; date alone is not proof of pending state.

- `Version20261020_001_SuspendedReasonEverywhere` officially adds nullable `oci_sites.suspended_reason VARCHAR(40)` with `IF NOT EXISTS`. Its rollback intentionally retains the column. Use this upstream migration rather than adding a second local migration for the same column.
- Backup-history and multi-database metadata migrations create tables and extend backup records.
- Configuration deduplication deletes older duplicate global/system settings, preserving the highest ID per key. This cannot be reversed without a data restore.
- Agency lifecycle migrations add uniqueness and generated-column constraints, backfill relationship dates, and modify indexes. They refuse duplicate agencies or multiply claimed customers. Invite hardening also guards duplicate tokens.
- Commission removal drops the commission table and banking/commission columns. It refuses nonempty commission rows or stored banking details; rollback restores schema only. Validate guards on a restored copy before upgrade.
- Settings templates require the packaged `config/banner-templates.php`; the seed upserts system templates. Site-issue, sync, import-tracking and status-history changes add schema.
- Associated-domain indexing and two-phase scanning extend indexes and nullable fields. Microsoft Clarity migration replaces a combined blocking pattern with separate analytics/marketing entries.

`php bin/oci migrations:migrate` sorts files and records each successful class after `up()`. It does not wrap a batch in a transaction, and MariaDB DDL is not a batch rollback mechanism. Partial failure can leave statements applied before the migration is recorded. Restore a paired database/config/files backup and rehearse the full pending batch before changing live code. Do not run the legacy-import command as part of schema maintenance.

## Deployment preparation still required

Port and review existing OCI customizations separately: Resend API mail delivery, Mailer wiring, NullPlan repository behavior, Nginx/Compose configuration and scanner setup. This checkout intentionally does not import production environment files or credentials. A clean upstream image does not prove those operational changes are preserved.

Upstream v2.7.5 includes `phpunit.xml.dist`, but omits its referenced `tests/` directory and `.githooks/`. PHPUnit cannot establish application behavior until those tests are supplied. PHP syntax and Composer platform/dependency checks remain useful bounded checks.

## Preferred immediate production change: PHP-only backport

The PHP correction does not require upgrading the application to v2.7.5. Preserve the existing customized v2.7.1 source and change only its PHP base image reference, after checking every PHP stage in that customized Dockerfile. Build from that existing context and replace only the app, scheduler, worker and beacon-worker PHP services using the existing Compose configuration. Retain existing database, Redis, scanner, volumes and operational settings. Avoid the generic updater and full pending migration batch for this change.

The official `Version20261020_001_SuspendedReasonEverywhere.php` can be backported independently: it uses the existing migration base class, requires only `oci_sites.status`, and has no dependency on the preceding 17 new migrations. The user explicitly declined a Conzent backup for this narrow correction. On OCI, the deployed ledger and files were compared before execution: this class was the sole pending migration. A deployment of all v2.7.5 files would also expose the other 17 migrations and is a separate upgrade.

## Local verification

- Official registry raw manifest SHA-256 and `Docker-Content-Digest` match; ARM64 platform entry verified.
- PHP 8.3.36 amd64 container, network disabled, read-only source: syntax checks passed for 441 PHP files in `src`, `config`, `migrations`, `public`, and separately `bin/oci`.
- `git diff --check`, entrypoint `sh -n`, and both consent bundles' `node --check` passed.
- Application PHPUnit tests are unavailable because the public release omits `tests/`.
- Native amd64 production image build passed: `localhost/conzent-php-maintenance:8.3.36`, image ID `1a4af392d86dc5398ffd548456c2ab77420732184b9258cc436d8fdefa583e3c`.
- Network-disabled built-image checks passed: PHP exactly 8.3.36, Composer autoload/platform check, intl/opcache/pdo_mysql/zip/bcmath/redis extensions, and `php-fpm -t`.
- Composer installed all 41 locked production packages successfully. Upstream's copied `.git` directory produced Git ownership warnings during Composer; they did not fail the build. No application HTTP/database behavior was tested.

## Live OCI execution and verification

- The existing customized OCI source was preserved. Its PHP base was pinned to the 8.3.36 digest above, unused secret build arguments were removed, and `.dockerignore` excludes environment, Git, credential and runtime files. The official additive migration was placed in the existing migration directory and executed as the sole pending class. The `oci_sites.suspended_reason` column now exists.
- The app, worker, scheduler and beacon-worker use `conzent-php:8.3.36-20261022-r2`, image ID `sha256:6a3d62d31661d93688660cb243bac292e2ae469a75467fce8f05b77018ad17ef`; each reported PHP 8.3.36. The app healthcheck passed. Network-disabled image inspection confirmed the patched consent source and absence of `.env`, `.git` and credential files.
- A read-only Nginx mount for the generated `sites_data` volume corrected public script delivery. Georgianadaycare and Uglywork scripts returned `200 application/javascript` with a browser User-Agent. Initial public responses were 404 without this mount.
- The local one-line `Conzent.reinit()` change is mirrored in the customized OCI source. It reopens the mounted banner via `conzentOpenBox()` instead of reconstructing the entire runtime. A Chrome for Testing flow against the regenerated public script passed reject, reopen, accept, reopen and withdraw with one banner/style and the correct Rybbit activation state. The generated script must be refreshed after any future source rebuild.
- Authenticated Conzent UI now has sites for `georgianadaycare.ro` and `uglywork.com`, with `uglyjob.com` associated to the latter. Georgianadaycare has Romanian and English banner content. Uglyjob's Romanian banner has visible customize, reject and accept actions. Its public privacy/cookies pages remain drafts, so website analytics activation remains gated.
- At this PHP checkpoint, the image and Nginx changes were in OCI Compose override files outside the Coolify-generated base file. Existing containers retained them across a normal restart, while a future Coolify redeploy could omit them. The later start-command update is recorded below.

## Subsequent Conzent UI and localization repair

- The installed v2.7.1 cookie/category update and delete handlers expected a `route_params` request attribute, while the router injects `id` directly. Four read-only handler hotfixes mounted into the OCI app now use the actual route attribute. PHP lint passed. The authenticated category editor can save again.
- The installed `GenerateScriptHandler` expected a missing `session` attribute, yielding HTTP 401 for an authenticated administrator. A scoped handler hotfix uses the `user` attribute and checks site ownership. Authenticated tests returned 404 for a nonexistent site and 200 for Georgianadaycare. The upstream v2.7.5 handler uses its newer `SiteAccess` service and was left unchanged in this checkout.
- Georgianadaycare category names and descriptions were saved in Romanian and read back for all seven categories. Its public generated script contains the Romanian entries. Built-in GDPR layout/CSS hotfixes display the preference retention sentence in Romanian when the host document language is `ro`, with English as fallback. An isolated Chrome for Testing 148 visual probe showed a Romanian banner, all category names/descriptions, and the Romanian footer without page errors. Both Georgianadaycare and Uglywork public scripts were regenerated and returned HTTP 200 with the bilingual footer.
- Coolify's prior custom start command included only `redirect-fix.yml`. Appending all override paths exceeded its 255-character database column and returned 500 without mutation. A root-owned executable wrapper at `/data/coolify/conzent-hotfixes/wtqyyqefqyr12syrbybk9jnq/start-conzent.sh` now references the five validated overrides. Its `--check` mode passed `docker compose config --quiet`. The Coolify application API accepted the wrapper path and exact GET readback matched. This preserves the override list in Coolify's authoritative start-command field; an actual Coolify redeploy has not been rehearsed.
