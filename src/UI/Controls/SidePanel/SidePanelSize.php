<?php

namespace ADT\FancyAdmin\UI\Controls\SidePanel;

enum SidePanelSize: string
{
	case Small = 'sm';
	case Medium = 'md';
	case Large = 'lg';
	case Extreme = 'extreme';
	case Full = 'full';
	case FullExceptMenu = 'full-except-menu';
}
