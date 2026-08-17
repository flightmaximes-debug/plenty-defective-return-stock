<?php

namespace DefectiveReturnStock\Flow;

use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Order\Booking\Contracts\OrderBookingRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Transaction\Contracts\OrderItemTransactionRepositoryContract;
use RuntimeException;

class BookDefectiveReturnStockFlowAction extends StepActionDefinitionContract
{
    private const ORDER_TYPE_RETURN = 3;
    private const ORDER_ITEM_TYPE_VARIATION = 1;
    private const ORDER_ITEM_TYPE_BUNDLE_COMPONENT = 3;
    private const ORDER_ITEM_TYPE_SET_COMPONENT = 14;
    private const WAREHOUSE_ID = 1;
    private const TRANSACTION_MARKER_PREFIX = 'DefectiveReturnStock:';

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
        return 'Erzeugt für die gebuchte Retouren-Einbuchung eine sichere Gegenbuchung auf demselben Lagerort.';
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
        return 'Nur im Zweig für defekte Retouren verwenden.';
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
                'Der Flow hat keine Auftragseingabe an die Plugin-Aktion übergeben. Bitte den Adapter direkt vor der Aktion prüfen.'
            );
        }

        if (count($inputs[FlowTriggerObjectOrder::IDENTIFIER]) === 0) {
            throw new RuntimeException(
                'Die Auftragseingabe der Plugin-Aktion ist leer. Bitte im vorgeschalteten Adapter die Retoure als Auftrag zuordnen.'
            );
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);
        /** @var OrderItemTransactionRepositoryContract $transactionRepository */
        $transactionRepository = pluginApp(OrderItemTransactionRepositoryContract::class);
        /** @var OrderBookingRepositoryContract $bookingRepository */
        $bookingRepository = pluginApp(OrderBookingRepositoryContract::class);

        $outputs = [];
        $processedInputs = 0;
        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $processedInputs++;
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Der Flow hat keine gültige Auftrags-ID übergeben.');
            }

            $order = $orderRepository->findById(
                $orderId
            );
            if ((int) $order->typeId !== self::ORDER_TYPE_RETURN) {
                throw new RuntimeException('Auftrag ' . $orderId . ' ist keine Retoure.');
            }

            $transactionIds = $this->createOutgoingTransactions(
                $order,
                $transactionRepository
            );

            if (count($transactionIds) > 0) {
                try {
                    $booking = $bookingRepository->bookOrderItemTransactions(
                        $transactionIds,
                        self::WAREHOUSE_ID
                    );
                } catch (\Throwable $exception) {
                    throw new RuntimeException(
                        'Gegenbuchung der Retouren-Transaktionen fehlgeschlagen: '
                        . $exception->getMessage()
                    );
                }

                if (count((array) $booking->failed) > 0) {
                    throw new RuntimeException(
                        'Plenty konnte ' . count((array) $booking->failed)
                        . ' Retouren-Transaktion(en) nicht ausbuchen.'
                    );
                }
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

    private function createOutgoingTransactions(
        $order,
        OrderItemTransactionRepositoryContract $transactionRepository
    ): array {
        $transactionIds = [];
        $stockRelevantItems = 0;
        $bookedIncomingTransactions = 0;

        foreach ($order->orderItems as $orderItem) {
            $typeId = (int) $orderItem->typeId;
            if (!in_array($typeId, [
                self::ORDER_ITEM_TYPE_VARIATION,
                self::ORDER_ITEM_TYPE_BUNDLE_COMPONENT,
                self::ORDER_ITEM_TYPE_SET_COMPONENT
            ], true)) {
                continue;
            }

            $orderItemId = (int) $orderItem->id;
            $variationId = (int) $orderItem->itemVariationId;
            $orderItemQuantity = abs((float) $orderItem->quantity);
            if ($orderItemId <= 0 || $variationId <= 0 || $orderItemQuantity <= 0) {
                continue;
            }

            $stockRelevantItems++;
            $transactions = $transactionRepository->list($orderItemId);

            foreach ($transactions as $incomingTransaction) {
                if (
                    (string) $incomingTransaction->direction !== 'in'
                    || (string) $incomingTransaction->status !== 'regular'
                    || (int) $incomingTransaction->receiptId <= 0
                ) {
                    continue;
                }

                $incomingTransactionId = (int) $incomingTransaction->id;
                $quantity = abs((float) $incomingTransaction->quantity);
                $warehouseLocationId = (int) $incomingTransaction->warehouseLocationId;
                if ($incomingTransactionId <= 0 || $quantity <= 0) {
                    continue;
                }

                $bookedIncomingTransactions++;
                $identification = self::TRANSACTION_MARKER_PREFIX
                    . (int) $order->id . ':' . $orderItemId . ':' . $incomingTransactionId;
                $existingOutgoingTransaction = $this->findPluginTransaction(
                    $transactions,
                    $identification
                );

                if ($existingOutgoingTransaction !== null) {
                    if ((int) $existingOutgoingTransaction->receiptId <= 0) {
                        $transactionIds[] = (int) $existingOutgoingTransaction->id;
                    }
                    continue;
                }

                $transactionData = [
                    'orderItemId' => $orderItemId,
                    'quantity' => $quantity,
                    'identification' => $identification,
                    'direction' => 'out',
                    'status' => 'regular',
                    'warehouseLocationId' => $warehouseLocationId
                ];

                if ((int) $incomingTransaction->userId > 0) {
                    $transactionData['userId'] = (int) $incomingTransaction->userId;
                }
                if ((string) $incomingTransaction->batch !== '') {
                    $transactionData['batch'] = (string) $incomingTransaction->batch;
                }
                if ((string) $incomingTransaction->bestBeforeDate !== '') {
                    $transactionData['bestBeforeDate'] = (string) $incomingTransaction->bestBeforeDate;
                }

                try {
                    $outgoingTransaction = $transactionRepository->create($transactionData);
                } catch (\Throwable $exception) {
                    throw new RuntimeException(
                        'Gegenbuchung für Variante ' . $variationId
                        . ' konnte nicht vorbereitet werden: ' . $exception->getMessage()
                    );
                }

                $transactionIds[] = (int) $outgoingTransaction->id;
            }
        }

        if ($stockRelevantItems === 0) {
            throw new RuntimeException(
                'Retoure ' . (int) $order->id
                . ' enthält keine bestandsrelevanten Positionen.'
            );
        }

        if ($bookedIncomingTransactions === 0) {
            throw new RuntimeException(
                'Für Retoure ' . (int) $order->id
                . ' wurden keine gebuchten Retouren-Einbuchungen gefunden. '
                . 'Die Plugin-Aktion muss nach der automatischen Bestandseinbuchung ausgeführt werden.'
            );
        }

        return $transactionIds;
    }

    private function findPluginTransaction($transactions, string $identification)
    {
        foreach ($transactions as $transaction) {
            if (
                (string) $transaction->direction === 'out'
                && (string) $transaction->identification === $identification
            ) {
                return $transaction;
            }
        }

        return null;
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
