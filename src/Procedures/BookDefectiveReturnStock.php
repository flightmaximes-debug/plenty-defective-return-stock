<?php

namespace DefectiveReturnStock\Procedures;

use DefectiveReturnStock\Services\DefectiveReturnStockService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

class BookDefectiveReturnStock
{
    private $service;

    public function __construct(DefectiveReturnStockService $service)
    {
        $this->service = $service;
    }

    public function execute(EventProceduresTriggered $event): void
    {
        $this->service->process($event->getOrder());
    }
}
