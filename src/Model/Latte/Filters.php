<?php

namespace ADT\FancyAdmin\Model\Latte;

use Contributte\Translation\Translator;
use Latte\ContentType;
use Latte\Runtime\FilterInfo;

class Filters
{

	public function __construct(protected readonly Translator $translator)
	{
	}

	public function nbsp($string)
	{
		return str_replace(' ', html_entity_decode('&nbsp;'), $string);
	}

	public function number($number, $decimals = 2, $decimalSymbol = ',', $thousandsSeparator = '&nbsp;')
	{
		$thousandsSeparator = html_entity_decode($thousandsSeparator);
		return number_format($number, $decimals, $decimalSymbol, $thousandsSeparator);
	}

	public function date($time, string $format = 'j. n. Y')
	{
		if ($time === null) {
			return null;
		}

		$format = html_entity_decode($format);
		return \Nette\Utils\DateTime::from($time)->format($format);
	}

	public function datetime($time, string $format = 'j. n. Y&nbsp;H:i')
	{
		if ($time === null) {
			return null;
		}

		$format = html_entity_decode($format);
		return \Nette\Utils\DateTime::from($time)->format($format);
	}

	public function time($time, string $format = 'H:i')
	{
		$format = html_entity_decode($format);
		return \Nette\Utils\DateTime::from($time)->format($format);
	}

	public function ifEmpty(FilterInfo $info, $string)
	{
		if ((string)$string === '') {
			$info->contentType = ContentType::Html;
			return '<span class="empty">' . $this->translator->translate('app.appGeneral.model.filters.empty') . '</span>';
		}

		return $string;
	}

	public function boolIcon(FilterInfo $info, ?bool $value): string
	{
		$info->contentType = ContentType::Html;
		return $value === null ? '<i class="fa-regular fa-square" style="color: #999999"></i>' : ($value ? '<i class="fa-solid fa-square-check" style="color: #28C885"></i>' : '<i class="fa-solid fa-square-xmark" style="color: #FF4242"></i>');
	}

	public static function darken(string $hexColor, int $percent): string
	{
		// Odstranit "#" z barvy, pokud je přítomna
		$hexColor = ltrim($hexColor, '#');

		// Pokud je barva ve zkráceném formátu (#abc), rozšířit ji na plnou (#aabbcc)
		if (strlen($hexColor) == 3) {
			$hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
		}

		// Rozdělit barvu na jednotlivé složky RGB
		$r = hexdec(substr($hexColor, 0, 2)) / 255;
		$g = hexdec(substr($hexColor, 2, 2)) / 255;
		$b = hexdec(substr($hexColor, 4, 2)) / 255;

		// Převod z RGB do HSL
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$l = ($max + $min) / 2; // Světlost
		$s = $max == $min ? 0 : ($l > 0.5 ? ($max - $min) / (2 - $max - $min) : ($max - $min) / ($max + $min));
		$h = $max == $r
			? fmod((60 * (($g - $b) / ($max - $min)) + 360), 360)
			: ($max == $g
				? fmod((60 * (($b - $r) / ($max - $min)) + 120), 360)
				: fmod((60 * (($r - $g) / ($max - $min)) + 240), 360));

		// Snížit světlost
		$l = max(0, $l - $percent / 100);

		// Převod zpět z HSL do RGB
		$c = (1 - abs(2 * $l - 1)) * $s;
		$x = $c * (1 - abs(fmod($h / 60, 2) - 1));
		$m = $l - $c / 2;

		if ($h < 60) {
			$r = $c;
			$g = $x;
			$b = 0;
		} elseif ($h < 120) {
			$r = $x;
			$g = $c;
			$b = 0;
		} elseif ($h < 180) {
			$r = 0;
			$g = $c;
			$b = $x;
		} elseif ($h < 240) {
			$r = 0;
			$g = $x;
			$b = $c;
		} elseif ($h < 300) {
			$r = $x;
			$g = 0;
			$b = $c;
		} else {
			$r = $c;
			$g = 0;
			$b = $x;
		}

		// Normalizace a převod zpět do hexadecimálního formátu
		$r = ($r + $m) * 255;
		$g = ($g + $m) * 255;
		$b = ($b + $m) * 255;

		return sprintf('#%02x%02x%02x', round($r), round($g), round($b));
	}

	public static function lighten(string $hexColor, int $percent)
	{
		// Odstranit "#" z barvy, pokud je přítomna
		$hexColor = ltrim($hexColor, '#');

		// Pokud je barva ve zkráceném formátu (#abc), rozšířit ji na plnou (#aabbcc)
		if (strlen($hexColor) == 3) {
			$hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
		}

		// Rozdělit barvu na jednotlivé složky RGB
		$r = hexdec(substr($hexColor, 0, 2));
		$g = hexdec(substr($hexColor, 2, 2));
		$b = hexdec(substr($hexColor, 4, 2));

		// Vypočítat zesvětlené hodnoty
		$r = max(0, min(255, $r + ($r * $percent / 100)));
		$g = max(0, min(255, $g + ($g * $percent / 100)));
		$b = max(0, min(255, $b + ($b * $percent / 100)));

		// Převést zpět na hexadecimální hodnotu
		return sprintf('#%02x%02x%02x', $r, $g, $b);
	}
}
