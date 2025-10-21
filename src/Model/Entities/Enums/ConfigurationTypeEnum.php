<?php

namespace ADT\FancyAdmin\Model\Entities\Enums;

namespace ADT\FancyAdmin\Model\Entities\Enums;

enum ConfigurationTypeEnum: string implements ConfigurationType
{
	case TYPE_JSON = 'json';
	case TYPE_PLAINTEXT = 'plaintext';
	case TYPE_SELECT = 'select';

	public static function list(): array
	{
		$list = [];
		foreach (self::cases() as $case) {
			$list[$case->value] = $case->value;
		}
		return $list;
	}
}
