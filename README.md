# Multi-Tenant Marketing Insights Integration System

A robust, enterprise-grade multi-tenant platform built on Laravel 13. This system is designed to securely connect third-party marketing channels (starting with Facebook Graph API), stream high-volume advertising performance metrics asynchronously, and aggregate analytics data into isolated tenant databases.

---

## 🗺️ Architecture Decisions

### 1. Multi-Tenant Strategy: Database-per-Tenant Isolation
The platform implements a strict multi-database tenant isolation model using stancl/tenancy:
* Central Database: Manages central tenant identification, domain mappings, and the global onboarding pipeline.
* Tenant Databases: Every tenant gets a fully isolated database instance. When a tenant is created, the system intercepts the lifecycle via TenancyServiceProvider to automatically spin up a dedicated database and run migrations (CreateDatabase, MigrateDatabase).
* Significance: This keeps sensitive third-party marketing credentials (access_token, ad_account_id) and performance data physically isolated, ensuring maximum security and privacy compliance.

### 2. Multi-Context Tenant Resolution
The system architecture supports dual routing pipelines configured within bootstrap/app.php:
* Domain Routing (tenancy.domain): Resolves the tenant database context based on subdomains or custom domains (e.g., tenant-a.domain.com). Includes a defensive middleware (PreventAccessFromCentralDomains) to prevent central domain bleed-through.
* Request Payload/Header Routing (tenancy.request): Resolves the tenant based on request data (e.g., headers or parameters like X-Tenant). This is ideal for cross-platform unified App frontends accessing api routes prefixed with v1/app.

### 3. High-Volume Data Streaming via PHP Generators
To fetch Facebook Graph API insights without blowing up server memory limits:
* FacebookClient::getInsights() leverages PHP Generators (yield).
* Significance: Instead of building an infinite in-memory array from continuous cursor pages (paging.next), the client yields single page responses sequentially. The processing service (FacebookService) consumes and persists one page, flushes it from memory, and proceeds. This achieves an O(1) flat memory footprint regardless of data volume.

---

## 🛠️ How to Add a New Integration (Facebook Ads)

The project utilizes data sanitization via FormRequests, structured DTOs, and the Repository Pattern. To add a new integration provider, follow these 5 steps:

### Step 1: Create the Request DTO
Create a structured Data Transfer Object under App\Integrations\{Provider}\Dto to parse array data into typed properties. 

Example Class Structure:
- Namespace: App\Integrations\Facebook\Dto
- Class: FacebookInsightsRequestDTO
- Properties: string $provider, string $level, array $fields, string $dateFrom, string $dateTo
- Methods:
  * fromArray(array $data): self -> Maps snake_case array to typed parameters.
  * toArray(): array -> Serializes properties back to a clean array format.

### Step 2: Implement the Provider Client
Create your client under App\Integrations\{Provider} to execute HTTP network calls using Laravel's Http client. Use the "yield" keyword for paginated list endpoints to maintain the generator streaming contract.

Example Class Structure:
- Namespace: App\Integrations\Facebook
- Class: FacebookClient
- Method: getInsights(string $token, FacebookInsightsRequestDTO $dto): \Generator -> Implements sequential loop pagination based on cursor next urls and yields individual page arrays.

### Step 3: Implement the Integration Service
Build a processor service under App\Integrations\{Provider} to map provider-specific response attributes into system standard values and call repositories.

Example Class Structure:
- Namespace: App\Integrations\Facebook
- Class: FacebookService
- Dependencies: Inject InsightRecordRepository via constructor.
- Method: syncPageInsights(array $responsePage, FacebookInsightsRequestDTO $dto): void -> Loops over raw data rows, flattens metrics (impressions, clicks, spend), and executes updateOrCreateRecord on the repo layer.

### Step 4: Author the Asynchronous Sync Job
Create a queueable Job (ShouldQueue) following the multi-tenant runtime blueprint:
1. Call tenancy()->initialize($this->tenantId) at entry.
2. Fetch access tokens using ExternalAccountRepository.
3. Wrap your execution loop in a try-catch-finally block.
4. Call tenancy()->end() in the finally block to prevent tenant context leaks.

### Step 5: Update Validation Rules
Extend ConnectIntegrationRequest and SyncInsightsRequest validation constraints to accept the new provider identifier within the string enum validation rule (e.g., in:facebook).

---

## 🔄 Failure & Retry Strategy

External ad network APIs are prone to transient dropouts, rate limits, and throttling. The system handles failures gracefully across three architectural tiers:

### 1. HTTP Layer Network Resilience
All outgoing client connections enforce strict connection guards:
* Timeouts: Http::timeout(30) ensures slow connection gateways do not lock up queue threads indefinitely.
* Retries: retry(3, 200) instantly mitigates minor network ripples by retrying failed attempts up to 3 times with a 200ms delay before throwing an exception.

### 2. Queue Linear Backoffs
When a job encounters a valid failure (e.g., API token expiration or explicit server side errors), it is managed by a structural retry policy configured inside the queue handler:
- $tries = 3 -> Maximum 3 execution attempts before failure.
- $backoff = 60 -> Wait 60 seconds between sequential retry attempts.

### 3. Stateful Job Auditing
Every data synchronization event logs its execution lifecycle within the tenant's localized database through IntegrationJobRepository:
* Upon dispatching, an explicit audit trail is logged with a status of 'running'.
* If a critical failure happens, the catch block captures the exception, logs it via Log::error, and calls updateStatus($jobRecord, 'failed', $e->getMessage()).
* Note: The job safely re-throws the exception (throw $e) at the end of the catch process so the underlying Laravel queue manager handles the retry backoff safely.

---

## 🔒 Security Considerations

