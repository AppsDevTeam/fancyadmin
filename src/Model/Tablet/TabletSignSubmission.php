<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\BiometricSignature\Exception\InvalidSignatureException;
use ADT\BiometricSignature\SignatureCapture;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Co poslala stránka podpisu na tabletu (tabletSign.js): nakreslené podpisy a které místo je
 * podepsané kterým z nich. Podpis nakreslený jednou může podepisující potvrdit do dalších míst,
 * proto jsou podpisy a místa zvlášť.
 *
 * Obsah se dá podvrhnout - obrázek a biometrika každého podpisu se kontrolují
 * (SignatureCapture), úplnost míst TabletSigning::sign().
 */
final class TabletSignSubmission
{
	/** Víc podpisů jedna žádost nepotřebuje (každý má stovky kB) */
	public const int MAX_SIGNATURES = 50;

	/**
	 * @param array<string, SignatureCapture> $signatures ID podpisu na stránce => podpis
	 * @param array<string, string> $placements ID místa (TabletSignPlace::getId()) => ID podpisu
	 */
	public function __construct(
		public readonly array $signatures,
		public readonly array $placements,
	) {
	}

	/**
	 * {"signatures": {"<id>": {"image": "<data URI>", "biometrics": "<JSON>"}}, "placements": {"<místo>": "<id>"}}
	 *
	 * @throws InvalidSignatureException obsah nejde přečíst nebo některý podpis neprošel kontrolou
	 */
	public static function fromJson(string $json): self
	{
		try {
			$input = Json::decode($json, forceArrays: true);
		} catch (JsonException $e) {
			throw new InvalidSignatureException('Podpisy nejde přečíst.', previous: $e);
		}

		if (!is_array($input) || !is_array($input['signatures'] ?? null) || !is_array($input['placements'] ?? null)) {
			throw new InvalidSignatureException('Podpisy nejde přečíst.');
		}

		if (count($input['signatures']) > self::MAX_SIGNATURES) {
			throw new InvalidSignatureException('Příliš mnoho podpisů.');
		}

		$signatures = [];
		foreach ($input['signatures'] as $id => $signature) {
			if (!is_array($signature) || !is_string($signature['image'] ?? null) || !is_string($signature['biometrics'] ?? null)) {
				throw new InvalidSignatureException('Podpis nejde přečíst.');
			}

			$signatures[(string) $id] = SignatureCapture::fromInput($signature['image'], $signature['biometrics']);
		}

		$placements = [];
		foreach ($input['placements'] as $place => $id) {
			if (!is_string($id) && !is_int($id)) {
				throw new InvalidSignatureException('Podpis místa nejde přečíst.');
			}

			$placements[(string) $place] = (string) $id;
		}

		return new self($signatures, $placements);
	}
}
