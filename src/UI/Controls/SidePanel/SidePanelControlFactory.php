<?php

namespace ADT\FancyAdmin\UI\Controls\SidePanel;

use ADT\FancyAdmin\UI\Controls\SidePanel\SidePanelControl;

interface SidePanelControlFactory
{
	public function create(): SidePanelControl;
}
