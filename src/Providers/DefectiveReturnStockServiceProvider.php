<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Flow\BookDefectiveReturnStockFlowAction;
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
            pluginApp(BookDefectiveReturnStockFlowAction::class)
        );
    }
}
