<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;

interface HasIdentity
{
	public function getIdentity(): Identity;
}