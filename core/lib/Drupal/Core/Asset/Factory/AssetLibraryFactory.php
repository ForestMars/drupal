<?php
/**
 * @file
 * Contains Drupal\Core\Asset\AssetLibraryFactory.
 */

namespace Drupal\Core\Asset\Factory;

use Drupal\Core\Asset\Factory\AssetCollector;
use Drupal\Core\Asset\Collection\AssetLibrary;
use Drupal\Core\Asset\Metadata\DefaultAssetMetadataFactory;
use Drupal\Core\Asset\Metadata\MetadataFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class AssetLibraryFactory {

  /**
   * The module handler. Used to collect library data from hook_library_info().
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The asset collector to use in populating the libraries.
   *
   * @var \Drupal\Core\Asset\Factory\AssetCollector
   */
  protected $collector;

  /**
   * The metadata factory to provide to the collector
   *
   * @var \Drupal\Core\Asset\Metadata\MetadataFactoryInterface
   */
  protected $metadataFactory;

  /**
   * Creates a new AssetLibraryFactory.
   *
   * @param ModuleHandlerInterface $moduleHandler
   *   The module handler. The factory uses this to collect hook_library_info()
   *   declaration data.
   * @param AssetCollectorInterface $collector
   *   (optional) The collector to use in populating the asset library with
   *   asset objects. If not provided, core's default AssetCollector will be
   *   used.
   * @param MetadataFactoryInterface $metadataFactory
   *   (optional) A metadata factory to provide to the collector. Note that this
   *   will NOT be used if a collector is given.
   *
   * @throws \RuntimeException
   *   Thrown if a locked collector is given.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, AssetCollectorInterface $collector = NULL, MetadataFactoryInterface $metadataFactory = NULL) {
    $this->moduleHandler = $moduleHandler;
    $this->metadataFactory = $metadataFactory ?: new DefaultAssetMetadataFactory();
    $this->collector = $collector ?: new AssetCollector(NULL, $this->metadataFactory);

    if ($this->collector->isLocked()) {
      throw new \RuntimeException('The collector provided to an AssetLibraryFactory was locked; it must be unlocked so the factory can fully control it.');
    }
  }

  /**
   * Returns an AssetLibrary based on data declared in hook_library_info().
   *
   * @param $key
   *
   * @return AssetLibrary|bool
   *   An AssetLibrary instance, or FALSE if the key did not resolve to library
   *   data.
   */
  public function getLibrary($key) {
    list($module, $name) = preg_split('/\//', $key);

    if (!$this->moduleHandler->implementsHook($module, 'library_info')) {
      // Module doesn't implement hook_library_info(), a library can't exist.
      return FALSE;
    }

    $declarations = call_user_func($module . '_library_info') ?: array();

    $this->moduleHandler->alter('library_info', $declarations, $module);

    if (!isset($declarations[$name])) {
      // No library by the given name.
      return FALSE;
    }

    // Normalize the data - hook_library_info() allows sloppiness
    $info = $declarations[$name] + array('dependencies' => array(), 'js' => array(), 'css' => array());
    $library = new AssetLibrary();

    if (isset($info['title'])) {
      $library->setTitle($info['title']);
    }
    if (isset($info['version'])) {
      $library->setVersion($info['version']);
    }
    if (isset($info['website'])) {
      $library->setWebsite($info['website']);
    }

    // Record dependencies on the library, if any.
    foreach ($info['dependencies'] as $dep) {
      // TODO remove this, this is the wrong level at which to declare.
      $library->addDependency($dep[0] . '/' . $dep[1]);
    }

    // Populate the library with asset objects.
    $this->collector->setCollection($library);
    foreach (array('js', 'css') as $type) {
      foreach ($info[$type] as $data => $options) {
        if (is_scalar($options)) {
          $data = $options;
          $options = array();
        }

        $source_type = isset($options['type']) ? $options['type'] : 'file';
        unset($options['type']);

        if ($type == 'js' && $source_type == 'setting') {
          // TODO temporarily continue/skip if it's a js setting, can't handle those yet
          continue;
        }

        $asset = $this->collector->create($type, $source_type, $data, $options);
        foreach ($info['dependencies'] as $dep) {
          $asset->addDependency($dep[0] . '/' . $dep[1]);
        }
      }
    }

    // Ensure that auto-aftering of CSS doesn't bleed across libraries.
    $this->collector->clearLastCss();

    $library->freeze();
    return $library;
  }
}

