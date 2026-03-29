# V17 WebRTC

OWASP Application Security Verification Standard 5.0.0

## V17.1 TURN Server

This section defines security requirements for systems that operate their own TURN (Traversal Us‑ ing Relays around NAT) servers. TURN servers assist in relaying media in restrictive network envi‑ ronments but can pose risks if misconfigured. These controls focus on secure address filtering and protection against resource exhaustion.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 17.1.1 | 2 | Verify that the Traversal Using Relays around NAT (TURN) service only allows access to IP addresses that are not reserved for special purposes (e.g., internal networks, broadcast, loopback). Note that this applies to both IPv4 and IPv6 addresses. | Out of scope | Application does not use WebRTC. WebSocket connections are project-specific, out of fancyadmin scope. | — |
| 17.1.2 | 3 | Verify that the Traversal Using Relays around NAT (TURN) service is not susceptible to resource exhaustion when legitimate users attempt to open a large number of ports on the TURN server. | Out of scope | Application does not use WebRTC. | — |

## V17.2 Media

These requirements only apply to systems that host their own WebRTC media servers, such as Selec‑ tive Forwarding Units (SFUs), Multipoint Control Units (MCUs), recording servers, or gateway servers. Media servers handle and distribute media streams, making their security critical to protect commu‑ nication between peers. Safeguarding media streams is paramount in WebRTC applications to pre‑ vent eavesdropping, tampering, and denial‑of‑service attacks that could compromise user privacy and communication quality.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 17.2.1 | 2 | Verify that the key for the Datagram Transport Layer Security (DTLS) certificate is managed and protected based on the documented policy for management of cryptographic keys. | Out of scope | Application does not use WebRTC. | — |
| 17.2.2 | 2 | Verify that the media server is configured to use and support approved Datagram Transport Layer Security (DTLS) cipher suites and a secure protection profile for the DTLS Extension for establishing keys for the Secure Real‑time Transport Protocol (DTLS‑SRTP). | Out of scope | Application does not use WebRTC. | — |
| 17.2.3 | 2 | Verify that Secure Real‑time Transport Protocol (SRTP) authentication is checked at the media server to prevent Real‑time Transport Protocol (RTP) injection attacks from leading to either a Denial of Service condition or audio or video media insertion into media streams. | Out of scope | Application does not use WebRTC. | — |
| 17.2.4 | 2 | Verify that the media server is able to continue processing incoming media traffic when encountering malformed Secure Real‑time Transport Protocol (SRTP) packets. | Out of scope | Application does not use WebRTC. | — |
| 17.2.5 | 3 | Verify that the media server is able to continue processing incoming media traffic during a flood of Secure Real‑time Transport Protocol (SRTP) packets from legitimate users. | Out of scope | Application does not use WebRTC. | — |
| 17.2.6 | 3 | Verify that the media server is not susceptible to the "ClientHello"Race Condition vulnerability in Datagram Transport Layer Security (DTLS) by checking if the media server is publicly known to be vulnerable or by performing the race condition test. | Out of scope | Application does not use WebRTC. | — |
| 17.2.7 | 3 | Verify that any audio or video recording mechanisms associated with the media server are able to continue processing incoming media traffic during a flood of Secure Real‑time Transport Protocol (SRTP) packets from legitimate users. | Out of scope | Application does not use WebRTC. | — |
| 17.2.8 | 3 | Verify that the Datagram Transport Layer Security (DTLS) certificate is checked against the Session Description Protocol (SDP) fingerprint attribute, terminating the media stream if the check fails, to ensure the authenticity of the media stream. | Out of scope | Application does not use WebRTC. | — |

## V17.3 Signaling

This section defines requirements for systems that operate their own WebRTC signaling servers. Sig‑ naling coordinates peer‑to‑peer communication and must be resilient against attacks that could dis‑ rupt session establishment or control. To ensure secure signaling, systems must handle malformed inputs gracefully and remain available under load.

| # | Level | Requirement | Status | How We Comply | What to Do |
|---|-------|-------------|--------|---------------|------------|
| 17.3.1 | 2 | Verify that the signaling server is able to continue processing legitimate incoming signaling messages during a flood attack. This should be achieved by implementing rate limiting at the signaling level. | Out of scope | Application does not use WebRTC. | — |
| 17.3.2 | 2 | Verify that the signaling server is able to continue processing legitimate signaling messages when encountering malformed signaling message that could cause a denial of service condition. This could include implementing input validation, safely handling integer overflows, preventing buffer overflows, and employing other robust error‑handling techniques. | Out of scope | Application does not use WebRTC. | — |

---

**Total requirements in this chapter: 12**
- Level 1: 0
- Level 2: 7
- Level 3: 5
