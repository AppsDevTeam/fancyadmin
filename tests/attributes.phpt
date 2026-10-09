<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Attributes\Label;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;
use ADT\FancyAdmin\Model\Menu\StringResource;
use Tester\Assert;

/**
 * Atributy balicku - #[Label] a #[SecurityCheckAttribute].
 */

require __DIR__ . '/bootstrap.php';


#[Label('fcadmin.entity.order')]
class OrderWithLabel
{
	#[Label('fcadmin.entity.order.number')]
	public string $number = '';

	public string $bezPopisku = '';
}

class PresenterWithSecurityCheck
{
	#[SecurityCheckAttribute(new StringResource('portalBackoffice.orders'))]
	public function actionDefault(): void
	{
	}
}


test('Label nese klic prekladu', function () {
	Assert::same('fcadmin.orders', new Label('fcadmin.orders')->translationKey);
});


test('Label se da pouzit na tride i na property', function () {
	$classAttributes = new ReflectionClass(OrderWithLabel::class)->getAttributes(Label::class);
	Assert::count(1, $classAttributes);
	Assert::same('fcadmin.entity.order', $classAttributes[0]->newInstance()->translationKey);

	$propertyAttributes = new ReflectionProperty(OrderWithLabel::class, 'number')->getAttributes(Label::class);
	Assert::count(1, $propertyAttributes);
	Assert::same('fcadmin.entity.order.number', $propertyAttributes[0]->newInstance()->translationKey);

	Assert::same([], new ReflectionProperty(OrderWithLabel::class, 'bezPopisku')->getAttributes(Label::class));
});


test('Label nejde pouzit na metode', function () {
	$attribute = new ReflectionClass(Label::class)->getAttributes(Attribute::class)[0]->newInstance();

	Assert::same(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS, $attribute->flags);
});


test('SecurityCheckAttribute nese ACL zdroj', function () {
	$resource = new StringResource('portalBackoffice.orders');

	Assert::same($resource, new SecurityCheckAttribute($resource)->getResourceName());
});


test('SecurityCheckAttribute se precte z metody presenteru', function () {
	$attributes = new ReflectionMethod(PresenterWithSecurityCheck::class, 'actionDefault')->getAttributes(SecurityCheckAttribute::class);

	Assert::count(1, $attributes);
	Assert::same('portalBackoffice.orders', $attributes[0]->newInstance()->getResourceName()->getResourceId());
});
