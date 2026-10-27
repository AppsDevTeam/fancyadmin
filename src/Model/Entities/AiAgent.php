<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Entity\AgentInterface;

/**
 * Agent a prostředí Managed Agents založené pro anonymizované schéma databáze.
 * Zakládá je AgentProvisioner sám, ručně se nic nevyplňuje.
 */
interface AiAgent extends AgentInterface
{
}
