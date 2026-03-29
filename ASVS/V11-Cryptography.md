# V11 Cryptography

OWASP Application Security Verification Standard 5.0.0

## V11.1 Cryptographic Inventory and Documentation

Applications need to be designed with strong cryptographic architecture to protect data assets ac‑ cording to their classification. Encrypting everything is wasteful; not encrypting anything is legally negligent. A balance must be struck, usually during architectural or high‑level design, design sprints, or architectural spikes. Designing cryptography “on the fly”or retrofitting it will inevitably cost much more to implement securely than simply building it in from the start. It is important to ensure that all cryptographic assets are regularly discovered, inventoried, and as‑ sessed. Please see the appendix for more information on how this can be done. The need to future‑proof cryptographic systems against the eventual rise of quantum computing is also critical. Post‑Quantum Cryptography (PQC) refers to cryptographic algorithms designed to

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.1.1 | 2 | Verify that there is a documented policy for management of cryptographic keys and a cryptographic key lifecycle that follows a key management standard such as NIST SP 800‑57. This should include ensuring that keys are not overshared (for example, with more than two entities for shared secrets and more than one entity for private keys). | Partial | No formal documented policy for cryptographic key management. Keys managed implicitly by framework and infrastructure. |
| 11.1.2 | 2 | Verify that a cryptographic inventory is performed, maintained, regularly updated, and includes all cryptographic keys, algorithms, and certificates used by the application. It must also document where keys can and cannot be used in the system, and the types of data that can and cannot be protected using the keys. | Partial | No cryptographic inventory maintained. Crypto usage: bcrypt (passwords), SHA-256 (session tokens), TLS (transport). |
| 11.1.3 | 3 | Verify that cryptographic discovery mechanisms are employed to identify all instances of cryptography in the system, including encryption, hashing, and signing operations. | | |
| 11.1.4 | 3 | Verify that a cryptographic inventory is maintained. This must include a documented plan that outlines the migration path to new cryptographic standards, such as post‑quantum cryptography, in order to react to future threats. | | |

## V11.2 Secure Cryptography Implementation

This section defines the requirements for the selection, implementation, and ongoing management of core cryptographic algorithms for an application. The objective is to ensure that only robust, industry‑accepted cryptographic primitives are deployed, in alignment with current standards (e.g., NIST, ISO/IEC) and best practices. Organizations must ensure that each cryptographic component is selected based on peer‑reviewed evidence and practical security testing.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.2.1 | 2 | Verify that industry‑validated implementations (including libraries and hardware‑accelerated implementations) are used for cryptographic operations. | Compliant | Using PHP built-in password_hash (bcrypt), hash('sha256') for tokens, and OpenSSL for TLS. All industry-validated implementations. |
| 11.2.2 | 2 | Verify that the application is designed with crypto agility such that random number, authenticated encryption, MAC, or hashing algorithms, key lengths, rounds, ciphers and modes can be reconfigured, upgraded, or swapped at any time, to protect against cryptographic breaks. Similarly, it must also be possible to replace keys and passwords and re‑encrypt data. This will allow for seamless upgrades to post‑quantum cryptography (PQC), once high‑assurance implementations of approved PQC schemes or standards are widely available. | Partial | No explicit crypto agility design. Changing algorithms would require code changes. |
| 11.2.3 | 2 | Verify that all cryptographic primitives utilize a minimum of 128‑bits of security based on the algorithm, key size, and configuration. For example, a 256‑bit ECC key provides roughly 128 bits of security where RSA requires a 3072‑bit key to achieve 128 bits of security. | Compliant | bcrypt (128-bit security), SHA-256 (256-bit), TLS 1.2+ (128-bit minimum). All meet 112-bit minimum. |
| 11.2.4 | 3 | Verify that all cryptographic operations are constant‑time, with no ‘short‑circuit’operations in comparisons, calculations, or returns, to avoid leaking information. | | |
| 11.2.5 | 3 | Verify that all cryptographic modules fail securely, and errors are handled in a way that does not enable vulnerabilities, such as Padding Oracle attacks. | | |

## V11.3 Encryption Algorithms

