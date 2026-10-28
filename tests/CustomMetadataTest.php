<?php

/**
 * @file plugins/generic/customMetadata/tests/CustomMetadataTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomMetadataTest
 *
 * @brief Field definitions, the publication schema, the fields kept for the
 *        editors and the site level.
 */

namespace APP\plugins\generic\customMetadata\tests;

use APP\core\Application;
use APP\plugins\generic\customMetadata\classes\CustomMetadataSettingsForm;
use APP\plugins\generic\customMetadata\CustomMetadataPlugin;
use APP\publication\Publication;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\submission\ForTheEditors;
use PKP\tests\PKPTestCase;

#[CoversClass(CustomMetadataPlugin::class)]
#[CoversClass(CustomMetadataSettingsForm::class)]
class CustomMetadataTest extends PKPTestCase
{
    private function plugin(string $definition = '', bool $isEditor = true): CustomMetadataPlugin
    {
        return new class ($definition, $isEditor) extends CustomMetadataPlugin {
            public function __construct(private string $definition, private bool $editor)
            {
            }

            public function getCustomFields(): array
            {
                return $this->parseDefinition($this->definition);
            }

            public function getEnabled($contextId = null)
            {
                return true;
            }

            public function isEditor($user, int $submissionId, int $contextId): bool
            {
                return $this->editor;
            }

            public function addedKeys(): array
            {
                return $this->schemaKeys;
            }
        };
    }

    /** A publication with the given values; a multilingual value is an array by locale. */
    private function publication(array $values = []): Publication
    {
        $publication = new Publication();
        $publication->setData('submissionId', 1);
        foreach ($values as $key => $value) {
            $publication->setData($key, $value);
        }
        return $publication;
    }

    /** The metadata form (or a subclass) as the core hands it to Form::config::before. */
    private function form(Publication $publication, string $class = PKPMetadataForm::class): PKPMetadataForm
    {
        $form = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $context = Application::getContextDAO()->newDataObject();
        $context->setId(1);
        $form->context = $context;
        $form->publication = $publication;
        $form->locales = [['key' => 'en', 'label' => 'English'], ['key' => 'fr', 'label' => 'Français']];
        return $form;
    }

    /** @return array<string,object> The fields the plugin added, by name. */
    private function addedFields(CustomMetadataPlugin $plugin, PKPMetadataForm $form): array
    {
        $plugin->addToSchema('Schema::get::publication', [(object) ['properties' => (object) []]]);
        $plugin->addToForm('Form::config::before', $form);
        $fields = [];
        foreach ($form->fields as $field) {
            $fields[$field->name] = $field;
        }
        return $fields;
    }

    public function testDefinitionLinesBecomeFields(): void
    {
        $fields = $this->plugin()->parseDefinition("# comment\r\n\nprint-Isbn! | Print ISBN\ncollection | Collection | TEXTAREA | Yes\nnotes | | text | 0\nprintIsbn | Again | textarea\n | no key\n");
        $this->assertSame([
            ['key' => 'printIsbn', 'label' => 'Print ISBN', 'type' => 'text', 'multilingual' => false, 'editorsOnly' => false],
            ['key' => 'collection', 'label' => 'Collection', 'type' => 'textarea', 'multilingual' => true, 'editorsOnly' => false],
            ['key' => 'notes', 'label' => 'notes', 'type' => 'text', 'multilingual' => false, 'editorsOnly' => false],
        ], $fields);
    }

    public function testTheFifthColumnKeepsAFieldForTheEditors(): void
    {
        $fields = $this->plugin()->parseDefinition("a | A | text | | editors\nb | B | text | 0 | Editores\nc | C | textarea | 1 | EDITOR\nd | D | text | 0 | authors\ne | E | text | 0 |\nf | F");
        $this->assertSame(
            ['a' => true, 'b' => true, 'c' => true, 'd' => false, 'e' => false, 'f' => false],
            array_column($fields, 'editorsOnly', 'key')
        );
        $this->assertTrue($fields[2]['multilingual']);
    }

    public function testTheSchemaGetsNewKeysOnlyAndNeverLosesANativeProperty(): void
    {
        $plugin = $this->plugin("title | Title | text\nprintIsbn | Print ISBN\ncollection | Collection | text | 1");
        $title = (object) ['type' => 'string', 'multilingual' => true];
        $schema = (object) ['properties' => (object) ['title' => $title]];
        $plugin->addToSchema('Schema::get::publication', [$schema]);

        $this->assertSame($title, $schema->properties->title);
        $this->assertSame(['printIsbn', 'collection'], $plugin->addedKeys());
        $this->assertSame(['nullable'], $schema->properties->printIsbn->validation);
        $this->assertFalse(isset($schema->properties->printIsbn->multilingual));
        $this->assertTrue($schema->properties->collection->multilingual);
    }

