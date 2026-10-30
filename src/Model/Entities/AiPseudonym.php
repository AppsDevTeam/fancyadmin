<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Pseudonymization\PseudonymInterface;

/**
 * Hodnota kotvy ([OSOBA_1], [EMAIL_1]…) v konverzaci s AI asistentem - osobní údaj z textu
 * uživatele, který do AI nejde. Entity nedědí ze stejného důvodu jako {@see AiConversation}.
 */
interface AiPseudonym extends PseudonymInterface
{
}
