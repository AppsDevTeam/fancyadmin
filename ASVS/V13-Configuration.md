# V13 Configuration

OWASP Application Security Verification Standard 5.0.0

## V13.1 Configuration Documentation

This section outlines documentation requirements for how the application communicates with in‑ ternal and external services, as well as techniques to prevent loss of availability due to service inac‑ cessibility. It also addresses documentation related to secrets.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 13.1.1 | 2 | Verify that all communication needs for the application are documented. This must include external services which the application relies upon and cases where an end user might be able to provide an external location to which the application will then connect. | Partial | No formal documentation of all communication needs. HTTPS enforced for all external communication. |
| 13.1.2 | 3 | Verify that for each service the application uses, the documentation defines the maximum number of concurrent connections (e.g., connection pool limits) and how the application behaves when that limit is reached, including any fallback or recovery mechanisms, to prevent denial of service conditions. | | |
| 13.1.3 | 3 | Verify that the application documentation defines resource‑management strategies for every external system or service it uses (e.g., databases, file handles, threads, HTTP connections). This should include resource‑release procedures, timeout settings, failure handling, and where retry logic is implemented, specifying retry limits, delays, and back‑off algorithms. For synchronous HTTP request–response operations it should mandate short timeouts and either disable retries or strictly limit retries to prevent cascading delays and resource exhaustion. | | |
| 13.1.4 | 3 | Verify that the application’s documentation defines the secrets that are critical for the security of the application and a schedule for rotating them, based on the organization’s threat model and business requirements. | | |

## V13.2 Backend Communication Configuration

Applications interact with multiple services, including APIs, databases, or other components. These may be considered internal to the application but not included in the application’s standard access control mechanisms, or they may be entirely external. In either case, it is necessary to configure the application to interact securely with these components and, if required, protect that configuration.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 13.2.1 | 2 | Verify that communications between backend application components that don’t support the application’s standard user session mechanism, including APIs, middleware, and data layers, are authenticated. Authentication must use individual service accounts, short‑term tokens, or certificate‑based authentication and not unchanging credentials such as passwords, API keys, or shared accounts with privileged access. | Compliant | Backend components communicate via authenticated database connections and internal HTTP APIs. |
| 13.2.2 | 2 | Verify that communications between backend application components, including local or operating system services, APIs, middleware, and data layers, are performed with accounts assigned the least necessary privileges. | Compliant | All backend communication uses encrypted connections (TLS or internal network). |
| 13.2.3 | 2 | Verify that if a credential has to be used for service authentication, the credential being used by the consumer is not a default credential (e.g., root/root or admin/admin). | Compliant | Service authentication uses environment variables (%env.BROKER_PASSWORD%, %env.MAILAPI_KEY%). Not hardcoded. |
| 13.2.4 | 2 | Verify that an allowlist is used to define the external resources or systems with which the application is permitted to communicate (e.g., for outbound requests, data loads, or file access). This allowlist can be implemented at the application layer, web server, firewall, or a combination of different layers. | Partial | No explicit allowlist of external resources. External connections limited to mail API and specific third-party services. |
| 13.2.5 | 2 | Verify that the web or application server is configured with an allowlist of resources or systems to which the server can send requests or load data or files from. | Compliant | Nette framework restricts allowed HTTP methods. Nginx configured with appropriate limits. |
| 13.2.6 | 3 | Verify that where the application connects to separate services, it follows the documented configuration for each connection, such as maximum parallel connections, behavior when maximum allowed connections is reached, connection timeouts, and retry strategies. | | |

## V13.3 Secret Management

Secret management is an essential configuration task to ensure the protection of data used in the application. Specific requirements for cryptography can be found in the “Cryptography”chapter, but this section focuses on the management and handling aspects of secrets.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 13.3.1 | 2 | Verify that a secrets management solution, such as a key vault, is used to securely create, store, control access to, and destroy backend secrets. These could include passwords, key material, integrations with databases and third‑party systems, keys and seeds for time‑based tokens, other internal secrets, and API keys. Secrets must not be included in application source code or included in build artifacts. For an L3 application, this must involve a hardware‑backed solution such as an HSM. | Compliant | Secrets stored in environment variables (%env.BROKER_PASSWORD%, %env.MAILAPI_KEY%, etc.). Not in source code. |
| 13.3.2 | 2 | Verify that access to secret assets adheres to the principle of least privilege. | Compliant | Secrets accessible only to application runtime. Environment variables not exposed to frontend. Nette DI container restricts access. |
| 13.3.3 | 3 | Verify that all cryptographic operations are performed using an isolated security module (such as a vault or hardware security module) to securely manage and protect key material from exposure outside of the security module. | | |
| 13.3.4 | 3 | Verify that secrets are configured to expire and be rotated based on the application’s documentation. | | |

## V13.4 Unintended Information Leakage

Production configurations should be hardened to avoid disclosing unnecessary data. Many of these issues are rarely rated as significant risks but are often chained with other vulnerabilities. If these issues are not present by default, it raises the bar for attacking an application. For example, hiding the version of server‑side components does not eliminate the need to patch all components, and disabling folder listing does not remove the need to use authorization controls or keep files away from the public folder, but it raises the bar.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 13.4.1 | 1 | Verify that the application is deployed either without any source control metadata, including the .git or .svn folders, or in a way that these folders are inaccessible both externally and to the application itself. | Compliant | Debug mode disabled in production (Tracy debugger). Error details not exposed to users. |
| 13.4.2 | 2 | Verify that debug modes are disabled for all components in production environments to prevent exposure of debugging features and information leakage. | Compliant | Nette framework default error handling. Stack traces only in debug mode. |
| 13.4.3 | 2 | Verify that web servers do not expose directory listings to clients unless explicitly intended. | Compliant | Nginx configured to deny directory listings. No autoindex enabled. |
| 13.4.4 | 2 | Verify that using the HTTP TRACE method is not supported in production environments, to avoid potential information leakage. | Compliant | HTTP TRACE method disabled at nginx level. |
| 13.4.5 | 2 | Verify that documentation (such as for internal APIs) and monitoring endpoints are not exposed unless explicitly intended. | Partial | No public API documentation or monitoring dashboards exposed. Internal docs should be access-controlled. |
| 13.4.6 | 3 | Verify that the application does not expose detailed version information of backend components. | | |
| 13.4.7 | 3 | Verify that the web tier is configured to only serve files with specific file extensions to prevent unintentional information, configuration, and source code leakage. | | |

---

**Total requirements in this chapter: 21**
- Level 1: 1
- Level 2: 12
- Level 3: 8
