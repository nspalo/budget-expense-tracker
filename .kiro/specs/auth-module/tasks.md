# Implementation Plan: Auth Module

## Overview

Implement token-based API authentication for the Budget & Expense Tracker using Laravel Sanctum. This covers package installation, configuration, User model updates, AuthService with password hashing and token management, AuthController with Form Request validation, route registration with Sanctum middleware, rate limiting, and comprehensive testing.

All commands run through the Makefile interface (`make artisan cmd="..."`, `make composer cmd="..."`).

## Tasks

- [x] 1. Install and configure Laravel Sanctum
  - [x] 1.1 Install Sanctum package and publish configuration
    - Run `make composer cmd="require laravel/sanctum"` to install Sanctum
    - Run `make artisan cmd="vendor:publish --provider=Laravel\\Sanctum\\SanctumServiceProvider"` to publish config and migration
    - Verify `src/config/sanctum.php` is created
    - Set token expiration to 10080 minutes (7 days) in `sanctum.php`
    - _Requirements: 4.1, 4.4_

  - [x] 1.2 Run Sanctum migration and configure auth guard
    - Run `make artisan cmd="migrate"` to create the `personal_access_tokens` table
    - Update `src/config/auth.php` to set the API guard driver to `sanctum`
    - _Requirements: 4.1, 4.3_

  - [x] 1.3 Configure Sanctum middleware in application bootstrap
    - Update `src/bootstrap/app.php` to register Sanctum's `auth:sanctum` middleware for the `api` middleware group
    - Ensure unauthenticated requests return JSON 401 responses (configure `Authenticate` middleware redirect to null for API)
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 2. Update User model and create AuthService
  - [x] 2.1 Update User model with HasApiTokens trait
    - Add `use HasApiTokens` trait to `src/app/Models/User.php`
    - Ensure `password` is in the `$hidden` array
    - Ensure `password` uses the `hashed` cast with bcrypt
    - Add `SoftDeletes` trait for profile requirement 8.3
    - Verify `$fillable` includes `name`, `email`, `password`
    - _Requirements: 1.2, 4.3, 7.1, 7.2, 8.3_

  - [x] 2.2 Create AuthService with registration logic
    - Create `src/app/Services/AuthService.php` with `declare(strict_types=1)`
    - Implement `register(array $data): array` method that:
      - Wraps user creation + token generation in a `DB::transaction()`
      - Creates user with hashed password (bcrypt cost 12)
      - Creates a Sanctum token with `expires_at` based on configured TTL
      - Returns user profile data and plain-text token with expiration
    - Handle null/zero/negative TTL config by leaving `expires_at` null
    - _Requirements: 1.1, 1.2, 1.8, 1.9, 4.1, 4.4_

  - [x] 2.3 Add login logic to AuthService
    - Implement `login(string $email, string $password): array` method that:
      - Looks up user by email
      - If user not found, hashes password against a dummy hash (timing attack prevention) then throws AuthenticationException
      - If user found, verifies password with `Hash::check()`
      - On mismatch, throws AuthenticationException with generic message
      - On success, creates Sanctum token with configured TTL expiration
      - Returns user profile data and plain-text token
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 7.4, 7.5_

  - [x] 2.4 Add logout logic to AuthService
    - Implement `logout(\App\Models\User $user): void` method that:
      - Deletes only the current access token (`$user->currentAccessToken()->delete()`)
      - Does not affect other active tokens for the same user
    - _Requirements: 3.1, 3.2_

  - [ ]* 2.5 Write property test: Password never stored in plaintext
    - **Property 2: Password Never Stored in Plaintext**
    - Use PHPUnit data providers with varied password inputs
    - Assert the stored password column value never equals the plaintext input
    - Assert stored value is a valid bcrypt hash with cost factor 12
    - **Validates: Requirements 1.2, 7.1, 7.2**

  - [ ]* 2.6 Write property test: Registration creates valid user and token
    - **Property 1: Registration Creates Valid User and Token**
    - Use PHPUnit data providers with varied valid registration inputs
    - Assert exactly one user record is created with correct name/email
    - Assert returned token authenticates subsequent requests
    - **Validates: Requirements 1.1, 1.8**

