# V4 API and Web Service

OWASP Application Security Verification Standard 5.0.0

## V4.1 Generic Web Service Security

This section addresses general web service security considerations and, consequently, basic web service hygiene practices.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 4.1.1 | 1 | Verify that every HTTP response with a message body contains a Content‑Type header field that matches the actual content of the response, including the charset parameter to specify safe character encoding (e.g., UTF‑8, ISO‑8859‑1) according to IANA Media Types, such as “text/”, “/+xml” and “/xml”. | Compliant | Nette framework handles Content-Type validation. JSON APIs use Nette\Utils\Json. |
| 4.1.2 | 2 | Verify that only user‑facing endpoints (intended for manual web‑browser access) automatically redirect from HTTP to HTTPS, while other services or endpoints do not implement transparent redirects. This is to avoid a situation where a client is erroneously sending unencrypted HTTP requests, but since the requests are being automatically redirected to HTTPS, the leakage of sensitive data goes undiscovered. | Compliant | Application uses Nette presenters for user-facing endpoints. No separate machine-to-machine API endpoints exposed to users. |
| 4.1.3 | 2 | Verify that any HTTP header field used by the application and set by an intermediary layer, such as a load balancer, a web proxy, or a backend‑for‑frontend service, cannot be overridden by the end‑user. Example headers might include X‑Real‑IP, X‑Forwarded‑*, or X‑User‑ID. | Compliant | Standard HTTP headers used. Custom headers validated by Nette framework. |
| 4.1.4 | 3 | Verify that only HTTP methods that are explicitly supported by the application or its API (including OPTIONS during preflight requests) can be used and that unused methods are blocked. | | |
| 4.1.5 | 3 | Verify that per‑message digital signatures are used to provide additional assurance on top of transport protections for requests or transactions which are highly sensitive or which traverse a number of systems. | | |

## V4.2 HTTP Message Structure Validation

This section explains how the structure and header fields of an HTTP message should be validated to prevent attacks such as request smuggling, response splitting, header injection, and denial of service via overly long HTTP messages. These requirements are relevant for general HTTP message processing and generation, but are es‑ pecially important when converting HTTP messages between different HTTP versions.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 4.2.1 | 2 | Verify that all application components (including load balancers, firewalls, and application servers) determine boundaries of incoming HTTP messages using the appropriate mechanism for the HTTP version to prevent HTTP request smuggling. In HTTP/1.x, if a Transfer‑Encoding header field is present, the Content‑Length header must be ignored per RFC 2616. When using HTTP/2 or HTTP/3, if a Content‑Length header field is present, the receiver must ensure that it is consistent with the length of the DATA frames. | Compliant | Single Nette application. No mixed HTTP method interpretation. Nette router consistently handles methods. |
| 4.2.2 | 3 | Verify that when generating HTTP messages, the Content‑Length header field does not conflict with the length of the content as determined by the framing of the HTTP protocol, in order to prevent request smuggling attacks. | | |
| 4.2.3 | 3 | Verify that the application does not send nor accept HTTP/2 or HTTP/3 messages with connection‑specific header fields such as Transfer‑Encoding to prevent response splitting and header injection attacks. | | |
| 4.2.4 | 3 | Verify that the application only accepts HTTP/2 and HTTP/3 requests where the header fields and values do not contain any CR (\r), LF (\n), or CRLF (\r\n) sequences, to prevent header injection attacks. | | |
| 4.2.5 | 3 | Verify that, if the application (backend or frontend) builds and sends requests, it uses validation, sanitization, or other mechanisms to avoid creating URIs (such as for API calls) or HTTP request header fields (such as Authorization or Cookie), which are too long to be accepted by the receiving component. This could cause a denial of service, such as when sending an overly long request (e.g., a long cookie header field), which results in the server always responding with an error status. | | |

## V4.3 GraphQL

GraphQL is becoming more common as a way of creating data‑rich clients that are not tightly coupled to a variety of backend services. This section covers security considerations for GraphQL.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 4.3.1 | 2 | Verify that a query allowlist, depth limiting, amount limiting, or query cost analysis is used to prevent GraphQL or data layer expression Denial of Service (DoS) as a result of expensive, nested queries. | N/A | Application does not use GraphQL. |
| 4.3.2 | 2 | Verify that GraphQL introspection queries are disabled in the production environment unless the GraphQL API is meant to be used by other parties. | N/A | Application does not use GraphQL. |

## V4.4 WebSocket

WebSocket is a communications protocol that provides a simultaneous two‑way communication channel over a single TCP connection. It was standardized by the IETF as RFC 6455 in 2011 and is distinct from HTTP, even though it is designed to work over HTTP ports 443 and 80. This section provides key security requirements to prevent attacks related to communication security and session management that specifically exploit this real‑time communication channel.

| # | Level | Requirement | Status | How We Comply |
|---|-------|-------------|--------|---------------|
| 4.4.1 | 1 | Verify that WebSocket over TLS (WSS) is used for all WebSocket connections. | N/A | WebSocket usage is project-specific, not part of fancyadmin framework. |
| 4.4.2 | 2 | Verify that, during the initial HTTP WebSocket handshake, the Origin header field is checked against a list of origins allowed for the application. | N/A | WebSocket usage is project-specific, not part of fancyadmin framework. |
| 4.4.3 | 2 | Verify that, if the application’s standard session management cannot be used, dedicated tokens are being used for this, which comply with the relevant Session Management security requirements. | N/A | WebSocket usage is project-specific, not part of fancyadmin framework. |
| 4.4.4 | 2 | Verify that dedicated WebSocket session management tokens are initially obtained or validated through the previously authenticated HTTPS session when transitioning an existing HTTPS session to a WebSocket channel. | N/A | WebSocket usage is project-specific, not part of fancyadmin framework. |

---

**Total requirements in this chapter: 16**
- Level 1: 2
- Level 2: 8
- Level 3: 6
