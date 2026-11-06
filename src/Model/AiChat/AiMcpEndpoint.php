<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat;

use ADT\AiChat\Mcp\McpEndpoint;
use ADT\AiChat\Mcp\McpResponse;
use Nette\Http\IRequest;

/**
 * MCP endpoint AI asistenta: Nette požadavek → McpEndpoint z adt/ai-chat (Bearer token
 * AiMcpToken, pravidla vlastníka v AiMcpTokenPolicy, pak McpServer).
 *
 * Bez session a bez přihlášení přes SecurityUser, takže požadavek nepotřebuje cookie ani CSRF.
 */
class AiMcpEndpoint
{
	public function __construct(
		private readonly McpEndpoint $endpoint,
	) {
	}

	public function handle(IRequest $request): McpResponse
	{
		return $this->endpoint->handle(
			$request->getMethod(),
			$request->getHeader('Authorization'),
			(string) $request->getRawBody(),
		);
	}
}
