<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Queries\AccountQuery;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use LogicException;

/** Tovarna, ktera se v testovanych cestach nema k cemu dostat - kdyz se prece jen zavola, je to chyba. */
final class TestAccountQueryFactory implements AccountQueryFactory
{
	public function create(): AccountQuery
	{
		throw new LogicException('AccountQuery se v tomhle testu nema pouzit.');
	}
}
