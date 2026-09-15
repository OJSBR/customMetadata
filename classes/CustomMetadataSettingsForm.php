<?php

/**
 * @file plugins/generic/customMetadata/classes/CustomMetadataSettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomMetadataSettingsForm
 *
 * @brief Settings form of the Custom Metadata plugin: the field definitions of a press.
 */

namespace APP\plugins\generic\customMetadata\classes;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\plugins\Plugin;

class CustomMetadataSettingsForm extends Form
{
    public function __construct(private Plugin $plugin, private int $contextId)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load the field definitions of the press.
     */
    public function initData()
    {
        $this->setData('customFieldsDefinition', $this->plugin->getSetting($this->contextId, 'customFieldsDefinition'));
        parent::initData();
    }

    /**
     * Read the submitted definitions.
     */
    public function readInputData()
    {
        $this->readUserVars(['customFieldsDefinition']);
        parent::readInputData();
    }

    /**
     * Render the form.
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the field definitions of the press.
     */
    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting($this->contextId, 'customFieldsDefinition', (string) $this->getData('customFieldsDefinition'), 'string');
        return parent::execute(...$functionArgs);
    }
}
