<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationType;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Nette\Utils\Json;

trait ConfigurationTrait
{
	use UpdatedBy, UpdatedAt;

	#[Column(name: '`key`', nullable: false)]
	protected string $key;

	#[Column(name: '`type`', nullable: false, options: ["default" => ConfigurationTypeEnum::TYPE_PLAINTEXT])]
	protected ConfigurationTypeEnum $type = ConfigurationTypeEnum::TYPE_PLAINTEXT;

	#[Column(name: '`value`', type: Types::TEXT, nullable: false)]
	protected string $value;

	#[Column(name: '`options`', type: Types::TEXT, nullable: true)]
	protected ?string $options = null;

	public function getKey(): string
	{
		return $this->key;
	}

	public function setKey(string $key): static
	{
		$this->key = $key;
		return $this;
	}

	public function getType(): string
	{
		return $this->type->value;
	}

	public function setType(ConfigurationType $type): static
	{
		$this->type = $type;
		return $this;
	}

	public function getValue(): string
	{
		return $this->value;
	}

	public function setValue(string $value): static
	{
		$this->value = $value;
		return $this;
	}

	public function getOptions($asArray = false): array|string|null
	{
		if (!$this->options) {
			return $asArray ? [] : null;
		}

		return $asArray
			? Json::decode($this->options, true)
			: $this->options;
	}

	public function setOptions(array|string|null $options): static
	{
		$this->options = $options
			? (is_array($options) ? Json::encode($options) : $options)
			: null;

		return $this;
	}
}
