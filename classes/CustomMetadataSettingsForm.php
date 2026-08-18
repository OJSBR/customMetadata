<?php

/**
 * @file classes/CustomMetadataSettingsForm.php
 *
 * Formulario de configuracao do plugin CustomMetadata.
 *
 * @class CustomMetadataSettingsForm
 */

namespace APP\plugins\generic\customMetadata\classes;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\plugins\Plugin;

class CustomMetadataSettingsForm extends Form
{
    public Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->setData('customFieldsDefinition', $this->plugin->getSetting($this->getContextId(), 'customFieldsDefinition'));
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['customFieldsDefinition']);
        parent::readInputData();
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting(
            $this->getContextId(),
            'customFieldsDefinition',
            (string) $this->getData('customFieldsDefinition'),
            'string'
        );
        return parent::execute(...$functionArgs);
    }

    protected function getContextId(): int
    {
        $context = Application::get()->getRequest()->getContext();
        return $context ? $context->getId() : Application::CONTEXT_SITE;
    }
}
