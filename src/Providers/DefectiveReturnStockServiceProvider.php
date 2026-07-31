<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Procedures\DiagnosticReturnComment;
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
            'diagnosticReturnCommentV5',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => 'Defekte Retoure: Plugin-Aufruf prüfen (Test V5)',
                'en' => 'Defective return: verify plugin execution (test V5)'
            ],
            DiagnosticReturnComment::class . '@execute'
        );
    }
}
