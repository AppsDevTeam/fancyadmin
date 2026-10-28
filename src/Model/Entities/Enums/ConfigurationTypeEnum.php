<?php

namespace ADT\FancyAdmin\Model\Entities\Enums;

namespace ADT\FancyAdmin\Model\Entities\Enums;

enum ConfigurationTypeEnum: string implements ConfigurationType
{
	case TYPE_JSON = 'json';
	case TYPE_PLAINTEXT = 'plaintext';
	case TYPE_SELECT = 'select';
	case TYPE_FILE = 'file';
	// hodnota uložená zašifrovaná přes SecretCipher, v administraci se nikdy nezobrazí
	case TYPE_SECRET = 'secret';

	public static function list(): array
	{
		$list = [];
		foreach (self::cases() as $case) {
			$list[$case->value] = $case->value;
		}
		return $list;
	}
}
