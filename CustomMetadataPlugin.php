<?php

/**
 * @file plugins/generic/customMetadata/CustomMetadataPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomMetadataPlugin
 *
 * @brief Simple extra metadata fields (no validation) on the publication
 *  Metadata tab, available in the theme through $publication->getData('key')
 *  or $publication->getLocalizedData('key').
 */

namespace APP\plugins\generic\customMetadata;

use APP\core\Application;
use APP\plugins\generic\customMetadata\classes\CustomMetadataSettingsForm;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

class CustomMetadataPlugin extends GenericPlugin
{
    /** @var string[] Keys this plugin added to the publication schema in this request */
    protected $schemaKeys = [];

    /**
     * Register the plugin and its hooks.
     *
     * @param string $category
     * @param string $path
     * @param null|int $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || Application::isUnderMaintenance()) {
            return $success;
        }

        // The hooks are always registered and the form checks whether the plugin is
        // enabled (pkp/pkp-lib#11793): the publication schema must be extended on every
        // request (display, save, API), or saving a publication silently drops the
        // custom fields of a press where the plugin is off.
        Hook::add('Schema::get::publication', $this->addToSchema(...));
        Hook::add('Form::config::before', $this->addToForm(...));

        return $success;
    }

    /**
     * Name shown in the plugins list.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.customMetadata.displayName');
    }

    /**
     * Description shown in the plugins list.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.customMetadata.description');
    }

    /**
     * Read and normalize the field definitions configured for the press.
     *
     * One field per line:
     *   key | Label | type | multilingual
     *
     * - key: letters, numbers and underscore only (the name used in getData()).
     * - type: "text" (default) or "textarea".
     * - multilingual: 1/yes/true (or sim/s) for a multilingual field; empty or 0 otherwise.
     *
     * @return array<int,array{key:string,label:string,type:string,multilingual:bool}>
     */
    public function getCustomFields(): array
    {
        return $this->parseDefinition((string) $this->getSetting($this->resolveContextId(), 'customFieldsDefinition'));
    }

    /**
     * Parse a field definition text.
     *
     * @return array<int,array{key:string,label:string,type:string,multilingual:bool}>
     */
    public function parseDefinition(string $raw): array
    {
        $fields = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $key = preg_replace('/[^A-Za-z0-9_]/', '', $parts[0] ?? '');
            if ($key === '' || isset($fields[$key])) {
                continue;
            }
            $type = (isset($parts[2]) && strtolower($parts[2]) === 'textarea') ? 'textarea' : 'text';
            $multilingual = isset($parts[3]) && in_array(strtolower($parts[3]), ['1', 'sim', 'true', 'yes', 'y', 's']);
            $fields[$key] = [
                'key' => $key,
                'label' => ($parts[1] ?? '') !== '' ? $parts[1] : $key,
                'type' => $type,
                'multilingual' => $multilingual,
            ];
        }
        return array_values($fields);
    }

    /**
     * Add each custom field to the publication schema, with no validation rule
     * besides nullable, so it is saved in publication_settings and read back
     * with getData().
     *
     * Called by Hook::call('Schema::get::publication', [&$schema]).
     */
    public function addToSchema($hookName, $params): bool
    {
        $schema = $params[0];
        foreach ($this->getCustomFields() as $field) {
            // Never replace a property the schema already has.
            if (isset($schema->properties->{$field['key']})) {
                continue;
            }
            $this->schemaKeys[] = $field['key'];
            $prop = (object) [
                'type' => 'string',
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
            if ($field['multilingual']) {
                $prop->multilingual = true;
            }
            $schema->properties->{$field['key']} = $prop;
        }
        return Hook::CONTINUE;
    }

    /**
     * Add the custom fields to the publication metadata form.
     *
     * Called by Hook::run('Form::config::before', [$form]), so the second
     * argument is the form itself.
     */
    public function addToForm($hookName, $form): bool
    {
        if (!$form instanceof PKPMetadataForm) {
            return Hook::CONTINUE;
        }
        // OMP 3.5 (#11793): the hooks are always registered; the fields are only
        // shown when the plugin is enabled in the current press.
        if (!$this->getEnabled()) {
            return Hook::CONTINUE;
        }

        // A key that names a property of the core or of another plugin is not
        // shown: the field would replace that property's value on save.
        app()->get('schema')->get(PKPSchemaService::SCHEMA_PUBLICATION);
        $publication = $form->publication;
        foreach ($this->getCustomFields() as $field) {
            if (!in_array($field['key'], $this->schemaKeys, true)) {
                continue;
            }
            $options = [
                'label' => $field['label'],
                'isMultilingual' => $field['multilingual'],
                'value' => $publication->getData($field['key']),
            ];
            if ($field['multilingual']) {
                $options['locales'] = $form->locales;
            }
            if ($field['type'] === 'textarea') {
                $form->addField(new FieldTextarea($field['key'], $options));
            } else {
                $form->addField(new FieldText($field['key'], $options));
            }
        }
        return Hook::CONTINUE;
    }

    /**
     * Add the settings action to the plugin entry in the plugins list.
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$request->getContext() || !$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $settingsAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, [
                    'verb' => 'settings',
                    'plugin' => $this->getName(),
                    'category' => 'generic',
                ]),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        array_unshift($actions, $settingsAction);
        return $actions;
    }

    /**
     * Show and save the settings form.
     */
    public function manage($args, $request): JSONMessage
    {
        // The settings belong to a press; there is nothing to configure site-wide.
        $context = $request->getContext();
        if ($request->getUserVar('verb') !== 'settings' || !$context) {
            return parent::manage($args, $request);
        }

        $form = new CustomMetadataSettingsForm($this, (int) $context->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();

        return new JSONMessage(true);
    }

    /**
     * The id of the current press, or the site context without one.
     */
    protected function resolveContextId(): int
    {
        $context = Application::get()->getRequest()->getContext();
        return $context ? $context->getId() : Application::SITE_CONTEXT_ID;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\customMetadata\CustomMetadataPlugin', '\CustomMetadataPlugin');
}
