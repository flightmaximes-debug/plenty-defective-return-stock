<?php

namespace DefectiveReturnStock\Flow;

use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\Models\Output;
use Plenty\Modules\Flow\StepActions\Definitions\Contracts\StepActionDefinitionContract;
use Plenty\Modules\Flow\Triggers\Objects\FlowTriggerObjectOrder;
use RuntimeException;

class DiagnosticReturnCommentAction extends StepActionDefinitionContract
{
    private $flowName = '';
    private $workflowName = '';
    private $stepName = '';

    public function getIdentifier(): string
    {
        return 'DefectiveReturnStock::diagnostic-comment-v11';
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
        return 'Defekte Retoure: Flow-Aufruf prüfen (Test V11)';
    }

    public function getIcon(): string
    {
        return 'comment';
    }

    public function getDescription(): string
    {
        return 'Hinterlegt einen internen Testkommentar an der Retoure.';
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
        /** @var CommentRepositoryContract $commentRepository */
        $commentRepository = pluginApp(CommentRepositoryContract::class);
        $outputs = [];

        foreach ($inputs[FlowTriggerObjectOrder::IDENTIFIER] as $input) {
            $orderId = (int) $input->value;
            if ($orderId <= 0) {
                throw new RuntimeException('Der Flow hat keine gültige Auftrags-ID übergeben.');
            }

            $commentRepository->createComment([
                'referenceType' => 'order',
                'referenceValue' => $orderId,
                'text' => 'DefectiveReturnStock Flow-Test V11 wurde ausgeführt.',
                'isVisibleForContact' => false
            ]);

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
