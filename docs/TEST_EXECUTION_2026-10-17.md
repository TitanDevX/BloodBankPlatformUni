# Test execution record — 17 October 2026

Project revision: `fc0b584` with uncommitted workspace changes. Runs used the local project dependencies and the test configuration in the repository. Results below are evidence for the named test files only; they do not mark the complete planned TC cases or acceptance criteria as passed.

| Scope | Command | Result |
| --- | --- | --- |
| Backend full suite | `cd backend && php artisan test --compact` | Passed: 29/29 tests, 189 assertions |
| Organization records | `cd backend && php artisan test --compact Modules/Organizations/tests/Feature/OrganizationRecordsTest.php` | Passed: 6/6 tests, 20 assertions |
| Identity tests | `cd backend && php artisan test --compact Modules/Identity/tests/Feature/AuthenticationTest.php Modules/Identity/tests/Feature/UserManagementTest.php` | Passed: 19/19 tests, 154 assertions |
| Authentication | `cd backend && php artisan test --compact Modules/Identity/tests/Feature/AuthenticationTest.php` | Passed: 8/8 tests, 51 assertions |
| User management | `cd backend && php artisan test --compact Modules/Identity/tests/Feature/UserManagementTest.php` | Passed: 11/11 tests, 103 assertions |
| Frontend Node tests | `cd frontend && npm test` | Passed: 6/6 tests (4 access checks, 2 API client checks) |

The frontend API client test opens a local fixture server on `127.0.0.1`. The first run was blocked by the restricted shell (`listen EPERM`); a rerun with local loopback access passed all six tests. These are JavaScript helper/API client tests. No browser-level system or formal acceptance test was run.

RTM interpretation: FR-01 has partial organization model and seed evidence; FR-02 has partial authentication, access mapping, and API client evidence; FR-17 has partial user management evidence. The linked integration case sheets also include routes and scenarios that are not yet implemented or exercised, so their catalogue status remains `Planned`.
