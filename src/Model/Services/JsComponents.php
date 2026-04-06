<?php


namespace ADT\FancyAdmin\Model\Services;

class JsComponents extends \ADT\Utils\JsComponents
{
	public function setFirebaseLink(string $key, string $value): static
	{
		$this->components['notifications'][$key] = $value;
		return $this;
	}

	public function setFirebaseConfig(array $firebaseConfig): void
	{
		$this->components['notifications'] = [
			'initializeConfig' => $firebaseConfig,
		];

		$this->components['messaging'] = [
			'initializeConfig' => $firebaseConfig,
		];
	}

	public function setTranslateConfig(\App\Model\Translator $translator): void
	{
		$this->components['translate'] = [
			'all' => $translator->getCatalogue()->all('appJs')
		];
	}
}
