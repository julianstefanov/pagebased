<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Zeroseven\Rampage\Domain\Model\Demand\DemandInterface;
use Zeroseven\Rampage\Registration\Registration;
use Zeroseven\Rampage\Registration\RegistrationService;

abstract class AbstractPageTypeController extends AbstractController implements PageTypeControllerInterface
{
    protected ?Registration $registration = null;
    protected ?DemandInterface $demand = null;

    public function initializeAction()
    {
        parent::initializeAction();

        $this->initializeRegistration();
        $this->initializeDemand();
    }

    public function initializeRegistration(): void
    {
        $this->registration = RegistrationService::getRegistrationByController(get_class($this));
    }

    public function initializeDemand(): void
    {
        $objectClass = $this->registration->getObject()->getObjectClassName();
        $demandClass = $this->registration->getObject()->getDemandClassName();
        $parameterArray = array_merge($this->settings, (array)$this->requestArguments);

        $this->demand = GeneralUtility::makeInstance($demandClass, $objectClass, $parameterArray);
    }

    public function getDemand(): DemandInterface
    {
        return $this->demand;
    }

    public function listAction(): void
    {
//        $objects = $this->registration->getObject()->getRepositoryClassName()->findByDemand($this->demand);


        if (($contentID = ($this->contentData['uid'] ?? null)) && !$this->demand->getContentId()) {
            $this->demand->setContentId($contentID);
        }

        debug($this->demand);
    }
}
