<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\style\DefaultStyle.
 */

namespace Drupal\views\Plugin\views\style;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Unformatted style plugin to render rows one after another with no
 * decorations.
 *
 * @ingroup views_style_plugins
 *
 * @Plugin(
 *   id = "default",
 *   title = @Translation("Unformatted list"),
 *   help = @Translation("Displays rows one after another."),
 *   theme = "views_view_unformatted",
 *   type = "normal",
 *   help_topic = "style-unformatted"
 * )
 */
class DefaultStyle extends StylePluginBase {

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Set default options
   */
  function options(&$options) {
    parent::options($options);
  }

  function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);
  }

}
