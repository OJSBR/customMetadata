<?php

/**
 * @file plugins/generic/customMetadata/tests/CustomMetadataTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CustomMetadataTest
 *
 * @brief Field definitions, the publication schema and the settings request.
 */

namespace APP\plugins\generic\customMetadata\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\customMetadata\CustomMetadataPlugin;

class CustomMetadataTest extends TestCase
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

    public function testSavingSettingsNeedsAPostWithTheCsrfToken(): void
    {
        // The refusal is translated, and translation asks the request for its router.
        $appRequest = Application::get()->getRequest();
        if (!$appRequest->getRouter()) {
            $router = new PageRouter();
            $router->setApplication(Application::get());
            $appRequest->setRouter($router);
        }
        foreach ([[false, true], [true, false]] as [$post, $token]) {
            $request = new class ($post, $token) {
                public function __construct(private bool $post, private bool $token)
                {
                }

                public function getUserVar($name)
                {
                    return ['verb' => 'settings', 'save' => '1'][$name] ?? null;
                }

                public function isPost()
                {
                    return $this->post;
                }

                public function checkCSRF()
                {
                    return $this->token;
                }
            };
            $message = $this->plugin()->manage([], $request);
            $this->assertFalse($message->getStatus(), 'Settings were saved without a POST carrying the token.');
        }
    }
}
