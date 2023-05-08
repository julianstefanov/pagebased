<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Zeroseven\Rampage\Domain\Model\AbstractPage;
use Zeroseven\Rampage\Registration\Registration;
use Zeroseven\Rampage\Registration\RegistrationService;

class ObjectUtility
{
    protected static function getPageTypeField(): string
    {
        return $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type'];
    }

    public static function isCategory(int $pageUid, array $row = null): ?Registration
    {
        if ($typeField = self::getPageTypeField()) {
            $documentType = $row[$typeField] ?? (BackendUtility::getRecord(AbstractPage::TABLE_NAME, $pageUid, $typeField)[$typeField] ?? null);

            if ($documentType && $registration = RegistrationService::getRegistrationByCategoryDocumentType((int)$documentType)) {
                return $registration;
            }
        }

        return null;
    }

    public static function isObject(int $pageUid, array $row = null): ?Registration
    {
        if ($typeField = self::getPageTypeField()) {
            $registrationField = SettingsUtility::REGISTRATION_FIELD_NAME;

            if (!isset($row[$typeField], $row[$registrationField])) {
                $row = BackendUtility::getRecord(AbstractPage::TABLE_NAME, $pageUid, implode(',', [$registrationField, $typeField]));
            }

            if (($identifier = $row[$registrationField]) && !self::isCategory($pageUid, $row) && $registration = RegistrationService::getRegistrationByIdentifier($identifier)) {
                return $registration;
            }
        }

        return null;
    }

    public static function findRegistrationInRootLine(mixed $startPoint): ?Registration
    {
        if (MathUtility::canBeInterpretedAsInteger($startPoint)) {
            foreach (RootLineUtility::collectPagesAbove($startPoint) as $uid => $row) {
                if ($registration = self::isCategory((int)$uid, $row)) {
                    return $registration;
                }

                if ($registration = self::isObject((int)$uid, $row)) {
                    return $registration;
                }
            }
        }

        return null;
    }

    public static function findObjectInRootLine(mixed $startPoint): ?Registration
    {
        if (MathUtility::canBeInterpretedAsInteger($startPoint)) {
            foreach (RootLineUtility::collectPagesAbove($startPoint) as $uid => $row) {
                if ($registration = self::isObject((int)$uid, $row)) {
                    return $registration;
                }
            }
        }

        return null;
    }

    public static function findCategoryInRootLine(mixed $startPoint): ?Registration
    {
        if (MathUtility::canBeInterpretedAsInteger($startPoint)) {
            foreach (RootLineUtility::collectPagesAbove($startPoint) as $uid => $row) {
                if ($registration = self::isCategory((int)$uid, $row)) {
                    return $registration;
                }
            }
        }

        return null;
    }
}
