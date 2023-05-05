<?php

declare(strict_types=1);

namespace Zeroseven\Rampage\Backend\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Zeroseven\Rampage\Exception\RegistrationException;
use Zeroseven\Rampage\Registration\RegistrationService;
use Zeroseven\Rampage\Utility\IdentifierUtility;
use Zeroseven\Rampage\Utility\TagUtility;

class TagsElement extends AbstractFormElement
{
    protected string $name;
    protected string $id;
    protected string $value;
    protected string $placeholder;
    protected string $objectClass;
    protected int $languageUid;

    public function __construct(NodeFactory $nodeFactory, array $data)
    {
        parent::__construct($nodeFactory, $data);

        $parameterArray = $this->data['parameterArray'] ?? [];
        $placeholder = $parameterArray['fieldConf']['config']['placeholder'] ?? '';
        $sysLanguageUid = $this->data['databaseRow']['sys_language_uid'] ?? 0;

        $this->name = $parameterArray['itemFormElName'] ?? '';
        $this->id = $parameterArray['itemFormElID'] ?? '';
        $this->value = $parameterArray['itemFormElValue'] ?? '';
        $this->placeholder = str_starts_with($placeholder, 'LLL') ? $this->getLanguageService()->sL($placeholder) : $placeholder;
        $this->objectClass = $this->data['databaseRow'][IdentifierUtility::OBJECT_FIELD_NAME] ?? '';
        $this->languageUid = (int)($sysLanguageUid[0] ?? $sysLanguageUid);
    }

    /** @throws RegistrationException */
    protected function renderRequireJsModules(): array
    {
        $tags = ($registration = RegistrationService::getRegistrationByClassName($this->objectClass))
            ? TagUtility::getTagsByRegistration($registration, null, true, $this->languageUid)
            : [];

        return [['TYPO3/CMS/Rampage/Backend/Tagify' => 'function(Tagify){
             new Tagify(document.getElementById("' . $this->id . '"), {
                whitelist: ' . json_encode($tags) . ',
                originalInputValueFormat: (function (valuesArr) {
                  return valuesArr.map(function (item) {
                    return item.value;
                  }).join(", ").trim();
                })
            })
        }']];
    }

    protected function renderHtml(): string
    {
        $fieldWizardResult = $this->renderFieldWizard();
        $formField = '<input type="text" ' . GeneralUtility::implodeAttributes([
                'name' => $this->name,
                'value' => $this->value,
                'id' => $this->id,
                'placeholder' => $this->placeholder,
                'class' => 'form-control form-control--tags'
            ], true) . ' />';

        return '
            <div class="form-control-wrap">
                <div class="form-wizards-wrap">
                    <div class="form-wizards-element">' . $formField . '</div>
                    <div class="form-wizards-items-bottom">' . ($fieldWizardResult['html'] ?? '') . '</div>
                </div>
            </div>
        ';
    }

    public function render()
    {
        return [
            'html' => $this->renderHtml(),
            'requireJsModules' => $this->renderRequireJsModules()
        ];
    }

    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1677874287] = [
            'nodeName' => 'rampageTags',
            'priority' => 100,
            'class' => self::class,
        ];
    }
}
