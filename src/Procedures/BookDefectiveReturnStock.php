<?php

namespace DefectiveReturnStock\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use RuntimeException;

class BookDefectiveReturnStock
{
    public function execute(
        EventProceduresTriggered $event,
        ConfigRepository $config,
        OrderRepositoryContract $orderRepository,
        StockRepositoryContract $stockRepository
    ): void {
        $eventOrder = $event->getOrder();
        $orderId = $eventOrder === null ? 0 : (int) $eventOrder->id;

        if ($orderId <= 0) {
            throw new RuntimeException('Die Ereignisaktion hat keine Auftrags-ID übergeben.');
        }

        $order = $orderRepository->findOrderById($orderId, ['orderItems']);
        if ((int) $order->typeId !== OrderType::TYPE_RETURN) {
            throw new RuntimeException('Auftrag ' . $orderId . ' ist keine Retoure.');
        }

        $warehouseId = (int) $config->get(
            'DefectiveReturnStock.stock.warehouseId',
            0
        );
        $storageLocationId = (int) $config->get(
            'DefectiveReturnStock.stock.storageLocationId',
            0
        );
        $reasonId = (int) $config->get(
            'DefectiveReturnStock.stock.reasonId',
            207
        );

        if ($warehouseId <= 0 || $storageLocationId < 0) {
            throw new RuntimeException('Lager oder Lagerort ist nicht korrekt konfiguriert.');
        }

        $quantities = [];
        $stockRelevantTypes = [
            OrderItemType::TYPE_VARIATION,
            OrderItemType::TYPE_BUNDLE_COMPONENT,
            OrderItemType::TYPE_SET_COMPONENT
        ];

        foreach ((array) $order->orderItems as $orderItem) {
            if (!in_array((int) $orderItem->typeId, $stockRelevantTypes, true)) {
                continue;
            }

            $variationId = (int) $orderItem->itemVariationId;
            $quantity = abs((float) $orderItem->quantity);
            if ($variationId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!isset($quantities[$variationId])) {
                $quantities[$variationId] = 0.0;
            }
            $quantities[$variationId] += $quantity;
        }

        if (count($quantities) === 0) {
            throw new RuntimeException(
                'Retoure ' . $orderId . ' enthält keine bestandsrelevanten Positionen.'
            );
        }

        $outgoingItems = [];
        foreach ($quantities as $variationId => $quantity) {
            $outgoingItems[] = [
                'variationId' => (int) $variationId,
                'warehouseId' => $warehouseId,
                'storageLocationId' => $storageLocationId,
                'quantity' => $quantity,
                'reasonId' => $reasonId,
                'orderNumber' => (string) $orderId
            ];
        }

        $stockRepository->bookOutgoingItems(
            $warehouseId,
            ['outgoingItems' => $outgoingItems]
        );
    }
}
