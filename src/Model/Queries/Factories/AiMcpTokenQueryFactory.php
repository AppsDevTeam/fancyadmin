<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\AiMcpTokenQuery;

interface AiMcpTokenQueryFactory
{
	public function create(): AiMcpTokenQuery;
}
