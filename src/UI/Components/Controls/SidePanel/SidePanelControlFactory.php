<?php

namespace ADT\FancyAdmin\UI\Components\Controls\SidePanel;

interface SidePanelControlFactory
{
	public function create(): SidePanelControl;
}
