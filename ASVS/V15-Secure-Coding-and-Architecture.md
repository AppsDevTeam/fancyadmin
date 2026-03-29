# V15 Secure Coding and Architecture

OWASP Application Security Verification Standard 5.0.0

## V15.1 Secure Coding and Architecture Documentation

Many requirements for establishing a secure and defensible architecture depend on clear documen‑ tation of decisions made regarding the implementation of specific security controls and the compo‑ nents used within the application. This section outlines the documentation requirements, including identifying components consid‑ ered to contain "dangerous functionality"or to be "risky components."

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 15.1.1 | 1 | Verify that application documentation defines risk based remediation time frames for 3rd party component versions with vulnerabilities and for updating libraries in general, to minimize the risk from these components. | Partial | No formal vulnerability remediation policy. Composer dependencies updated periodically. | Define a formal vulnerability remediation policy with time frames. |
| 15.1.2 | 2 | Verify that an inventory catalog, such as software bill of materials (SBOM), is maintained of all third‑party libraries in use, including verifying that components come from pre‑defined, trusted, and continually maintained repositories. | Partial | composer.lock serves as dependency inventory. No formal SBOM generated. | Generate a formal SBOM from composer.lock (e.g., using CycloneDX Composer plugin). |
| 15.1.3 | 2 | Verify that the application documentation identifies functionality which is time‑consuming or resource‑demanding. This must include how to prevent a loss of availability due to overusing this functionality and how to avoid a situation where building a response takes longer than the consumer's timeout. Potential defenses may include asynchronous processing, using queues, and limiting parallel processes per user and per application. | Partial | Sensitive data flows not formally documented. Known flows: authentication, password reset, personal data editing. | Document resource-intensive functionality and mitigation strategies. |
| 15.1.4 | 3 | Verify that application documentation highlights third‑party libraries which are considered to be "risky components". | | | |
| 15.1.5 | 3 | Verify that application documentation highlights parts of the application where "dangerous functionality"is being used. | | | |

## V15.2 Security Architecture and Dependencies

This section includes requirements for handling risky, outdated, or insecure dependencies and com‑ ponents through dependency management. It also includes using architectural‑level techniques such as sandboxing, encapsulation, container‑ ization, and network isolation to reduce the impact of using "dangerous operations"or "risky compo‑

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 15.2.1 | 1 | Verify that the application only contains components which have not breached the documented update and remediation time frames. | Compliant | All dependencies managed via Composer with known versions. No abandoned packages in use. | — |
| 15.2.2 | 2 | Verify that the application has implemented defenses against loss of availability due to functionality which is time‑consuming or resource‑demanding, based on the documented security decisions and strategies for this. | Partial | No automated dependency vulnerability scanning (e.g., composer audit). Should be added to CI/CD. | Add `composer audit` to CI/CD pipeline for automated vulnerability scanning. |
| 15.2.3 | 2 | Verify that the production environment only includes functionality that is required for the application to function, and does not expose extraneous functionality such as test code, sample snippets, and development functionality. | Compliant | Production environment only includes production dependencies. Dev dependencies excluded via composer install --no-dev. | — |
| 15.2.4 | 3 | Verify that third‑party components and all of their transitive dependencies are included from the expected repository, whether internally owned or an external source, and that there is no risk of a dependency confusion attack. | | | |
| 15.2.5 | 3 | Verify that the application implements additional protections around parts of the application which are documented as containing "dangerous functionality"or using third‑party libraries considered to be "risky components". This could include techniques such as sandboxing, encapsulation, containerization or network level isolation to delay and deter attackers who compromise one part of an application from pivoting elsewhere in the application. | | | |

## V15.3 Defensive Coding

