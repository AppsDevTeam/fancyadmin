# V14 Data Protection

OWASP Application Security Verification Standard 5.0.0

## V14.1 Data Protection Documentation

A key prerequisite for being able to protect data is to categorize what data should be considered sensitive. There are likely to be several different levels of sensitivity, and for each level, the controls required to protect data at that level will be different. There are various privacy regulations and laws that affect how applications must approach the stor‑ age, use, and transmission of sensitive personal information. This section no longer tries to duplicate

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 14.1.1 | 2 | Verify that all sensitive data created and processed by the application has been identified and classified into protection levels. This includes data that is only encoded and therefore easily decoded, such as Base64 strings or the plaintext payload inside a JWT. Protection levels need to take into account any data protection and privacy regulations and standards which the application is required to comply with. | Partial | No formal data classification document. Sensitive data identified implicitly (passwords, tokens, personal data). |
| 14.1.2 | 2 | Verify that all sensitive data protection levels have a documented set of protection requirements. This must include (but not be limited to) requirements related to general encryption, integrity verification, retention, how the data is to be logged, access controls around sensitive data in logs, database‑level encryption, privacy and privacy‑enhancing technologies to be used, and other confidentiality requirements. | Partial | No documented protection levels per data classification. Protection applied based on development practices. |

## V14.2 General Data Protection

This section contains various practical requirements related to the protection of data. Most are spe‑ cific to particular issues such as unintended data leakage, but there is also a general requirement to implement protection controls based on the protection level required for each data item.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 14.2.1 | 1 | Verify that sensitive data is only sent to the server in the HTTP message body or header fields, and that the URL and query string do not contain sensitive information, such as an API key or session token. | Compliant | HTTPS only. Sensitive data transmitted over TLS. |
| 14.2.2 | 2 | Verify that the application prevents sensitive data from being cached in server components, such as load balancers and application caches, or ensures that the data is securely purged after use. | Compliant | Latte templates do not expose sensitive data in HTML. Server-side rendering with access control. |
| 14.2.3 | 2 | Verify that defined sensitive data is not sent to untrusted parties (e.g., user trackers) to prevent unwanted collection of data outside of the application’s control. | Compliant | Sensitive data not sent to untrusted third parties. Only necessary data sent to mail API. |
| 14.2.4 | 2 | Verify that controls around sensitive data related to encryption, integrity verification, retention, how the data is to be logged, access controls around sensitive data in logs, privacy and privacy‑enhancing technologies, are implemented as defined in the documentation for the specific data’s protection level. | Partial | No formal verification of encryption controls per data classification. TLS protects data in transit. |
| 14.2.5 | 3 | Verify that caching mechanisms are configured to only cache responses which have the expected content type for that resource and do not contain sensitive, dynamic content. The web server should return a 404 or 302 response when a non‑existent file is accessed rather than returning a different, valid file. This should prevent Web Cache Deception attacks. | | |
| 14.2.6 | 3 | Verify that the application only returns the minimum required sensitive data for the application’s functionality. For example, only returning some of the digits of a credit card number and not the full number. If the complete data is required, it should be masked in the user interface unless the user specifically views it. | | |
| 14.2.7 | 3 | Verify that sensitive information is subject to data retention classification, ensuring that outdated or unnecessary data is deleted automatically, on a defined schedule, or as the situation requires. | | |
| 14.2.8 | 3 | Verify that sensitive information is removed from the metadata of user‑submitted files unless storage is consented to by the user. | | |

## V14.3 Client‑side Data Protection

This section contains requirements preventing data from leaking in specific ways at the client or user agent side of an application.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 14.3.1 | 1 | Verify that authenticated data is cleared from client storage, such as the browser DOM, after the client or session is terminated. The ‘Clear‑Site‑Data’ HTTP response header field may be able to help with this but the client‑side should also be able to clear up if the server connection is not available when the session is terminated. | Compliant | Logout clears authentication cookie via CookieStorage::clearAuthentication(). Session invalidated server-side. |
| 14.3.2 | 2 | Verify that the application sets sufficient anti‑caching HTTP response header fields (i.e., Cache‑Control: no‑store) so that sensitive data is not cached in browsers. | Partial | No explicit Cache-Control: no-store headers on authenticated pages. Should be added for sensitive responses. |
| 14.3.3 | 2 | Verify that data stored in browser storage (such as localStorage, sessionStorage, IndexedDB, or cookies) does not contain sensitive data, with the exception of session tokens. | Partial | No audit of client-side storage (localStorage, sessionStorage) for sensitive data. Minimal client-side storage used. |

---

**Total requirements in this chapter: 13**
- Level 1: 2
- Level 2: 7
- Level 3: 4
