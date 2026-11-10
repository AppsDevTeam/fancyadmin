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


final class NoticePresenter extends Presenter
{
	use BasePresenterTrait;

	public function setPrimaryTemplate(bool $primaryTemplate): void
	{
		$this->primaryTemplate = $primaryTemplate;
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


test('ajax, ktery nic neprekresluje, prekresli cele body i s panelem', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);

	$presenter->afterRender();

	Assert::true($presenter->isControlInvalid('body'));
	Assert::false($presenter->isControlInvalid('persistentNotice'));
});


test('ajax, ktery prekresluje cast stranky, prekresli i panel', function () {
	$presenter = new NoticePresenter();
	$presenter->setPrimaryTemplate(true);
	$presenter->redrawControl('flashes');

	$presenter->afterRender();

	Assert::true($presenter->isControlInvalid('persistentNotice'));
	Assert::false($presenter->isControlInvalid('body'));
});


test('mimo prihlasenou cast se panel neprekresluje', function () {
	$presenter = new NoticePresenter();
	$presenter->redrawControl('flashes');

	$presenter->afterRender();

	Assert::false($presenter->isControlInvalid('persistentNotice'));
});
