<?php

namespace ADT\FancyAdmin\Model\Quickbar;

/**
 * Položky rychlé lišty dole na obrazovce; které zobrazit, říká nastavení `quickbar` (QuickbarSettings).
 */
enum QuickbarItem: string
{
	case Search = 'search';
	case Notifications = 'notifications';
	case Todo = 'todo';
	case Chat = 'chat';

	public function getIcon(): string
	{
		return match ($this) {
			self::Search => 'magnifying-glass',
			self::Notifications => 'bell',
			self::Todo => 'list-check',
			self::Chat => 'comments',
		};
	}

	/** Úkoly jsou jen připomínka, nic nehoří - proto šedé počítadlo */
	public function getBadgeClass(): string
	{
		return $this === self::Todo ? 'bg-secondary' : 'bg-danger';
	}
}
