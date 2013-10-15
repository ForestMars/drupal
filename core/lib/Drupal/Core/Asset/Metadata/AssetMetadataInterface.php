<?php

/**
 * @file
 * Contains \Drupal\Core\Asset\Metadata\AssetMetadataInterface.
 */

namespace Drupal\Core\Asset\Metadata;

/**
 * Interface describing asset metadata bags.
 */
interface AssetMetadataInterface extends \Countable, \IteratorAggregate {

  /**
   * Indicates the type of asset for which this metadata is intended.
   *
   * @return string
   *   A string indicating type - 'js' or 'css' are the expected values.
   */
  public function getType();

  /**
   * Returns all values in the metadata bag as an associative array.
   *
   * @return array
   */
  public function all();

  /**
   * Returns the keys of all values in the bag as an indexed array.
   *
   * @return array
   */
  public function keys();

  /**
   * Indicates whether or not a value is present in the bag.
   *
   * @param $key
   *
   * @return bool
   */
  public function has($key);

  /**
   * Sets the provided key to the provided value.
   *
   * @param $key
   * @param $value
   *
   * @return void
   */
  public function set($key, $value);

  /**
   * Reverts the associated with the passed key back to its default.
   *
   * If no default is set, the value for that key simply disappears.
   *
   * @param $key
   *   The key identifying the value to revert.
   *
   * @return void
   */
  public function revert($key);

  /**
   * Indicates whether the provided key is a default value.
   *
   * @param $key
   *
   * @return bool
   *   TRUE if the value is a default, FALSE if it is explicit or nonexistent.
   */
  public function isDefault($key);

  /**
   * Adds a set of key/value pairs into the bag. Replaces existing keys.
   *
   * @param array $values
   *
   * @return void
   */
  public function add(array $values = array());

  /**
   * Wholly replaces all explicit values in the bag with the provided values.
   *
   * @param array $values
   *
   * @return void
   */
  public function replace(array $values = array());

  /**
   * Gets the value for the provided key from the bag.
   *
   * @param $key
   *
   * @return mixed
   */
  public function get($key);
}