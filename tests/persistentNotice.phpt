<?php

declare(strict_types=1);

use ADT\FancyAdmin\UI\Presenters\BasePresenterTrait;
use ADT\FancyAdmin\UI\Presenters\PersistentNotice;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use Tester\Assert;

/**
 * Trvaly panel v layoutu portalu (PersistentNotice, @persistentNotice.latte) a jeho prekresleni po ajaxu.
 */

require __DIR__ . '/bootstrap.php';


const NOTICE_TEMPLATE = __DIR__ . '/../src/UI/Presenters/@persistentNotice.latte';


final class NoticeTranslator implements Translator
{
	public function translate(string|Stringable $message, mixed ...$parameters): string
	{
		$params = $parameters[0] ?? [];

		return '[' . $message . ']' . ($params ? ' ' . json_encode($params) : '');
	}
}


/**
 * Presenter s BasePresenterTrait bez DI. Signal a vykresleni volaji tytez metody traitu jako
 * processSignal() a beforeRender(), jen bez zbytku zivotniho cyklu presenteru.
 */
final class NoticePresenter extends Presenter
{
	use BasePresenterTrait;

	public ?PersistentNotice $notice = null;

	public function setPrimaryTemplate(bool $primaryTemplate): void
	{
		$this->primaryTemplate = $primaryTemplate;
	}

	/** Ajaxovy signal, ktery muze panel zmenit. */
	public function ajaxSignal(callable $handler): void
	{
		$this->rememberPersistentNoticeBeforeSignal();
		$handler($this);
	}

	public function renderPage(): void
	{
		$this->loadRenderedPersistentNotice();
		$this->afterRender();
	}

	protected function getPersistentNotice(): ?PersistentNotice
	{
		return $this->notice;
	}
}


function renderNotice(?PersistentNotice $notice): string
{
	$latte = new Engine();
	$latte->setTempDirectory(null);
	$latte->addExtension(new TranslatorExtension(new NoticeTranslator()));

	return trim($latte->renderToString(NOTICE_TEMPLATE, ['persistentNotice' => $notice]));
}


test('bez panelu se nic nevykresli', function () {
	Assert::same('', renderNotice(null));
});


test('text se prelozi s parametry a escapuje', function () {
	$html = renderNotice(new PersistentNotice('app.notice.<b>', ['time' => '14:30']));

	Assert::contains('class="persistent-notice"', $html);
	Assert::contains('<span class="persistent-notice-text">[app.notice.&lt;b&gt;] &#123;"time":"14:30"}</span>', $html);
	Assert::notContains('<b>', $html);
	Assert::notContains('<a', $html);
});


test('HTML z Nette\Utils\Html se nepreklada ani neescapuje', function () {
	$html = renderNotice(new PersistentNotice(Html::el('strong')->setText('Pozor')));

	Assert::contains('<strong>Pozor</strong>', $html);
	Assert::notContains('[', $html);
});


test('odkaz ma prelozeny popisek a tridu ajax jen na pozadani', function () {
	$html = renderNotice(new PersistentNotice('app.notice', link: '/portal?do=send&_sec=abc', linkLabel: 'app.sendNow'));

	Assert::contains('<a href="/portal?do=send&amp;_sec=abc" class="btn btn-primary btn-sm">[app.sendNow]</a>', $html);

	$html = renderNotice(new PersistentNotice('app.notice', link: '/portal?do=send', linkLabel: 'app.sendNow', ajax: true));

	Assert::contains('class="btn btn-primary btn-sm ajax"', $html);
});


test('odkaz javascript: Latte do href nepusti', function () {
	$html = renderNotice(new PersistentNotice('app.notice', link: 'javascript:alert(1)', linkLabel: 'app.sendNow'));

	Assert::notContains('javascript:', $html);
});


test('odkaz bez popisku nejde', function () {
	Assert::exception(
		fn() => new PersistentNotice('app.notice', link: '/portal?do=send'),
		InvalidArgumentException::class,
	);
});


