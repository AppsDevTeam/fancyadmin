# V9 Self‑contained Tokens

OWASP Application Security Verification Standard 5.0.0

## V9.1 Token source and integrity

This section includes requirements to ensure that the token has been produced by a trusted party and has not been tampered with.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 9.1.1 | 1 | Verify that self‑contained tokens are validated using their digital signature or MAC to protect against tampering before accepting the token’s contents. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |
| 9.1.2 | 1 | Verify that only algorithms on an allowlist can be used to create and verify self‑contained tokens, for a given context. The allowlist must include the permitted algorithms, ideally only either symmetric or asymmetric algorithms, and must not include the ‘None’algorithm. If both symmetric and asymmetric must be supported, additional controls will be needed to prevent key confusion. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |
| 9.1.3 | 1 | Verify that key material that is used to validate self‑contained tokens is from trusted pre‑configured sources for the token issuer, preventing attackers from specifying untrusted sources and keys. For JWTs and other JWS structures, headers such as ‘jku’, ‘x5u’, and ‘jwk’must be validated against an allowlist of trusted sources. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |

## V9.2 Token content

Before making security decisions based on the content of a self‑contained token, it is necessary to validate that the token has been presented within its validity period and that it is intended for use by the receiving service and for the purpose for which it was presented. This helps avoid insecure cross‑usage between different services or with different token types from the same issuer. Specific requirements for OAuth and OIDC are covered in the dedicated chapter.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 9.2.1 | 1 | Verify that, if a validity time span is present in the token data, the token and its content are accepted only if the verification time is within this validity time span. For example, for JWTs, the claims ‘nbf’and ‘exp’must be verified. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |
| 9.2.2 | 2 | Verify that the service receiving a token validates the token to be the correct type and is meant for the intended purpose before accepting the token’s contents. For example, only access tokens can be accepted for authorization decisions and only ID Tokens can be used for proving user authentication. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |
| 9.2.3 | 2 | Verify that the service only accepts tokens which are intended for use with that service (audience). For JWTs, this can be achieved by validating the ‘aud’ claim against an allowlist defined in the service. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |
| 9.2.4 | 2 | Verify that, if a token issuer uses the same private key for issuing tokens to different audiences, the issued tokens contain an audience restriction that uniquely identifies the intended audiences. This will prevent a token from being reused with an unintended audience. If the audience identifier is dynamically provisioned, the token issuer must validate these audiences in order to make sure that they do not result in audience impersonation. | N/A | Application uses reference tokens (DoctrineAuthenticator), not self-contained tokens like JWT/SAML. |

---

**Total requirements in this chapter: 7**
- Level 1: 4
- Level 2: 3
- Level 3: 0
