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
            'bookDefectiveReturnStockV4',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => 'Defekte Retoure ausbuchen (Direkttest V4)',
                'en' => 'Book defective return stock out (direct test V4)'
            ],
            BookDefectiveReturnStock::class . '@execute'
        );
    }
}
