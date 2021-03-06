<?php

/**
 * @file
 * Definition of Drupal\language\Tests\LanguageNegotiationInfoTest.
 */

namespace Drupal\language\Tests;

use Drupal\Core\Language\Language;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUI;
use Drupal\simpletest\WebTestBase;

/**
 * Functional test for language types/negotiation info.
 */
class LanguageNegotiationInfoTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language');

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Language negotiation info',
      'description' => 'Tests alterations to language types/negotiation info.',
      'group' => 'Language',
    );
  }

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
    $this->languageManager = $this->container->get('language_manager');
    $admin_user = $this->drupalCreateUser(array('administer languages', 'access administration pages', 'view the administration theme'));
    $this->drupalLogin($admin_user);
    $this->drupalPostForm('admin/config/regional/language/add', array('predefined_langcode' => 'it'), t('Add language'));
  }

  /**
   * Tests alterations to language types/negotiation info.
   */
  function testInfoAlterations() {
    // Enable language type/negotiation info alterations.
    \Drupal::state()->set('language_test.language_types', TRUE);
    \Drupal::state()->set('language_test.language_negotiation_info', TRUE);
    $this->languageNegotiationUpdate();

    // Check that fixed language types are properly configured without the need
    // of saving the language negotiation settings.
    $this->checkFixedLanguageTypes();

    // Make the content language type configurable by updating the language
    // negotiation settings with the proper flag enabled.
    \Drupal::state()->set('language_test.content_language_type', TRUE);
    $this->languageNegotiationUpdate();
    $type = Language::TYPE_CONTENT;
    $language_types = $this->languageManager->getLanguageTypes();
    $this->assertTrue(in_array($type, $language_types), 'Content language type is configurable.');

    // Enable some core and custom language negotiation methods. The test
    // language type is supposed to be configurable.
    $test_type = 'test_language_type';
    $interface_method_id = LanguageNegotiationUI::METHOD_ID;
    $test_method_id = 'test_language_negotiation_method';
    $form_field = $type . '[enabled]['. $interface_method_id .']';
    $edit = array(
      $form_field => TRUE,
      $type . '[enabled][' . $test_method_id . ']' => TRUE,
      $test_type . '[enabled][' . $test_method_id . ']' => TRUE,
      $test_type . '[configurable]' => TRUE,
    );
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Remove the interface language negotiation method by updating the language
    // negotiation settings with the proper flag enabled.
    \Drupal::state()->set('language_test.language_negotiation_info_alter', TRUE);
    $this->languageNegotiationUpdate();
    $negotiation = \Drupal::config('language.types')->get('negotiation.' . $type . '.enabled') ?: array();
    $this->assertFalse(isset($negotiation[$interface_method_id]), 'Interface language negotiation method removed from the stored settings.');
    $this->assertNoFieldByXPath("//input[@name=\"$form_field\"]", NULL, 'Interface language negotiation method unavailable.');

    // Check that type-specific language negotiation methods can be assigned
    // only to the corresponding language types.
    foreach ($this->languageManager->getLanguageTypes() as $type) {
      $form_field = $type . '[enabled][test_language_negotiation_method_ts]';
      if ($type == $test_type) {
        $this->assertFieldByXPath("//input[@name=\"$form_field\"]", NULL, format_string('Type-specific test language negotiation method available for %type.', array('%type' => $type)));
      }
      else {
        $this->assertNoFieldByXPath("//input[@name=\"$form_field\"]", NULL, format_string('Type-specific test language negotiation method unavailable for %type.', array('%type' => $type)));
      }
    }

    // Check language negotiation results.
    $this->drupalGet('');
    $last = \Drupal::state()->get('language_test.language_negotiation_last');
    foreach ($this->languageManager->getDefinedLanguageTypes() as $type) {
      $langcode = $last[$type];
      $value = $type == Language::TYPE_CONTENT || strpos($type, 'test') !== FALSE ? 'it' : 'en';
      $this->assertEqual($langcode, $value, format_string('The negotiated language for %type is %language', array('%type' => $type, '%language' => $value)));
    }

    // Uninstall language_test and check that everything is set back to the
    // original status.
    $this->languageNegotiationUpdate('uninstall');

    // Check that only the core language types are available.
    foreach ($this->languageManager->getDefinedLanguageTypes() as $type) {
      $this->assertTrue(strpos($type, 'test') === FALSE, format_string('The %type language is still available', array('%type' => $type)));
    }

    // Check that fixed language types are properly configured, even those
    // previously set to configurable.
    $this->checkFixedLanguageTypes();

    // Check that unavailable language negotiation methods are not present in
    // the negotiation settings.
    $negotiation = \Drupal::config('language.types')->get('negotiation.' . $type . '.enabled') ?: array();
    $this->assertFalse(isset($negotiation[$test_method_id]), 'The disabled test language negotiation method is not part of the content language negotiation settings.');

    // Check that configuration page presents the correct options and settings.
    $this->assertNoRaw(t('Test language detection'), 'No test language type configuration available.');
    $this->assertNoRaw(t('This is a test language negotiation method'), 'No test language negotiation method available.');
  }

  /**
   * Update language types/negotiation information.
   *
   * Manually invoke language_modules_installed()/language_modules_uninstalled()
   * since they would not be invoked after installing/uninstalling language_test
   * the first time.
   */
  protected function languageNegotiationUpdate($op = 'install') {
    static $last_op = NULL;
    $modules = array('language_test');

    // Install/uninstall language_test only if we did not already before.
    if ($last_op != $op) {
      call_user_func(array($this->container->get('module_handler'), $op), $modules);
      $last_op = $op;
    }
    else {
      $function = "language_modules_{$op}ed";
      if (function_exists($function)) {
        $function($modules);
      }
    }

    $this->languageManager->reset();
    $this->drupalGet('admin/config/regional/language/detection');
  }

  /**
   * Check that language negotiation for fixed types matches the stored one.
   */
  protected function checkFixedLanguageTypes() {
    $configurable = $this->languageManager->getLanguageTypes();
    foreach ($this->languageManager->getDefinedLanguageTypesInfo() as $type => $info) {
      if (!in_array($type, $configurable) && isset($info['fixed'])) {
        $negotiation = \Drupal::config('language.types')->get('negotiation.' . $type . '.enabled') ?: array();
        $equal = count($info['fixed']) == count($negotiation);
        while ($equal && list($id) = each($negotiation)) {
          list(, $info_id) = each($info['fixed']);
          $equal = $info_id == $id;
        }
        $this->assertTrue($equal, format_string('language negotiation for %type is properly set up', array('%type' => $type)));
      }
    }
  }
}