This section covers vulnerability types, including type juggling, prototype pollution, and others, which result from using insecure coding patterns in a particular language. Some may not be relevant to all languages, whereas others will have language‑specific fixes or may relate to how a particular language or framework handles a feature such as HTTP parameters. It also considers the risk of not cryptographically validating application updates. It also considers the risks associated with using objects to represent data items and accepting and returning these via external APIs. In this case, the application must ensure that data fields that should not be writable are not modified by user input (mass assignment) and that the API is selective about what data fields get returned. Where field access depends on a user's permissions, this should be considered in the context of the field‑level access control requirement in the Authorization chapter.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 15.3.1 | 1 | Verify that the application only returns the required subset of fields from a data object. For example, it should not return an entire data object, as some individual fields should not be accessible to users. | Compliant | Doctrine queries return only requested fields. Presenters pass only needed data to templates. | — |
| 15.3.2 | 2 | Verify that where the application backend makes calls to external URLs, it is configured to not follow redirects unless it is intended functionality. | Compliant | External API calls (mail service) use validated, hardcoded endpoints. No SSRF vectors. | — |
| 15.3.3 | 2 | Verify that the application has countermeasures to protect against mass assignment attacks by limiting allowed fields per controller and action, e.g., it is not possible to insert or update a field value when it was not intended to be part of that action. | Partial | No explicit mass assignment protection beyond Nette form validation. Doctrine entities set properties individually. | Audit entity update flows for mass assignment risks. Ensure only form-defined fields are persisted. |
| 15.3.4 | 2 | Verify that all proxying and middleware components transfer the user's original IP address correctly using trusted data fields that cannot be manipulated by the end user, and the application and web server use this correct value for logging and security decisions such as rate limiting, taking into account that even the original IP address may not be reliable due to dynamic IPs, VPNs, or corporate firewalls. | Out of scope | No proxying or middleware components that transfer client info. | — |
| 15.3.5 | 2 | Verify that the application explicitly ensures that variables are of the correct type and performs strict equality and comparator operations. This is to avoid type juggling or type confusion vulnerabilities caused by the application code making an assumption about a variable type. | Compliant | PHP initializes variables explicitly. Nette framework uses strict types throughout. | — |
| 15.3.6 | 2 | Verify that JavaScript code is written in a way that prevents prototype pollution, for example, by using Set() or Map() instead of object literals. | Partial | Client-side JS uses standard patterns. No comprehensive prototype pollution audit. | Audit client-side JavaScript for prototype pollution vulnerabilities. |
| 15.3.7 | 2 | Verify that the application has defenses against HTTP parameter pollution attacks, particularly if the application framework makes no distinction about the source of request parameters (query string, body parameters, cookies, or header fields). | Compliant | Nette framework handles HTTP parameter parsing. No duplicate parameter vulnerability. | — |

## V15.4 Safe Concurrency

Concurrency issues such as race conditions, time‑of‑check to time‑of‑use (TOCTOU) vulnerabilities, deadlocks, livelocks, thread starvation, and improper synchronization can lead to unpredictable be‑ havior and security risks. This section includes various techniques and strategies to help mitigate these risks.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 15.4.1 | 3 | Verify that shared objects in multi‑threaded code (such as caches, files, or in‑memory objects accessed by multiple threads) are accessed safely by using thread‑safe types and synchronization mechanisms like locks or semaphores to avoid race conditions and data corruption. | | | |
| 15.4.2 | 3 | Verify that checks on a resource's state, such as its existence or permissions, and the actions that depend on them are performed as a single atomic operation to prevent time‑of‑check to time‑of‑use (TOCTOU) race conditions. For example, checking if a file exists before opening it, or verifying a user's access before granting it. | | | |
| 15.4.3 | 3 | Verify that locks are used consistently to avoid threads getting stuck, whether by waiting on each other or retrying endlessly, and that locking logic stays within the code responsible for managing the resource to ensure locks cannot be inadvertently or maliciously modified by external classes or code. | | | |
| 15.4.4 | 3 | Verify that resource allocation policies prevent thread starvation by ensuring fair access to resources, such as by leveraging thread pools, allowing lower‑priority threads to proceed within a reasonable timeframe. | | | |

---

**Total requirements in this chapter: 21**
- Level 1: 3
- Level 2: 10
- Level 3: 8
