<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

/**
 * Stav žádosti o podpis poslané na tablet (TabletSignRequest::getState()). V DB se neukládá -
 * plyne z časů žádosti (vyzvednutí, podpis, zrušení, platnost).
 */
enum TabletSignRequestState: string
{
	/** Čeká, až si ji čekací stránka tabletu vyzvedne */
	case Pending = 'pending';

	/** Tablet ji zobrazuje (formulář je otevřený) */
	case Opened = 'opened';

	case Signed = 'signed';

	/** Tablet se vrátil na čekací stránku, byl odpojen nebo dostal jinou žádost */
	case Cancelled = 'cancelled';

	/** Tablet si ji do konce platnosti nevyzvedl */
	case Expired = 'expired';
}
