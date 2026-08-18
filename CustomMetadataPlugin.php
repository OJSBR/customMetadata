<?php

/**
 * @file CustomMetadataPlugin.php
 *
 * Plugin de metadados personalizados para OMP 3.4.
 *
 * Permite cadastrar campos de metadados simples (sem validacao) que aparecem
 * na aba "Metadados" da publicacao e ficam disponiveis no tema via
 * $publication->getData('chave') ou $publication->getLocalizedData('chave').
 *
 * @class CustomMetadataPlugin
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

class CustomMetadataPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return $success;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            // Injeta as propriedades no schema da publicacao para que sejam persistidas.
            Hook::add('Schema::get::publication', [$this, 'addToSchema']);
            // Adiciona os campos visuais ao formulario de metadados da publicacao.
            Hook::add('Form::config::before', [$this, 'addToForm']);
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.customMetadata.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.customMetadata.description');
    }

    /**
     * Le e normaliza a definicao dos campos personalizados configurada pelo usuario.
     *
     * Formato esperado (um campo por linha):
     *   chave | Rotulo exibido | tipo | multilingue
     *
     * - chave: somente letras, numeros e underscore (usada em getData()).
     * - tipo: "text" (padrao) ou "textarea".
     * - multilingue: 1/sim/true para multilingue; vazio ou 0 para monolingue.
     *
     * @return array<int,array{key:string,label:string,type:string,multilingual:bool}>
     */
    public function getCustomFields(): array
    {
        $raw = (string) $this->getSetting($this->resolveContextId(), 'customFieldsDefinition');
        $fields = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $key = preg_replace('/[^A-Za-z0-9_]/', '', $parts[0] ?? '');
            if ($key === '') {
                continue;
            }
            $type = (isset($parts[2]) && strtolower($parts[2]) === 'textarea') ? 'textarea' : 'text';
            $multilingual = isset($parts[3]) && in_array(strtolower($parts[3]), ['1', 'sim', 'true', 'yes', 'y', 's']);
            $fields[] = [
                'key' => $key,
                'label' => ($parts[1] ?? '') !== '' ? $parts[1] : $key,
                'type' => $type,
                'multilingual' => $multilingual,
            ];
        }
        return $fields;
    }

    /**
     * Adiciona cada campo personalizado como propriedade do schema da publicacao,
     * sem regras de validacao (apenas nullable), para que sejam salvos em
     * publication_settings e recuperaveis via getData().
     *
     * Disparado por Hook::call('Schema::get::publication', [&$schema]),
     * portanto o segundo argumento chega como array.
     */
    public function addToSchema(string $hookName, array $params): bool
    {
        $schema = $params[0];
        foreach ($this->getCustomFields() as $field) {
            // Nao sobrescreve propriedades nativas ja existentes no schema.
            if (isset($schema->properties->{$field['key']})) {
                continue;
            }
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
     * Adiciona os campos personalizados ao formulario de metadados da publicacao.
     *
     * Disparado por Hook::run('Form::config::before', [$form]),
     * portanto o segundo argumento chega como o proprio objeto do formulario.
     */
    public function addToForm(string $hookName, $form): bool
    {
        if (!$form instanceof PKPMetadataForm) {
            return Hook::CONTINUE;
        }

        $publication = $form->publication;
        foreach ($this->getCustomFields() as $field) {
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
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
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
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new CustomMetadataSettingsForm($this);
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Retorna o id do contexto (press) atual, com fallback para o contexto do site.
     */
    protected function resolveContextId(): int
    {
        $context = Application::get()->getRequest()->getContext();
        return $context ? $context->getId() : Application::CONTEXT_SITE;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\customMetadata\CustomMetadataPlugin', '\CustomMetadataPlugin');
}
