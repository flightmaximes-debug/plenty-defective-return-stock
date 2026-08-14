<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Flow\DiagnosticReturnCommentAction;
use DefectiveReturnStock\Flow\BookDefectiveReturnStockFlowAction;
use DefectiveReturnStock\Flow\LoadReturnOrderDiagnosticAction;
use Plenty\Modules\Flow\Services\StepActionRegistrationService;
use Plenty\Plugin\ServiceProvider;

class DefectiveReturnStockServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(StepActionRegistrationService $registrationService)
    {
        $registrationService->registerAction(
            pluginApp(DiagnosticReturnCommentAction::class)
        );

        $registrationService->registerAction(
            pluginApp(BookDefectiveReturnStockFlowAction::class)
        );

        $registrationService->registerAction(
            pluginApp(LoadReturnOrderDiagnosticAction::class)
        );
    }
}
