<?php

namespace ADT\FancyAdmin\Model\Mailer;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Services\OnetimeTokenService;
use ADT\SingleRecipient\SingleRecipient;
use Contributte\Translation\Exceptions\InvalidArgument;
use Contributte\Translation\Translator;
use DateMalformedStringException;
use DateTimeImmutable;
use Exception;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\Component;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\TemplateFactory;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Mail\Message;
use ReflectionClass;
use TijsVerkoyen\CssToInlineStyles;

trait MailerTrait
{
	use SingleRecipient;

	public function __construct(
		protected readonly \Nette\Mail\Mailer $mailer,
		protected readonly string $from,
		protected readonly string $fromName,
		protected readonly ?string $singleRecipient,
		protected readonly string $supportEmail,
		protected readonly string $title,
		protected readonly string $web,
		protected readonly string $wwwDir,
		protected readonly TemplateFactory $templateFactory,
		protected readonly Translator $translator,
		protected readonly EntityManager $em,
		protected readonly LinkGenerator $linkGenerator,
		protected readonly FancyAdmin $administration,
		protected readonly OnetimeTokenService $onetimeTokenService,
	) {
	}

	public function createMessage(): Message
	{
		return new Message();
	}

	/**
	 * @throws InvalidArgument
	 */
	public function createTemplateMessage(string $templateName, string $subject, array $data = [], ?string $locale = null, array $translateVariables = []): Message
	{
		if ($locale) {
			$originalLocale = $this->translator->getLocale();
			$this->translator->setLocale($locale);
		}

		$templateName .= '.latte';
		$dirname = dirname(new ReflectionClass($this)->getFileName()) . '/templates/';
		$templateFile = $dirname . $templateName;
		if (!file_exists($templateFile)) {
			$templateFile = __DIR__ . '/templates/' . $templateName;
		}

		$layoutName = '@layout.latte';
		$layoutFile = dirname(new ReflectionClass($this)->getFileName()) . '/templates/' . $layoutName;
		if (!file_exists($layoutFile)) {
			$layoutFile = __DIR__ . '/templates/' . $layoutName;
		}

		/** @var Template $template */
		$template = $this->templateFactory->createTemplate();
		$template->addFilter('translate', [$this->translator, 'translate'])
			->setFile($templateFile);

		$template->projectName = $this->administration->getProjectName();
		$template->fromName = $this->fromName;
		$template->logoFileName = $this->administration->getLogoBitmapPublicPath();
		$template->subject = $this->translator->translate($subject, $translateVariables);
		$template->layoutFile = $layoutFile;

		foreach ($data as $key => $value) {
			$template->$key = $value;
		}

		foreach (['supportEmail', 'title', 'web'] as $privateParam) {
			$template->$privateParam = $this->$privateParam;
		}

		$message = static::createMessage()
			->setSubject($this->translator->translate($subject, $translateVariables))
			->setHtmlBody(
				new CssToInlineStyles\CssToInlineStyles()->convert((string) $template),
				$this->wwwDir
			);

		if (isset($originalLocale)) {
			$this->translator->setLocale($originalLocale);
		}

		return $message;
	}

	/**
	 * @throws Exception
	 */
	public function send(Message $mail): void
	{
		if (! $mail->getFrom() && $this->from) {
			$mail->setFrom($this->from, $this->fromName)
				->addReplyTo($this->supportEmail, $this->fromName);
		}

		if (! empty($this->singleRecipient)) {
			$this->applySingleRecipient($mail, $this->singleRecipient);
		}

		$this->mailer->send($mail);
	}

	/**
	 * @throws InvalidArgument
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	public function sendAccountCreationEmail(Identity $identity): void
	{
		$this->em->beginTransaction();

		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = $this->onetimeTokenService->generateToken($identity, new DateTimeImmutable('+' . OnetimeToken::PASSWORD_CREATION_VALID_FOR . ' hours'));

		$message = $this->createTemplateMessage(
			'accountCreation',
			'Vytvoření účtu',
			[
				'link' => $this->link(':Portal:Sign:newPassword', ['email' => $identity->getEmail(), 'token' => $onetimeToken->getToken()])
			]
		);
		$message->addTo($identity->getEmail());
		$this->send($message);

		$this->em->commit();
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws Exception
	 */
	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime): void
	{
		$this->em->beginTransaction();

		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = $this->onetimeTokenService->generateToken($identity, new DateTimeImmutable('+ ' . $tokenLifetime . ' hour'));

		$message = $this->createTemplateMessage(
			'passwordRecovery',
			'Nové heslo',
			[
				'link' => $this->link(':Portal:Sign:newPassword', ['email' => $identity->getEmail(), 'token' => $onetimeToken->getToken()]),
			]
		);
		$message->addTo($identity->getEmail());
		$this->send($message);

		$this->em->commit();
	}

	/**
	 * @throws InvalidLinkException
	 */
	public function link(
		string $destination,
		array $args = [],
		?Component $component = null,
		?string $mode = null,
	): ?string
	{
		return $this->linkGenerator->link($destination, $args, $component, $mode);
	}
}
