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
 *  Metadata tab of OJS and OMP, available in the theme through
 *  $publication->getData('key') or $publication->getLocalizedData('key').
 *  Each field is editable by everyone who can edit the publication, or by
 *  the editors only.
 */

namespace APP\plugins\generic\customMetadata;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\customMetadata\classes\CustomMetadataSettingsForm;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\submission\ForTheEditors;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\publication\PKPPublication;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class CustomMetadataPlugin extends GenericPlugin
{
    /** Values of the fifth column that keep a field for the editors. */
    public const EDITORS_ONLY = ['editor', 'editors', 'editores', 'editoras'];

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

        // The hooks are always registered and each callback checks whether the plugin is
        // enabled (pkp/pkp-lib#11793): the publication schema must be extended on every
        // request (display, save, API), or saving a publication silently drops the
        // custom fields of a context where the plugin is off.
        Hook::add('Schema::get::publication', $this->addToSchema(...));
        Hook::add('Form::config::before', $this->addToForm(...));
        Hook::add('Publication::validate', $this->validateEditorsOnly(...));

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
     * Read and normalize the field definitions configured for the context.
     *
     * One field per line:
     *   key | Label | type | multilingual | who edits
     *
     * - key: letters, numbers and underscore only (the name used in getData()).
     * - type: "text" (default) or "textarea".
     * - multilingual: 1/yes/true (or sim/s) for a multilingual field; empty or 0 otherwise.
     * - who edits: "editors" (or "editores") keeps the field for the editors; empty or
     *   anything else lets the authors edit it too.
     *
     * @return array<int,array{key:string,label:string,type:string,multilingual:bool,editorsOnly:bool}>
     */
    public function getCustomFields(): array
    {
        return $this->parseDefinition((string) $this->getSetting($this->resolveContextId(), 'customFieldsDefinition'));
    }

    /**
     * Parse a field definition text.
     *
     * @return array<int,array{key:string,label:string,type:string,multilingual:bool,editorsOnly:bool}>
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
                'editorsOnly' => isset($parts[4]) && in_array(mb_strtolower($parts[4]), self::EDITORS_ONLY, true),
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
     * Add the custom fields to the publication metadata form, and to the "For the
     * Editors" step of the submission wizard, which extends it.
     *
     * A field kept for the editors is shown to anybody else as read-only text on the
     * Metadata tab, and left out of the submission wizard, where it would be empty.
     *
     * Called by Hook::run('Form::config::before', [$form]), so the second
     * argument is the form itself.
     */
    public function addToForm($hookName, $form): bool
    {
        if (!$form instanceof PKPMetadataForm) {
            return Hook::CONTINUE;
        }
        $publication = $form->publication;
        $contextId = $form->context->getId();
        if (!$this->getEnabled($contextId)) {
            return Hook::CONTINUE;
        }

        // A key that names a property of the core or of another plugin is not
        // shown: the field would replace that property's value on save.
        app()->get('schema')->get(PKPSchemaService::SCHEMA_PUBLICATION);
        $isEditor = null;
        foreach ($this->getCustomFields() as $field) {
            if (!in_array($field['key'], $this->schemaKeys, true)) {
                continue;
            }
            if ($field['editorsOnly']) {
                $isEditor ??= $this->isEditor(Application::get()->getRequest()->getUser(), (int) $publication->getData('submissionId'), $contextId);
                if (!$isEditor) {
                    if (!$form instanceof ForTheEditors) {
                        $form->addField(new FieldHTML($field['key'], [
                            'label' => $field['label'],
                            'description' => $this->readOnlyValue($field, $publication, $form->locales),
                        ]));
                    }
                    continue;
                }
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
     * Refuse a change to a field kept for the editors when it comes from somebody
     * else. Hiding the field is not enough: the publication API accepts any key of the
     * schema from whoever may edit the publication.
     *
     * Called by Hook::call('Publication::validate', [&$errors, $publication, $props, $allowedLocales, $primaryLocale]).
     */
    public function validateEditorsOnly($hookName, $args): bool
    {
        $errors = &$args[0];
        $publication = $args[1];
        $props = $args[2];
        if (!$publication instanceof PKPPublication) {
            return Hook::CONTINUE;
        }
        $user = Application::get()->getRequest()->getUser();
        // Without a user it is the system at work (command line, jobs, imports).
        if (!$user) {
            return Hook::CONTINUE;
        }
        $submission = Repo::submission()->get((int) $publication->getData('submissionId'));
        if (!$submission || !$this->getEnabled($submission->getData('contextId'))) {
            return Hook::CONTINUE;
        }

        $changed = self::changedEditorsOnlyKeys($this->getCustomFields(), $props, $publication);
        if ($changed && !$this->isEditor($user, $submission->getId(), $submission->getData('contextId'))) {
            foreach ($changed as $key) {
                $errors[$key][] = __('plugins.generic.customMetadata.editorsOnly');
            }
        }
        return Hook::CONTINUE;
    }

    /**
     * The keys of the fields kept for the editors whose value the props change.
     *
     * A form that sends a field back unchanged changes nothing, and an empty string is
     * the same as no value.
     *
     * @param array<int,array{key:string,multilingual:bool,editorsOnly:bool}> $fields
     *
     * @return string[]
     */
    public static function changedEditorsOnlyKeys(array $fields, array $props, object $publication): array
    {
        $changed = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            if (!$field['editorsOnly'] || !array_key_exists($key, $props)) {
                continue;
            }
            $new = $props[$key];
            if ($field['multilingual'] && is_array($new)) {
                foreach ($new as $locale => $value) {
                    if (self::normalize($value) !== self::normalize($publication->getData($key, $locale))) {
                        $changed[] = $key;
                        break;
                    }
                }
            } elseif (self::normalize($new) !== self::normalize($publication->getData($key))) {
                $changed[] = $key;
            }
        }
        return $changed;
    }

    /**
     * A value as it is compared: null and blank are the same.
     */
    protected static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = array_filter(array_map(self::normalize(...), $value), fn ($v) => $v !== null);
            ksort($value);
            return $value ?: null;
        }
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        return (string) $value;
    }

