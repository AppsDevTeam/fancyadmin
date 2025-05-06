<?php

namespace App\UI\Portal\Components\SidePanels;

interface SidePanelControlFactory
{
	public function create(): SidePanelControl;
}
