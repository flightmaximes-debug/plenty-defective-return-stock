<?php

namespace DefectiveReturnStock\Providers;

use DefectiveReturnStock\Flow\BookDefectiveReturnStockFlowActionV3;
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
            pluginApp(BookDefectiveReturnStockFlowActionV3::class)
        );
    }
}