Authenticated encryption algorithms built on AES and CHACHA20 form the backbone of modern cryptographic practice.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.3.1 | 1 | Verify that insecure block modes (e.g., ECB) and weak padding schemes (e.g., PKCS#1 v1.5) are not used. | N/A | Application does not use block cipher modes directly. Encryption handled by TLS at transport layer. |
| 11.3.2 | 1 | Verify that only approved ciphers and modes such as AES with GCM are used. | N/A | Application does not perform application-level encryption. TLS cipher suite configured at nginx level. |
| 11.3.3 | 2 | Verify that encrypted data is protected against unauthorized modification preferably by using an approved authenticated encryption method or by combining an approved encryption method with an approved MAC algorithm. | N/A | No application-level encryption of stored data. Sensitive data protected by access control, not encryption at rest. |
| 11.3.4 | 3 | Verify that nonces, initialization vectors, and other single‑use numbers are not used for more than one encryption key and data‑element pair. The method of generation must be appropriate for the algorithm being used. | | |
| 11.3.5 | 3 | Verify that any combination of an encryption algorithm and a MAC algorithm is operating in encrypt‑then‑MAC mode. | | |

## V11.4 Hashing and Hash‑based Functions

Cryptographic hashes are used in a wide variety of cryptographic protocols, such as digital signatures, HMAC, key derivation functions (KDF), random bit generation, and password storage. The security of the cryptographic system is only as strong as the underlying hash functions used. This section outlines the requirements for using secure hash functions in cryptographic operations. For password storage, as well as the cryptography appendix, the OWASP Password Storage Cheat Sheet will also provide useful context and guidance.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.4.1 | 1 | Verify that only approved hash functions are used for general cryptographic use cases, including digital signatures, HMAC, KDF, and random bit generation. Disallowed hash functions, such as MD5, must not be used for any cryptographic purpose. | Compliant | Passwords hashed using bcrypt via PHP password_hash(). Nette Security default. |
| 11.4.2 | 2 | Verify that passwords are stored using an approved, computationally intensive, key derivation function (also known as a “password hashing function”), with parameter settings configured based on current guidance. The settings should balance security and performance to make brute‑force attacks sufficiently challenging for the required level of security. | Compliant | Passwords stored using bcrypt via PHP password_hash(PASSWORD_DEFAULT). Approved algorithm with automatic cost factor. |
| 11.4.3 | 2 | Verify that hash functions used in digital signatures, as part of data authentication or data integrity are collision resistant and have appropriate bit‑lengths. If collision resistance is required, the output length must be at least 256 bits. If only resistance to second pre‑image attacks is required, the output length must be at least 128 bits. | Compliant | SHA-256 used for session token hashing. Approved hash function for this use case. |
| 11.4.4 | 2 | Verify that the application uses approved key derivation functions with key stretching parameters when deriving secret keys from passwords. The parameters in use must balance security and performance to prevent brute‑force attacks from compromising the resulting cryptographic key. | N/A | No application-level key derivation. password_hash handles password KDF internally. |

## V11.5 Random Values

Cryptographically secure Pseudo‑random Number Generation (CSPRNG) is incredibly difficult to get right. Generally, good sources of entropy within a system will be quickly depleted if over‑used, but

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.5.1 | 2 | Verify that all random numbers and strings which are intended to be non‑guessable must be generated using a cryptographically secure pseudo‑random number generator (CSPRNG) and have at least 128 bits of entropy. Note that UUIDs do not respect this condition. | Compliant | Session tokens generated via Nette\Utils\Random::generate(32) which uses random_bytes() (CSPRNG). OTP tokens also use secure random generation. |
| 11.5.2 | 3 | Verify that the random number generation mechanism in use is designed to work securely, even under heavy demand. | | |

## V11.6 Public Key Cryptography

Public Key Cryptography will be used where it is not possible or not desirable to share a secret key between multiple parties. As part of this, there exists a need for approved key exchange mechanisms, such as Diffie‑Hellman and Elliptic Curve Diffie‑Hellman (ECDH) to ensure that the cryptosystem remains secure against modern threats. The “Secure Communication”chapter provides requirements for TLS so the require‑ ments in this section are intended for situations where Public Key Cryptography is being used in use cases other than TLS.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.6.1 | 2 | Verify that only approved cryptographic algorithms and modes of operation are used for key generation and seeding, and digital signature generation and verification. Key generation algorithms must not generate insecure keys vulnerable to known attacks, for example, RSA keys which are vulnerable to Fermat factorization. | N/A | No application-level public key cryptography. TLS handled at infrastructure level. |
| 11.6.2 | 3 | Verify that approved cryptographic algorithms are used for key exchange (such as Diffie‑Hellman) with a focus on ensuring that key exchange mechanisms use secure parameters. This will prevent attacks on the key establishment process which could lead to adversary‑in‑the‑middle attacks or cryptographic breaks. | | |

## V11.7 In‑Use Data Cryptography

Protecting data while it is being processed is paramount. Techniques such as full memory encryp‑ tion, encryption of data in transit, and ensuring data is encrypted as quickly as possible after use is recommended.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 11.7.1 | 3 | Verify that full memory encryption is in use that protects sensitive data while it is in use, preventing access by unauthorized users or processes. | | |
| 11.7.2 | 3 | Verify that data minimization ensures the minimal amount of data is exposed during processing, and ensure that data is encrypted immediately after use or as soon as feasible. | | |

---

**Total requirements in this chapter: 24**
- Level 1: 3
- Level 2: 11
- Level 3: 10
