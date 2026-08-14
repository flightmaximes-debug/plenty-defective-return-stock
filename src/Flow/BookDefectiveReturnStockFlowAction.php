<?php

namespace DefectiveReturnStock\Flow;

use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\NumberField;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\Models\Output;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
use RuntimeException;

class BookDefectiveReturnStockFlowAction extends StepActionDefinitionContract
{
    private const ORDER_TYPE_RETURN = 3;
    private const ORDER_ITEM_TYPE_VARIATION = 1;
    private const ORDER_ITEM_TYPE_BUNDLE_COMPONENT = 3;
    private const ORDER_ITEM_TYPE_SET_COMPONENT = 14;
    private const SUCCESS_MARKER = 'DefectiveReturnStock: Bestand erfolgreich ausgebucht.';

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
        return 'Bucht Varianten und Set-Komponenten einer Retoure aus dem gewählten Lagerort aus.';
    }

    public function getUIConfigFields(): array
    {
        /** @var UIConfigFormContract $configForm */
        $configForm = pluginApp(UIConfigFormContract::class, [
            'translationNamespace' => 'DefectiveReturnStock'
        ]);

        $warehouseField = $this->getFormField(NumberField::class, [
            'name' => 'warehouseId',
            'label' => 'Lager-ID',
            'value' => '1'
        ]);

        $storageLocationField = $this->getFormField(NumberField::class, [
            'name' => 'storageLocationId',
            'label' => 'Lagerort-ID',
            'value' => '0'
        ]);

        $reasonField = $this->getFormField(SelectboxField::class, [
            'name' => 'reasonId',
            'label' => 'Buchungsgrund'
        ]);
        $reasonField->addSelectboxValue('Defekt (207)', '207', false);
        $reasonField->addSelectboxValue('Ausschuss (205)', '205', false);
        $reasonField->addSelectboxValue('Reklamation (208)', '208', false);
        $reasonField->addSelectboxValue('Korrektur (216)', '216', false);
        $reasonField->value = '207';

        $configForm->addNumberField($warehouseField);
        $configForm->addNumberField($storageLocationField);
        $configForm->addSelectboxField($reasonField);

        return $configForm->getConfigFields();
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

        $warehouseId = (int) $configFields['warehouseId']->value;
        $storageLocationId = (int) $configFields['storageLocationId']->value;
        $reasonId = (int) $configFields['reasonId']->value;

        if ($warehouseId <= 0 || $storageLocationId < 0) {
            throw new RuntimeException('Lager-ID oder Lagerort-ID ist ungültig.');
        }

        if (!in_array($reasonId, [205, 207, 208, 216], true)) {
            throw new RuntimeException('Der gewählte Buchungsgrund ist ungültig.');
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);
        /** @var StockRepositoryContract $stockRepository */
        $stockRepository = pluginApp(StockRepositoryContract::class);
        /** @var CommentRepositoryContract $commentRepository */
        $commentRepository = pluginApp(CommentRepositoryContract::class);

        $outputs = [];
        $processedInputs = 0;
        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $processedInputs++;
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Der Flow hat keine gültige Auftrags-ID übergeben.');
            }

            $order = $orderRepository->findById(
                $orderId,
                ['comments', 'orderItems']
            );
            if ((int) $order->typeId !== self::ORDER_TYPE_RETURN) {
                throw new RuntimeException('Auftrag ' . $orderId . ' ist keine Retoure.');
            }

            $alreadyProcessed = false;
            if ($order->comments !== null) {
                foreach ($order->comments as $comment) {
                    if ((string) $comment->text === self::SUCCESS_MARKER) {
                        $alreadyProcessed = true;
                        break;
                    }
                }
            }

            if (!$alreadyProcessed) {
                $quantities = [];
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

                $commentRepository->createComment([
                    'referenceType' => 'order',
                    'referenceValue' => $orderId,
                    'text' => self::SUCCESS_MARKER,
                    'isVisibleForContact' => false
                ]);
            }

            $outputs[] = pluginApp(Output::class, [
                'name' => FlowTriggerObjectOrder::IDENTIFIER,
                'value' => $input->value
            ]);
        }

        if ($processedInputs === 0) {
            throw new RuntimeException(
                'Plenty hat eine Auftragseingabe gemeldet, aber keinen auswertbaren Auftrag geliefert.'
            );
        }

        return $outputs;
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
