<?php

namespace DefectiveReturnStock\Flow;

use DefectiveReturnStock\Services\DefectiveReturnStockService;
use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\Models\Output;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use RuntimeException;

class BookDefectiveReturnStockAction extends StepActionDefinitionContract
{
    private const IDENTIFIER = 'defective-return-stock-book-out';

    private $service;
    private $orderRepository;
    private $flowName = '';
    private $workflowName = '';
    private $stepName = '';

    public function __construct(
        DefectiveReturnStockService $service,
        OrderRepositoryContract $orderRepository
    ) {
        $this->service = $service;
        $this->orderRepository = $orderRepository;
    }

    public function getIdentifier(): string
    {
        return 'DefectiveReturnStock::' . self::IDENTIFIER;
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
        return 'Defekte Retoure aus Retouren-Lagerort ausbuchen';
    }

    public function getIcon(): string
    {
        return 'remove_shopping_cart';
    }

    public function getDescription(): string
    {
        return 'Bucht alle bestandsrelevanten Positionen der aktuellen '
            . 'Retoure aus dem konfigurierten Retouren-Lagerort aus.';
    }

    public function getTooltip(): string
    {
        return 'Nur für defekte Retouren verwenden.';
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
        $filter = null,
        array $extraParams = []
    ): array {
        $outputs = [];
        $processedInput = false;

        foreach ($inputs as $input) {
            if ($input->name !== FlowTriggerObjectOrder::IDENTIFIER) {
                continue;
            }

            $processedInput = true;
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException(
                    'Der Flow hat keine gültige Auftrags-ID übergeben.'
                );
            }

            $order = $this->orderRepository->findOrderById($orderId);
            $this->service->process($order);

            $outputs[] = pluginApp(Output::class, [
                'name' => FlowTriggerObjectOrder::IDENTIFIER,
                'value' => $input->value
            ]);
        }

        if (!$processedInput) {
            throw new RuntimeException(
                'Der Flow hat kein Auftragsobjekt an die Plugin-Aktion übergeben.'
            );
        }

        return $outputs;
    }

    public function validateConfigFields(array $configFields): void
    {
    }

    public function validateInputs($inputs): void
    {
        if (!is_array($inputs) || count($inputs) === 0) {
            throw new RuntimeException(
                'Die Plugin-Aktion benötigt mindestens ein Flow-Eingabeobjekt.'
            );
        }
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
