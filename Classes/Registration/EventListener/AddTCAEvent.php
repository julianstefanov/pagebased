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
use Zeroseven\Rampage\Domain\Model\Demand\AbstractObjectDemand;
use Zeroseven\Rampage\Exception\RegistrationException;
use Zeroseven\Rampage\Registration\AbstractPluginRegistration;
use Zeroseven\Rampage\Registration\FlexForm\FlexFormConfiguration;
use Zeroseven\Rampage\Registration\FlexForm\FlexFormSheetConfiguration;
use Zeroseven\Rampage\Registration\Registration;
use Zeroseven\Rampage\Registration\RegistrationService;
use Zeroseven\Rampage\Utility\IdentifierUtility;
use Zeroseven\Rampage\Utility\TCAUtility;

class AddTCAEvent
{
    protected function createPlugin(Registration $registration, AbstractPluginRegistration $pluginRegistration): string
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
    protected function addPageType(Registration $registration): void
    {
        if ($objectRegistration = $registration->getObject()) {
            $displayCondition = sprintf('FIELD:%s:=:%s', IdentifierUtility::OBJECT_FIELD_NAME, $objectRegistration->getClassName());

            TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_date', $displayCondition);
            TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_relations_to', $displayCondition);
            TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_relations_from', $displayCondition);

            if ($objectRegistration->topEnabled()) {
                TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_top', $displayCondition);
            }

            if ($objectRegistration->tagsEnabled()) {
                TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_tags', $displayCondition);
            }

            if ($objectRegistration->topicsEnabled()) {
                TCAUtility::addDisplayCondition(AbstractPage::TABLE_NAME, '_rampage_topics', $displayCondition);
            }
        }
    }

    /** @throws RegistrationException */
    protected function addPageCategory(Registration $registration): void
    {
        if (($categoryRegistration = $registration->getCategory()) && $pageType = $categoryRegistration->getObjectType()) {

            // Add to type list
            if ($tcaTypeField = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type'] ?? null) {
                ExtensionManagementUtility::addTcaSelectItem(
                    AbstractPage::TABLE_NAME,
                    $tcaTypeField,
                    [
                        $categoryRegistration->getTitle(),
                        $pageType,
                        $categoryRegistration->getIconIdentifier()
                    ],
                    '1',
                    'after'
                );
            }

            // Add basic fields
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['types'][$pageType]['showitem'] = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['types'][1]['showitem'];

            // Add icon
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['typeicon_classes'][$pageType] = $categoryRegistration->getIconIdentifier();
            $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['typeicon_classes'][$pageType . '-hideinmenu'] = $categoryRegistration->getIconIdentifier(true);
        }
    }

    /** @throws TypeException | RegistrationException */
    protected function addListPlugin(Registration $registration): void
    {
        if ($registration->getListPlugin()) {
            $cType = $this->createPlugin($registration, $registration->getListPlugin());

            // FlexForm configuration
            if ($cType) {
                $filterSheet = FlexFormSheetConfiguration::makeInstance('filter', 'FILTER');

                if ($registration->hasCategory() && ($tcaTypeField = $GLOBALS['TCA'][AbstractPage::TABLE_NAME]['ctrl']['type'] ?? null)) {
                    $filterSheet->addField('settings.category', [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'minitems' => 0,
                        'maxitems' => 1,
                        'itemsProcFunc' => ItemsProcFunc::class . '->filterCategories',
                        'foreign_table' => 'pages',
                        'foreign_table_where' => sprintf(' AND pages.sys_language_uid <= 0 AND pages.%s = %d', $tcaTypeField, $registration->getCategory()->getObjectType()),
                        'items' => [
                            ['NO RESTRICTION', '--div--'],
                            ['SHOW ALL', 0],
                            ['AVAILABLE CATEGORIES', '--div--'],
                        ]
                    ], 'CATEGORY');
                }

                if ($registration->getObject()->tagsEnabled()) {
                    $filterSheet->addField('settings.tags', [
                        'type' => 'user',
                        'renderType' => 'rampageTags',
                        'placeholder' => 'ADD TAGS …',
                        'object' => $registration->getObject()->getClassName()
                    ], 'TAGS');
                }

                if ($registration->getObject()->topicsEnabled() && $topicPageIds = $registration->getObject()->getTopicPageIds()) {
                    $filterSheet->addField('settings.topics', [
                        'type' => 'select',
                        'renderType' => 'selectCheckBox',
                        'foreign_table' => 'tx_rampage_domain_model_topic',
                        'MM' => 'tx_rampage_object_topic_mm',
                        'default' => 0,
                        'foreign_table_where' => sprintf(' AND {#tx_rampage_domain_model_topic}.{#pid} IN(%s)', implode(',', $topicPageIds))
                    ], 'TOPICS');
                }

                $optionsSheet = FlexFormSheetConfiguration::makeInstance('options', 'OPTIONS');

                if ($registration->getObject()->topEnabled()) {
                    $optionsSheet->addField('settings.' . AbstractObjectDemand::PARAMETER_TOP_MODE, [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'minitems' => 1,
                        'maxitems' => 1,
                        'items' => [
                            ['DEFAULT', 0],
                            ['TOP OBJECTS FIRST', AbstractObjectDemand::TOP_MODE_FIRST],
                            ['ONLY TOP OBJECTS', AbstractObjectDemand::TOP_MODE_ONLY]
                        ]
                    ], 'TOP MODE');
                }

                $optionsSheet->addField('settings.' . AbstractDemand::PARAMETER_ORDER_BY, [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'minitems' => 1,
                    'maxitems' => 1,
                    'items' => [
                        ['DEFAULT', ''],
                        ['Date (newest first)', 'date_desc'],
                        ['Date (oldest first)', 'date_asc'],
                        ['Title (ASC)', 'title_asc'],
                        ['Title (DESC)', 'title_desc']
                    ]
                ], 'SORTING');

                $layoutSheet = FlexFormSheetConfiguration::makeInstance('layout', 'LAYOUT')
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
                    ->addSheet($filterSheet)
                    ->addSheet($optionsSheet)
                    ->addSheet($layoutSheet)
                    ->addToTCA();
            }
        }
    }

    /** @throws TypeException */
    protected function addFilterPlugin(Registration $registration): void
    {
        if ($registration->getFilterPlugin()) {
            $cType = $this->createPlugin($registration, $registration->getFilterPlugin());
            $listCType = $registration->getListPlugin()->getCType($registration);

            // FlexForm configuration
            if ($cType && $listCType) {
                $table = 'tt_content';

                $generalSheet = FlexFormSheetConfiguration::makeInstance('general', 'General setttings')
                    ->addField('settings.' . AbstractObjectDemand::PARAMETER_CONTENT_ID, [
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

    /** @throws RegistrationException | TypeException */
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
