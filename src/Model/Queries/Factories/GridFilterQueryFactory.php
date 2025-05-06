<?php


use ADT\FancyAdmin\Model\Queries\GridFilterQuery;

interface GridFilterQueryFactory
{
	public function create(): GridFilterQuery;
}
