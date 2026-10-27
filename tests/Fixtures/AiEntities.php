<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\AiAgent;
use ADT\FancyAdmin\Model\Entities\AiAgentTrait;
use ADT\FancyAdmin\Model\Entities\AiConversation;
use ADT\FancyAdmin\Model\Entities\AiConversationTrait;
use ADT\FancyAdmin\Model\Entities\AiMessage;
use ADT\FancyAdmin\Model\Entities\AiMessageTrait;

final class TestAiConversation implements AiConversation
{
	use Identifier;
	use AiConversationTrait;
}

final class TestAiMessage implements AiMessage
{
	use Identifier;
	use AiMessageTrait;
}

final class TestAiAgent implements AiAgent
{
	use Identifier;
	use AiAgentTrait;
}
