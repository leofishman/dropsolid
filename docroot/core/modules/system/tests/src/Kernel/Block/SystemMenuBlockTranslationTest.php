<?php

namespace Drupal\Tests\system\Kernel\Block;

use Drupal\Core\Language\Language;
use Drupal\system\Entity\Menu;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Tests \Drupal\system\Plugin\Block\SystemMenuBlock translation.
 *
 * @group Block
 *
 * @see \Drupal\system\Plugin\Block\SystemMenuBlock
 */
class SystemMenuBlockTranslationTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'content_translation',
    'language',
    'link',
    'menu_link_content',
    'system',
    'user',
  ];

  /**
   * The menu for testing.
   *
   * @var \Drupal\system\MenuInterface
   */
  protected $menu;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|\Drupal\content_translation\BundleTranslationSettingsInterface
   */
  protected $contentTranslationManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The default language.
   *
   * @var \Drupal\Core\Language\LanguageDefault
   */
  protected $languageDefault;

  /**
   * French language.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas & config.
    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');

    // Get services.
    $this->blockManager = $this->container->get('plugin.manager.block');
    $this->contentTranslationManager = $this->container->get('content_translation.manager');
    $this->languageManager = $this->container->get('language_manager');
    $this->languageDefault = $this->container->get('language.default');

    // Add custom menu.
    $this->menu = Menu::create([
      'id' => 'mock',
      'label' => $this->randomMachineName(16),
      'description' => 'Description text',
    ]);
    $this->menu->save();

    // Add French language and make menu links translatable.
    $this->language = ConfigurableLanguage::createFromLangcode('fr');
    $this->language->save();
    $this->contentTranslationManager->setEnabled('menu_link_content', 'menu_link_content', TRUE);
  }

  /**
   * Tests that menu blocks display links in proper language.
   */
  public function testMenuBlockTranslation() {
    // Create menu links in each language.
    $languages = [
      'en' => 'English',
      'fr' => 'French',
      Language::LANGCODE_NOT_SPECIFIED => 'Not specified',
      Language::LANGCODE_NOT_APPLICABLE => 'Not applicable',
    ];
    $links = [];
    foreach ($languages as $langcode => $langname) {
      $link = MenuLinkContent::create([
        'title' => "test $langname",
        'link' => ['uri' => 'https://www.drupal.org/'],
        'menu_name' => $this->menu->id(),
        'external' => TRUE,
        'bundle' => 'menu_link_content',
        'langcode' => $langcode,
      ]);
      $link->save();
      $links[$langcode] = $link;
    }

    // Place menu block in a region.
    /** @var \Drupal\Core\Block\BlockPluginInterface */
    $block = $this->blockManager->createInstance('system_menu_block:' . $this->menu->id(), [
      'region' => 'footer',
      'id' => 'menu_block_footer',
      'theme' => 'stark',
      'level' => 1,
      'depth' => 0,
    ]);

    // Viewing block from English interface should show links which language is
    // - English
    // - Not specified
    // - Not applicable
    // It should not show French links.
    $build = $block->build();
    $items = $build['#items'] ?? [];
    $this->assertArrayHasKey('menu_link_content:' . $links['en']->uuid(), $items);
    $this->assertArrayNotHasKey('menu_link_content:' . $links['fr']->uuid(), $items);
    $this->assertArrayHasKey('menu_link_content:' . $links[Language::LANGCODE_NOT_SPECIFIED]->uuid(), $items);
    $this->assertArrayHasKey('menu_link_content:' . $links[Language::LANGCODE_NOT_APPLICABLE]->uuid(), $items);

    // Viewing block from French interface should show links which language is
    // - French
    // - Not specified
    // - Not applicable
    // It should not show English links.
    $this->languageDefault->set($this->language);
    $this->languageManager->reset();
    $build = $block->build();
    $items = $build['#items'] ?? [];
    $this->assertArrayNotHasKey('menu_link_content:' . $links['en']->uuid(), $items);
    $this->assertArrayHasKey('menu_link_content:' . $links['fr']->uuid(), $items);
    $this->assertArrayHasKey('menu_link_content:' . $links[Language::LANGCODE_NOT_SPECIFIED]->uuid(), $items);
    $this->assertArrayHasKey('menu_link_content:' . $links[Language::LANGCODE_NOT_APPLICABLE]->uuid(), $items);
  }

}
