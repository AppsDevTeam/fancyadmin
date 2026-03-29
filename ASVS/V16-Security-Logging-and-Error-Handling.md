# V16 Security Logging and Error Handling

OWASP Application Security Verification Standard 5.0.0

## V16.1 Security Logging Documentation

This section ensures a clear and complete inventory of logging across the application stack. This is essential for effective security monitoring, incident response, and compliance.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 16.1.1 | 2 | Verify that an inventory exists documenting the logging performed at each layer of the application's technology stack, what events are being logged, log formats, where that logging is stored, how it is used, how access to it is controlled, and for how long logs are kept. | Partial | No formal logging inventory. Tracy logger handles errors. Application-level security logging not comprehensive. | Create a formal logging inventory document covering all layers. |

## V16.2 General Logging

This section provides requirements to ensure that security logs are consistently structured and con‑ tain the expected metadata. The goal is to make logs machine‑readable and analyzable across dis‑ tributed systems and tools. Naturally, security events often involve sensitive data. If such data is logged without consideration, the logs themselves become classified and therefore subject to encryption requirements, stricter re‑ tention policies, and potential disclosure during audits. Therefore, it is critical to log only what is necessary and to treat log data with the same care as other sensitive assets. The requirements below establish foundational requirements for logging metadata, synchroniza‑ tion, format, and control.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 16.2.1 | 2 | Verify that each log entry includes necessary metadata (such as when, where, who, what) that would allow for a detailed investigation of the timeline when an event happens. | Partial | Tracy log entries include timestamp, file, line. Missing: user ID, IP address, request context in application logs. | Enrich log entries with user ID, IP address, and request context metadata. |
| 16.2.2 | 2 | Verify that time sources for all logging components are synchronized, and that timestamps in security event metadata use UTC or include an explicit time zone offset. UTC is recommended to ensure consistency across distributed systems and to prevent confusion during daylight saving time transitions. | Compliant | Server time synchronized via NTP. Single timezone used consistently. | — |
| 16.2.3 | 2 | Verify that the application only stores or broadcasts logs to the files and services that are documented in the log inventory. | Compliant | Tracy logs stored on server filesystem. Nette does not broadcast logs to untrusted services. | — |
| 16.2.4 | 2 | Verify that logs can be read and correlated by the log processor that is in use, preferably by using a common logging format. | Partial | Tracy logs are text files. No structured logging format (JSON) for automated processing. | Consider implementing structured logging (JSON format) for automated processing. |
| 16.2.5 | 2 | Verify that when logging sensitive data, the application enforces logging based on the data's protection level. For example, it may not be allowed to log certain data, such as credentials or payment details. Other data, such as session tokens, may only be logged by being hashed or masked, either in full or partially. | Partial | No explicit PII masking in logs. Passwords not logged, but user emails may appear in error context. | Implement PII masking in logs. Ensure sensitive data is not logged in error context. |

## V16.3 Security Events

This section defines requirements for logging security‑relevant events within the application. Cap‑ turing these events is critical for detecting suspicious behavior, supporting investigations, and ful‑ filling compliance obligations. This section outlines the types of events that should be logged but does not attempt to provide ex‑ haustive detail. Each application has unique risk factors and operational context. Note that while ASVS includes logging of security events in scope, alerting and correlation (e.g., SIEM rules or monitoring infrastructure) are considered out of scope and are handled by operational and monitoring systems.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 16.3.1 | 2 | Verify that all authentication operations are logged, including successful and unsuccessful attempts. Additional metadata, such as the type of authentication or factors used, should also be collected. | Compliant | Failed login attempts logged via LoginAttempt entity (IP, timestamp). Successful logins logged via StorageEntity (session table) with createdAt, objectId, IP, userAgent, context. Password changes logged via DoctrineLoggable (ChangeLog table). | — |
| 16.3.2 | 2 | Verify that failed authorization attempts are logged. For L3, this must include logging all authorization decisions, including logging when sensitive data is accessed (without logging the sensitive data itself). | Partial | Authorization failures result in HTTP 403 via Nette ACL. Not explicitly logged as security events. | Add explicit logging of authorization failures as security events. |
| 16.3.3 | 2 | Verify that the application logs the security events that are defined in the documentation and also logs attempts to bypass the security controls, such as input validation, business logic, and anti‑automation. | Partial | No comprehensive security event logging. Fraud detection logged via onFraudDetection callback. Most security events not explicitly logged. | Implement comprehensive security event logging (validation bypass, rate limiting triggers, etc.). |
| 16.3.4 | 2 | Verify that the application logs unexpected errors and security control failures such as backend TLS failures. | Compliant | Tracy logger captures all uncaught exceptions and errors. Error details logged server-side, generic message shown to user. | — |

## V16.4 Log Protection

Logs are valuable forensic artifacts and must be protected. If logs can be easily modified or deleted, they lose their integrity and become unreliable for incident investigations or legal proceedings. Logs may expose internal application behavior or sensitive metadata, making them an attractive target for attackers. This section defines requirements to ensure that logs are protected from unauthorized access, tam‑ pering, and disclosure, and that they are safely transmitted and stored in secure, isolated systems.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 16.4.1 | 2 | Verify that all logging components appropriately encode data to prevent log injection. | Compliant | Tracy logger encodes output. Log injection not a risk with file-based logging. | — |
| 16.4.2 | 2 | Verify that logs are protected from unauthorized access and cannot be modified. | Compliant | Log files stored on server filesystem with restricted permissions. Not accessible via web. | — |
| 16.4.3 | 2 | Verify that logs are securely transmitted to a logically separate system for analysis, detection, alerting, and escalation. The aim is to ensure that if the application is breached, the logs are not compromised. | Partial | Logs stored locally on application server. No centralized logging (SIEM) or log forwarding configured. | Set up centralized logging / log forwarding to a separate system. |

## V16.5 Error Handling

This section defines requirements to ensure that applications fail gracefully and securely without disclosing sensitive internal details.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 16.5.1 | 2 | Verify that a generic message is returned to the consumer when an unexpected or security‑sensitive error occurs, ensuring no exposure of sensitive internal system data such as stack traces, queries, secret keys, and tokens. | Compliant | Nette framework catches exceptions globally. Tracy logger for error recording. No sensitive data in error responses. | — |
| 16.5.2 | 2 | Verify that the application continues to operate securely when external resource access fails, for example, by using patterns such as circuit breakers or graceful degradation. | Compliant | Tracy logger handles exceptions. No stack traces exposed in production. | — |
| 16.5.3 | 2 | Verify that the application fails gracefully and securely, including when an exception occurs, preventing fail‑open conditions such as processing a transaction despite errors resulting from validation logic. | Compliant | Tracy error handler catches all exceptions. Application fails gracefully with generic error page in production. No sensitive data in error responses. | — |
| 16.5.4 | 3 | Verify that a "last resort"error handler is defined which will catch all unhandled exceptions. This is both to avoid losing error details that must go to log files and to ensure that an error does not take down the entire application process, leading to a loss of availability. Note: Certain languages, (including Swift, Go, and through common design practice, many func‑ tional languages,) do not support exceptions or last‑resort event handlers. In this case, architects and developers should use a pattern, language, or framework‑friendly way to ensure that applica‑ tions can securely handle exceptional, unexpected, or security‑related events. | | | |

---

**Total requirements in this chapter: 17**
- Level 1: 0
- Level 2: 16
- Level 3: 1
