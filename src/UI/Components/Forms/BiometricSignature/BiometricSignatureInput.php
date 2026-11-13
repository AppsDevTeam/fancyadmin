<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\BiometricSignature;

use ADT\BiometricSignature\Exception\InvalidSignatureException;
use ADT\BiometricSignature\SignatureCapture;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Form;
use Nette\Forms\Helpers;
use Nette\Forms\Rule;
use Nette\Utils\Html;

/**
 * Pole s biometrickým podpisem (adt/biometric-signature, docs/biometric-signature.md).
 *
 * Vykreslí plátno, tlačítko „Vymazat podpis“ a dva hidden inputy (obrázek PNG
 * a biometrický záznam), které plní `@fancyadmin/js/biometricSignature`.
 * Hodnota pole je SignatureCapture (zkontrolovaný podpis), nebo null, když se
 * nikdo nepodepsal. Obsah hidden inputů se dá podvrhnout - neplatný podpis
 * (SignatureCapture::fromInput() vyhodí výjimku) je chyba pole, ne prázdná hodnota.
 *
 *     $form->addBiometricSignature('signature', 'Podpis')
 *         ->setRequired('Podepište se prosím.');
 *     $capture = $form['signature']->getValue(); // ?SignatureCapture
 */
class BiometricSignatureInput extends BaseControl
{
	private string $rawImage = '';

	private string $rawBiometrics = '';

	private ?SignatureCapture $capture = null;

	private string|\Stringable $clearCaption = 'fcadmin.biometricSignature.clear';

	private Rule $invalidRule;

	public function __construct(string|\Stringable|null $label = null)
	{
		parent::__construct($label);

		$this->control = Html::el('div');

		// Podpis, který prohlížeč poslal, ale nejde přijmout. Hláška jen „podepište
		// se znovu“ - důvod (obrázek / biometrika) uživateli nic neřekne.
		$this->addRule([self::class, 'validateSignature'], 'fcadmin.biometricSignature.invalid');
		$rules = iterator_to_array($this->getRules(), false);
		$this->invalidRule = end($rules);
	}

	/**
	 * Hláška pro podpis, který nejde přijmout (výchozí přeložená „Podpis se nepodařilo
	 * zachytit…“) - pro formulář s vlastními texty, např. v jazyce šablony.
	 */
	public function setInvalidMessage(string|\Stringable $message): static
	{
		$this->invalidRule->message = $message;

		return $this;
	}

	/**
	 * Popisek tlačítka, které podpis smaže (výchozí přeložený „Vymazat podpis“).
	 */
	public function setClearCaption(string|\Stringable $caption): static
	{
		$this->clearCaption = $caption;

		return $this;
	}

	public function loadHttpData(): void
	{
		$this->rawImage = (string) $this->getHttpData(Form::DataLine, '[image]');
		$this->rawBiometrics = (string) $this->getHttpData(Form::DataText, '[biometrics]');

		$this->capture = null;
		if ($this->rawImage === '') {
			return;
		}

		try {
			$this->capture = SignatureCapture::fromInput($this->rawImage, $this->rawBiometrics);
		} catch (InvalidSignatureException) {
			// Chybu ohlásí validateSignature(), hodnota zůstane null
		}
	}

	/**
	 * Podpis nejde předvyplnit (plátno se po překreslení vždy maže), hodnota
	 * jde nastavit jen na null.
	 */
	public function setValue(mixed $value): static
	{
		if ($value !== null && !$value instanceof SignatureCapture) {
			throw new \InvalidArgumentException('Hodnota pole s podpisem je SignatureCapture nebo null.');
		}

		$this->capture = $value;

		return $this;
	}

	public function getValue(): ?SignatureCapture
	{
		return $this->capture;
	}

	/**
	 * Vyplněné = prohlížeč poslal obrázek, i neplatný - jinak by podvržený podpis
	 * u nepovinného pole prošel jako „bez podpisu“.
	 */
	public function isFilled(): bool
	{
		return $this->rawImage !== '' || $this->capture !== null;
	}

	public static function validateSignature(self $control): bool
	{
		return $control->rawImage === '' || $control->capture !== null;
	}

	public function getControl(): Html
	{
		$this->setOption('rendered', true);

		$name = $this->getHtmlName();

		$wrapper = clone $this->control;
		$wrapper->id = $this->getHtmlId();
		$wrapper->appendAttribute('class', 'biometric-signature');
		$wrapper->setAttribute('data-biometric-signature', true);

		$wrapper->addHtml(Html::el('canvas')->setAttribute('class', 'biometric-signature-canvas'));

		$wrapper->addHtml(
			Html::el('div')->setAttribute('class', 'biometric-signature-actions')->addHtml(
				Html::el('button')
					->setAttribute('type', 'button')
					->setAttribute('class', 'btn btn-outline-secondary btn-sm')
					->setAttribute('data-biometric-signature-clear', true)
					->setText($this->translate($this->clearCaption))
			)
		);

		// Pravidla (povinnost) nese input s obrázkem - prázdný = nepodepsáno.
		// Hodnoty se nevykreslují: obrázek by bez biometriky neplatil a plátno
		// se po překreslení stejně smaže.
		$wrapper->addHtml(
			Html::el('input')
				->setAttribute('type', 'hidden')
				->setAttribute('name', $name . '[image]')
				->setAttribute('disabled', $this->isDisabled())
				->setAttribute('data-biometric-signature-image', true)
				->setAttribute('data-nette-rules', Helpers::exportRules($this->getRules()) ?: null)
		);
		$wrapper->addHtml(
			Html::el('input')
				->setAttribute('type', 'hidden')
				->setAttribute('name', $name . '[biometrics]')
				->setAttribute('disabled', $this->isDisabled())
				->setAttribute('data-biometric-signature-biometrics', true)
		);

		return $wrapper;
	}

	public static function addBiometricSignature(Container $container, string $name, string|\Stringable|null $label = null): self
	{
		$component = new self($label);
		$container->addComponent($component, $name);

		return $component;
	}

	/**
	 * $form->addBiometricSignature() - registruje FancyAdminExtension, je-li
	 * nainstalovaný adt/biometric-signature.
	 */
	public static function register(): void
	{
		Form::extensionMethod('addBiometricSignature', [self::class, 'addBiometricSignature']);
		Container::extensionMethod('addBiometricSignature', [self::class, 'addBiometricSignature']);
	}
}
