<?php

declare(strict_types=1);

use ADT\AiChat\Mcp\McpServer;
use ADT\AiChat\ToolExecutor;
use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\AiChat\AiMcpEndpoint;
use ADT\FancyAdmin\Model\Entities\AiMcpTokenTrait;
use ADT\FancyAdmin\Model\Security\ApiKeyHasher;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestAiMcpToken;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;

/**
 * MCP server AI asistenta: token, ověření a práva v AiMcpEndpoint.
 */

require __DIR__ . '/bootstrap.php';


const MCP_RAW_TOKEN = 'abcdefghijklmnopqrstuvwxyz012345';


final class McpFakeTools implements ToolExecutor
{
	public array $calls = [];

	public function handleToolCall(string $toolName, array $input): array
	{
		$this->calls[] = $toolName;

		return ['result' => [['n' => 1]], 'row_count' => 1];
	}

	public function getCustomToolDefinitions(): array
	{
		return [
			['type' => 'custom', 'name' => 'execute_sql', 'description' => 'SQL', 'input_schema' => ['type' => 'object', 'properties' => ['sql' => ['type' => 'string']], 'required' => ['sql']]],
			['type' => 'custom', 'name' => 'render_chart', 'description' => 'Chart', 'input_schema' => ['type' => 'object']],
		];
	}
}


/**
 * @param list<string> $resources
 */
function mcpIdentity(array $resources = ['portalBackoffice.dashboard', 'portalBackoffice.ai.mcp', 'fullData']): TestIdentity
{
	$role = new TestAclRole('editor');
	foreach ($resources as $_resource) {
		$role->allowResource(new TestAclResource($_resource));
	}

	$identity = new TestIdentity();
	$identity->setId(7);
	$identity->addRole($role);

	return $identity;
}


/**
 * @return array{0: AiMcpEndpoint, 1: TestEntityManager, 2: TestAiMcpToken, 3: McpFakeTools}
 */
function mcpEndpoint(?TestIdentity $identity = null, array $fancyAdmin = []): array
{
	$token = new TestAiMcpToken()
		->setId(3)
		->setName('Claude Code')
		->setTokenHash(ApiKeyHasher::hash(MCP_RAW_TOKEN))
		->setIdentity($identity ?? mcpIdentity());

	$em = new TestEntityManager([TestAiMcpToken::class]);
	$em->getRepository(TestAiMcpToken::class)->entities = [$token];

	$tools = new McpFakeTools();
	$endpoint = new AiMcpEndpoint(new McpServer($tools), $em, FancyAdminFactory::create($fancyAdmin), TestAiMcpToken::class);

	return [$endpoint, $em, $token, $tools];
}


function mcpRequest(?string $authorization = 'Bearer ' . MCP_RAW_TOKEN, array $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], string $method = 'POST'): Request
{
	return new Request(
		new UrlScript('https://admin.example.com/mcp'),
		headers: $authorization !== null ? ['Authorization' => $authorization] : [],
		method: $method,
		rawBodyCallback: fn() => json_encode($message),
	);
}


test('otisk tokenu se anonymizuje', function () {
	$attributes = new ReflectionProperty(AiMcpTokenTrait::class, 'tokenHash')->getAttributes(Anonymize::class);

	Assert::same(AnonymizationType::SECRET, $attributes[0]->newInstance()->type);
});


test('platnost tokenu', function () {
	$token = new TestAiMcpToken();

	Assert::false($token->isExpired());
	Assert::true($token->setExpiresAt(new DateTimeImmutable('-1 minute'))->isExpired());
	Assert::false($token->setExpiresAt(new DateTimeImmutable('+1 day'))->isExpired());
});


test('platny token projde na MCP server a zapise posledni pouziti', function () {
	[$endpoint, $em, $token] = mcpEndpoint();

	$response = $endpoint->handle(mcpRequest());

	Assert::same(200, $response->status);
	Assert::same(['execute_sql'], array_column(json_decode($response->body, true)['result']['tools'], 'name'));
	Assert::type(DateTimeImmutable::class, $token->getLastUsedAt());
	Assert::same(1, $em->flushCount);

	// hned další požadavek už do DB nepíše
	$endpoint->handle(mcpRequest());
	Assert::same(1, $em->flushCount);
});


test('chybejici, neplatny a propadly token je 401', function () {
	[$endpoint, , $token, $tools] = mcpEndpoint();

	foreach ([null, 'Basic xyz', 'Bearer spatny-token'] as $_header) {
		$response = $endpoint->handle(mcpRequest($_header));
		Assert::same(401, $response->status, (string) $_header);
		Assert::same('Bearer realm="mcp"', $response->headers['WWW-Authenticate']);
	}

	$token->setExpiresAt(new DateTimeImmutable('-1 second'));
	Assert::same(401, $endpoint->handle(mcpRequest())->status);
	Assert::same([], $tools->calls);
});


test('neaktivni identita nebo bez prava do portalu je 401', function () {
	$inactive = mcpIdentity();
	$inactive->setIsActive(false);
	Assert::same(401, mcpEndpoint($inactive)[0]->handle(mcpRequest())->status);

	Assert::same(401, mcpEndpoint(mcpIdentity(['portalBackoffice.ai.mcp', 'fullData']))[0]->handle(mcpRequest())->status);
});


test('bez prava na MCP je 403', function () {
	[$endpoint] = mcpEndpoint(mcpIdentity(['portalBackoffice.dashboard', 'fullData']));

	Assert::same(403, $endpoint->handle(mcpRequest())->status);
});


test('multi-tenant pusti jen s pravem na data vsech uctu, single-tenant i bez nej', function () {
	$withoutFullData = ['portalBackoffice.dashboard', 'portalBackoffice.ai.mcp'];

	Assert::same(403, mcpEndpoint(mcpIdentity($withoutFullData))[0]->handle(mcpRequest())->status);
	Assert::same(200, mcpEndpoint(mcpIdentity($withoutFullData), ['multiTenant' => false])[0]->handle(mcpRequest())->status);
});


test('volani nastroje jde pres ToolExecutor, nastroje jen pro chat ne', function () {
	[$endpoint, , , $tools] = mcpEndpoint();

	$ok = $endpoint->handle(mcpRequest(message: ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'execute_sql', 'arguments' => ['sql' => 'SELECT 1']]]));
	Assert::false(json_decode($ok->body, true)['result']['isError']);

	$chart = $endpoint->handle(mcpRequest(message: ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'render_chart', 'arguments' => []]]));
	Assert::same(McpServer::ERROR_INVALID_PARAMS, json_decode($chart->body, true)['error']['code']);

	Assert::same(['execute_sql'], $tools->calls);
});


test('GET je 405 i bez tokenu', function () {
	[$endpoint] = mcpEndpoint();

	Assert::same(405, $endpoint->handle(mcpRequest(null, method: 'GET'))->status);
});


test('texty sprava tokenu jsou v cs i sk', function () {
	$cs = Yaml::parseFile(__DIR__ . '/../src/lang/fcadmin.cs.yml')['presenters']['aiMcpTokens'];
	$sk = Yaml::parseFile(__DIR__ . '/../src/lang/fcadmin.sk.yml')['presenters']['aiMcpTokens'];

	Assert::same(array_keys($cs), array_keys($sk));
	Assert::contains('%token%', $cs['messages']['created']);
	Assert::contains('%token%', $sk['messages']['created']);
});
