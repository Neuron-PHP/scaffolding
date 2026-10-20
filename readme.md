[![CI](https://github.com/Neuron-PHP/scaffolding/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/scaffolding/actions)
[![codecov](https://codecov.io/gh/Neuron-PHP/scaffolding/branch/develop/graph/badge.svg)](https://codecov.io/gh/Neuron-PHP/scaffolding)
# Neuron Scaffolding

Code generators and scaffolding tools for the Neuron PHP framework.

## Installation

```bash
composer require --dev neuron-php/scaffolding
```

## Available Commands

### CRUD Resource Generation

Generate a complete, runnable Neuron ORM CRUD stack — model, DTO YAML,
repository (interface + database implementation), an attribute-routed
controller, and field-aware views — with a single command.

```bash
# From an explicit field spec (creates a new table + migration)
php neuron scaffold:generate Post \
  --fields="title:string,body:text,published:boolean"

# From an existing database table (introspects columns; skips migration)
php neuron scaffold:generate Docket --from-table=jud_docket
```

The `--from-table` path introspects the live schema via PDO
(`information_schema.columns` on MySQL/PostgreSQL, `PRAGMA table_info` on
SQLite) using the project's configured connection, so it is the primary path
for mapping existing GroupOffice tables.

#### Wide tables: exposing a subset

Large legacy tables (e.g. `jud_docket`) often have many columns you don't want
in the editing UI. Inspect the columns first, then narrow the **editable
surface** with `--only` or `--except`:

```bash
# 1. See what's there (prints columns + types, generates nothing)
php neuron scaffold:generate Docket --from-table=jud_docket --list-fields

# 2. Expose just the columns you need
php neuron scaffold:generate Docket --from-table=jud_docket \
  --only="case_number,status,filed_date,judge_id"

# ...or keep everything except a few
php neuron scaffold:generate Docket --from-table=jud_docket \
  --except="created_by,modified_by,acl_id"
```

`--only` / `--except` affect **only the DTO and views** — the model,
repository, and migration always map the complete table, so inserts and
updates that touch `NOT NULL` columns keep working. The primary key is always
retained. (`--only` and `--except` are mutually exclusive.)

Generated controllers use PHP 8 route attributes (`#[Get]` / `#[Post]` …) — no
`routes.yaml` is written. Unsafe-method routes are tagged with
`filters: ['csrf']`, and forms emit `csrf_field()`; both are provided by the
framework-level CSRF support in `neuron-php/mvc` (enabled by default via the
`security.csrf` setting), so no per-app wiring is required.

After generation, bind the repository interface to its database implementation
in your service provider, e.g. `IPostRepository` → `DatabasePostRepository`.

### Controller Generation
```bash
php neuron controller:generate UserController --type=resource
```

### Event & Listener Generation
```bash
php neuron event:generate UserRegistered --property="userId:int" --property="email:string"
php neuron listener:generate SendWelcomeEmail --event="App\Events\UserRegistered"
```

### Job Generation
```bash
php neuron job:generate SendEmailReminders --cron="0 9 * * *"
```

### Initializer Generation
```bash
php neuron initializer:generate QueueInitializer
```

### Migration Generation
```bash
php neuron db:migration:generate CreateUsersTable
```

### Email Template Generation
```bash
php neuron mail:generate welcome
```

### Docker Files
```bash
php neuron docker:install                      # defaults read from config/neuron.yaml
php neuron docker:install --db=mysql --redis   # add database and Redis services
php neuron docker:install --php=8.4 --docroot=public --queue=high,default
php neuron docker:install --web-only           # no scheduler / worker services
php neuron docker:install --railway            # also write railway.json
```

Writes `Dockerfile`, `.dockerignore`, `docker-compose.yml` and `.docker/`
(`php.ini`, `vhost.conf`, `entrypoint.sh`). Existing files are skipped unless
`--force`.

One image serves every role; only the command differs. The `dev` target has
Xdebug (off until `XDEBUG_MODE=debug`) and expects the source bind-mounted;
the `prod` target bakes `vendor/` in with `composer install --no-dev`.
`docker-compose.yml` runs `app` (Apache), `scheduler` (`jobs:schedule`) and
`worker` (`jobs:work`, scale with `--scale worker=N`), plus `db` and `redis`
when requested.

Things the generated files take care of that are easy to miss by hand:

- `pcntl` is installed; without it job timeouts and graceful shutdown are off.
- The entrypoint `exec`s the command so PHP is PID 1 and receives signals.
- `php:*-apache` declares `STOPSIGNAL SIGWINCH`. `neuron-php/jobs` 0.2.18+
  stops cleanly on it when headless; the scheduler and worker services also
  set `stop_signal: SIGTERM` for older versions and to make the intent plain.
- `stop_grace_period` on the worker exceeds `--timeout`.
- `memory_limit` (256M) stays above `jobs:work --memory=128` so the worker
  recycles itself before PHP aborts.
- CLI roles run as `www-data`, the same user as Apache, so `storage/` stays
  writable by all three.
- Database and Redis services carry health checks and the PHP services
  `depends_on` them with `condition: service_healthy`.

## Learn More

Visit [neuronphp.com](http://neuronphp.com) for full documentation.
