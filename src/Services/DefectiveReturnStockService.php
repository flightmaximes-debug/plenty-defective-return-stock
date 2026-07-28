<?php

namespace DefectiveReturnStock\Services;

use DefectiveReturnStock\Models\ProcessedReturn;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
use Plenty\Modules\StockManagement\Stock\Contracts\StockStorageLocationRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use RuntimeException;
use Throwable;

class DefectiveReturnStockService
{
    use Loggable;

    private const DEFAULT_REASON_ID = 207;
    private const ALLOWED_REASON_IDS = [205, 207, 208, 216];
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_SUCCESS = 'success';
    private const STATUS_FAILED = 'failed';

    private $config;
    private $database;
    private $orderRepository;
    private $stockRepository;
    private $stockStorageLocationRepository;

    public function __construct(
        ConfigRepository $config,
        DataBase $database,
        OrderRepositoryContract $orderRepository,
        StockRepositoryContract $stockRepository,
        StockStorageLocationRepositoryContract $stockStorageLocationRepository
    ) {
        $this->config = $config;
        $this->database = $database;
        $this->orderRepository = $orderRepository;
        $this->stockRepository = $stockRepository;
        $this->stockStorageLocationRepository = $stockStorageLocationRepository;
    }

    public function process(Order $flowOrder): void
    {
        $orderId = (int) $flowOrder->id;
        $warehouseId = $this->positiveConfigId('DefectiveReturnStock.stock.warehouseId');
        $storageLocationId = $this->positiveConfigId('DefectiveReturnStock.stock.storageLocationId');
        $reasonId = (int) $this->config->get(
            'DefectiveReturnStock.stock.reasonId',
            self::DEFAULT_REASON_ID
        );

        if (!in_array($reasonId, self::ALLOWED_REASON_IDS, true)) {
            throw new RuntimeException('Ungültiger Buchungsgrund in der Plugin-Konfiguration.');
        }

        $existing = $this->findMarker($orderId);
        if ($existing !== null && in_array(
            $existing->status,
            [self::STATUS_PROCESSING, self::STATUS_SUCCESS],
            true
        )) {
            $this->log('duplicate', $orderId, [
                'status' => $existing->status
            ]);
            return;
        }

        /** @var ProcessedReturn $marker */
        $marker = $existing ?: pluginApp(ProcessedReturn::class);
        $marker->orderId = $orderId;
        $marker->status = self::STATUS_PROCESSING;
        $marker->message = '';
        $marker->updatedAt = time();

        try {
            $this->database->save($marker);
        } catch (Throwable $exception) {
            // A concurrent Flow execution may have claimed the same return.
            $concurrentMarker = $this->findMarker($orderId);
            if ($concurrentMarker !== null && in_array(
                $concurrentMarker->status,
                [self::STATUS_PROCESSING, self::STATUS_SUCCESS],
                true
            )) {
                return;
            }
            throw $exception;
        }

        try {
            $order = $this->orderRepository->findOrderById(
                $orderId,
                ['orderItems']
            );

            if ((int) $order->typeId !== OrderType::TYPE_RETURN) {
                throw new RuntimeException(
                    'Auftrag ' . $orderId . ' ist keine Retoure.'
                );
            }

            $quantities = $this->collectVariationQuantities($order);
            if (count($quantities) === 0) {
                throw new RuntimeException(
                    'Retoure ' . $orderId . ' enthält keine bestandsrelevanten Positionen.'
                );
            }

            $this->assertAvailableStock(
                $warehouseId,
                $storageLocationId,
                $quantities
            );

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

            $this->stockRepository->bookOutgoingItems(
                $warehouseId,
                ['outgoingItems' => $outgoingItems]
            );

            $marker->status = self::STATUS_SUCCESS;
            $marker->message = count($outgoingItems)
                . ' Variantenposition(en) ausgebucht.';
            $marker->updatedAt = time();
            $this->database->save($marker);

            $this->log('success', $orderId, [
                'warehouseId' => $warehouseId,
                'storageLocationId' => $storageLocationId,
                'items' => $outgoingItems
            ]);
        } catch (Throwable $exception) {
            $marker->status = self::STATUS_FAILED;
            $marker->message = substr($exception->getMessage(), 0, 240);
            $marker->updatedAt = time();

            try {
                $this->database->save($marker);
            } catch (Throwable $markerException) {
                $this->log('marker_update_failed', $orderId, [
                    'message' => $markerException->getMessage()
                ]);
            }

            $this->log('failed', $orderId, [
                'message' => $exception->getMessage()
            ]);

            throw new RuntimeException(
                'Bestandsausbuchung für Retoure ' . $orderId
                . ' fehlgeschlagen: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    private function collectVariationQuantities(Order $order): array
    {
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

        return $quantities;
    }

    private function assertAvailableStock(
        int $warehouseId,
        int $storageLocationId,
        array $quantities
    ): void {
        foreach ($quantities as $variationId => $requiredQuantity) {
            $result = $this->stockStorageLocationRepository
                ->listStockStorageLocations(
                    [],
                    1,
                    50,
                    [],
                    [
                        'warehouseId' => $warehouseId,
                        'storageLocationId' => $storageLocationId,
                        'variationId' => (int) $variationId
                    ]
                );

            $availableQuantity = 0.0;
            foreach ((array) $result->entries as $stockEntry) {
                if (
                    (int) $stockEntry->warehouseId === $warehouseId
                    && (int) $stockEntry->storageLocationId === $storageLocationId
                    && (int) $stockEntry->variationId === (int) $variationId
                ) {
                    $availableQuantity += (float) $stockEntry->quantity;
                }
            }

            if ($availableQuantity < $requiredQuantity) {
                throw new RuntimeException(
                    'Nicht genügend Bestand für Variante ' . $variationId
                    . ' auf Lagerort ' . $storageLocationId
                    . '. Benötigt: ' . $requiredQuantity
                    . ', verfügbar: ' . $availableQuantity . '.'
                );
            }
        }
    }

    private function positiveConfigId(string $key): int
    {
        $value = (int) $this->config->get($key, 0);
        if ($value <= 0) {
            throw new RuntimeException(
                'Pflichtfeld "' . $key . '" ist nicht konfiguriert.'
            );
        }
        return $value;
    }

    private function findMarker(int $orderId): ?ProcessedReturn
    {
        $rows = $this->database
            ->query(ProcessedReturn::class)
            ->where('orderId', '=', $orderId)
            ->limit(1)
            ->get();

        return count($rows) > 0 ? $rows[0] : null;
    }

    private function log(string $identifier, int $orderId, array $context): void
    {
        $this->getLogger('DefectiveReturnStock_' . $identifier)
            ->setReferenceType('orderId')
            ->setReferenceValue($orderId)
            ->info(
                'DefectiveReturnStock::stock.' . $identifier,
                $context
            );
    }
}
