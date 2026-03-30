# V2 Validation and Business Logic

OWASP Application Security Verification Standard 5.0.0

## V2.1 Validation and Business Logic Documentation

Validation and business logic documentation should clearly define business logic limits, validation rules, and contextual consistency of combined data items, so it is clear what needs to be implemented in the application.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 2.1.1 | 1 | Verify that the application's documentation defines input validation rules for how to check the validity of data items against an expected structure. This could be common data formats such as credit card numbers, email addresses, telephone numbers, or it could be an internal data format. | Partial | No formal documentation of input validation strategy. Nette Forms handle validation rules per-form. | Per-project: document validation rules for all form inputs — which `addRule()` rules apply to which fields. |
| 2.1.2 | 2 | Verify that the application's documentation defines how to validate the logical and contextual consistency of combined data items, such as checking that suburb and ZIP code match. | Partial | No formal documentation of unexpected input handling. Nette framework returns 400/404 for invalid requests. | Per-project: document cross-field/contextual validation rules (e.g., date ranges, related selects). |
| 2.1.3 | 2 | Verify that expectations for business logic limits and validations are documented, including both per‑user and globally across the application. | Partial | No formal documentation of business logic limits. Limits enforced in code but not documented. | Per-project: document business logic limits (rate limits via `setLoginAttemptProtection()`, quotas, max records). |

## V2.2 Input Validation

Effective input validation controls enforce business or functional expectations around the type of data the application expects to receive. This ensures good data quality and reduces the attack sur‑ face. However, it does not remove or replace the need to use correct encoding, parameterization, or sanitization when using the data in another component or for presenting it for output. In this context, "input"could come from a wide variety of sources, including HTML form fields, REST requests, URL parameters, HTTP header fields, cookies, files on disk, databases, and external APIs. A business logic control might check that a particular input is a number less than 100. A functional expectation might check that a number is below a certain threshold, as that number controls how many times a particular loop will take place, and a high number could lead to excessive processing and a potential denial of service condition.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 2.2.1 | 1 | Verify that input is validated to enforce business or functional expectations for that input. This should either use positive validation against an allow list of values, patterns, and ranges, or be based on comparing the input to an expected structure and logical limits according to predefined rules. For L1, this can focus on input which is used to make specific business or security decisions. For L2 and up, this should apply to all input. | Partial | Nette Forms provide server-side validation with rules. Not all inputs have business-level validation. | Per-project: audit all Nette form inputs — ensure every input has appropriate `addRule()` business validation. |
| 2.2.2 | 1 | Verify that the application is designed to enforce input validation at a trusted service layer. While client‑side validation improves usability and should be encouraged, it must not be relied upon as a security control. | Compliant | Nette Forms enforce server-side validation via addRule(). All form inputs validated on the trusted backend. | — |
| 2.2.3 | 2 | Verify that the application ensures that combinations of related data items are reasonable according to the pre‑defined rules. | Partial | Related input validation (e.g., password + password confirmation) handled in form validation. No comprehensive cross-field validation audit. | Per-project: audit cross-field validation in forms using `addConditionOn()` or custom `addRule()` callbacks. |

## V2.3 Business Logic Security

This section considers key requirements to ensure that the application enforces business logic pro‑ cesses in the correct way and is not vulnerable to attacks that exploit the logic and flow of the appli‑ cation.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 2.3.1 | 1 | Verify that the application will only process business logic flows for the same user in the expected sequential step order and without skipping steps. | Compliant | Business logic only processes authenticated, authorized requests. Presenter authorization checks via ACL. | — |
| 2.3.2 | 2 | Verify that business logic limits are implemented per the application's documentation to avoid business logic flaws being exploited. | Partial | Business logic limits enforced in code. Not formally documented per application documentation. | Per-project: document business logic limits formally (max records, max file count, operation quotas). |
| 2.3.3 | 2 | Verify that transactions are being used at the business logic level such that either a business logic operation succeeds in its entirety or it is rolled back to the previous correct state. | Compliant | Doctrine ORM uses database transactions for multi-step operations. | — |
| 2.3.4 | 2 | Verify that business logic level locking mechanisms are used to ensure that limited quantity resources (such as theater seats or delivery slots) cannot be double‑booked by manipulating the application's logic. | Partial | Database-level locking (SELECT FOR UPDATE) used where needed. No formal TOCTOU audit across all operations. | Per-project: audit for TOCTOU race conditions. Use Doctrine `LOCK_PESSIMISTIC_WRITE` for critical operations. |
| 2.3.5 | 3 | Verify that high‑value business logic flows require multi‑user approval to prevent unauthorized or accidental actions. This could include but is not limited to large monetary transfers, contract approvals, access to classified information, or safety overrides in manufacturing. | | | |

## V2.4 Anti‑automation

This section includes anti‑automation controls to ensure that human‑like interactions are required and excessive automated requests are prevented.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 2.4.1 | 2 | Verify that anti‑automation controls are in place to protect against excessive calls to application functions that could lead to data exfiltration, garbage‑data creation, quota exhaustion, rate‑limit breaches, denial‑of‑service, or overuse of costly resources. | Compliant | IP-based rate limiting via DoctrineAuthenticator::setLoginAttemptProtection(). Configurable max attempts and timeout. | — |
| 2.4.2 | 3 | Verify that business logic flows require realistic human timing, preventing excessively rapid transaction submissions. | | | |

---

**Total requirements in this chapter: 13**
- Level 1: 4
- Level 2: 7
- Level 3: 2