- [x] 3. Create Form Request validation classes
  - [x] 3.1 Create RegisterRequest form request
    - Create `src/app/Http/Requests/Auth/RegisterRequest.php`
    - Validation rules: `name` (required, string, max:255), `email` (required, email, unique:users, max:255), `password` (required, string, min:8, max:128, confirmed)
    - Return `true` from `authorize()` (public endpoint)
    - _Requirements: 1.3, 1.4, 1.5, 1.6, 1.7_

  - [x] 3.2 Create LoginRequest form request
    - Create `src/app/Http/Requests/Auth/LoginRequest.php`
    - Validation rules: `email` (required, email), `password` (required, string)
    - Return `true` from `authorize()` (public endpoint)
    - _Requirements: 2.5, 2.6_

  - [ ]* 3.3 Write property test: Invalid registration input is rejected
    - **Property 3: Invalid Registration Input is Rejected**
    - Use PHPUnit data providers with invalid inputs (short password, mismatched confirmation, invalid email)
    - Assert HTTP 422 returned and no user created in database
    - **Validates: Requirements 1.4, 1.5, 1.7**

- [x] 4. Checkpoint - Verify core components
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement AuthController and route registration
  - [x] 5.1 Create AuthController with register and login actions
    - Create `src/app/Http/Controllers/Auth/AuthController.php`
    - Inject `AuthService` via constructor
    - Implement `register(RegisterRequest $request)` returning JSON 201 with user data and token
    - Implement `login(LoginRequest $request)` returning JSON 200 with user data and token
    - Catch `AuthenticationException` in login and return JSON 401 with generic message
    - Response format: `{ "user": { "id", "name", "email", "created_at" }, "token": "...", "expires_at": "..." }`
    - _Requirements: 1.1, 1.9, 2.1, 2.2, 2.3, 2.4_

  - [x] 5.2 Add logout and user profile actions to AuthController
    - Implement `logout()` returning JSON 200 with success message
    - Implement `user()` returning JSON 200 with authenticated user's id, name, email, and token expires_at
    - Handle soft-deleted user edge case: if user is trashed, revoke token and return 401
    - _Requirements: 3.1, 8.1, 8.3, 8.4_

  - [x] 5.3 Register auth routes in routes/api.php
    - Create or update `src/routes/api.php` with auth route group:
      - `POST /api/auth/register` → `AuthController@register` (public, rate-limited)
      - `POST /api/auth/login` → `AuthController@login` (public, rate-limited)
      - `POST /api/auth/logout` → `AuthController@logout` (auth:sanctum)
      - `GET /api/auth/user` → `AuthController@user` (auth:sanctum)
    - Apply `auth:sanctum` middleware to logout and user routes
    - _Requirements: 5.1, 5.2, 5.3, 5.5_

  - [ ]* 5.4 Write property test: Login returns token only for valid credentials
    - **Property 4: Login Returns Token Only for Valid Credentials**
    - Use data providers with valid credentials, wrong password, non-existent email
    - Assert token returned only when email matches existing user AND password is correct
    - Assert all failures return HTTP 401
    - **Validates: Requirements 2.1, 2.2, 2.3**

  - [ ]* 5.5 Write property test: User enumeration prevention
    - **Property 5: User Enumeration Prevention**
    - Assert failed login response body is identical regardless of failure reason
    - Compare response structure and message for non-existent email vs wrong password
    - Assert HTTP status code is 401 in both cases
    - **Validates: Requirements 2.3, 2.4, 7.4**

- [x] 6. Implement rate limiting
  - [x] 6.1 Configure rate limiters for auth endpoints
    - In `src/bootstrap/app.php` (or `AppServiceProvider`), define rate limiters:
      - `auth-register`: 5 attempts per minute, keyed by IP address
      - `auth-login`: 5 attempts per minute, keyed by lowercase email + IP combination
    - Return JSON response body with error message and retry seconds on limit exceeded
    - Include `Retry-After` header with seconds remaining in the 60-second fixed window
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 6.2 Apply rate limit middleware to auth routes
    - Add `throttle:auth-register` middleware to the register route
    - Add `throttle:auth-login` middleware to the login route
    - Ensure 429 response includes JSON body with message and retry_after fields
    - _Requirements: 6.1, 6.2, 6.4_

  - [ ]* 6.3 Write property test: Rate limiting enforcement
    - **Property 11: Rate Limiting Enforcement**
    - Send 6+ requests to register/login endpoints within one minute
    - Assert 6th request returns HTTP 429
    - Assert Retry-After header is present and value is between 1-60
    - **Validates: Requirements 6.1, 6.2, 6.3**

