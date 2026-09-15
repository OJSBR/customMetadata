<?php

/**
 * @file plugins/generic/customMetadata/tests/CustomMetadataTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomMetadataTest
 *
 * @brief Field definitions, the publication schema and the site level.
 */

namespace APP\plugins\generic\customMetadata\tests;

use APP\plugins\generic\customMetadata\classes\CustomMetadataSettingsForm;
use APP\plugins\generic\customMetadata\CustomMetadataPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;

#[CoversClass(CustomMetadataPlugin::class)]
#[CoversClass(CustomMetadataSettingsForm::class)]
class CustomMetadataTest extends PKPTestCase
{
    private function plugin(string $definition = ''): CustomMetadataPlugin
    {
        return new class ($definition) extends CustomMetadataPlugin {
            public function __construct(private string $definition)
            {
            }

            public function getCustomFields(): array
            {
                return $this->parseDefinition($this->definition);
            }

            public function addedKeys(): array
            {
                return $this->schemaKeys;
            }
        };
    }

    public function testDefinitionLinesBecomeFields(): void
    {
        $fields = $this->plugin()->parseDefinition("# comment\r\n\nprint-Isbn! | Print ISBN\ncollection | Collection | TEXTAREA | Yes\nnotes | | text | 0\nprintIsbn | Again | textarea\n | no key\n");
        $this->assertSame([
            ['key' => 'printIsbn', 'label' => 'Print ISBN', 'type' => 'text', 'multilingual' => false],
            ['key' => 'collection', 'label' => 'Collection', 'type' => 'textarea', 'multilingual' => true],
            ['key' => 'notes', 'label' => 'notes', 'type' => 'text', 'multilingual' => false],
        ], $fields);
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