    /**
     * Whether the user edits the fields kept for the editors of a submission: a site
     * administrator, a manager of the context, or somebody assigned to the submission
     * in a role other than author. The same line the core draws between the editors
     * and the authors for a published publication (Repository::canEditPublication()).
     */
    public function isEditor(?User $user, int $submissionId, int $contextId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID)
            || $user->hasRole(Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES, $contextId)) {
            return true;
        }
        return StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($user->getId())
            ->get()
            ->contains(fn (StageAssignment $assignment) => $assignment->userGroup && $assignment->userGroup->roleId != Role::ROLE_ID_AUTHOR);
    }

    /**
     * The value of a field as read-only HTML, with every language of the form for a
     * multilingual field.
     *
     * @param array{key:string,type:string,multilingual:bool} $field
     */
    protected function readOnlyValue(array $field, object $publication, array $locales): string
    {
        $format = fn ($value) => nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), false);
        $lines = [];
        if ($field['multilingual']) {
            foreach ($locales as $locale) {
                $value = $publication->getData($field['key'], $locale['key']);
                if (self::normalize($value) !== null) {
                    $lines[] = '<strong>' . htmlspecialchars($locale['label'], ENT_QUOTES, 'UTF-8') . ':</strong> ' . $format($value);
                }
            }
        } elseif (self::normalize($publication->getData($field['key'])) !== null) {
            $lines[] = $format($publication->getData($field['key']));
        }
        $value = $lines ? implode('<br>', $lines) : '—';
        return '<p>' . $value . '</p><p><em>' . htmlspecialchars(__('plugins.generic.customMetadata.editorsOnly'), ENT_QUOTES, 'UTF-8') . '</em></p>';
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
        // The settings belong to a journal or press; there is nothing to configure site-wide.
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
     * The id of the current context, or the site context without one.
     */
    protected function resolveContextId(): ?int
    {
        return Application::get()->getRequest()->getContext()?->getId() ?? Application::SITE_CONTEXT_ID;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\customMetadata\CustomMetadataPlugin', '\CustomMetadataPlugin');
}