    public function testAnEditorEditsEveryField(): void
    {
        $plugin = $this->plugin("isbn | ISBN\nnote | Note | textarea | | editors", true);
        $fields = $this->addedFields($plugin, $this->form($this->publication(['note' => 'Kept'])));

        $this->assertInstanceOf(FieldText::class, $fields['isbn']);
        $this->assertInstanceOf(FieldTextarea::class, $fields['note']);
        $this->assertSame('Kept', $fields['note']->value);
    }

    public function testAnAuthorSeesAFieldOfTheEditorsReadOnly(): void
    {
        $plugin = $this->plugin("isbn | ISBN\nnote | Note | textarea | | editors\nseries | Series | text | 1 | editors\nempty | Empty | text | | editors", false);
        $publication = $this->publication([
            'note' => "<b>First</b>\nSecond",
            'series' => ['en' => 'Series & Co', 'fr' => ''],
        ]);
        $fields = $this->addedFields($plugin, $this->form($publication));

        // The authors' own field stays editable.
        $this->assertInstanceOf(FieldText::class, $fields['isbn']);
        foreach (['note', 'series', 'empty'] as $name) {
            $this->assertInstanceOf(FieldHTML::class, $fields[$name], "{$name} must be read-only.");
            $this->assertTrue($fields[$name]->isInert, 'A read-only field is never sent back.');
            $this->assertStringContainsString(__('plugins.generic.customMetadata.editorsOnly'), $fields[$name]->description);
        }
        $this->assertSame('Note', $fields['note']->label);
        // The value is shown as text, never as markup.
        $this->assertStringContainsString('&lt;b&gt;First&lt;/b&gt;<br>' . "\n" . 'Second', $fields['note']->description);
        $this->assertStringNotContainsString('<b>', $fields['note']->description);
        // Every language that has a value, by the name of the language.
        $this->assertStringContainsString('<strong>English:</strong> Series &amp; Co', $fields['series']->description);
        $this->assertStringNotContainsString('Français', $fields['series']->description);
        $this->assertStringContainsString('<p>—</p>', $fields['empty']->description);
    }

    public function testTheSubmissionWizardLeavesOutTheFieldsOfTheEditorsForAnAuthor(): void
    {
        $definition = "isbn | ISBN\nnote | Note | textarea | | editors";

        $fields = $this->addedFields($this->plugin($definition, false), $this->form($this->publication(), ForTheEditors::class));
        $this->assertSame(['isbn'], array_keys($fields));

        $fields = $this->addedFields($this->plugin($definition, true), $this->form($this->publication(), ForTheEditors::class));
        $this->assertSame(['isbn', 'note'], array_keys($fields), 'An editor who submits fills them in.');
    }

    public function testOnlyAChangedValueOfAFieldOfTheEditorsCounts(): void
    {
        $plugin = $this->plugin("isbn | ISBN\nnote | Note | text | | editors\nseries | Series | text | 1 | editors");
        $fields = $plugin->getCustomFields();
        $publication = $this->publication(['note' => 'Kept', 'series' => ['en' => 'One']]);
        $changed = fn (array $props) => CustomMetadataPlugin::changedEditorsOnlyKeys($fields, $props, $publication);

        $this->assertSame([], $changed(['isbn' => 'free', 'title' => ['en' => 'x']]), 'The authors\' own fields are not checked.');
        $this->assertSame([], $changed(['note' => 'Kept', 'series' => ['en' => 'One', 'fr' => '']]), 'Sent back unchanged.');
        $this->assertSame([], $changed(['series' => ['fr' => null]]));
        $this->assertSame(['note'], $changed(['note' => 'Other']));
        $this->assertSame(['note'], $changed(['note' => '']), 'Emptying is a change.');
        $this->assertSame(['series'], $changed(['series' => ['fr' => 'Deux']]));
        $this->assertSame(['series'], $changed(['series' => 'not by language']));
        $this->assertSame([], CustomMetadataPlugin::changedEditorsOnlyKeys($fields, ['note' => ' '], $this->publication()), 'Blank is no value.');
    }

    public function testTheSiteLevelHasNoSettingsToOpen(): void
    {
        $request = new class () {
            public function getContext()
            {
                return null;
            }

            public function getUserVar($name)
            {
                return $name === 'verb' ? 'settings' : null;
            }

            public function getRouter()
            {
                throw new \RuntimeException('The site level must not build a settings URL.');
            }
        };
        $plugin = new class () extends CustomMetadataPlugin {
            public function getEnabled($contextId = null)
            {
                return true;
            }
        };

        $this->assertSame([], array_filter($plugin->getActions($request, []), fn ($action) => $action->getId() === 'settings'));
        $this->expectExceptionMessage('Unhandled management action!');
        $plugin->manage([], $request);
    }
}
