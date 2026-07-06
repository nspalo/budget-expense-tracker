# Requirements Document

## Introduction

The Auth Module provides token-based API authentication for the Budget & Expense Tracker application. It establishes user identity through registration and login, manages token lifecycle via Laravel Sanctum, and protects all downstream API endpoints. This module is the security foundation that enables user-scoped data isolation for all financial features.

## Glossary

- **Auth_Module**: The authentication subsystem responsible for user registration, login, logout, and token management
- **AuthController**: The REST controller that handles HTTP authentication requests and delegates to AuthService
- **AuthService**: The service class containing authentication business logic including credential verification and token management
- **Sanctum_Middleware**: Laravel Sanctum's authentication middleware that resolves Bearer tokens and populates the authenticated user context
- **Rate_Limiter**: The middleware that restricts the number of authentication requests per time window to prevent abuse
- **User_Model**: The Eloquent model representing a registered user with the HasApiTokens trait
- **Personal_Access_Token**: A Sanctum-managed token stored as a SHA-256 hash in the database, used for stateless API authentication
- **Bearer_Token**: The plain-text token sent in the HTTP Authorization header to authenticate API requests
- **Form_Request**: Laravel validation classes that validate input before it reaches the controller

## Requirements

### Requirement 1: User Registration

**User Story:** As a new user, I want to register an account with my name, email, and password, so that I can access the Budget & Expense Tracker application.

#### Acceptance Criteria

1. WHEN a user submits valid registration data (name, email, password, password_confirmation), THE Auth_Module SHALL create a new user record and return an HTTP 201 response containing the user profile (id, name, email, created_at) and a valid Bearer_Token
2. THE Auth_Module SHALL hash the password using bcrypt before persisting the user record to the database
3. IF a user submits a registration request with an email that already exists in the database, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error indicating the email is taken
4. IF a user submits a registration request with a password shorter than 8 characters or longer than 128 characters, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error
5. IF a user submits a registration request where password and password_confirmation do not match, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error
6. IF a user submits a registration request with a missing or empty name, or a name exceeding 255 characters, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error
7. IF a user submits a registration request with an invalid email format or an email exceeding 255 characters, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error
8. THE Auth_Module SHALL wrap user creation and token generation in a database transaction to ensure atomicity
9. THE Auth_Module SHALL issue a Bearer_Token with an expiration timestamp set per the configured token TTL and include the token expiration in the registration response

### Requirement 2: User Login

**User Story:** As a registered user, I want to log in with my email and password, so that I can receive a token to access protected API resources.

#### Acceptance Criteria

1. WHEN a user submits valid credentials (existing email and correct password), THE Auth_Module SHALL return an HTTP 200 response containing the user's id, name, email, and a valid Bearer_Token with expiration set per the configured token TTL
2. WHEN a user submits credentials with a non-existent email, THE Auth_Module SHALL return an HTTP 401 response with a generic "Invalid credentials" message
3. WHEN a user submits credentials with an incorrect password for an existing email, THE Auth_Module SHALL return an identical HTTP 401 response with a generic "Invalid credentials" message
4. THE Auth_Module SHALL return the same response body and HTTP status code for all authentication failures regardless of whether the email exists in the database
5. IF a login request is submitted with a missing or empty email field or a missing or empty password field, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error indicating the missing fields
6. IF a login request is submitted with an email value that is not a valid email format, THEN THE Auth_Module SHALL return an HTTP 422 response with a validation error indicating the email format is invalid

### Requirement 3: User Logout

**User Story:** As an authenticated user, I want to log out, so that my current token is revoked and can no longer be used for API access.

#### Acceptance Criteria

1. WHEN an authenticated user sends a logout request, THE Auth_Module SHALL delete the current Personal_Access_Token from the database and return an HTTP 200 response containing a success message
2. WHEN a logout is performed, THE Auth_Module SHALL ensure that only the Personal_Access_Token used in the logout request is revoked, leaving any other active tokens for the same user valid
3. IF a revoked token is used in any subsequent API request after logout, THEN THE Sanctum_Middleware SHALL reject the request with an HTTP 401 response

### Requirement 4: Token Lifecycle Management

**User Story:** As a system operator, I want tokens to have configurable expiration, so that stale sessions are automatically invalidated for security.

#### Acceptance Criteria

