<?php

/**
 * @file
 * Contains \Drupal\Core\Asset\AsseticAdapterAsset.
 */

namespace Drupal\Core\Asset;
use Assetic\Asset\AssetInterface;
use Drupal\Core\Asset\Exception\UnsupportedAsseticBehaviorException;

/**
 * A class that reduces boilerplate code by centrally disabling the Assetic
 * properties and methods Drupal does not support.
 */
abstract class AsseticAdapterAsset implements AssetInterface {
  /**
   * @throws \InvalidArgumentException
   */
  public function getVars() {
    throw new UnsupportedAsseticBehaviorException("Drupal does not use or support Assetic's 'vars' concept.");
  }

  /**
   * @throws \InvalidArgumentException
   */
  public function setValues(array $values) {
    throw new UnsupportedAsseticBehaviorException("Drupal does not use or support Assetic's 'values' concept.");
  }

  /**
   * @throws \InvalidArgumentException
   */
  public function getValues() {
    throw new UnsupportedAsseticBehaviorException("Drupal does not use or support Assetic's 'values' concept.");
  }

}