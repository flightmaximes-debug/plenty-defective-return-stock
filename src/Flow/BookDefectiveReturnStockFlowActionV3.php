<?php

namespace DefectiveReturnStock\Flow;

use Carbon\Carbon;
use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
use RuntimeException;

class BookDefectiveReturnStockFlowActionV3 extends StepActionDefinitionContract
{
    private const ORDER_TYPE_RETURN = 3;
    private const ORDER_ITEM_TYPE_VARIATION = 1;
    private const ORDER_ITEM_TYPE_BUNDLE_COMPONENT = 3;
    private const ORDER_ITEM_TYPE_SET_COMPONENT = 14;
    private const WAREHOUSE_ID = 1;
    private const REASON_ID_DEFECT = 207;

    private $flowName = '';
    private $workflowName = '';
    private $stepName = '';

    public function getIdentifier(): string
    {
        return 'DefectiveReturnStock::book-out-v3';
    }

    public function getPath(): string
    {
        return 'plugins';
    }

    public function getPathIcon(): string
    {
        return 'extension';
    }

    public function getParentPathIcon(): string
    {
        return 'extension';
    }

    public function getName(): string
    {
        return 'Defekte Retoure aus Lagerort ausbuchen (V3)';
    }

    public function getIcon(): string
    {
        return 'remove_shopping_cart';
    }

    public function getDescription(): string
    {
        return 'V3: Bucht die Retourenmenge ueber die Lagerbestands-Schnittstelle aus Lager 1 aus und prueft die physische Bestandsminderung.';
    }

    public function getUIConfigFields(): array
    {
        return [];
    }

    public function getRequiredInputTypes(): array
    {
        return [FlowTriggerObjectOrder::IDENTIFIER];
    }

    public function getProvidedOutputTypes(): array
    {
        return [FlowTriggerObjectOrder::IDENTIFIER];
    }

    public function getTooltip(): string
    {
        return 'Nur im Zweig fuer defekte Retouren verwenden.';
    }

    public function getDeprecationDate(): ?string
    {
        return null;
    }

    public function isSystemSpecific(): bool
    {
        return false;
    }

    public function getCondition(): bool
    {
        return true;
    }

    public function performTask(
        array $inputs,
        array $configFields,
        Filter $filter = null,
        array $extraParams = []
    ): array {
        if (!isset($inputs[FlowTriggerObjectOrder::IDENTIFIER])) {
            throw new RuntimeException(
                'Der Flow hat keine Auftragseingabe an die Plugin-Aktion uebergeben. Bitte den Adapter direkt vor der Aktion pruefen.'
            );
        }

        if (count($inputs[FlowTriggerObjectOrder::IDENTIFIER]) === 0) {
            throw new RuntimeException(
                'Die Auftragseingabe der Plugin-Aktion ist leer. Bitte im vorgeschalteten Adapter die Retoure als Auftrag zuordnen.'
            );
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);
        /** @var StockRepositoryContract $stockRepository */
        $stockRepository = pluginApp(StockRepositoryContract::class);

        $outputs = [];
        $processedInputs = 0;

        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $processedInputs++;
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Der Flow hat keine gueltige Auftrags-ID uebergeben.');
            }

            $order = $orderRepository->findById($orderId, ['amounts']);
            if ((int) $order->typeId !== self::ORDER_TYPE_RETURN) {
                throw new RuntimeException('Auftrag ' . $orderId . ' ist keine Retoure.');
            }

            $variationQuantities = $this->getVariationQuantities($order);
            if (count($variationQuantities) === 0) {
                throw new RuntimeException(
                    'Retoure ' . $orderId . ' enthaelt keine bestandsrelevanten Positionen.'
                );
            }

            $bookingPlans = [];
            $alreadyBookedDescriptions = [];
            $bookingMetadata = $this->getBookingMetadata($order);
            foreach ($variationQuantities as $variationId => $requiredQuantity) {
                if ($this->hasExistingBooking(
                    $orderId,
                    (int) $variationId,
                    (float) $requiredQuantity,
                    $stockRepository
                )) {
                    $alreadyBookedDescriptions[] = 'Variante ' . (int) $variationId
                        . ' mit Menge ' . (float) $requiredQuantity;
                    continue;
                }

                $bookingPlans[] = [
                    'variationId' => (int) $variationId,
                    'quantity' => (float) $requiredQuantity
                ];
            }

