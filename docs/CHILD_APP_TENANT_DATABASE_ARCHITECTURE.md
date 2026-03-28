# Child App Tenant Database Architecture

## Goal

Future child apps must not share a single business database across every tenant.

The baseline model is:

- mother app provisions the tenant and remains the source of truth
- each tenant gets its own dedicated business database
- child app routes resolve the tenant from the URL
- a child app may add a small registry database later if it needs non-deterministic DB lookup

Example:

- mother app onboarding: `demo.dsn-dev.com`
- child app URL: `secret-vault.dsn-dev.com/t/{tenantSlug}/login`

## Registry Database: Optional Baseline, Required for Indirection

The child app must always answer two questions before it can open the correct business database:

1. Which tenant is addressed by this request?
2. Which database belongs to that tenant?

When the database path can be derived deterministically from the public slug, a registry DB is optional.

When the database path or DSN is not deterministic, the registry database stores that mapping:

- `tenant_uuid`
- `tenant_slug`
- `child_app_key`
- `database_path` or `database_url`
- provisioning status metadata

Business tables such as `user`, `project`, `secret`, and invitation tables must live in the tenant database, not in the registry database.

## Tenant Slug Contract

The tenant slug is public and appears in URLs. It must therefore be:

- stable
- human-readable
- non-sensitive
- not directly derived from the raw tenant UUID

Current contract:

- readable base from `firstName + lastName`
- short opaque suffix derived from the tenant admin UUID through HMAC

Example:

- `anthony-delbee-a1b2c3d4e5`

This keeps URLs understandable without exposing the raw UUID.

## Mother App Responsibilities

The mother app must:

- generate the public `tenantSlug`
- send `tenant_uuid`, `tenant_slug`, admin identity, and child app metadata during provisioning
- keep its own internal tenant UUID private

The current provisioning contract already transports `tenant_slug`.

## Child App Responsibilities

The child app must:

1. resolve the tenant from `/t/{tenantSlug}/...`
2. determine the tenant DB path or DSN
3. create the tenant business database if missing
4. migrate that tenant database
5. persist the provisioned admin in the tenant database

## Authentication Impact

The default Doctrine entity provider is not sufficient for DB-per-tenant.

The child app needs:

- a `TenantContext` resolved from the route
- a tenant-aware user provider
- a tenant-aware Doctrine connection or entity manager provider

This is required because login itself already depends on the tenant database.

## Routing Policy

Business routes must live under a tenant prefix:

- `/t/{tenantSlug}/login`
- `/t/{tenantSlug}/projects`
- `/t/{tenantSlug}/projects/{id}`

Global routes stay outside the tenant prefix only when they are truly global:

- health endpoints
- internal provisioning endpoint

## Migration Strategy for Future Child Apps

When creating a new child app:

1. add a registry database
2. introduce a public tenant slug contract
3. place business routes under `/t/{tenantSlug}`
4. implement a tenant-aware user provider
5. move business entities to the tenant database
6. keep only registry entities in the global database

## Status in This Repository

At the moment:

- slug generation is standardized on the mother app side
- the `client_secret_vault` child app already uses `/t/{tenantSlug}` routes
- that child app provisions tenant-specific SQLite databases under `var/tenants/{tenantSlug}.sqlite`
- the registry DB remains an optional next step for future child apps that need path indirection

This document is the reference architecture for that migration.
