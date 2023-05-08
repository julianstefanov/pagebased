<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Registration;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Zeroseven\Rampage\Domain\Model\AbstractPage;
use Zeroseven\Rampage\Domain\Model\PageTypeInterface;
use Zeroseven\Rampage\Utility\RootLineUtility;

class RegistrationService
{
    public static function createRegistration(string $extensionName): Registration
    {
        return GeneralUtility::makeInstance(Registration::class, $extensionName);
    }

    /** @return Registration[] */
    public static function getRegistrations(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['USER']['zeroseven-rampage']['registrations'] ?? [];
    }

    public static function addRegistration(Registration $registration): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['USER']['zeroseven-rampage']['registrations'][$registration->getIdentifier()] = $registration;
    }

    public static function getRegistrationByClassName($className): ?Registration
    {
        foreach (self::getRegistrations() as $registration) {
            if ($registration->getObject()->getClassName() === $className) {
                return $registration;
            }
        }

        return null;
    }

    public static function getRegistrationByController($className): ?Registration
    {
        foreach (self::getRegistrations() as $registration) {
            if ($registration->getObject()->getControllerClassName() === $className) {
                return $registration;
            }
        }

        return null;
    }

    public static function getRegistrationByRepository($className): ?Registration
    {
        foreach (self::getRegistrations() as $registration) {
            if ($registration->getObject()->getRepositoryClassName() === $className) {
                return $registration;
            }
        }

        return null;
    }

    public static function getRegistrationByDemandClass($className): ?Registration
    {
        foreach (self::getRegistrations() as $registration) {
            if ($registration->getObject()->getRepositoryClassName() === $className) {
                return $registration;
            }
        }

        return null;
    }

    public static function getRegistrationByCategoryDocumentType(int $documentType): ?Registration
    {
        foreach (self::getRegistrations() as $registration) {
            if ($registration->getCategory()->getObjectType() === $documentType) {
                return $registration;
            }
        }

        return null;
    }

    public static function getRegistrationByIdentifier(string $identifier): ?Registration
    {
        $registrations = self::getRegistrations();

        return $registrations[$identifier] ?? null;
    }

    public static function getRegistrationByCategoryPageUid(int $id, array $row = null): ?Registration
    {
        if ($typeField = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type']) {
            $documentType = $row[$typeField] ?? (BackendUtility::getRecord(AbstractPage::TABLE_NAME, $id, $typeField)[$typeField] ?? null);

            if ($documentType && $registration = self::getRegistrationByCategoryDocumentType((int)$documentType)) {
                return $registration;
            }
        }

        return null;
    }

    public static function getObjectRegistrationInRootLine(mixed $startPoint, array $row = null): ?Registration
    {
        if (MathUtility::canBeInterpretedAsInteger($startPoint) && self::getRegistrationByCategoryPageUid($startPoint, $row) === null) {
            foreach (RootLineUtility::collectPagesAbove($startPoint) as $uid => $data) {
                if ($uid !== $startPoint && $registration = self::getRegistrationByCategoryPageUid((int)$uid, $data)) {
                    return $registration;
                }
            }
        }

        return null;
    }

    public static function extbasePersistenceConfiguration(array $classConfiguration): array
    {
        foreach ($classConfiguration as $className => $configuration) {
            if (!is_array($configuration)) {
                $classConfiguration[$className] = [];
            }

            if (!isset($configuration['tableName']) && is_subclass_of($className, AbstractPage::class)) {
                $classConfiguration[$className]['tableName'] = AbstractPage::TABLE_NAME;
            }

            if (!isset($configuration['recordType']) && is_subclass_of($className, PageTypeInterface::class)) {
                $classConfiguration[$className]['recordType'] = $className::getType();
            }
        }

        return $classConfiguration;
    }
}