            if (count($bookingPlans) === 0) {
                throw new RuntimeException(
                    'Keine neue Ausbuchung ausgefuehrt: Fuer Retoure ' . $orderId
                    . ' wurde bereits eine Ausbuchungsbewegung mit Grund 207 gefunden ('
                    . implode(', ', $alreadyBookedDescriptions) . ').'
                );
            }

            foreach ($bookingPlans as $bookingPlan) {
                $this->bookVariation(
                    $orderId,
                    $bookingPlan,
                    $bookingMetadata,
                    $stockRepository
                );
            }

            $outputs[] = $input;
        }

        if ($processedInputs === 0) {
            throw new RuntimeException(
                'Plenty hat eine Auftragseingabe gemeldet, aber keinen auswertbaren Auftrag geliefert.'
            );
        }

        return $outputs;
    }

    private function getVariationQuantities($order): array
    {
        $variationQuantities = [];

        foreach ($order->orderItems as $orderItem) {
            $typeId = (int) $orderItem->typeId;
            if (!in_array($typeId, [
                self::ORDER_ITEM_TYPE_VARIATION,
                self::ORDER_ITEM_TYPE_BUNDLE_COMPONENT,
                self::ORDER_ITEM_TYPE_SET_COMPONENT
            ], true)) {
                continue;
            }

            $variationId = (int) $orderItem->itemVariationId;
            $quantity = abs((float) $orderItem->quantity);
            if ($variationId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!isset($variationQuantities[$variationId])) {
                $variationQuantities[$variationId] = 0.0;
            }
            $variationQuantities[$variationId] += $quantity;
        }

        return $variationQuantities;
    }

    private function hasExistingBooking(
        int $orderId,
        int $variationId,
        float $requiredQuantity,
        StockRepositoryContract $stockRepository
    ): bool {
        try {
            $stockRepository->setFilters([
                'variationId' => $variationId,
                'orderId' => $orderId,
                'processRowType' => 2
            ]);

            $movements = $stockRepository->listStockMovements(
                self::WAREHOUSE_ID,
                [
                    'id',
                    'variationId',
                    'warehouseId',
                    'quantity',
                    'reason',
                    'processRowId',
                    'processRowType'
                ],
                1,
                50
            )->getResult();
            $stockRepository->clearFilters();
        } catch (\Throwable $exception) {
            $stockRepository->clearFilters();
            throw new RuntimeException(
                'Vorhandene Ausbuchungen der Variante ' . $variationId
                . ' fuer Retoure ' . $orderId . ' konnten nicht geprueft werden: '
                . $exception->getMessage()
            );
        }

        $bookedQuantity = 0.0;
        foreach ($movements as $movement) {
            if ((int) $movement->variationId !== $variationId
                || (int) $movement->warehouseId !== self::WAREHOUSE_ID
                || (int) $movement->processRowType !== 2
                || (int) $movement->processRowId !== $orderId
                || (int) $movement->reason !== self::REASON_ID_DEFECT
                || (float) $movement->quantity >= 0
            ) {
                continue;
            }

            $bookedQuantity += abs((float) $movement->quantity);
        }

        return $bookedQuantity >= $requiredQuantity;
    }

    private function bookVariation(
        int $orderId,
        array $bookingPlan,
        array $bookingMetadata,
        StockRepositoryContract $stockRepository
    ): void {
        $variationId = (int) $bookingPlan['variationId'];
        $quantity = (float) $bookingPlan['quantity'];
        $bookingData = [
            'outgoingItems' => [
                [
                    'variationId' => $variationId,
                    'warehouseId' => self::WAREHOUSE_ID,
                    'deliveredAt' => $bookingMetadata['deliveredAt'],
                    'orderNumber' => (string) $orderId,
                    'currency' => $bookingMetadata['currency'],
                    'exchangeRate' => $bookingMetadata['exchangeRate'],
                    'quantity' => $quantity,
                    'reasonId' => self::REASON_ID_DEFECT
                ]
            ]
        ];

        try {
            $stockBefore = $this->readPhysicalStock(
                $variationId,
                $stockRepository
            );

            $stockRepository->bookOutgoingItems(
                self::WAREHOUSE_ID,
                $bookingData
            );

            $stockAfter = $this->readPhysicalStock(
                $variationId,
                $stockRepository
            );
            $expectedMaximum = $stockBefore - $quantity + 0.00001;
            if ($stockAfter > $expectedMaximum) {
                throw new RuntimeException(
                    'Plenty hat keine Bestandsminderung bestaetigt. Variante '
                    . $variationId . ', Lager 1: vorher ' . $stockBefore
                    . ', nachher ' . $stockAfter . ', erwartet hoechstens '
                    . ($stockBefore - $quantity) . '.'
                );
            }
        } catch (ValidationException $exception) {
            $validationMessages = $exception->getMessageBag()->all();
            throw new RuntimeException(
                'Ausbuchung der Variante ' . $variationId . ' fuer Retoure '
                . $orderId . ' aus Lager 1, Standard-Lagerort abgelehnt: '
                . implode(' | ', $validationMessages)
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Ausbuchung der Variante ' . $variationId . ' fuer Retoure '
                . $orderId . ' aus Lager 1, Standard-Lagerort fehlgeschlagen: '
                . $exception->getMessage()
            );
        }
    }

    private function readPhysicalStock(
        int $variationId,
        StockRepositoryContract $stockRepository
    ): float {
        try {
            $stockRepository->setFilters([
                'variationId' => $variationId
            ]);
            $stocks = $stockRepository->listStockByWarehouseId(
                self::WAREHOUSE_ID,
                [
                    'variationId',
                    'warehouseId',
                    'stockPhysical'
                ],
                1,
                50
            )->getResult();
            $stockRepository->clearFilters();
        } catch (\Throwable $exception) {
            $stockRepository->clearFilters();
            throw new RuntimeException(
                'Der physische Bestand der Variante ' . $variationId
                . ' in Lager 1 konnte nicht gelesen werden: '
                . $exception->getMessage()
            );
        }

        foreach ($stocks as $stock) {
            if ((int) $stock->variationId !== $variationId
                || (int) $stock->warehouseId !== self::WAREHOUSE_ID
            ) {
                continue;
            }

            return (float) $stock->stockPhysical;
        }

        throw new RuntimeException(
            'Plenty hat fuer Variante ' . $variationId
            . ' keinen Aggregatbestand in Lager 1 geliefert.'
        );
    }

    private function getBookingMetadata($order): array
    {
        $deliveredAt = Carbon::now()->toW3cString();

        $currency = 'EUR';
        $exchangeRate = 1.0;
        $fallbackCurrency = '';
        $fallbackExchangeRate = 1.0;

        if ($order->amounts !== null) {
            foreach ($order->amounts as $amount) {
                $amountCurrency = (string) $amount->currency;
                $amountExchangeRate = (float) $amount->exchangeRate;
                if ($amountCurrency === '') {
                    continue;
                }
                if ($amountExchangeRate <= 0) {
                    $amountExchangeRate = 1.0;
                }

                if ($fallbackCurrency === '') {
                    $fallbackCurrency = $amountCurrency;
                    $fallbackExchangeRate = $amountExchangeRate;
                }

                if ((bool) $amount->isSystemCurrency) {
                    $currency = $amountCurrency;
                    $exchangeRate = $amountExchangeRate;
                    return [
                        'deliveredAt' => $deliveredAt,
                        'currency' => $currency,
                        'exchangeRate' => $exchangeRate
                    ];
                }
            }
        }

        if ($fallbackCurrency !== '') {
            $currency = $fallbackCurrency;
            $exchangeRate = $fallbackExchangeRate;
        }

        return [
            'deliveredAt' => $deliveredAt,
            'currency' => $currency,
            'exchangeRate' => $exchangeRate
        ];
    }

    public function validateConfigFields(array $configFields): void
    {
    }

    public function validateInputs($inputs): void
    {
    }

    public function setFlowName(string $flowName): void
    {
        $this->flowName = $flowName;
    }

    public function getFlowName(): string
    {
        return $this->flowName;
    }

    public function setWorkflowName(string $workflowName): void
    {
        $this->workflowName = $workflowName;
    }

    public function getWorkflowName(): string
    {
        return $this->workflowName;
    }

    public function setStepName(string $stepName): void
    {
        $this->stepName = $stepName;
    }

    public function getStepName(): string
    {
        return $this->stepName;
    }
}
