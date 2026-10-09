<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;

/**
 * Keycloak s podvrzenym PAR endpointem.
 *
 * Guzzle je v balicku jen "suggest", takze se misto HTTP volani zaznamena, co by se
 * na PAR endpoint poslalo, a vrati se nastavene `request_uri` (null = Keycloak odmitl).
 */
final class TestParKeycloak extends Keycloak
{
	public ?string $requestUri = 'urn:ietf:params:oauth:request_uri:abc123';

	/** @var array<int, array<string, string>> */
	public array $pushed = [];

	protected function pushAuthorizationRequest(array $parameters): ?string
	{
		$this->pushed[] = $parameters;

		return $this->requestUri;
	}
}
