<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Hooks\IconFactory;

use TYPO3\CMS\Core\Imaging\IconFactory;
use Zeroseven\Rampage\Domain\Model\AbstractPage;
use Zeroseven\Rampage\Utility\ObjectUtility;

class OverrideIconOverlay
{
    public function postOverlayPriorityLookup(string $table, array $row, array $status, string $iconName = null): ?string
    {
        if ($table === AbstractPage::TABLE_NAME && empty($iconName) && $uid = (int)($row['uid'] ?? 0)) {

            if (($registration = ObjectUtility::isObject($uid)) && $object = $registration->getObject()->getRepositoryClass()->findByUid($uid)) {
                if ($object->isTop()) {
                    return 'overlay-approved';
                }

                if ($object->getParentObject()) {
                    return 'overlay-advanced';
                }

                return 'overlay-list';
            }
        }

        return $iconName;
    }

    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][IconFactory::class]['overrideIconOverlay'][] = self::class;
    }
}
