<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Registration\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Type\Exception as TypeException;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Zeroseven\Rampage\Backend\TCA\GroupFilter;
use Zeroseven\Rampage\Backend\TCA\ItemsProcFunc;
use Zeroseven\Rampage\Domain\Model\AbstractPage;
use Zeroseven\Rampage\Domain\Model\Demand\AbstractDemand;
use Zeroseven\Rampage\Exception\RegistrationException;
use Zeroseven\Rampage\Registration\FlexForm\FlexFormConfiguration;
use Zeroseven\Rampage\Registration\FlexForm\FlexFormSheetConfiguration;
use Zeroseven\Rampage\Registration\PageObjectRegistration;
use Zeroseven\Rampage\Registration\PluginRegistration;
use Zeroseven\Rampage\Registration\Registration;
use Zeroseven\Rampage\Registration\RegistrationService;

class AddTCAEvent
{
    protected function createPlugin(Registration $registration, PluginRegistration $pluginRegistration): string
    {
        $CType = $pluginRegistration->getCType($registration);

        // Add some default fields to the content elements by copy configuration of "header"
        $GLOBALS['TCA']['tt_content']['types'][$CType]['showitem'] = $GLOBALS['TCA']['tt_content']['types']['header']['showitem'];

        // Register plugin
        ExtensionUtility::registerPlugin(
            $registration->getExtensionName(),
            ucfirst($pluginRegistration->getType()),
            $pluginRegistration->getTitle(),
            $pluginRegistration->getIconIdentifier()
        );

        // Register icon
        $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$CType] = $pluginRegistration->getIconIdentifier();

