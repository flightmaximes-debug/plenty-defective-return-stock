<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Flow\BookDefectiveReturnStockAction;
use DefectiveReturnStock\Procedures\BookDefectiveReturnStock;
use DefectiveReturnStock\Services\DefectiveReturnStockService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Flow\Services\StepActionRegistrationService;
use Plenty\Plugin\ServiceProvider;

class DefectiveReturnStockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->getApplication()->bind(DefectiveReturnStockService::class);
        $this->getApplication()->bind(BookDefectiveReturnStockAction::class);
        $this->getApplication()->bind(BookDefectiveReturnStock::class);
    }

    public function boot(
        StepActionRegistrationService $stepActionRegistrationService,
        EventProceduresService $eventProceduresService
    ): void
    {
        $stepActionRegistrationService->registerAction(
            pluginApp(BookDefectiveReturnStockAction::class)
        );

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
