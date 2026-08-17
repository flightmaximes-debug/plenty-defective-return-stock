<?php

namespace DefectiveReturnStock\Flow;

use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\StockManagement\Stock\Contracts\StockStorageLocationRepositoryContract;
use RuntimeException;

class BookDefectiveReturnStockFlowAction extends StepActionDefinitionContract
{
    private const ORDER_TYPE_RETURN = 3;
    private const ORDER_ITEM_TYPE_VARIATION = 1;
    private const ORDER_ITEM_TYPE_BUNDLE_COMPONENT = 3;
    private const ORDER_ITEM_TYPE_SET_COMPONENT = 14;
    private const WAREHOUSE_ID = 1;
    private const REASON_ID_DEFECT = 207;
    private const MARKER_PREFIX = 'DefectiveReturnStock V1.5:';

    private $flowName = '';
    private $workflowName = '';
    private $stepName = '';

    public function getIdentifier(): string
    {
        return 'DefectiveReturnStock::book-out-v2';
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
        return 'Defekte Retoure aus Lagerort ausbuchen (V2)';
    }

    public function getIcon(): string
    {
        return 'remove_shopping_cart';
    }

    public function getDescription(): string
    {
        return 'Bucht die Retourenmenge aus dem tatsaechlich belegten Standard-Lagerort in Lager 1 aus.';
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
        /** @var StockStorageLocationRepositoryContract $stockLocationRepository */
        $stockLocationRepository = pluginApp(StockStorageLocationRepositoryContract::class);
        /** @var VariationStockRepositoryContract $variationStockRepository */
        $variationStockRepository = pluginApp(VariationStockRepositoryContract::class);
        /** @var CommentRepositoryContract $commentRepository */
        $commentRepository = pluginApp(CommentRepositoryContract::class);

        $outputs = [];
        $processedInputs = 0;

        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $processedInputs++;
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Der Flow hat keine gueltige Auftrags-ID uebergeben.');
            }

            $order = $orderRepository->findById($orderId, ['comments']);
            if ((int) $order->typeId !== self::ORDER_TYPE_RETURN) {
                throw new RuntimeException('Auftrag ' . $orderId . ' ist keine Retoure.');
            }

            $commentTexts = $this->getCommentTexts($order);
            $successMarker = self::MARKER_PREFIX . 'DONE:' . $orderId;
            if (in_array($successMarker, $commentTexts, true)) {
                $outputs[] = $input;
                continue;
            }

            $variationQuantities = $this->getVariationQuantities($order);
            if (count($variationQuantities) === 0) {
                throw new RuntimeException(
                    'Retoure ' . $orderId . ' enthaelt keine bestandsrelevanten Positionen.'
                );
            }

            $bookingPlans = [];
            foreach ($variationQuantities as $variationId => $requiredQuantity) {
                $variationMarker = $this->getVariationMarker(
                    $orderId,
                    (int) $variationId,
                    (float) $requiredQuantity
                );
                if (in_array($variationMarker, $commentTexts, true)) {
                    continue;
                }

                $stockEntries = $this->getStandardLocationStock(
                    (int) $variationId,
                    $stockLocationRepository
                );
                $availableQuantity = 0.0;
                foreach ($stockEntries as $stockEntry) {
                    $availableQuantity += (float) $stockEntry->quantity;
                }

                if (count($stockEntries) === 0 || $availableQuantity + 0.00001 < (float) $requiredQuantity) {
                    throw new RuntimeException(
                        'Variante ' . (int) $variationId . ' der Retoure ' . $orderId
                        . ' hat im Standard-Lagerort von Lager 1 nur Bestand '
                        . (string) $availableQuantity . ', benoetigt wird '
                        . (string) $requiredQuantity . '. Es wurde noch nichts ausgebucht.'
                    );
                }

                $bookingPlans[] = [
                    'variationId' => (int) $variationId,
                    'quantity' => (float) $requiredQuantity,
                    'stockEntries' => $stockEntries,
                    'marker' => $variationMarker
                ];
            }

            foreach ($bookingPlans as $bookingPlan) {
                $this->bookVariation(
                    $orderId,
                    $bookingPlan,
                    $variationStockRepository
                );

                $commentRepository->createComment([
                    'referenceType' => 'order',
                    'referenceValue' => $orderId,
                    'text' => $bookingPlan['marker'],
                    'isVisibleForContact' => false
                ], true);
                $commentTexts[] = $bookingPlan['marker'];
            }

