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
		'contextLabel', 'contextOk', 'contextWarn', 'contextFull', 'errorConnection', 'errorTimeout',
		'errorUnavailable', 'errorUnknown', 'errorTooManyFiles', 'errorFileTooLarge', 'sentContent',
	];

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
	 * @return array<string, string>
	 */
	public function getWidgetLabels(): array
	{
		$labels = [];
		foreach (self::WIDGET_LABELS as $_label) {
			$key = 'fcadmin.aiChat.labels.' . $_label;
			$translated = (string) $this->translator->translate($key);
			if ($translated !== $key) {
				$labels[$_label] = $translated;
			}
		}

		return $labels;
	}
}
