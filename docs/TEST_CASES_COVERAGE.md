# Test Cases Coverage (Critical Paths)

Ce document trace les cas critiques vérifiés et les sorties importantes validées.
This document tracks critical scenarios and validated important outputs.

## 1) Onboarding Password Flow
- Component: `App\Controller\OnboardingController`
- Tests: `tests/Unit/OnboardingControllerTest.php`
- Verified cases:
  - Missing token -> state `invalid`, explicit message.
  - Unknown token -> state `invalid`, explicit message.
  - Expired token -> state `expired`, `DemoRequest` marked expired, token hash cleared.
  - Weak/mismatched password -> state `invalid_password`, detailed password errors.
  - Valid password + sync OK -> state `accepted`, `DemoRequest` marked accepted.
  - Valid password + child app sync failure -> state `sync_failed`, no false positive activation.

## 2) Demo Request API Contract
- Component: `App\Controller\RegisterController`
- Tests:
  - `tests/Integration/PublicApiRoutesTest.php`
  - `tests/Unit/RegisterControllerTest.php`
- Verified cases:
  - Missing required fields -> HTTP `422`, structured `errors`.
  - Invalid `birth_date` format -> HTTP `422`, explicit format error.
  - Provisioning failure -> HTTP `503`, stable failure payload.
  - Successful request -> HTTP `201`, payload includes:
    - `status=requested`
    - `demo_request_uuid`
    - `tenant_uuid`
    - `tenant_slug`
    - `demo_expires_at`

## 3) Tenant Provisioning and DB Orchestration
- Components:
  - `App\Infrastructure\Provisioning\TenantProvisioner`
  - `App\Infrastructure\Provisioning\PostgresDbOrchestrator`
  - `App\Command\TenantProvisionCommand`
- Tests:
  - `tests/Unit/TenantProvisionerTest.php`
  - `tests/Unit/PostgresDbOrchestratorTest.php`
  - `tests/Unit/TenantProvisionCommandTest.php`
- Verified cases:
  - Account creation with slug normalization and unique suffix handling.
  - DB provisioning success and retry strategy.
  - Provisioning hard failure after max retries -> tenant marked failed + credentials reset.
  - Idempotent provisioning when tenant already created.
  - PostgreSQL role/database create/alter/drop + rollback behavior.
  - Invalid tenant identifier rejected.
  - CLI command success/failure exit code consistency.

## 4) Child App Provisioning Contract
- Component: `App\Infrastructure\Provisioning\ChildAppAdminClient`
- Tests: `tests/Unit/ChildAppAdminClientTest.php`
- Verified cases:
  - Missing URL in dev -> sync skipped (safe behavior).
  - Missing token -> service unavailable error.
  - Non-2xx downstream response -> service unavailable error.
  - Transport/network failure -> service unavailable error.
  - 2xx response -> success path.

## 5) Security/Audit Logging Output
- Components:
  - `App\Logging\AuditDbHandler`
  - `App\Logging\TenantContextProcessor`
- Tests:
  - `tests/Unit/AuditDbHandlerTest.php`
  - `tests/Unit/TenantContextProcessorTest.php`
- Verified cases:
  - Missing/unknown tenant slug -> no DB write.
  - Valid tenant slug -> persisted `AuditEvent` with normalized payload (`before/after`, metadata, user id).
  - Monolog v3 `LogRecord` and Monolog v2 array compatibility.

## 6) Entity State and Trait Lifecycle
- Components:
  - `Tenant`, `DemoRequest`, `Contact`, `AuditEvent`, `TenantMigrationVersion`
  - Traits: `TimestampableTrait`, `SoftDeleteTrait`, `UuidPrimaryKeyTrait`
- Tests: `tests/Unit/EntityLifecycleTest.php`
- Verified cases:
  - State transitions and normalization behavior.
  - UUID/timestamps/deletion lifecycle behavior.
  - Getter/setter integrity and persistence-facing invariants.

## 7) Admin and Debug Routes
- Components:
  - Admin CRUD controllers
  - Onboarding debug controller
- Tests:
  - `tests/Unit/AdminCrudControllersTest.php`
  - `tests/Unit/OnboardingDebugControllerTest.php`
  - `tests/Integration/AdminAccessTest.php`
  - `tests/Integration/DebugRouteSecurityTest.php`
- Verified cases:
  - Admin CRUD metadata/actions/fields consistency.
  - Debug token endpoint returns `invalid|valid|expired` with expected payload semantics.
  - Admin and debug access controls remain enforced.

## 8) Coverage Target
- Target: `>= 80%` line coverage (CI gate).
- Current validated result: `93.48%` lines (`746/798`), from `var/coverage.xml`.
