<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Push;

use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Push přes Mercure hub (např. FrankenPHP). Topiky dostanou prefix projektu - hub může sdílet
 * víc projektů nebo klinik a token jedné nesmí odebírat cizí. Publikuje i odebírá se s JWT
 * (HS256, typ at+jwt, authorization_details podle RFC 9396 - moderní režim Mercure).
 */
final class MercurePush implements Push
{
	public function __construct(
		private readonly string $publishUrl,
		private readonly string $subscribeUrl,
		#[\SensitiveParameter]
		private readonly string $publisherKey,
		#[\SensitiveParameter]
		private readonly string $subscriberKey,
		private readonly string $topicPrefix,
		private readonly ?string $issuer = null,
		private readonly ?string $audience = null,
		private readonly int $tokenTtlSeconds = 43200,
		private readonly float $timeoutSeconds = 1.0,
	) {
	}

	public function publish(string $topic, array $data = []): void
	{
		$fullTopic = $this->fullTopic($topic);
		// Topik jde i do dat - SSE stream Mercure ho u zprávy neuvádí a stránka odebírá víc topiků;
		// origin = stránka, jejíž požadavek změnu udělal (push.js zprávu sám sobě přeskočí)
		$origin = self::origin();
		$body = http_build_query([
			'topic' => $fullTopic,
			'data' => json_encode(['topic' => $topic] + $data + ($origin !== null ? ['origin' => $origin] : []), JSON_THROW_ON_ERROR),
			'private' => 'on',
		]);
		$context = stream_context_create(['http' => [
			'method' => 'POST',
			'header' => implode("\r\n", [
				'Authorization: Bearer ' . $this->jwt('publish', [$fullTopic], 60, $this->publisherKey),
				'Content-Type: application/x-www-form-urlencoded',
			]),
			'content' => $body,
			'timeout' => $this->timeoutSeconds,
			'ignore_errors' => true,
		]]);

		$response = @file_get_contents($this->publishUrl, false, $context); // @ - selhání řeší log níž
		$headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : null;
		$status = (string) ($headers[0] ?? '');
		if ($response === false || !str_contains($status, ' 200')) {
			Debugger::log(sprintf('Mercure: změna %s se neodeslala (%s).', $fullTopic, $status !== '' ? $status : 'bez odpovědi'), ILogger::WARNING);
		}
	}

	public function subscription(array $topics): ?array
	{
		if ($topics === []) {
			return null;
		}
		$fullTopics = array_values(array_map($this->fullTopic(...), $topics));

		return [
			'url' => $this->subscribeUrl,
			'topics' => $fullTopics,
			'token' => $this->jwt('subscribe', $fullTopics, $this->tokenTtlSeconds, $this->subscriberKey),
		];
	}

	/** ID stránky z hlavičky push.js; v CLI (joby, cron) a u požadavků bez ní null */
	public static function origin(): ?string
	{
		$origin = $_SERVER['HTTP_X_FANCYADMIN_PUSH_CLIENT'] ?? null;

		return is_string($origin) && preg_match('/^[a-z0-9]{8,40}$/', $origin) === 1 ? $origin : null;
	}

	private function fullTopic(string $topic): string
	{
		return rtrim($this->topicPrefix, '/') . '/' . ltrim($topic, '/');
	}

	/** @param list<string> $topics */
	private function jwt(string $action, array $topics, int $ttl, string $key): string
	{
		$now = time();
		$claims = array_filter(['iss' => $this->issuer, 'aud' => $this->audience], static fn(?string $value): bool => $value !== null) + [
			'sub' => 'fancyadmin',
			'client_id' => 'fancyadmin',
			'iat' => $now,
			'exp' => $now + $ttl,
			'jti' => bin2hex(random_bytes(16)),
			'authorization_details' => [[
				'type' => 'https://mercure.rocks/authorization-detail',
				'actions' => [$action],
				'topics' => array_map(static fn(string $topic): array => ['match' => $topic], $topics),
			]],
			'mercure' => [$action => $topics],
		];
		$encode = static fn(string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		$unsigned = $encode((string) json_encode(['alg' => 'HS256', 'typ' => 'at+jwt'])) . '.' . $encode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

		return $unsigned . '.' . $encode(hash_hmac('sha256', $unsigned, $key, true));
	}
}
