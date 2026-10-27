<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Ai;

use ADT\AiChat\Mcp\McpResponse;
use ADT\FancyAdmin\Model\AiChat\AiMcpEndpoint;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\CallbackResponse;
use Nette\DI\Attributes\Inject;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

/**
 * MCP endpoint AI asistenta jako holý presenter (Nette\Application\IPresenter): bez
 * session, CSRF, kanonizace URL a přihlašování - ověření tokenu a práv dělá
 * AiMcpEndpoint. V projektu stačí:
 *
 *   final class McpPresenter implements \Nette\Application\IPresenter
 *   {
 *       use AiMcpPresenterTrait;
 *   }
 *
 * a routa na něj (např. `mcp`). Nesmí dědit z AuthPresenteru ani jiného presenteru
 * s přihlášením - ten by MCP klienta bez cookie přesměroval na přihlašovací stránku.
 */
trait AiMcpPresenterTrait
{
	#[Inject]
	public AiMcpEndpoint $aiMcpEndpoint;

	#[Inject]
	public IRequest $aiMcpHttpRequest;

	public function run(Request $request): Response
	{
		$response = $this->aiMcpEndpoint->handle($this->aiMcpHttpRequest);

		return new CallbackResponse(static function (IRequest $httpRequest, IResponse $httpResponse) use ($response): void {
			self::sendAiMcpResponse($httpResponse, $response);
		});
	}

	private static function sendAiMcpResponse(IResponse $httpResponse, McpResponse $response): void
	{
		$httpResponse->setCode($response->status);
		$httpResponse->setHeader('Cache-Control', 'no-store');
		// i u odpovědí bez těla (202, 204, 405), jinak by je Tracy brala jako HTML a připojila panel
		$httpResponse->setContentType('application/json', 'utf-8');
		foreach ($response->headers as $_name => $_value) {
			$httpResponse->setHeader($_name, $_value);
		}

		if ($response->body !== null) {
			echo $response->body;
		}
	}
}
