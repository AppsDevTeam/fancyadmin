<?php

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\Translator;

class FancyAdminExtension extends \Nette\DI\CompilerExtension
{
	/* TODO */
	const ERROR_MODE_SILENT = 'silent';
	const ERROR_MODE_EXCEPTION = 'exception';

	/**
	 * @return array
	 */
	static function errorModes() {
		return [ self::ERROR_MODE_SILENT, self::ERROR_MODE_EXCEPTION ];
	}

	public function loadConfiguration() {
		$config = $this->validateConfig(
			[

			],
			$this->config
		);

		$this->getContainerBuilder()
			->addDefinition($this->prefix('translator'))
			->setType(Translator::class)
			->setArguments([ $config ]);
	}

	public function validateConfig(array $expected, ?array $config = NULL, $name = NULL): array {
		$config = parent::validateConfig($expected, $config, $name);

/*		if (empty($config['remote']['api'])) {
			throw new \Nette\UnexpectedValueException('Specify remote API endpoint.');
		}

		if (empty($config['remote']['key']) || !(is_string($config['remote']['key']) || is_array($config['remote']['key']))) {
			throw new \Nette\UnexpectedValueException('Specify authentication key as string or method (e.g. [@ServiceClass, method]).');
		}

		if (!in_array($config['error']['mode'], static::errorModes(), TRUE)) {
			throw new \Nette\UnexpectedValueException(
				'Error mode can be either "' . static::ERROR_MODE_SILENT . '" or "' . static::ERROR_MODE_EXCEPTION . '".'
			);
		}

		if ($config['error']['mode'] === static::ERROR_MODE_SILENT && empty($config['error']['logDir'])) {
			throw new \Nette\UnexpectedValueException('Specify mail log directory.');
		}*/

		return $config;
	}
}