        return $CType;
    }

    /** @throws RegistrationException */
    protected function createPageType(PageObjectRegistration $pageObjectRegistration): void
    {
        if ($pageType = $pageObjectRegistration->getObjectType()) {

            // Add to type list
            if ($tcaTypeField = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type'] ?? null) {
                ExtensionManagementUtility::addTcaSelectItem(
                    AbstractPage::TABLE_NAME,
                    $tcaTypeField,
                    [
                        $pageObjectRegistration->getTitle(),
                        $pageType,
                        $pageObjectRegistration->getIconIdentifier()
                    ],
                    '1',
                    'after'
                );
            }

            // Add basic fields
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['types'][$pageType]['showitem'] = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['types'][1]['showitem'];

            // Add icon
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['typeicon_classes'][$pageType] = $pageObjectRegistration->getIconIdentifier();
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['typeicon_classes'][$pageType . '-hideinmenu'] = $pageObjectRegistration->getIconIdentifier(true);
        }
    }

    /** @throws RegistrationException */
    protected function addPageType(Registration $registration): void
    {
        if (($pageObject = $registration->getObject()) && $pageObject->isEnabled()) {
            $this->createPageType($pageObject);

            if ($pageType = $pageObject->getObjectType()) {
                ExtensionManagementUtility::addToAllTCAtypes(AbstractPage::TABLE_NAME, sprintf('
                    --div--;%s,
                        _rampage_top,
                        _rampage_tags,
                        _rampage_relations_to,
                        _rampage_relations_from
                ', $pageObject->getTitle()), (string)$pageType);

                // Configure relations
                $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['types'][$pageType]['columnsOverrides']['_rampage_relations_to']['config'] = [
                    'filter' => [
                        [
                            'userFunc' => GroupFilter::class . '->filterTypes',
                            'parameters' => [
                                'allowed' => $pageType
                            ]
                        ]
                    ],
                    'suggestOptions' => [
                        'default' => [
                            'searchWholePhrase' => 1,
                            'addWhere' => ' AND ' . AbstractPage::TABLE_NAME . '.uid != ###THIS_UID###'
                        ],
                        AbstractPage::TABLE_NAME => [
                            'searchCondition' => 'doktype = ' . $pageType
                        ]
                    ],
                ];
            }
        }
    }

    protected function addPageCategory(Registration $registration): void
    {
        if (($pageCategory = $registration->getCategory()) && $pageCategory->isEnabled()) {
            $this->createPageType($pageCategory);
        }
    }

    /** @throws TypeException */
    protected function addListPlugin(Registration $registration): void
    {
        if ($registration->getListPlugin()->isEnabled()) {
            $cType = $this->createPlugin($registration, $registration->getListPlugin());

            // FlexForm configuration
            if ($cType) {
                $optionsSheet = FlexFormSheetConfiguration::makeInstance('options');

                try {
                    $optionsSheet->addField('settings.tags', [
                            'type' => 'user',
                            'renderType' => 'rampageTags',
                            'placeholder' => 'ADD TAGS …',
                            'object' => $registration->getObject()->getObjectClassName()
                        ], 'PLACEHOLDER');
                } catch (RegistrationException $e) {
                }

                if ($registration->getCategory()->isEnabled() && $tcaTypeField = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type'] ?? null) {
                    $optionsSheet->addField('settings.category', [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'minitems' => 0,
                        'maxitems' => 1,
                        'itemsProcFunc' => ItemsProcFunc::class . '->filterCategories',
                        'foreign_table' => 'pages',
                        'foreign_table_where' => sprintf(' AND pages.sys_language_uid <= 0 AND pages.%s = %d', $tcaTypeField, $registration->getCategory()->getObjectClassName()::getType()),
                        'items' => [
                            ['NO RESTRICTION', '--div--'],
                            ['SHOW ALL', 0],
                            ['AVAILABLE CATEGORIES', '--div--'],
                        ]
                    ], 'CATEGORY');
                }

                $layoutSheet = FlexFormSheetConfiguration::makeInstance('layout', 'LAYOUT')
                    ->addField('settings.sorting', [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'minitems' => 1,
                        'maxitems' => 1,
                        'items' => [
                            ['default', 0],
                            ['Title (ASC)', 'title_asc'],
                            ['Title (DESC)', 'title_desc'],
                        ]
                    ], 'SORTING')
                    ->addField('settings.itemsPerStage', [
                        'placeholder' => '6',
                        'type' => 'input',
                        'eval' => 'trim,is_in',
                        'is_in' => ',0123456789'
                    ], 'ITEMS_PER_PAGE')
                    ->addField('settings.maxStages', [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'minitems' => 1,
                        'maxitems' => 1,
                        'items' => [
                            ['UNLIMITED', 0],
                            [1, 1],
                            [2, 2],
                            [3, 3],
                            [4, 4],
                            [5, 5],
                        ]
                    ], 'MAX_STAGES');

                FlexFormConfiguration::makeInstance('tt_content', $cType, 'pi_flexform', 'after:header')
                    ->addSheet($optionsSheet)
                    ->addSheet($layoutSheet)
                    ->addToTCA();
            }
        }
    }

    /** @throws TypeException */
    protected function addFilterPlugin(Registration $registration): void
    {
        if ($registration->getFilterPlugin()->isEnabled()) {
            $cType = $this->createPlugin($registration, $registration->getFilterPlugin());
            $listCType = $registration->getListPlugin()->getCType($registration);

            // FlexForm configuration
            if ($cType && $listCType) {
                $table = 'tt_content';

                $generalSheet = FlexFormSheetConfiguration::makeInstance('general', 'General setttings')
                    ->addField('settings.' . AbstractDemand::PARAMETER_CONTENT_ID, [
                        'type' => 'group',
                        'internal_type' => 'db',
                        'foreign_table' => $table,
                        'allowed' => $table,
                        'size' => '1',
                        'maxitems' => '1',
                        'suggestOptions' => [
                            'default' => [
                                'searchWholePhrase' => true
                            ],
                            $table => [
                                'searchCondition' => 'CType = "' . $listCType . '"'
                            ]
                        ],
                        'filter' => [
                            'userFunc' => GroupFilter::class . '->filterTypes',
                            'parameters' => [
                                'allowed' => $listCType
                            ]
                        ]
                    ], 'CONTENT id');

                FlexFormConfiguration::makeInstance($table, $cType, 'pi_flexform', 'after:header')
                    ->addSheet($generalSheet)
                    ->addToTCA();
            }
        }
    }

    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        foreach (RegistrationService::getRegistrations() as $registration) {
            $this->addPageType($registration);
            $this->addPageCategory($registration);
            $this->addListPlugin($registration);
            $this->addFilterPlugin($registration);
        }

        $event->setTca($GLOBALS['TCA']);
    }
}
