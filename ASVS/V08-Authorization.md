# V8 Authorization

OWASP Application Security Verification Standard 5.0.0

## V8.1 Authorization Documentation

Comprehensive authorization documentation is essential to ensure that security decisions are con‑ sistently applied, auditable, and aligned with organizational policies. This reduces the risk of unau‑ thorized access by making security requirements clear and actionable for developers, administra‑ tors, and testers.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 8.1.1 | 1 | Verify that authorization documentation defines rules for restricting function‑level and data‑specific access based on consumer permissions and resource attributes. | Partial | ACL resources and roles documented in code. No formal authorization documentation. | Per-project: document all `AclResource` entries and which `AclRole` has access. Use `Permission` class as reference. |
| 8.1.2 | 2 | Verify that authorization documentation defines rules for field‑level access restrictions (both read and write) based on consumer permissions and resource attributes. Note that these rules might depend on other attribute values of the relevant data object, such as state or status. | Partial | Field-level access not formally documented. ACL handles resource-level access. | Per-project: document field-level access restrictions per `AclRole` for sensitive entity fields. |
| 8.1.3 | 3 | Verify that the application's documentation defines the environmental and contextual attributes (including but not limited to, time of day, user location, IP address, or device) that are used in the application to make security decisions, including those pertaining to authentication and authorization. | | | |
| 8.1.4 | 3 | Verify that authentication and authorization documentation defines how environmental and contextual factors are used in decision‑making, in addition to function‑level, data‑specific, and field‑level authorization. This should include the attributes evaluated, thresholds for risk, and actions taken (e.g., allow, challenge, deny, step‑up authentication). | | | |

## V8.2 General Authorization Design

Implementing granular authorization controls at the function, data, and field levels ensures that con‑ sumers can access only what has been explicitly granted to them.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 8.2.1 | 1 | Verify that the application ensures that function‑level access is restricted to consumers with explicit permissions. | Compliant | Nette Security with ACL-based authorization. Permission checks via SecurityUser and AclResource system. | — |
| 8.2.2 | 1 | Verify that the application ensures that data‑specific access is restricted to consumers with explicit permissions to specific data items to mitigate insecure direct object reference (IDOR) and broken object level authorization (BOLA). | Compliant | Doctrine security filters enforce data-specific access control per Account (tenant). Users only see data belonging to their Account. | — |
| 8.2.3 | 2 | Verify that the application ensures that field‑level access is restricted to consumers with explicit permissions to specific fields to mitigate broken object property level authorization (BOPLA). | Partial | Field-level access control not systematically implemented. ACL works at resource/action level. | Pending fancyadmin: implement field-level ACL. Currently `#[SecurityCheckAttribute]` and `Permission` work at resource/action level only. |
| 8.2.4 | 3 | Verify that adaptive security controls based on a consumer's environmental and contextual attributes (such as time of day, location, IP address, or device) are implemented for authentication and authorization decisions, as defined in the application's documentation. These controls must be applied when the consumer tries to start a new session and also during an existing session. | | | |

## V8.3 Operation Level Authorization

The immediate application of authorization changes in the appropriate tier of an application's archi‑ tecture is crucial to preventing unauthorized actions, especially in dynamic environments.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 8.3.1 | 1 | Verify that the application enforces authorization rules at a trusted service layer and doesn't rely on controls that an untrusted consumer could manipulate, such as client‑side JavaScript. | Compliant | Presenter-level authorization checks. ACL resources mapped to presenter actions. | — |
| 8.3.2 | 3 | Verify that changes to values on which authorization decisions are made are applied immediately. Where changes cannot be applied immediately, (such as when relying on data in self‑contained tokens), there must be mitigating controls to alert when a consumer performs an action when they are no longer authorized to do so and revert the change. Note that this alternative would not mitigate information leakage. | | | |
| 8.3.3 | 3 | Verify that access to an object is based on the originating subject's (e.g. consumer's) permissions, not on the permissions of any intermediary or service acting on their behalf. For example, if a consumer calls a web service using a self‑contained token for authentication, and the service then requests data from a different service, the second service will use the consumer's token, rather than a machine‑to‑machine token from the first service, to make permission decisions. | | | |

## V8.4 Other Authorization Considerations

Additional considerations for authorization, particularly for administrative interfaces and multi‑ tenant environments, help prevent unauthorized access.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 8.4.1 | 2 | Verify that multi‑tenant applications use cross‑tenant controls to ensure consumer operations will never affect tenants with which they do not have permissions to interact. | Compliant | Multi-tenant data isolation via Doctrine security filters. Queries automatically scoped to current Account. Cross-tenant access prevented at ORM level. | — |
| 8.4.2 | 3 | Verify that access to administrative interfaces incorporates multiple layers of security, including continuous consumer identity verification, device security posture assessment, and contextual risk analysis, ensuring that network location or trusted endpoints are not the sole factors for authorization even though they may reduce the likelihood of unauthorized access. | | | |

---

**Total requirements in this chapter: 13**
- Level 1: 4
- Level 2: 3
- Level 3: 6
