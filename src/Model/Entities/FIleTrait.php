<?php

namespace ADT\FancyAdmin\Model\Entities;

use App\Model\Entities\Abstract\BaseEntity;

#[ORM\Entity]
class FileTrait extends BaseEntity implements \ADT\Files\Entities\File
{
	use \ADT\Files\Entities\FileTrait;
}
