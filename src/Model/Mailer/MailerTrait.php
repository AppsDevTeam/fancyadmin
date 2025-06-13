<?php

namespace ADT\FancyAdmin\Model\Mailer;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\Mailer\Services\Api;
use ADT\SingleRecipient\SingleRecipient;
use Contributte\Translation\Exceptions\InvalidArgument;
use Contributte\Translation\Translator;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\TemplateFactory;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Mail;
use Nette\Mail\Message;
use TijsVerkoyen\CssToInlineStyles;

trait MailerTrait
{
	use SingleRecipient;

	abstract protected function getOnetimeTokenClass(): string;

	public function __construct(
		protected readonly string $from,
		protected readonly string $fromName,
		protected readonly ?string $singleRecipient,
		protected readonly string $supportEmail,
		protected readonly string $title,
		protected readonly string $web,
		protected readonly string $wwwDir,
		protected readonly TemplateFactory $templateFactory,
		protected readonly Api $mailapi,
		protected readonly Translator $translator,
		protected readonly BackgroundQueue $backgroundQueue,
		protected readonly EntityManagerInterface $em,
		protected readonly LinkGenerator $linkGenerator
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

		/** @var Template $template */
		$template = $this->templateFactory->createTemplate();
		$template->addFilter('translate', [$this->translator, 'translate'])
			->setFile(__DIR__ . '/templates/' . $templateName . '.latte');

		$template->subject = $this->translator->translate($subject, $translateVariables);

		foreach ($data as $key => $value) {
			$template->$key = $value;
		}

		foreach (['supportEmail', 'title', 'web'] as $privateParam) {
			$template->$privateParam = $this->$privateParam;
		}

		$message = static::createMessage()
			->setSubject($this->translator->translate($subject, $translateVariables))
			->setHtmlBody(
				(new CssToInlineStyles\CssToInlineStyles())->convert((string) $template),
				$this->wwwDir
			);

		if (isset($originalLocale)) {
			$this->translator->setLocale($originalLocale);
		}

		return $message;
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function send(Message $mail): void
	{
		$this->backgroundQueue->publish('sendEmail', ['mail' => $mail]);
	}

	/**
	 * @throws Exception
	 * @internal
	 */
	public function sendEmail(Message $mail): void
	{
		if (! $mail->getFrom() && $this->from) {
			$mail->setFrom($this->from, $this->fromName)
				->addReplyTo($this->supportEmail, $this->fromName);
		}

		if (! empty($this->singleRecipient)) {
			$this->applySingleRecipient($mail, $this->singleRecipient);
		}

		$this->mailapi->send($mail);
	}

	/**
	 * @throws InvalidLinkException
	 * @throws InvalidArgument
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function sendAccountCreationEmail(Identity $identity): void
	{
		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->getOnetimeTokenClass());
		$onetimeToken
			->setObjectId($identity->getId())
			->setType('login')
			->setToken($onetimeToken::generateRandomToken())
			->setValidUntil((new DateTimeImmutable('+' . OnetimeToken::PASSWORD_CREATION_VALID_FOR . ' hours')));
		$this->em->persist($onetimeToken);
		$this->em->flush();

		$message = $this->createTemplateMessage(
			'accountCreation',
			'Vytvoření účtu', // TODO translate
			[
				'link' => $this->linkGenerator->link(':Portal:Sign:token', ['email' => $identity->getEmail(), 'token' => $onetimeToken->getToken()]),
				'validTill' => $this->translator->translate('app.emails.passwordRecovery.validTill', ['date' => $onetimeToken->getValidUntil()->format('j. n. Y G:i')])
			]
		);
		$message->addTo($identity->getEmail());
		$this->send($message);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws InvalidArgument
	 * @throws InvalidLinkException|\Doctrine\DBAL\Exception
	 */
	public function sendPasswordRecoveryMail(Identity $identity, int $tokenLifetime): void
	{
		$this->em->beginTransaction();

		/** @var OnetimeToken $onetimeToken */
		$onetimeToken = new ($this->getOnetimeTokenClass());
		$onetimeToken
			->setObjectId($identity->getId())
			->setType('login') // TODO
			->setToken($onetimeToken::generateRandomToken())
			->setIpAddress($_SERVER['REMOTE_ADDR'])
			->setValidUntil(new DateTimeImmutable('+ ' . $tokenLifetime . ' hour'));
		$this->em->persist($onetimeToken);
		$this->em->flush();

		$message = $this->createTemplateMessage(
			'passwordRecovery',
			'app.emails.passwordRecovery.subject',
			[
				'link' => $this->linkGenerator->link(':Portal:Sign:token', ['email' => $identity->getEmail(), 'token' => $onetimeToken->getToken()]),
				'validTill' => $this->translator->translate('app.emails.passwordRecovery.validTill', ['date' => $onetimeToken->getValidUntil()->format('j. n. Y G:i')])
			]
		);
		$message->addTo($identity->getEmail());
		$this->send($message);

		$this->em->commit();
	}
}
