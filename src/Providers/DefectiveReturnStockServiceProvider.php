<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Procedures\BookDefectiveReturnStock;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Plugin\ServiceProvider;

class DefectiveReturnStockServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(EventProceduresService $eventProceduresService)
    {
        $eventProceduresService->registerProcedure(
            'bookDefectiveReturnStockV2',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => 'Defekte Retoure ausbuchen (Test V2)',
                'en' => 'Book defective return stock out (Test V2)'
            ],
            BookDefectiveReturnStock::class . '@execute'
        );
    }
}
