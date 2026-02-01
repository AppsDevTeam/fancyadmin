<?php

namespace ADT\FancyAdmin\Model\Services;

enum OnetimeTokenTypeEnum: string implements OnetimeTokenType
{
	case LOGIN = 'login';
}
