<?php

namespace Drupal\Tests\juicebox\Functional;

use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;
use Drupal\image\Entity\ImageStyle;

/**
 * Tests global configuration logic for Juicebox galleries.
 *
 * @group juicebox
 */
class JuiceboxConfGlobalTest extends JuiceboxCaseTestBase {

  // @todo Reactivate config_translation when issue #2573975 is resolved.
  /**
   * Public static $modules = array('node', 'field_ui', 'image', 'juicebox');.
   *
   * @var array
   */
  protected static $modules = ['node', 'field_ui', 'image', 'juicebox'];

  /**
   * Define setup tasks.
   */
  public function setUp(): void {
    parent::setUp();
    // Create and login user.
    // @todo Reactivate translation perms when issue #2573975 is resolved.
    // $this->webUser = $this->drupalCreateUser(array('access content', 'access
    // administration pages', 'administer site configuration', 'administer
    // content types', 'administer nodes', 'administer node fields', 'administer
    // node display', 'bypass node access', 'administer languages', 'translate
    // interface'));
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer nodes',
      'administer node fields',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($this->webUser);
    // Prep a node with an image/file field and create a test entity.
    $this->initNode();
    // Activte the field formatter for our new node instance.
    $this->activateJuiceboxFieldFormatter();
    // Create a test node.
    $this->createNodeWithFile();
    // Start all cases as an anon user.
    $this->drupalLogout();
  }

  /**
   * Test basic global configuraton options.
   */
  public function testGlobalConf() {
    $node = $this->node;
    // Do a control request of the XML as an anon user that will also prime any
    // caches.
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    $this->assertSession()->statusCodeEquals(200);
    // Enable optional global settings.
    $this->drupalLogin($this->webUser);
    $edit = [
      'enable_cors' => TRUE,
    ];
    $this->drupalGet('admin/config/media/juicebox');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The Juicebox configuration options have been saved');
    // Now check the resulting XML again as an anon user.
    $this->drupalLogout();
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    // Check the the XML now returns an 'Access-Control-Allow-Origin' header
    // for CORS support.
    $this->assertEquals($this->getSession()->getResponseHeader('Access-Control-Allow-Origin'), '*', 'Expected CORS header found.');
  }

  /**
   * Test global Juicebox interface translation settings.
   *
   * @todo Reactivate this test when issue #2573975 is resolved.
   */
  public function voidtestGlobalTrans() {
    $node = $this->node;
    // Do a control request of the XML as an anon user that will also prime any
    // caches.
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    $this->assertSession()->statusCodeEquals(200);
    // We want to be able to set translations.
    $this->drupalLogin($this->webUser);
    $edit = [
      'locale_translate_english' => TRUE,
    ];
    $this->drupalGet('admin/config/regional/language/edit/en');
    $this->submitForm($edit, 'Save language');
    // Enable translation-related global settings.
    $edit = [
      'translate_interface' => TRUE,
      'base_languagelist' => 'Show Thumbnails|Hide Thumbnails|Expand Gallery|Close Gallery|Open Image in New Window',
    ];
    $this->drupalGet('admin/config/media/juicebox');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The Juicebox configuration options have been saved');
    // We need to set a translation for our languagelist string. There is
    // probably a good way to do this directly in code, but for now it's fairly
    // easy to just brute-force it via the UI. First we need to visit the
    // gallery to allow Drupal to detect our Juicebox languagelist translatable
    // string.
    $this->drupalGet('node/' . $node->id());
    // Then we set the translation by searching for the base string and then
    // inputting an english translation for it.
    $edit = [
      'string' => 'Show Thumbnails|Hide Thumbnails|Expand Gallery|Close Gallery|Open Image in New Window',
    ];
    $this->drupalGet('admin/config/media/juicebox');
    $this->submitForm($edit, 'Filter');
    $matches = [];
    $this->assertTrue(preg_match('/name="strings\[([0-9]+)\]\[translations\]\[0\]"/', $this->getRawContent(), $matches), 'Languagelist base string is available for translation.');
    $edit = [
      'strings[' . $matches[1] . '][translations][0]' => 'Translated|Lang|List',
    ];
    $this->submitForm($edit, 'Save translations');
    $this->assertSession()->pageTextContains($this->t('The strings have been saved'));
    // Now check the resulting XML again as an anon user.
    $this->drupalLogout();
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    // Check that the languagelist configuration option was both included and
    // translated in the XML.
    $this->assertSession()->responseContains('languagelist="Translated|Lang|List"');
  }

  /**
   * Test multi-size integration.
   */
  public function testGlobalMultisize() {
    $node = $this->node;
    // Do a control request of the XML as an anon user that will also prime any
    // caches.
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    $this->assertSession()->statusCodeEquals(200);
    // Customize one of our global multi-size settings from the default for a
    // true end-to-end test.
    $this->drupalLogin($this->webUser);
    $edit = [
      'juicebox_multisize_large' => 'large',
    ];
    $this->drupalGet('admin/config/media/juicebox');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The Juicebox configuration options have been saved');
    // Alter field formatter specific settings to use multi-size style.
    $this->drupalGet('admin/structure/types/manage/' . $this->instBundle . '/display');
    $this->submitForm([], $this->instFieldName . '_settings_edit', 'entity-view-display-edit-form');
    $edit = [
      'fields[' . $this->instFieldName . '][settings_edit_form][settings][image_style]' => 'juicebox_multisize',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');
    // Calculate the multi-size styles that should be found in the XML.
    $uri = File::load($node->{$this->instFieldName}[0]->target_id)->getFileUri();

    $formatted_image_small = ImageStyle::load('juicebox_small')->buildUrl($uri);
    $formatted_image_medium = ImageStyle::load('juicebox_medium')->buildUrl($uri);
    $formatted_image_large = ImageStyle::load('juicebox_large')->buildUrl($uri);
    // Now check the resulting XML again as an anon user.
    $this->drupalLogout();
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    // Check that the expected images are found in the XML.
    $this->assertSession()->responseContains('smallImageURL="' . Html::escape($formatted_image_small));
    $this->assertSession()->responseContains('imageURL="' . Html::escape($formatted_image_medium));
    $this->assertSession()->responseContains('largeImageURL="' . Html::escape($formatted_image_large));
  }

}
