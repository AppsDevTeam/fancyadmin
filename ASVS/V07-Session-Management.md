# V7 Session Management

OWASP Application Security Verification Standard 5.0.0

## V7.1 Session Management Documentation

There is no single pattern that suits all applications. Therefore, it is not feasible to define universal boundaries and limits that suit all cases. A risk analysis with documented security decisions related to session handling must be conducted as a prerequisite to implementation and testing. This ensures that the session management system is tailored to the specific requirements of the application. Regardless of whether a stateful or "stateless"session mechanism is chosen, the analysis must be complete and documented to demonstrate that the selected solution is capable of satisfying all rel‑ evant security requirements. Interaction with any Single Sign‑on (SSO) mechanisms in use should also be considered.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.1.1 | 2 | Verify that the user's session inactivity timeout and absolute maximum session lifetime are documented, are appropriate in combination with other controls, and that the documentation includes justification for any deviations from NIST SP 800‑63B re‑authentication requirements. | Partial | Inactivity timeout: 14 days (configurable via SessionExpirationCallback). Absolute lifetime: same as inactivity (extended on each request). Documentation not yet formalized. | Formalize session timeout documentation with justification. |
| 7.1.2 | 2 | Verify that the documentation defines how many concurrent (parallel) sessions are allowed for one account as well as the intended behaviors and actions to be taken when the maximum number of active sessions is reached. | Partial | No limit on concurrent sessions. Documentation not yet formalized. | Document concurrent session policy and intended behavior. |
| 7.1.3 | 2 | Verify that all systems that create and manage user sessions as part of a federated identity management ecosystem (such as SSO systems) are documented along with controls to coordinate session lifetimes, termination, and any other conditions that require re‑authentication. | Out of scope | No federated identity management / SSO in use. | — |

## V7.2 Fundamental Session Management Security

This section satisfies the essential requirements of secure sessions by verifying that session tokens are securely generated and validated.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.2.1 | 1 | Verify that the application performs all session token verification using a trusted, backend service. | Compliant | All session token verification in DoctrineAuthenticator backend. Token looked up via SHA-256 hash in database. | — |
| 7.2.2 | 1 | Verify that the application uses either self‑contained or reference tokens that are dynamically generated for session management, i.e. not using static API secrets and keys. | Compliant | Reference tokens dynamically generated per session via Nette\Utils\Random::generate(32). | — |
| 7.2.3 | 1 | Verify that if reference tokens are used to represent user sessions, they are unique and generated using a cryptographically secure pseudo‑random number generator (CSPRNG) and possess at least 128 bits of entropy. | Compliant | 32-character random tokens generated via CSPRNG (Random::generate). 128+ bits of entropy. | — |
| 7.2.4 | 1 | Verify that the application generates a new session token on user authentication, including re‑authentication, and terminates the current session token. | Compliant | New session token generated on each authentication via sleepIdentity(). Previous token not reused. | — |

## V7.3 Session Timeout

Session timeout mechanisms serve to minimize the window of opportunity for session hijacking and other forms of session abuse. Timeouts must satisfy documented security decisions.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.3.1 | 2 | Verify that there is an inactivity timeout such that re‑authentication is enforced according to risk analysis and documented security decisions. | Compliant | Session inactivity managed via validUntil on StorageEntity. Extended on each request. Configurable expiration (default 14 days). | — |
| 7.3.2 | 2 | Verify that there is an absolute maximum session lifetime such that re‑authentication is enforced according to risk analysis and documented security decisions. | Compliant | Absolute session lifetime via validUntil field. SessionExpirationCallback allows per-identity expiration. | — |

## V7.4 Session Termination

Session termination may be handled either by the application itself or by the SSO provider if the SSO provider is handling session management instead of the application. It may be necessary to decide whether the SSO provider is in scope when considering the requirements in this section as some may be controlled by the provider. Session termination should result in requiring re‑authentication and be effective across the applica‑ tion, federated login (if present), and any relying parties. For stateful session mechanisms, termination typically involves invalidating the session on the back‑ end. In the case of self‑contained tokens, additional measures are required to revoke or block these tokens, as they may otherwise remain valid until expiration.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.4.1 | 1 | Verify that when session termination is triggered (such as logout or expiration), the application disallows any further use of the session. For reference tokens or stateful sessions, this means invalidating the session data at the application backend. Applications using self‑contained tokens will need a solution such as maintaining a list of terminated tokens, disallowing tokens produced before a per‑user date and time or rotating a per‑user signing key. | Compliant | Logout sets validUntil to now via clearIdentity(). Token invalidated in database. Further use blocked by validUntil check in wakeupIdentity(). | — |
| 7.4.2 | 1 | Verify that the application terminates all active sessions when a user account is disabled or deleted (such as an employee leaving the company). | Compliant | clearIdentity(objectId) invalidates all sessions for a user by setting validUntil to now. | — |
| 7.4.3 | 2 | Verify that the application gives the option to terminate all other active sessions after a successful change or removal of any authentication factor (including password change via reset or recovery and, if present, an MFA settings update). | Compliant | Password change in NewPasswordFormTrait calls clearIdentity() which invalidates all sessions, then creates a new session for the current user. 'Logout all devices' also available on Account page. | — |
| 7.4.4 | 2 | Verify that all pages that require authentication have easy and visible access to logout functionality. | Compliant | Logout button in profile dropdown menu and side menu, visible on all authenticated pages. | — |
| 7.4.5 | 2 | Verify that application administrators are able to terminate active sessions for an individual user or for all users. | Compliant | Admin can manage sessions via clearIdentity(objectId). clearSession(sessionId) for individual session termination. | — |

## V7.5 Defenses Against Session Abuse

This section provides requirements to mitigate the risk posed by active sessions that are either hi‑ jacked or abused through vectors that rely on the existence and capabilities of active user sessions. For example, using malicious content execution to force an authenticated victim browser to perform an action using the victim's session. Note that the level‑specific guidance in the "Authentication"chapter should be taken into account when considering requirements in this section.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.5.1 | 2 | Verify that the application requires full re‑authentication before allowing modifications to sensitive account attributes which may affect authentication such as email address, phone number, MFA configuration, or other information used in account recovery. | Partial | No re-authentication required before changing email or other sensitive attributes. | Implement re-authentication (password confirmation) before sensitive account changes. |
| 7.5.2 | 2 | Verify that users are able to view and (having authenticated again with at least one factor) terminate any or all currently active sessions. | Compliant | Active sessions visible on Account page with IP, User-Agent, creation date, validity. Users can terminate individual sessions via clearSession(). | — |
| 7.5.3 | 3 | Verify that the application requires further authentication with at least one factor or secondary verification before performing highly sensitive transactions or operations. | | | |

## V7.6 Federated Re‑authentication

This section relates to those writing Relying Party (RP) or Identity Provider (IdP) code. These re‑ quirements are derived from the NIST SP 800‑63C for Federation & Assertions.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 7.6.1 | 2 | Verify that session lifetime and termination between Relying Parties (RPs) and Identity Providers (IdPs) behave as documented, requiring re‑authentication as necessary such as when the maximum time between IdP authentication events is reached. | Out of scope | No federated identity management / SSO in use. | — |
| 7.6.2 | 2 | Verify that creation of a session requires either the user's consent or an explicit action, preventing the creation of new application sessions without user interaction. | Compliant | Session is only created after explicit user login action (form submission with credentials). | — |

---

**Total requirements in this chapter: 19**
- Level 1: 6
- Level 2: 12
- Level 3: 1
