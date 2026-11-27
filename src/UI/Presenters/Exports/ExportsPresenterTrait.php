<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Exports;

use ADT\Exporter\Model\Entities\Export;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\ExporterInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByInterface;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;
use Nette\Application\Responses\FileResponse;
use Nette\Http\IResponse;

/**
 * Stažení exportu z odkazu v e-mailu (adt/exporter: downloadLink, docs/datagrid-export.md). Soubor vydá jen
 * tomu, kdo export zadal - odkaz z přeposlaného e-mailu nikomu jinému data nedá.
 */
trait ExportsPresenterTrait
{
	use EntityManagerInject;
	use ExporterInject;
	use SecurityUserInject;

	public function actionDownload(int $id): void
	{
		$export = $this->_em->getRepository($this->_em->findEntityClassByInterface(Export::class))->find($id);
		$owner = $export instanceof CreatedByNullableInterface || $export instanceof CreatedByInterface ? $export->getCreatedBy() : null;
		$identity = $this->_securityUser->getIdentity();
		if (!$export instanceof Export || !$owner || !$identity instanceof Identity || $owner->getId() !== $identity->getId()) {
			$this->error();
		}

		$path = $this->_exporter->getFilePath($export);
		if (!$path || !is_file($path)) {
			$this->onExportFilePurged();
		}

		$this->_exporter->fileDownloaded($export);
		$this->sendResponse(new FileResponse($path, (string) $export->getFileName()));
	}

	/** Soubor už smazal `exporter:purge-files` po uplynutí retence, záznam exportu zůstává. */
	protected function onExportFilePurged(): never
	{
		$this->error('', IResponse::S410_Gone);
	}
}
