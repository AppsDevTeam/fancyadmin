<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\AiConversation;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DeleteParams;
use ADT\FancyAdmin\Model\Entities\AiConversation;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\AiConversationQuery;
use ADT\FancyAdmin\Model\Queries\Factories\AiConversationQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;
use Nette\Utils\Html;

/**
 * Seznam konverzací s AI asistentem. Úprava (edit!) otevře konverzaci v bočním
 * panelu presenteru, viz AiPresenterTrait.
 */
trait AiConversationGridTrait
{
	use Editable;
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->withoutIsActiveColumn = true;

		$grid->setDefaultSort(['updated' => 'DESC']);

		$this->addSearchFilter($grid, ['title']);

		$grid->addColumnText('title', 'fcadmin.presenters.ai.grid.title')
			->setRenderer(fn(AiConversation $conversation) => $conversation->getTitle()
				?: $this->getTranslator()->translate('fcadmin.aiChat.labels.noTitle'));

		if ($this->canViewOthersAiConversations()) {
			$grid->addColumnText('identity', 'fcadmin.presenters.ai.grid.identity')
				->setRenderer(fn(AiConversation $conversation) => $conversation->getIdentity()->getFullName());
		}

		$grid->addColumnText('model', 'fcadmin.presenters.ai.grid.model')
			->setRenderer(fn(AiConversation $conversation) => trim($conversation->getModel() . ' ' . $conversation->getEffort()));

		$grid->addColumnText('rating', 'fcadmin.presenters.ai.grid.rating')
			->setRenderer(fn(AiConversation $conversation) => $this->renderAiConversationRating($conversation));

		$grid->addColumnDateTime('updated', 'fcadmin.presenters.ai.grid.updated')
			->setFormat('j. n. Y H:i')
			->setSortable();

		$grid->addColumnDateTime('created', 'fcadmin.presenters.ai.grid.created')
			->setFormat('j. n. Y H:i')
			->setSortable();

		// cizí konverzace jde jen číst, ne mazat
		if ($this->allowDelete()) {
			$grid->allowRowsAction('delete', fn(AiConversation $conversation) => $this->isOwnAiConversation($conversation));
		}
	}

	/** @param AiConversationQuery $queryObject */
	protected function initQueryObject($queryObject): void
	{
		$queryObject->notDeleted();

		if (!$this->canViewOthersAiConversations()) {
			$queryObject->byIdentity($this->_securityUser->getId());
		}
	}

	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams(
			// Smazání je jen příznak - zprávy zůstávají kvůli auditu a statistice tokenů
			onDelete: function (AiConversation $conversation): bool {
				if (!$this->isOwnAiConversation($conversation)) {
					$this->getPresenter()->error(null, 403);
				}

				$conversation->setDeleted(true);
				$this->getEntityManager()->flush();
				$this->getPresenter()->flashMessageSuccess('action.delete.yes');
				$this->getGrid()->reload();

				return false;
			},
		);
	}

	/** Palec nahoru/dolů, komentář k hodnocení v title. */
	protected function renderAiConversationRating(AiConversation $conversation): Html|string
	{
		$rating = $conversation->getRating();
		if ($rating === null) {
			return '';
		}

		return Html::el('span')
			->class($rating > 0 ? 'fa fa-thumbs-up text-success' : 'fa fa-thumbs-down text-danger')
			->title((string) $conversation->getRatingComment());
	}

	protected function canViewOthersAiConversations(): bool
	{
		return $this->_securityUser->isAllowed(AclResourceNameEnum::BACKOFFICE_AI_VIEW_OTHERS);
	}

	protected function isOwnAiConversation(AiConversation $conversation): bool
	{
		return $conversation->getIdentity()->getId() === $this->_securityUser->getId();
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AiConversationQueryFactory::class;
	}
}
