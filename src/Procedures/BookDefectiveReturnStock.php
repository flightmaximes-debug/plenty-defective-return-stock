<?php

namespace DefectiveReturnStock\Procedures;

use DefectiveReturnStock\Services\DefectiveReturnStockService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

class BookDefectiveReturnStock
{
    public function execute(EventProceduresTriggered $event): void
    {
        /** @var DefectiveReturnStockService $service */
        $service = pluginApp(DefectiveReturnStockService::class);
        $service->process($event->getOrder());
    }
}
