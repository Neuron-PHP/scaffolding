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

## Learn More

Visit [neuronphp.com](http://neuronphp.com) for full documentation.
