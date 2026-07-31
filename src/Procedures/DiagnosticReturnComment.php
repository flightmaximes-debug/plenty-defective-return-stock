<?php

namespace DefectiveReturnStock\Procedures;

use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use RuntimeException;

class DiagnosticReturnComment
{
    public function execute(
        EventProceduresTriggered $event,
        CommentRepositoryContract $commentRepository
    ): void {
        $order = $event->getOrder();
        $orderId = $order === null ? 0 : (int) $order->id;

        if ($orderId <= 0) {
            throw new RuntimeException(
                'Die Ereignisaktion hat keine gültige Auftrags-ID übergeben.'
            );
        }

        $commentRepository->createComment([
            'referenceType' => 'order',
            'referenceValue' => $orderId,
            'text' => 'DefectiveReturnStock V5 wurde ausgeführt.',
            'isVisibleForContact' => false
        ]);
    }
}
