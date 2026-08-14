<?php

namespace DefectiveReturnStock\Flow;

use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\Models\Output;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use RuntimeException;

class LoadReturnOrderDiagnosticAction extends StepActionDefinitionContract
{
    private $flowName = '';
    private $workflowName = '';
    private $stepName = '';

    public function getIdentifier(): string
    {
        return 'DefectiveReturnStock::load-return-diagnostic-v3';
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
        return 'Defekte Retoure: Auftrag laden (Diagnose V3)';
    }

    public function getIcon(): string
    {
        return 'search';
    }

    public function getDescription(): string
    {
        return 'Prüft ausschließlich, ob der Retourenauftrag geladen werden kann. Ändert keinen Bestand.';
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
        return 'Sicherer Diagnosetest ohne Bestandsänderung.';
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
            throw new RuntimeException('Keine Auftragseingabe vorhanden.');
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);
        $outputs = [];

        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Ungültige Auftrags-ID.');
            }

            $order = $orderRepository->findById($orderId);
            if ((int) $order->id !== $orderId) {
                throw new RuntimeException('Auftrag konnte nicht eindeutig geladen werden.');
            }

            $outputs[] = pluginApp(Output::class, [
                'name' => FlowTriggerObjectOrder::IDENTIFIER,
                'value' => $input->value
            ]);
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
