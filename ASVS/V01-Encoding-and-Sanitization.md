# V1 Encoding and Sanitization

OWASP Application Security Verification Standard 5.0.0

## V1.1 Encoding and Sanitization Architecture

In the sections below, syntax‑specific or interpreter‑specific requirements for safely processing un‑ safe content to avoid security vulnerabilities are provided. The requirements in this section cover the order in which this processing should occur and where it should take place. They also aim to ensure that whenever data is stored, it remains in its original state and is not stored in an encoded or escaped form (e.g., HTML encoding), to prevent double encoding issues.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 1.1.1 | 2 | Verify that input is decoded or unescaped into a canonical form only once, it is only decoded when encoded data in that form is expected, and that this is done before processing the input further, for example it is not performed after input validation or sanitization. | | |
| 1.1.2 | 2 | Verify that the application performs output encoding and escaping either as a final step before being used by the interpreter for which it is intended or by the interpreter itself. | | |

## V1.2 Injection Prevention

Output encoding or escaping, performed close to or adjacent to a potentially dangerous context, is critical to the security of any application. Typically, output encoding and escaping are not persisted, but are instead used to render output safe for immediate use in the appropriate interpreter. Attempt‑ ing to perform this too early may result in malformed content or render the encoding or escaping ineffective. In many cases, software libraries include safe or safer functions that perform this automatically, although it is necessary to ensure that they are correct for the current context.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 1.2.1 | 1 | Verify that output encoding for an HTTP response, HTML document, or XML document is relevant for the context required, such as encoding the relevant characters for HTML elements, HTML attributes, HTML comments, CSS, or HTTP header fields, to avoid changing the message or document structure. | Compliant | Latte templating engine auto-escapes output by context (HTML, JS, URL). Nette framework handles context-aware encoding. |
| 1.2.2 | 1 | Verify that when dynamically building URLs, untrusted data is encoded according to its context (e.g., URL encoding or base64url encoding for query or path parameters). Ensure that only safe URL protocols are permitted (e.g., disallow javascript: or data:). | Compliant | Nette\Application\LinkGenerator handles URL building with proper encoding. Latte uses context-aware escaping for URLs. |
| 1.2.3 | 1 | Verify that output encoding or escaping is used when dynamically building JavaScript content (including JSON), to avoid changing the message or document structure (to avoid JavaScript and JSON injection). | Compliant | Latte engine escapes JS context. Nette\Utils\Json for JSON encoding. |
| 1.2.4 | 1 | Verify that data selection or database queries (e.g., SQL, HQL, NoSQL, Cypher) use parameterized queries, ORMs, entity frameworks, or are otherwise protected from SQL Injection and other database injection attacks. This is also relevant when writing stored procedures. | Compliant | Doctrine ORM with parameterized queries (DQL, QueryBuilder). No raw SQL. |
| 1.2.5 | 1 | Verify that the application protects against OS command injection and that operating system calls use parameterized OS queries or use contextual command line output encoding. | Compliant | Doctrine ORM parameterized queries handle all database interactions. |
| 1.2.6 | 2 | Verify that the application protects against LDAP injection vulnerabilities, or that specific security controls to prevent LDAP injection have been implemented. | Compliant | No OS command execution in the application code. |
| 1.2.7 | 2 | Verify that the application is protected against XPath injection attacks by using query parameterization or precompiled queries. | | |
| 1.2.8 | 2 | Verify that LaTeX processors are configured securely (such as not using the “– shell‑escape”flag) and an allowlist of commands is used to prevent LaTeX injection attacks. | Compliant | Latte auto-escaping prevents XSS. Content rendered through Latte templates. |
| 1.2.9 | 2 | Verify that the application escapes special characters in regular expressions (typically using a backslash) to prevent them from being misinterpreted as metacharacters. | Compliant | HTTP response headers set via Nette\Http\Response with proper encoding. |
| 1.2.10 | 3 | Verify that the application is protected against CSV and Formula Injection. The application must follow the escaping rules defined in RFC 4180 sections 2.6 and 2.7 when exporting CSV content. Additionally, when exporting to CSV or other spreadsheet formats (such as XLS, XLSX, or ODF), special characters (including ‘=’, ‘+’, ‘‑’, ‘@’, ‘\t’(tab), and ‘\0’(null character)) must be escaped with a single quote if they appear as the first character in a field value. Note: Using parameterized queries or escaping SQL is not always sufficient. Query parts such as table and column names (including “ORDER BY”column names) cannot be escaped. Including escaped user‑supplied data in these fields results in failed queries or SQL injection. | | |

## V1.3 Sanitization

