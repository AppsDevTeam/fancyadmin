<?php

namespace ADT\FancyAdmin\Model\Security;

enum UserTypeEnum: string
{
	case EMAIL = 'email';
	case PHONE_NUMBER = 'phone_number';
	case USERNAME = 'username';
}
