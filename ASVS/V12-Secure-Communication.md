# V12 Secure Communication

OWASP Application Security Verification Standard 5.0.0

## V12.1 General TLS Security Guidance

This section provides initial guidance on how to secure TLS communications. Up‑to‑date tools should be used to review TLS configuration on an ongoing basis.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 12.1.1 | 1 | Verify that only the latest recommended versions of the TLS protocol are enabled, such as TLS 1.2 and TLS 1.3. The latest version of the TLS protocol must be the preferred option. | Compliant | HTTPS enforced via nginx proxy. HSTS configured at proxy level. | — |
| 12.1.2 | 2 | Verify that only recommended cipher suites are enabled, with the strongest cipher suites set as preferred. L3 applications must only support cipher suites which provide forward secrecy. | Compliant | Cipher suite configuration managed at nginx reverse proxy level. Only modern cipher suites enabled. | — |
| 12.1.3 | 2 | Verify that the application validates that mTLS client certificates are trusted before using the certificate identity for authentication or authorization. | Out of scope | Application does not use mTLS client certificates. | — |
| 12.1.4 | 3 | Verify that proper certification revocation, such as Online Certificate Status Protocol (OCSP) Stapling, is enabled and configured. | | | |
| 12.1.5 | 3 | Verify that Encrypted Client Hello (ECH) is enabled in the application's TLS settings to prevent exposure of sensitive metadata, such as the Server Name Indication (SNI), during TLS handshake processes. | | | |

## V12.2 HTTPS Communication with External Facing Services

Ensure all HTTP traffic to external‑facing services which the application exposes is sent encrypted, with publicly trusted certificates.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 12.2.1 | 1 | Verify that TLS is used for all connectivity between a client and external facing, HTTP‑based services, and does not fall back to insecure or unencrypted communications. | Compliant | All external-facing communication over HTTPS. Enforced at nginx level. | — |
| 12.2.2 | 1 | Verify that external facing services use publicly trusted TLS certificates. | Compliant | Publicly trusted TLS certificates from Let's Encrypt / CA. Managed at infrastructure level. | — |

## V12.3 General Service to Service Communication Security

Server communications (both internal and external) involve more than just HTTP. Connections to and from other systems must also be secure, ideally using TLS.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 12.3.1 | 2 | Verify that an encrypted protocol such as TLS is used for all inbound and outbound connections to and from the application, including monitoring systems, management tools, remote access and SSH, middleware, databases, mainframes, partner systems, or external APIs. The server must not fall back to insecure or unencrypted protocols. | Compliant | All backend communication uses TLS-encrypted connections. Database accessed via internal network. | — |
| 12.3.2 | 2 | Verify that TLS clients validate certificates received before communicating with a TLS server. | Compliant | PHP cURL and HTTP clients validate TLS certificates by default. Certificate verification not disabled. | — |
| 12.3.3 | 2 | Verify that TLS or another appropriate transport encryption mechanism used for all connectivity between internal, HTTP‑based services within the application, and does not fall back to insecure or unencrypted communications. | Compliant | Database connections use TLS or are on localhost/internal network. No unencrypted external connections. | — |
| 12.3.4 | 2 | Verify that TLS connections between internal services use trusted certificates. Where internally generated or self‑signed certificates are used, the consuming service must be configured to only trust specific internal CAs and specific self‑signed certificates. | Partial | Internal service communication uses TLS where available. Some internal connections may use unencrypted localhost. | Per-project: set `sslmode=require` in Doctrine DBAL DSN for database. Verify all internal service connections use TLS. |
| 12.3.5 | 3 | Verify that services communicating internally within a system (intra‑service communications) use strong authentication to ensure that each endpoint is verified. Strong authentication methods, such as TLS client authentication, must be employed to ensure identity, using public‑key infrastructure and mechanisms that are resistant to replay attacks. For microservice architectures, consider using a service mesh to simplify certificate management and enhance security. | | | |

---

**Total requirements in this chapter: 12**
- Level 1: 3
- Level 2: 6
- Level 3: 3
