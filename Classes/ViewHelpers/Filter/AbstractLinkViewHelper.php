<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\ViewHelpers\Filter;

use TYPO3\CMS\Fluid\ViewHelpers\Link\ActionViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use Zeroseven\Rampage\Domain\Model\Demand\DemandInterface;

abstract class AbstractLinkViewHelper extends ActionViewHelper
{
    protected ?DemandInterface $demand;

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        // Register demand argument
        $this->registerArgument('demand', 'object', sprintf('The demand object (instance of %s)', DemandInterface::class));
    }

    /** @throws Exception */
    public function validateArguments(): void
    {
        parent::validateArguments();

        $this->initializeDemand();

        if (!$this->demand) {
            throw new Exception('Demand is undefined. Add argument "demand" to this viewHelper', 1678130615);
        }
    }

    protected function initializeDemand(): void
    {
        $this->demand = ($value = $this->arguments['demand'] ?? ($this->templateVariableContainer->get('demand'))) instanceof DemandInterface
            ? $value
            : null;
    }

    abstract protected function overrideDemandProperties(): void;

    abstract protected function overrideArguments(): void;

    public function render(): string
    {
        $this->overrideDemandProperties();
        $this->overrideArguments();

        if (empty($this->arguments['pluginName'])) {
            $this->arguments['pluginName'] = 'List';
        }

        return parent::render();
    }
}
