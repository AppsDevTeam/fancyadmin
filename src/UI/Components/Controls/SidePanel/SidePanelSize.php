<?php

namespace ADT\FancyAdmin\UI\Components\Controls\SidePanel;

enum SidePanelSize: string
{
	case Small = 'sm';
	case Medium = 'md';
	case Large = 'lg';
	case Extreme = 'extreme';
	case Full = 'full';
	case FullExceptMenu = 'full-except-menu';
}
