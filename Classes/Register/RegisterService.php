<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Register;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RegisterService
{
    public static function createRegistration(string $extensionName, string $objectClassName, string $controllerClassName, string $repositoryClassName): Registration
    {
        return GeneralUtility::makeInstance(Registration::class, $extensionName, $objectClassName, $controllerClassName, $repositoryClassName);
    }

    /** @return Registration[] */
    public static function getRegistrations(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['USER']['zeroseven-rampage']['registrations'] ?? [];
    }

    public static function addRegistration(Registration $registration): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['USER']['zeroseven-rampage']['registrations'][$registration->getExtensionName()][$registration->getObect()->getTitle()] = $registration;
    }
}
