<?php

namespace DefectiveReturnStock\Procedures;

use DefectiveReturnStock\Services\DefectiveReturnStockService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class BookDefectiveReturnStock
{
    use Loggable;

    public function execute(EventProceduresTriggered $event): void
    {
        $order = $event->getOrder();
        $orderId = $order === null ? 0 : (int) $order->id;

        $this->getLogger(__METHOD__)->error(
            'DefectiveReturnStock::legacy.procedure_started',
            ['orderId' => $orderId]
        );

        try {
            /** @var DefectiveReturnStockService $service */
            $service = pluginApp(DefectiveReturnStockService::class);
            $service->process($order);
        } catch (Throwable $exception) {
            $this->getLogger(__METHOD__)->error(
                'DefectiveReturnStock::legacy.procedure_failed',
                [
                    'orderId' => $orderId,
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception)
                ]
            );

            throw $exception;
        }
    }
}
