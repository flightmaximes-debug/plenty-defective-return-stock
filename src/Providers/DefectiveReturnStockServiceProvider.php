<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Procedures\BookDefectiveReturnStock;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Plugin\ServiceProvider;

class DefectiveReturnStockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->getApplication()->bind(BookDefectiveReturnStock::class);
    }

    public function boot(EventProceduresService $eventProceduresService): void
    {
        $eventProceduresService->registerProcedure(
            'DefectiveReturnStock',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => 'Defekte Retoure aus Retouren-Lagerort ausbuchen',
                'en' => 'Book defective return out of return storage location'
            ],
            BookDefectiveReturnStock::class . '@execute',
            ProcedureEntry::PROCEDURE_GROUP_RETURN
        );
    }
}
