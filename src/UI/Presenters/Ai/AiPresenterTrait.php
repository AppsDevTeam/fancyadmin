<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Ai;

use ADT\AiChat\AttachmentUpload;
use ADT\AiChat\ChatService;
use ADT\AiChat\Http\ChatEndpoint;
use ADT\FancyAdmin\Model\AiChat\AiChatTranslator;
use ADT\FancyAdmin\Model\Entities\AiConversation;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\Grids\AiConversation\AiConversationGrid;
use ADT\FancyAdmin\UI\Components\Grids\AiConversation\AiConversationGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\Responses\CallbackResponse;
use Nette\Http\FileUpload;

/**
 * AI asistent nad daty projektu (adt/ai-chat): grid konverzací a konverzace
 * v bočním panelu. Zapojení viz README (AI asistent).
 *
 * Widget (assets/js/aiChat.js) volá signály ai*!, ty jen předávají ChatEndpointu.
 * Vlastní konverzace jdou číst i psát, do cizích s právem BACKOFFICE_AI_VIEW_OTHERS
 * jde taky psát (navázat na analýzu kolegy) - u zprávy se ukládá odesílatel.
 * Hodnotit a mazat jde jen vlastní konverzace.
 *
 * Otevřená konverzace je v URL (?conversation=ID), odkaz jde poslat - stránka pak
 * konverzaci rovnou otevře v bočním panelu (aiOpen!).
 */
trait AiPresenterTrait
{
	use PresenterTrait;

	/** Parametr URL s otevřenou konverzací (sdílený odkaz). */
	private const string AI_URL_PARAM = 'conversation';

	#[Autowire]
	protected ChatService $_aiChatService;

	#[Autowire]
	protected ChatEndpoint $_aiChatEndpoint;

	#[Autowire]
	protected AiChatTranslator $_aiChatTranslator;

	protected ?AiConversation $aiConversation = null;