1. WHEN a new token is created (via registration or login), THE Auth_Module SHALL set the expires_at timestamp to the current time plus the token TTL value defined in the Sanctum configuration, defaulting to 10080 minutes (7 days) if not explicitly configured
2. WHEN a request is made with a token whose expires_at timestamp has passed, THE Sanctum_Middleware SHALL reject the request with an HTTP 401 response and an "Unauthenticated" message body
3. THE Auth_Module SHALL store only the SHA-256 hash of the token in the database, returning the plain-text token to the client only once at creation time
4. IF the configured token TTL value is set to null, zero, or a negative number, THEN THE Auth_Module SHALL treat tokens as non-expiring by leaving the expires_at column null

### Requirement 5: Protected Route Access

**User Story:** As a developer, I want all API endpoints (except auth) to require a valid token, so that unauthenticated users cannot access financial data.

#### Acceptance Criteria

1. WHEN a request to a protected endpoint includes a valid, non-expired Bearer_Token in the Authorization header with the format "Bearer {token}", THE Sanctum_Middleware SHALL authenticate the request and make the user available via Auth::user(). Protected endpoints are all API routes except user registration and user login.
2. WHEN a request to a protected endpoint is missing the Authorization header, THE Sanctum_Middleware SHALL return an HTTP 401 response with an "Unauthenticated" message
3. WHEN a request to a protected endpoint includes an expired, invalid, or revoked Bearer_Token, THE Sanctum_Middleware SHALL return an HTTP 401 response with an "Unauthenticated" message indistinguishable from a missing-header rejection
4. WHEN a user is authenticated, THE Auth_Module SHALL enable the UserScope global scope to enforce data isolation by filtering all queries with the authenticated user's ID
5. WHEN a request to a protected endpoint includes an Authorization header that does not follow the "Bearer {token}" format, THE Sanctum_Middleware SHALL return an HTTP 401 response with an "Unauthenticated" message

### Requirement 6: Rate Limiting

**User Story:** As a system operator, I want authentication endpoints to be rate-limited, so that the system is protected against brute-force and credential stuffing attacks.

#### Acceptance Criteria

1. WHEN a client exceeds 5 registration requests per minute from the same IP address, THE Rate_Limiter SHALL reject subsequent requests with an HTTP 429 response including a Retry-After header whose value is the number of seconds remaining until the current fixed 60-second window resets
2. WHEN a client exceeds 5 login requests per minute for the same case-insensitive email and IP combination, THE Rate_Limiter SHALL reject subsequent requests with an HTTP 429 response including a Retry-After header whose value is the number of seconds remaining until the current fixed 60-second window resets
3. WHEN the fixed 60-second rate limit window expires, THE Rate_Limiter SHALL reset the request counter to zero and resume accepting requests from the previously-limited client
4. WHEN THE Rate_Limiter rejects a request, THE Rate_Limiter SHALL return a JSON response body containing an error message indicating the rate limit has been exceeded and the number of seconds until the client may retry

### Requirement 7: Security Properties

**User Story:** As a security-conscious user, I want my credentials to be stored securely and error responses to not leak information, so that my account is protected from common attack vectors.

#### Acceptance Criteria

1. THE Auth_Module SHALL store passwords exclusively as bcrypt hashes with cost factor 12 in the database, never in plaintext
2. THE Auth_Module SHALL exclude the password field from all API response serializations via the User_Model $hidden array
3. THE Auth_Module SHALL never include the plaintext password in application logs or error messages
4. IF a login attempt fails, THEN THE Auth_Module SHALL perform a bcrypt hash comparison against a fixed dummy hash when the user is not found, ensuring that the response time difference between "user not found" and "wrong password" failures does not exceed 50 milliseconds
5. IF a login attempt fails, THEN THE Auth_Module SHALL return an identical response structure and message for all failure reasons (invalid email, wrong password), providing no indication of which credential component was incorrect

### Requirement 8: Authenticated User Profile

**User Story:** As an authenticated user, I want to retrieve my profile information, so that the frontend can display my identity and manage session state.

#### Acceptance Criteria

1. WHEN an authenticated user sends a GET request to the user profile endpoint, THE Auth_Module SHALL return an HTTP 200 response containing only the user's id, name, and email fields
2. WHEN an unauthenticated user sends a GET request to the user profile endpoint, THE Sanctum_Middleware SHALL return an HTTP 401 response with an "Unauthenticated" message
3. IF the authenticated user's account has been soft-deleted between token issuance and the profile request, THEN THE Auth_Module SHALL return an HTTP 401 response and revoke the current token
4. WHEN an authenticated user sends a GET request to the user profile endpoint, THE Auth_Module SHALL include the token's expires_at timestamp in the response so the frontend can manage session expiration proactively