- [x] 7. Checkpoint - Verify routes and rate limiting
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement token lifecycle and protected route enforcement
  - [x] 8.1 Configure token expiration handling
    - Ensure Sanctum config `expiration` is set to 10080 (minutes)
    - Verify that expired tokens are automatically rejected by Sanctum middleware
    - Add token pruning artisan command schedule (optional future): `sanctum:prune-expired`
    - _Requirements: 4.1, 4.2, 4.4_

  - [x] 8.2 Implement UserScope global scope for data isolation
    - Create `src/app/Scopes/UserScope.php` that adds `WHERE user_id = Auth::id()` to queries
    - Create `src/app/Traits/BelongsToUser.php` trait that applies UserScope in `booted()`
    - UserScope only applies when a user is authenticated (skip in CLI/seeder context)
    - _Requirements: 5.4_

  - [ ]* 8.3 Write property test: Logout invalidates current token
    - **Property 6: Logout Invalidates Current Token**
    - Register/login to get a token, call logout, then attempt to use the same token
    - Assert the token returns 401 after logout
    - Assert other tokens for the same user remain valid
    - **Validates: Requirements 3.1, 3.2**

  - [ ]* 8.4 Write property test: Token expiry enforcement
    - **Property 7: Token Expiry Enforcement**
    - Create a token with a past `expires_at` timestamp using Carbon time travel
    - Assert that the expired token returns HTTP 401
    - **Validates: Requirements 4.1, 4.2**

  - [ ]* 8.5 Write property test: Protected routes require valid token
    - **Property 9: Protected Routes Require Valid Token**
    - Send requests to protected endpoints with no token, invalid token, and revoked token
    - Assert all return HTTP 401 with identical "Unauthenticated" message
    - **Validates: Requirements 5.1, 5.2, 5.3**

- [x] 9. Write integration tests for auth flows
  - [x] 9.1 Write feature tests for registration flow
    - Create `src/tests/Feature/Auth/RegistrationTest.php`
    - Test successful registration returns 201 with user data and token
    - Test duplicate email returns 422
    - Test invalid password returns 422
    - Test missing fields return 422 with specific error messages
    - Test token returned is valid for authenticated requests
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_

  - [x] 9.2 Write feature tests for login flow
    - Create `src/tests/Feature/Auth/LoginTest.php`
    - Test successful login returns 200 with user data and token
    - Test wrong password returns 401 with generic message
    - Test non-existent email returns 401 with identical message
    - Test missing fields return 422
    - Test invalid email format returns 422
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [x] 9.3 Write feature tests for logout and user profile
    - Create `src/tests/Feature/Auth/LogoutTest.php`
    - Test successful logout returns 200 and token is revoked
    - Test logout only revokes current token, not others
    - Create `src/tests/Feature/Auth/UserProfileTest.php`
    - Test authenticated user gets 200 with id, name, email, expires_at
    - Test unauthenticated request gets 401
    - Test soft-deleted user gets 401 and token revoked
    - _Requirements: 3.1, 3.2, 3.3, 8.1, 8.2, 8.3, 8.4_

  - [ ]* 9.4 Write property test: User data isolation
    - **Property 10: User Data Isolation**
    - Create two users with separate records
    - Authenticate as user A and query scoped models
    - Assert user A never sees user B's records
    - **Validates: Requirements 5.4**

  - [ ]* 9.5 Write property test: Profile returns correct user data
    - **Property 12: Profile Returns Correct User Data**
    - Authenticate and request profile endpoint
    - Assert response contains id, name, email of the authenticated user
    - Assert password field is never present in response
    - **Validates: Requirements 8.1, 7.2**

- [x] 10. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- All commands should be run through the Makefile interface (e.g., `make artisan cmd="test"`)
- Tests run via PHPUnit 11.x inside the Docker PHP container
- Sanctum's `personal_access_tokens` table is created by Sanctum's published migration, no custom migration needed
- The `UserScope` global scope will be consumed by downstream financial models (transactions, accounts, etc.)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["1.3", "2.1"] },
    { "id": 3, "tasks": ["2.2", "3.1", "3.2"] },
    { "id": 4, "tasks": ["2.3", "2.4", "2.5", "2.6", "3.3"] },
    { "id": 5, "tasks": ["5.1", "5.2"] },
    { "id": 6, "tasks": ["5.3", "5.4", "5.5"] },
    { "id": 7, "tasks": ["6.1"] },
    { "id": 8, "tasks": ["6.2", "6.3"] },
    { "id": 9, "tasks": ["8.1", "8.2"] },
    { "id": 10, "tasks": ["8.3", "8.4", "8.5"] },
    { "id": 11, "tasks": ["9.1", "9.2", "9.3"] },
    { "id": 12, "tasks": ["9.4", "9.5"] }
  ]
}
```
