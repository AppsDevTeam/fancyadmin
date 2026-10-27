<?php

namespace ADT\FancyAdmin\Model\Quickbar;

/**
 * Položky rychlé lišty dole na obrazovce; které zobrazit, říká nastavení `quickbar` (QuickbarSettings).
 */
enum QuickbarItem: string
{
	case Search = 'search';
	case Ai = 'ai';
	case Notifications = 'notifications';
	case Todo = 'todo';
	case Chat = 'chat';

	public function getIcon(): string
	{
		return match ($this) {
			self::Search => 'magnifying-glass',
			self::Ai => 'sparkles',
			self::Notifications => 'bell',
			self::Todo => 'list-check',
			self::Chat => 'comments',
		};
	}

	/** Null = bez počítadla; úkoly jsou jen připomínka, nic nehoří - proto šedé */
	public function getBadgeClass(): ?string
	{
		return match ($this) {
			self::Search, self::Ai => null,
			self::Todo => 'bg-secondary',
			default => 'bg-danger',
		};
	}
}