The ideal protection against using untrusted content in an unsafe context is to use context‑specific encoding or escaping, which maintains the same semantic meaning of the unsafe content but renders it safe for use in that particular context, as discussed in more detail in the previous section. Where this is not possible, sanitization becomes necessary, removing potentially dangerous charac‑ ters or content. In some cases, this may change the semantic meaning of the input, but for security reasons, there may be no alternative.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 1.3.1 | 1 | Verify that all untrusted HTML input from WYSIWYG editors or similar is sanitized using a well‑known and secure HTML sanitization library or framework feature. | | |
| 1.3.2 | 1 | Verify that the application avoids the use of eval() or other dynamic code execution features such as Spring Expression Language (SpEL). Where there is no alternative, any user input being included must be sanitized before being executed. | | |
| 1.3.3 | 2 | Verify that data being passed to a potentially dangerous context is sanitized beforehand to enforce safety measures, such as only allowing characters which are safe for this context and trimming input which is too long. | | |
| 1.3.4 | 2 | Verify that user‑supplied Scalable Vector Graphics (SVG) scriptable content is validated or sanitized to contain only tags and attributes (such as draw graphics) that are safe for the application, e.g., do not contain scripts and foreignObject. | | |
| 1.3.5 | 2 | Verify that the application sanitizes or disables user‑supplied scriptable or expression template language content, such as Markdown, CSS or XSL stylesheets, BBCode, or similar. | | |
| 1.3.6 | 2 | Verify that the application protects against Server‑side Request Forgery (SSRF) attacks, by validating untrusted data against an allowlist of protocols, domains, paths and ports and sanitizing potentially dangerous characters before using the data to call another service. | | |
| 1.3.7 | 2 | Verify that the application protects against template injection attacks by not allowing templates to be built based on untrusted input. Where there is no alternative, any untrusted input being included dynamically during template creation must be sanitized or strictly validated. | | |
| 1.3.8 | 2 | Verify that the application appropriately sanitizes untrusted input before use in Java Naming and Directory Interface (JNDI) queries and that JNDI is configured securely to prevent JNDI injection attacks. | | |
| 1.3.9 | 2 | Verify that the application sanitizes content before it is sent to memcache to prevent injection attacks. | | |
| 1.3.10 | 2 | Verify that format strings which might resolve in an unexpected or malicious way when used are sanitized before being processed. | | |
| 1.3.11 | 2 | Verify that the application sanitizes user input before passing to mail systems to protect against SMTP or IMAP injection. | | |
| 1.3.12 | 3 | Verify that regular expressions are free from elements causing exponential backtracking, and ensure untrusted input is sanitized to mitigate ReDoS or Runaway Regex attacks. | | |

## V1.4 Memory, String, and Unmanaged Code

The following requirements address risks associated with unsafe memory use, which generally apply when the application uses a systems language or unmanaged code. In some cases, it may be possible to achieve this by setting compiler flags that enable buffer overflow protections and warnings, including stack randomization and data execution prevention, and that break the build if unsafe pointer, memory, format string, integer, or string operations are found.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 1.4.1 | 2 | Verify that the application uses memory‑safe string, safer memory copy and pointer arithmetic to detect or prevent stack, buffer, or heap overflows. | | |
| 1.4.2 | 2 | Verify that sign, range, and input validation techniques are used to prevent integer overflows. | | |
| 1.4.3 | 2 | Verify that dynamically allocated memory and resources are released, and that references or pointers to freed memory are removed or set to null to prevent dangling pointers and use‑after‑free vulnerabilities. | | |

## V1.5 Safe Deserialization

The conversion of data from a stored or transmitted representation into actual application objects (deserialization) has historically been the cause of various code injection vulnerabilities. It is impor‑ tant to perform this process carefully and safely to avoid these types of issues. In particular, certain methods of deserialization have been identified by programming language or framework documentation as insecure and cannot be made safe with untrusted data. For each mech‑ anism in use, careful due diligence should be performed.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 1.5.1 | 1 | Verify that the application configures XML parsers to use a restrictive configuration and that unsafe features such as resolving external entities are disabled to prevent XML eXternal Entity (XXE) attacks. | Compliant | No custom deserialization of untrusted data. Nette\Utils\Json used for JSON parsing. |
| 1.5.2 | 2 | Verify that deserialization of untrusted data enforces safe input handling, such as using an allowlist of object types or restricting client‑defined object types, to prevent deserialization attacks. Deserialization mechanisms that are explicitly defined as insecure must not be used with untrusted input. | | |
| 1.5.3 | 3 | Verify that different parsers used in the application for the same data type (e.g., JSON parsers, XML parsers, URL parsers), perform parsing in a consistent way and use the same character encoding mechanism to avoid issues such as JSON Interoperability vulnerabilities or different URI or file parsing behavior being exploited in Remote File Inclusion (RFI) or Server‑side Request Forgery (SSRF) attacks. | | |

---

**Total requirements in this chapter: 30**
- Level 1: 8
- Level 2: 19
- Level 3: 3