            $commentRepository->createComment([
                'referenceType' => 'order',
                'referenceValue' => $orderId,
                'text' => $successMarker,
                'isVisibleForContact' => false
            ], true);

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

    private function getStandardLocationStock(
        int $variationId,
        StockStorageLocationRepositoryContract $stockLocationRepository
    ): array {
        try {
            $paginatedResult = $stockLocationRepository->listStockStorageLocationsByVariationId(
                $variationId,
                [],
                1,
                100,
                ['storageLocation']
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Bestand der Variante ' . $variationId . ' konnte nicht gelesen werden: '
                . $exception->getMessage()
            );
        }

        $standardEntries = [];
        foreach ($paginatedResult->getResult() as $stockEntry) {
            if (
                (int) $stockEntry->warehouseId !== self::WAREHOUSE_ID
                || (float) $stockEntry->quantity <= 0
                || !$this->isStandardLocation($stockEntry)
            ) {
                continue;
            }
            $standardEntries[] = $stockEntry;
        }

        return $standardEntries;
    }

    private function isStandardLocation($stockEntry): bool
    {
        if ((int) $stockEntry->storageLocationId === 0) {
            return true;
        }

        $stockData = $stockEntry->toArray();
        if (!isset($stockData['storageLocation']['name'])) {
            return false;
        }

        $name = (string) $stockData['storageLocation']['name'];

        return $name === 'Standard-Lagerort'
            || $name === 'Standardlagerort';
    }

    private function bookVariation(
        int $orderId,
        array $bookingPlan,
        VariationStockRepositoryContract $variationStockRepository
    ): void {
        $variationId = (int) $bookingPlan['variationId'];
        $remainingQuantity = (float) $bookingPlan['quantity'];

        foreach ($bookingPlan['stockEntries'] as $stockEntry) {
            if ($remainingQuantity <= 0.00001) {
                break;
            }

            $availableQuantity = (float) $stockEntry->quantity;
            $bookingQuantity = $availableQuantity < $remainingQuantity
                ? $availableQuantity
                : $remainingQuantity;

            $bookingData = [
                'warehouseId' => self::WAREHOUSE_ID,
                'quantity' => $bookingQuantity,
                'reasonId' => self::REASON_ID_DEFECT
            ];

            $storageLocationId = (int) $stockEntry->storageLocationId;
            if ($storageLocationId > 0) {
                $bookingData['storageLocationId'] = $storageLocationId;
            }

            $batch = (string) $stockEntry->batch;
            if ($batch !== '') {
                $bookingData['batch'] = $batch;
            }

            $bestBeforeDate = (string) $stockEntry->bestBeforeDate;
            if ($bestBeforeDate !== '' && $bestBeforeDate !== '0000-00-00') {
                $bookingData['bestBeforeDate'] = $bestBeforeDate;
            }

            try {
                $variationStockRepository->bookOutgoingItems(
                    $variationId,
                    $bookingData
                );
            } catch (\Throwable $exception) {
                throw new RuntimeException(
                    'Ausbuchung der Variante ' . $variationId
                    . ' aus Lager 1, Lagerort ' . $storageLocationId
                    . ' fehlgeschlagen: ' . $exception->getMessage()
                );
            }

            $remainingQuantity -= $bookingQuantity;
        }

        if ($remainingQuantity > 0.00001) {
            throw new RuntimeException(
                'Ausbuchung der Variante ' . $variationId . ' fuer Retoure '
                . $orderId . ' war unvollstaendig.'
            );
        }
    }

    private function getCommentTexts($order): array
    {
        $commentTexts = [];
        if ($order->comments === null) {
            return $commentTexts;
        }

        foreach ($order->comments as $comment) {
            $commentTexts[] = (string) $comment->text;
        }

        return $commentTexts;
    }

    private function getVariationMarker(
        int $orderId,
        int $variationId,
        float $quantity
    ): string {
        return self::MARKER_PREFIX . 'POSITION:' . $orderId . ':'
            . $variationId . ':' . (string) $quantity;
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
