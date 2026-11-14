<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat\Page;

use ADT\AiChat\Tool\Tool;
use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuHeading;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\InvalidStateException;

/**
 * `open_page` - AI otevře uživateli stránku administrace: položku menu (Pokladní kniha,
 * Úkoly …) nebo stránku záznamu, kterou projekt zaregistroval jako AiPage (karta klienta).
 * Výsledek je `{type: navigate}` z adt/ai-chat: widget ho předá stránce, rychlá lišta ji
 * načte za otevřeným chatem.
 *
 * Kontrola práv tu je kvůli srozumitelné odpovědi AI, ne jako ochrana - presenter cíle si
 * práva kontroluje při načtení sám. Menu se vyhodnocuje bez presenteru (worker fronty
 * žádný nemá): podmínky položek (setCondition) dostanou místo presenteru null.
 */
final class OpenPageTool implements Tool
{
	public const NAME = 'open_page';

	/** @var array<string, AiPage> */
	private array $pages = [];

	/** @var array<string, array{item: NavbarMenuItem|NavbarSubmenuItem, parent: ?NavbarMenuItem, label: string}>|null */
	private ?array $menuPages = null;

	/**
	 * @param iterable<AiPage> $pages stránky záznamů projektu
	 * @param string $module modul backoffice (`PortalBackoffice`), v něm jsou cíle odkazů i ACL resource
	 * @param class-string<NavbarMenuFactory>|null $menuFactory menu backoffice; null = jen stránky záznamů
	 */
	public function __construct(
		iterable $pages,
		private readonly LinkGenerator $linkGenerator,
		private readonly SecurityUser $securityUser,
		private readonly string $module,
		private readonly ?string $menuFactory = null,
	) {
		foreach ($pages as $page) {
			$this->pages[$page->getName()] = $page;
		}
	}

	public function getName(): string
	{
		return self::NAME;
	}

	public function getDefinition(): array
	{
		$lines = [];
		foreach ($this->getMenuPages() as $name => $page) {
			$lines[] = sprintf('- `%s`: %s', $name, $page['label']);
		}
		foreach ($this->pages as $name => $page) {
			$lines[] = sprintf('- `%s` (needs id): %s', $name, $page->getDescription());
		}

		return [
			'name' => self::NAME,
			'description' => 'Open a page of the application for the user - it loads behind the chat, the chat stays open. '
				. 'Use it whenever the user asks to open, show or go to a page or a record. Pages:' . "\n" . implode("\n", $lines) . "\n"
				. 'A record page needs the id of the record (from an anchor like [CLIENT #12] or from SQL); when the user '
				. 'names a record that may be one of several, ask which one first. Then reply in one short sentence.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'page' => ['type' => 'string', 'enum' => [...array_keys($this->getMenuPages()), ...array_keys($this->pages)]],
					'id' => ['type' => 'integer', 'description' => 'Record id - only for pages that need it.'],
				],
				'required' => ['page'],
			],
		];
	}

	public function handle(array $input): array
	{
		$name = is_string($input['page'] ?? null) ? $input['page'] : '';

		if (isset($this->pages[$name])) {
			$id = is_int($input['id'] ?? null) ? $input['id'] : (is_numeric($input['id'] ?? null) ? (int) $input['id'] : 0);
			if ($id <= 0) {
				return ['error' => sprintf('Page "%s" needs the id of the record.', $name)];
			}
			$target = $this->securityUser->isLoggedIn() ? $this->pages[$name]->resolve($id) : null;
			if ($target === null) {
				return ['error' => sprintf('Record %d does not exist or the user may not see it.', $id)];
			}
			if (!$this->isAllowed($this->presenterResource($target->destination))) {
				return ['error' => 'The user is not allowed to open this page.'];
			}

			return $this->navigate($target->destination, $target->args, $target->title);
		}

		$menuPage = $this->getMenuPages()[$name] ?? null;
		if ($menuPage === null) {
			return ['error' => sprintf('Unknown page "%s".', $name)];
		}
		if (!$this->securityUser->isLoggedIn() || !$this->isMenuItemVisible($menuPage['item'], $menuPage['parent'])) {
			return ['error' => 'The user is not allowed to open this page.'];
		}

		return $this->navigate((string) $menuPage['item']->getLink(), $menuPage['item']->getLinkArgs(), $menuPage['label']);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function navigate(string $destination, array $args, string $title): array
	{
		try {
			$url = $this->linkGenerator->link($this->module . ':' . $destination, $args);
		} catch (InvalidLinkException) {
			return ['error' => 'The page cannot be opened.'];
		}

		// adt/ai-chat pustí do prohlížeče jen cestu na vlastním webu
		$query = (string) parse_url($url, PHP_URL_QUERY);

		return [
			'type' => 'navigate',
			'url' => parse_url($url, PHP_URL_PATH) . ($query !== '' ? '?' . $query : ''),
			'title' => $title,
			'opened' => true,
		];
	}

	/**
	 * Položky menu s odkazem, klíčem je odkaz (`CashBook:default`), popisek s rodičem
	 * („Pokladna › Pokladní kniha"). ACL resource doplní menu stejně jako v presenteru.
	 *
	 * @return array<string, array{item: NavbarMenuItem|NavbarSubmenuItem, parent: ?NavbarMenuItem, label: string}>
	 */
	private function getMenuPages(): array
	{
		if ($this->menuPages !== null) {
			return $this->menuPages;
		}

		$this->menuPages = [];
		if ($this->menuFactory === null) {
			return $this->menuPages;
		}

		/** @var NavbarMenuFactory $factory */
		$factory = new ($this->menuFactory)();
		$menu = $factory->create()->resolveAclResources($this->module);
		foreach ($menu->getMenuItems() as $item) {
			if ($item->getLink() !== null) {
				$this->menuPages[$this->pageName($item->getLink(), $item->getLinkArgs())] ??= ['item' => $item, 'parent' => null, 'label' => $item->getLabel()];
			}
			foreach ($item->getSubmenu()?->getSubMenuItems() ?? [] as $subItem) {
				if ($subItem instanceof NavbarSubmenuHeading) {
					continue;
				}
				$this->menuPages[$this->pageName($subItem->getLink(), $subItem->getLinkArgs())] ??= [
					'item' => $subItem,
					'parent' => $item,
					'label' => $item->getLabel() . ' › ' . $subItem->getLabel(),
				];
			}
		}

		return $this->menuPages;
	}

	/** @param array<string, mixed> $args */
	private function pageName(string $link, array $args): string
	{
		return $args === [] ? $link : $link . '?' . http_build_query($args);
	}

	private function isMenuItemVisible(NavbarMenuItem|NavbarSubmenuItem $item, ?NavbarMenuItem $parent): bool
	{
		try {
			return ($parent === null || $parent->isVisible($this->securityUser, null)) && $item->isVisible($this->securityUser, null);
		} catch (InvalidStateException) {
			// Resource v ACL neexistuje - jako bez práva
			return false;
		}
	}

	/** Resource presenteru cíle - stejné pravidlo jako AuthPresenterTrait::validatePresenterPermission() */
	private function presenterResource(string $destination): string
	{
		return lcfirst($this->module) . '.' . lcfirst(explode(':', ltrim($destination, ':'))[0]);
	}

	private function isAllowed(string $resource): bool
	{
		try {
			return $this->securityUser->isAllowed($resource);
		} catch (InvalidStateException) {
			return false;
		}
	}
}