### 1. Prevention of Horizontal Privilege Escalation
Cross-tenant data leakage is completely blocked by enforcing tenant-scoped authentication and authorizing actions at the controller perimeter using:
Gate::authorize('manageSettings', [tenant()]);

The TenantIntegrationPolicy validates that the actively authenticated tenant owner running the session explicitly owns the tenant() identifier being requested.

### 2. Sanctum Token Model Overrides
Standard Laravel Sanctum reads from a single central database table. Because this system splits tenants into completely different databases, token evaluation is overridden inside AppServiceProvider:
\Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\SanctumToken::class);

This enables Sanctum API tokens (tokens()->create()) to be evaluated dynamically inside the specific tenant's database partition.

### 3. API Politeness and Throttle Compliance
To prevent upstream servers from blocking or penalizing tenant access tokens for aggressive pagination scraping, the FacebookClient pagination loop enforces a micro-throttle policy:
if ($url !== null) { usleep(200000); } // 200ms polite pause before pulling the next cursor page

---

## ⚖️ Assumptions & Trade-offs

### 1. Cron-to-Queue Fanout Decoupling
* Assumption: The scheduler expects an active, multi-worker supervisor queue subsystem (queue:work) to run alongside the core platform.
* Trade-off: The daily cron coordinator utilizes chunkById(100) to read active tenants from the central table. It does not query third-party APIs on the schedule thread. It acts strictly as an execution dispatcher, pushing lightweight sync tasks onto asynchronous queues. This keeps the cron lifecycle exceptionally short and delegates high-compute processing to queue workers.

### 2. Idempotent Target Overwriting vs. Bulk Inserts
* Assumption: Upstream marketing metrics can retroactively shift due to ad-fraud reconciliations or late attribute adjustments.
* Trade-off: Data persistence uses InsightRecordRepository::updateOrCreateRecord(). Row-by-row lookups are slower than raw SQL mass-inserts. However, this trade-off is made to ensure absolute consistency and idempotency. If a daily sync task runs multiple times for an overlapping timeframe, data is cleanly overwritten rather than creating duplicate row aggregates.

### 3. Defensive Error Truncation
* Assumption: Deep nested exception messages and JSON responses from corporate Graph APIs can contain immense payload tracking strings.
* Trade-off: The IntegrationJobRepository forces error field constraints through string length clipping: substr($error, 0, 1000). While this occasionally truncates extremely long stack traces, it acts as a defensive strategy preventing database crashes caused by "Data too long" exceptions, ensuring that system monitoring remains active.

---

## 🧪 Testing Strategy

The platform maintains a rigorous testing suite built on **Pest PHP**, specifically tailored to validate multi-tenant isolation, cross-database behavior, and third-party API resilience.

### 1. Isolated Tenant Test Lifecycle (`TenantTestCase`)
Testing a database-per-tenant architecture introduces unique challenges, such as stale connection leakage and database locks between test workers. The suite utilizes a custom `TenantTestCase` to enforce strict environment hygiene:
* **Pre-emptive & Post-test Cleanup:** Both `setUp()` and `tearDown()` trigger `cleanupTenantSystem()` to forcefully exit active tenant contexts (`tenancy()->end()`) and return to the central database connection.
* **PostgreSQL Deadlock Mitigation:** When utilizing PostgreSQL, dropping tenant databases during rapid test cycles frequently throws `Object in use` errors due to persistent connection pooling. The teardown architecture solves this by explicitly purging the DB facade (`DB::purge('tenant')`) and, if necessary, executing an administrative statement via the central connection to terminate all active backends targeting the test database:
  ```sql
  SELECT pg_terminate_backend(pg_stat_activity.pid)
  FROM pg_stat_activity
  WHERE pg_stat_activity.datname = ? AND pid <> pg_backend_pid();

### 2. Concrete Test Profiles & Boundary Validations
A. Integration, Queue & Scheduler Testing (FacebookSyncJobTest)
Validates asynchronous job execution loops and CRON scheduling mechanisms:

Generator Multi-Page Mocking: Utilizes Http::sequence() to mimic Facebook Graph API cursor pagination (paging.next). It ensures that SyncFacebookInsightsJob processes multiple page streams sequentially and verifies that records are properly stored within the tenant database via $this->tenant->run().

Scheduler Fanout Verification: Uses Queue::fake() and Artisan::call('schedule:run') to ensure the central daily sync coordinator scans external accounts and correctly dispatches RunFacebookDailySyncJob to the queue with the appropriate decoupled tenantId.

B. Tenant-Scoped API & Controller Testing (IntegrationBusinessTest)
Validates RESTful API endpoints matching tenant-isolated routing domains:

Token Authentication Boundary: Ensures that requests lacking a valid Sanctum bearer token or using corrupted credentials are blocked at the perimeter with a 401 Unauthorized status.

Permission Guard Validation: Simulates upstream API behaviors (e.g., Facebook token permission arrays) using Http::fake() to verify that the system accurately allows connection setups on granted status, or throws a 422 Unprocessable Entity validation error if critical scopes (like ads_read) are declined.

C. Central Pipeline & Multi-Context Middleware Testing (TenantRouteMiddlewareTest)
Validates global onboarding endpoints and the dual-routing resolution pipeline:

Central Onboarding Pipeline: Validates that hitting the central /api/tenants routes successfully creates global records in the central pgsql connection, initializes the tenant lifecycle, and issues cross-database Sanctum tokens.

Dual Routing Contexts: Asserts that tenants can be successfully resolved via either Domain-based routing (http://{tenant.domain}/api/*) using traditional bearer tokens, or Header-based routing (/api/v1/app/*) via custom X-Tenant headers.