test('layout kresli panel ve snippetu jen v primarni sablone', function () {
	$layout = file_get_contents(__DIR__ . '/../src/UI/Presenters/@layout.latte');

	Assert::match('~\{if \$primaryTemplate\}.*\{snippet persistentNotice\}\s*\{include @persistentNotice\.latte\}\s*\{/snippet\}.*\{else\}~s', $layout);
});


test('navigace ajaxem bez prekresleni prekresli cele body i s panelem', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);
	$presenter->notice = new PersistentNotice('app.notice');

	$presenter->renderPage();

	Assert::true($presenter->isControlInvalid('body'));
	Assert::false($presenter->isControlInvalid('persistentNotice'));
});


test('signal, ktery panel zalozi, ho prekresli i pri castecnem prekresleni', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);

	$presenter->ajaxSignal(function (NoticePresenter $presenter) {
		$presenter->notice = new PersistentNotice('app.notice', ['time' => '14:30']);
		$presenter->redrawControl('flashes');
	});
	$presenter->renderPage();

	Assert::true($presenter->isControlInvalid('persistentNotice'));
	Assert::false($presenter->isControlInvalid('body'));
});


test('signal, ktery panel zrusi nebo zmeni, ho prekresli', function () {
	foreach ([null, new PersistentNotice('app.notice', ['time' => '15:00']), new PersistentNotice('app.notice', ['time' => '14:30'], ajax: true)] as $after) {
		$presenter = new NoticePresenter();
		$presenter->setPrimaryTemplate(true);
		$presenter->notice = new PersistentNotice('app.notice', ['time' => '14:30']);

		$presenter->ajaxSignal(function (NoticePresenter $presenter) use ($after) {
			$presenter->notice = $after;
			$presenter->redrawControl('flashes');
		});
		$presenter->renderPage();

		Assert::true($presenter->isControlInvalid('persistentNotice'));
	}
});


/**
 * Rozbaleni vetve stromu gridu (getChildren) vlozi kazdy snippet odpovedi mezi radky jako polozku
 * stromu. Panel, ktery se nezmenil, v odpovedi byt nesmi.
 */
test('castecne prekresleni bez zmeny panelu panel neprekresli', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);
	$presenter->notice = new PersistentNotice('app.notice', ['time' => '14:30'], link: '/portal?do=send&_sec=abc', linkLabel: 'app.sendNow');

	$presenter->ajaxSignal(function (NoticePresenter $presenter) {
		// getPersistentNotice() vraci pokazde novou instanci se stejnymi hodnotami
		$presenter->notice = new PersistentNotice('app.notice', ['time' => '14:30'], link: '/portal?do=send&_sec=abc', linkLabel: 'app.sendNow');
		$presenter->redrawControl('items');
	});
	$presenter->renderPage();

	Assert::false($presenter->isControlInvalid('persistentNotice'));
	Assert::false($presenter->isControlInvalid('body'));
});


test('castecne prekresleni bez signalu panel neprekresli', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);
	$presenter->notice = new PersistentNotice('app.notice');
	$presenter->redrawControl('flashes');

	$presenter->renderPage();

	Assert::false($presenter->isControlInvalid('persistentNotice'));
});


test('mimo prihlasenou cast se panel neprekresluje', function () {
	$presenter = new NoticePresenter();

	$presenter->ajaxSignal(function (NoticePresenter $presenter) {
		$presenter->notice = new PersistentNotice('app.notice');
		$presenter->redrawControl('flashes');
	});
	$presenter->renderPage();

	Assert::false($presenter->isControlInvalid('persistentNotice'));
});


test('panel pred signalem si pamatuje processSignal() traitu a CSRF kontrola zustava', function () {
	$method = new ReflectionMethod(NoticePresenter::class, 'processSignal');

	Assert::contains('rememberPersistentNoticeBeforeSignal', implode('', array_slice(
		file($method->getFileName()),
		$method->getStartLine() - 1,
		$method->getEndLine() - $method->getStartLine() + 1,
	)));
	Assert::true(method_exists(NoticePresenter::class, 'signalCsrfProcessSignal'));
});