	public function actionDefault(): void
	{
		$this->checkAiTenantAccess();
		$conversationId = (string) $this->getHttpRequest()->getQuery(self::AI_URL_PARAM);
		$this->template->aiOpenLink = $conversationId !== '' ? $this->link('aiOpen!', ['conversationId' => $conversationId]) : null;
		$this->template->aiMcpTokensLink = $this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_MCP)
			? $this->getAiMcpTokensLink()
			: null;
		$this->setPackageTemplateFile(__DIR__ . '/default.latte');
	}

	/** Grid volá presenterový handleEdit s id, při TypeError s entitou. */
	public function handleEdit(AiConversation $aiConversation): void
	{
		$this->checkAiTenantAccess();

		if (!$this->isAiConversationReadable($aiConversation)) {
			$this->error();
		}

		$this->aiConversation = $aiConversation;
		$this->redrawSidePanel();
	}

	/** Konverzace z odkazu (?conversation=ID), volá assets/js/aiChat.js po načtení stránky. */
	public function handleAiOpen(string $conversationId): void
	{
		$this->checkAiTenantAccess();

		$conversation = $this->_aiChatService->findConversation($conversationId);
		if (!$conversation instanceof AiConversation || !$this->isAiConversationReadable($conversation)) {
			$this->error();
		}

		$this->aiConversation = $conversation;
		$this->redrawSidePanel();
	}

	public function handleNew(): void
	{
		$this->checkAiTenantAccess();
		$this->aiConversation = null;
		$this->redrawSidePanel();
	}

	/** Konfigurace widgetu pro AI panel rychlé lišty (BasePresenterTrait::getQuickbarAiUrl). */
	public function handleAiQuickbar(): void
	{
		$this->checkAiTenantAccess();
		$config = $this->getAiChatConfig(null);
		// Odesílání jako u ostatních panelů lišty
		$config['icons'] = ($config['icons'] ?? []) + ['send' => 'fa-regular fa-paper-plane'];

		$this->sendJson([
			'chat' => $config,
			'historyUrl' => $this->link('default'),
		]);
	}

	/** Grid po založení konverzace v panelu (assets/js/aiChat.js). */
	public function handleRefreshAiConversations(): void
	{
		$this->checkAiTenantAccess();
		$this['aiConversationGrid']->getGrid()->reload();
	}

	public function handleAiConversations(): void
	{
		$this->checkAiTenantAccess();
		$this->sendJson($this->_aiChatEndpoint->conversations($this->getAiOwner()));
	}

	public function handleAiLoad(): void
	{
		$this->checkAiTenantAccess();
		[$owner, $own] = $this->resolveAiOwner($this->getAiParam('conversationId'));
		$this->sendJson($this->getAiChatEndpoint()->load($owner, $this->getAiParam('conversationId'), false, $own, $this->getAiOwner()));
	}

	public function handleAiSend(): void
	{
		$this->checkAiTenantAccess();
		$this->assertAiPost();

		$files = $this->getHttpRequest()->getFile('attachments') ?? [];
		$uploads = array_map(
			AttachmentUpload::fromNetteFileUpload(...),
			array_filter(is_array($files) ? $files : [$files], static fn($f): bool => $f instanceof FileUpload),
		);

		// do konverzace jejího vlastníka (nová je přihlášeného), píše přihlášený
		[$owner] = $this->resolveAiOwner($this->getAiParam('conversationId'));
		$this->sendJson($this->getAiChatEndpoint()->send(
			$owner,
			$this->getAiParam('conversationId'),
			(string) $this->getHttpRequest()->getPost('message'),
			array_values($uploads),
			$this->getAiParam('model') ?: null,
			$this->getAiParam('effort') ?: null,
			$this->getAiOwner(),
		));
	}

	/** Hodnocení 👍/👎 s komentářem - hodnotit jde jen vlastní konverzaci. */
	public function handleAiRate(): void
	{
		$this->checkAiTenantAccess();
		$this->assertAiPost();

		$rating = $this->getAiParam('rating');
		$comment = $this->getHttpRequest()->getPost('comment');

		$this->sendJson($this->_aiChatEndpoint->rate(
			$this->getAiOwner(),
			$this->getAiParam('conversationId'),
			$rating !== '' ? (int) $rating : null,
			is_string($comment) ? $comment : null,
		));
	}

	public function handleAiPoll(): void
	{
		$this->checkAiTenantAccess();
		[$owner] = $this->resolveAiOwner($this->getAiParam('conversationId'));
		$this->sendJson($this->getAiChatEndpoint()->poll(
			$owner,
			$this->getAiParam('conversationId'),
			$this->getAiParam('afterId'),
			$this->getAiOwner(),
		));
	}

	public function handleAiDelete(): void
	{
		$this->checkAiTenantAccess();
		$this->assertAiPost();
		$this->sendJson($this->_aiChatEndpoint->delete($this->getAiOwner(), $this->getAiParam('conversationId')));
	}

	public function handleAiExport(): void
	{
		$this->checkAiTenantAccess();
		[$owner] = $this->resolveAiOwner($this->getAiParam('conversationId'));

		$export = $this->_aiChatEndpoint->export(
			$owner,
			$this->getAiParam('conversationId'),
			$this->getAiParam('messageId'),
			(string) $this->getHttpRequest()->getQuery('token'),
		) ?? $this->error();

		foreach ($export->getHeaders() as $_name => $_value) {
			$this->getHttpResponse()->setHeader($_name, $_value);
		}

		$this->sendResponse(new CallbackResponse(static fn() => $export->stream()));
	}

	public function createComponentAiConversationGrid(AiConversationGridFactory $factory): AiConversationGrid
	{
		return $factory->create();
	}

	public function createComponentSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		return $factory->create()
			->setSize($this->getAiSidePanelSize())
			->setBodyTemplate(__DIR__ . '/sidePanel.latte', [
				'aiChatConfig' => $this->getAiChatConfig($this->aiConversation),
			]);
	}

	/**
	 * Konfigurace widgetu (data-adt-fancyadmin-ai-chat). Přepsáním jde doplnit třeba
	 * vlastní ikony (`icons`) nebo interval dotazování (`pollIntervalMs`).
	 *
	 * @return array<string, mixed>
	 */
	protected function getAiChatConfig(?AiConversation $conversation): array
	{
		$attachments = $this->_aiChatService->getOptions()->attachments;

		return [
			'mode' => 'single',
			'conversationId' => $conversation?->getId(),
			'urlParam' => self::AI_URL_PARAM,
			'readOnly' => false,
			'canManage' => $conversation === null || $this->isOwnAiConversation($conversation),
			'locale' => $this->getAiLocale(),
			'fitViewport' => false,
			'accept' => $attachments->getAcceptAttribute(),
			'maxAttachments' => $attachments->maxCount,
			'maxAttachmentBytes' => $attachments->maxBytes,
			'modelSelection' => $this->_aiChatService->getOptions()->modelSelection->toClient(),
			'labels' => $this->_aiChatTranslator->getWidgetLabels(),
			'urls' => [
				'conversations' => $this->link('aiConversations!'),
				'load' => $this->link('aiLoad!'),
				'send' => $this->link('aiSend!'),
				'poll' => $this->link('aiPoll!'),
				'delete' => $this->link('aiDelete!'),
				'export' => $this->link('aiExport!'),
				'rate' => $this->link('aiRate!'),
			],
		];
	}

	/**
	 * Odkaz na správu MCP tokenů (AiMcpTokensPresenterTrait) v hlavičce stránky;
	 * null = projekt MCP server nemá a tlačítko se nezobrazí.
	 */
	protected function getAiMcpTokensLink(): ?string
	{
		return null;
	}

	protected function getAiSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Large;
	}

	/**
	 * AI čte celé anonymizované schéma databáze, účty (tenanty) nerozlišuje. Kde jich
	 * v jedné databázi žije víc, smí k němu jen ten, kdo smí vidět data všech účtů.
	 */
	protected function checkAiTenantAccess(): void
	{
		if ($this->_securityUser->isMultiTenant() && !$this->_securityUser->isAllowedFullDataAclResource()) {
			$this->error('', 403);
		}
	}

	protected function getAiOwner(): Identity
	{
		return $this->_securityUser->getIdentity();
	}

	protected function isOwnAiConversation(AiConversation $conversation): bool
	{
		return $conversation->getIdentity()->getId() === $this->_securityUser->getId();
	}

	protected function isAiConversationReadable(AiConversation $conversation): bool
	{
		return !$conversation->isDeleted()
			&& ($this->isOwnAiConversation($conversation) || $this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_VIEW_OTHERS));
	}

	/**
	 * Vlastník, pod kterým se ke konverzaci přistupuje: cizí (s právem číst ostatní)
	 * pod svým vlastníkem, jinak přihlášený - endpoint pak cizí konverzaci nenajde.
	 *
	 * @return array{0: Identity, 1: bool} [vlastník, je to vlastní konverzace]
	 */
	protected function resolveAiOwner(string $conversationId): array
	{
		$conversation = $conversationId !== '' ? $this->_aiChatService->findConversation($conversationId) : null;

		if ($conversation instanceof AiConversation
			&& !$this->isOwnAiConversation($conversation)
			&& $this->isAiConversationReadable($conversation)
		) {
			return [$conversation->getIdentity(), false];
		}

		return [$this->getAiOwner(), true];
	}

	/** Jméno nad zprávou kolegy ve sdílené konverzaci. */
	protected function formatAiSender(Identity $sender): string
	{
		return $sender->getFullName();
	}

	protected function getAiLocale(): ?string
	{
		return method_exists($this->_translator, 'getLocale') ? $this->_translator->getLocale() : null;
	}

	private function getAiChatEndpoint(): ChatEndpoint
	{
		return $this->_aiChatEndpoint->setSenderFormatter(fn(Identity $sender): string => $this->formatAiSender($sender));
	}

	/** FormData (POST) má přednost před query. */
	private function getAiParam(string $name): string
	{
		$request = $this->getHttpRequest();

		return (string) ($request->getPost($name) ?? $request->getQuery($name) ?? '');
	}

	private function assertAiPost(): void
	{
		if (!$this->getHttpRequest()->isMethod('POST')) {
			$this->error('POST required', 405);
		}
	}
}
