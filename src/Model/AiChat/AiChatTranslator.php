<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\AiChat;

use Nette\Localization\Translator;

/**
 * Překlady hlášek adt/ai-chat (klíče aiChat.*) ze slovníku fancyadminu
 * (fcadmin.aiChat.*). Chybějící klíč spadne na anglický text balíku.
 */
class AiChatTranslator
{
	/** Texty widgetu (assets/ai-chat.js, DEFAULT_LABELS), překládají se do konfigurace widgetu. */
	public const array WIDGET_LABELS = [
		'title', 'newConversation', 'noConversations', 'noTitle', 'placeholder', 'send', 'attach',
		'removeFile', 'delete', 'deleteConfirm', 'loading', 'welcome', 'welcomeSingle', 'exportCsv',
		'contextLabel', 'contextOk', 'contextWarn', 'contextFull', 'contextSwitch', 'errorConnection', 'errorTimeout',
		'errorUnavailable', 'errorUnknown', 'errorTooManyFiles', 'errorFileTooLarge', 'model', 'effort',
		'modelHint', 'rateQuestion', 'rateUp', 'rateDown', 'rateComment', 'rateCommentUp', 'rateSave',
		'rateThanks', 'rated', 'stop', 'stopping', 'interrupted', 'subagent', 'toolCalls',
	];

	/** Úrovně přemýšlení (labels.effortLevels widgetu). */
	public const array EFFORT_LEVELS = ['low', 'medium', 'high', 'xhigh', 'max'];

	/** Stavy subagentů (labels.subagentStatus widgetu). */
	public const array SUBAGENT_STATUSES = ['running', 'rescheduling', 'idle', 'terminated'];

	public function __construct(
		private readonly Translator $translator,
	) {
	}

	/**
	 * Callback pro ChatService::setTranslator().
	 *
	 * @param array<string, scalar> $params
	 */
	public function translate(string $key, array $params, string $default): string
	{
		$fullKey = 'fcadmin.' . $key;
		$translated = (string) $this->translator->translate($fullKey, $params);

		return $translated !== $fullKey ? $translated : $default;
	}

	/**
	 * Texty widgetu. Zástupné symboly {max}, {name} doplňuje až widget, ve slovníku
	 * proto zůstávají ve složených závorkách.
	 *
	 * @return array<string, string|array<string, string>>
	 */
	public function getWidgetLabels(): array
	{
		$labels = [];
		foreach (self::WIDGET_LABELS as $_label) {
			$translated = $this->translateLabel('fcadmin.aiChat.labels.' . $_label);
			if ($translated !== null) {
				$labels[$_label] = $translated;
			}
		}

		foreach (['effortLevels' => self::EFFORT_LEVELS, 'subagentStatus' => self::SUBAGENT_STATUSES] as $_group => $_keys) {
			$group = [];
			foreach ($_keys as $_key) {
				$translated = $this->translateLabel('fcadmin.aiChat.labels.' . $_group . '.' . $_key);
				if ($translated !== null) {
					$group[$_key] = $translated;
				}
			}
			if ($group !== []) {
				$labels[$_group] = $group;
			}
		}

		return $labels;
	}

	private function translateLabel(string $key): ?string
	{
		$translated = (string) $this->translator->translate($key);

		return $translated !== $key ? $translated : null;
	}
}
