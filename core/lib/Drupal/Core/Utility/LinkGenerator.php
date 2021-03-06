<?php

/**
 * @file
 * Contains \Drupal\Core\Utility\LinkGenerator.
 */

namespace Drupal\Core\Utility;

use Drupal\Component\Utility\Json;
use Drupal\Component\Utility\String;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a class which generates a link with route names and parameters.
 */
class LinkGenerator implements LinkGeneratorInterface {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The module handler firing the route_link alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a LinkGenerator instance.
   *
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   */
  public function __construct(UrlGeneratorInterface $url_generator, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager, AliasManagerInterface $alias_manager) {
    $this->urlGenerator = $url_generator;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * Sets the $request property.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HttpRequest object representing the current request.
   */
  public function setRequest(Request $request) {
    // Pre-calculate and store values based on the request that may be used
    // repeatedly in generate().
    $raw_variables = $request->attributes->get('_raw_variables');
    // $raw_variables is a ParameterBag object or NULL.
    $parameters = $raw_variables ? $raw_variables->all() : array();
    $this->active = array(
      'route_name' => $request->attributes->get(RouteObjectInterface::ROUTE_NAME),
      'language' => $this->languageManager->getCurrentLanguage(Language::TYPE_URL)->id,
      'parameters' => $parameters + (array) $request->query->all(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getActive() {
    return $this->active;
  }

  /**
   * {@inheritdoc}
   *
   * For anonymous users, the "active" class will be calculated on the server,
   * because most sites serve each anonymous user the same cached page anyway.
   * For authenticated users, the "active" class will be calculated on the
   * client (through JavaScript), only data- attributes are added to links to
   * prevent breaking the render cache. The JavaScript is added in
   * system_page_build().
   *
   * @see system_page_build()
   */
  public function generate($text, $route_name, array $parameters = array(), array $options = array()) {
    // Start building a structured representation of our link to be altered later.
    $variables = array(
      // @todo Inject the service when drupal_render() is converted to one.
      'text' => is_array($text) ? drupal_render($text) : $text,
      'route_name' => $route_name,
      'parameters' => $parameters,
      'options' => $options,
    );

    // Merge in default options.
    $variables['options'] += array(
      'attributes' => array(),
      'query' => array(),
      'html' => FALSE,
      'language' => NULL,
      'set_active_class' => FALSE,
    );

    // Add a hreflang attribute if we know the language of this link's url and
    // hreflang has not already been set.
    if (!empty($variables['options']['language']) && !isset($variables['options']['attributes']['hreflang'])) {
      $variables['options']['attributes']['hreflang'] = $variables['options']['language']->id;
    }

    // Set the "active" class if the 'set_active_class' option is not empty.
    if (!empty($variables['options']['set_active_class'])) {
      // Add a "data-drupal-link-query" attribute to let the
      // drupal.active-link library know the query in a standardized manner.
      if (!empty($variables['options']['query'])) {
        $query = $variables['options']['query'];
        ksort($query);
        $variables['options']['attributes']['data-drupal-link-query'] = Json::encode($query);
      }

      // Add a "data-drupal-link-system-path" attribute to let the
      // drupal.active-link library know the path in a standardized manner.
      if (!isset($variables['options']['attributes']['data-drupal-link-system-path'])) {
        $path = $this->urlGenerator->getPathFromRoute($route_name, $parameters);
        $variables['options']['attributes']['data-drupal-link-system-path'] = $this->aliasManager->getSystemPath($path);
      }
    }

    // Remove all HTML and PHP tags from a tooltip, calling expensive strip_tags()
    // only when a quick strpos() gives suspicion tags are present.
    if (isset($variables['options']['attributes']['title']) && strpos($variables['options']['attributes']['title'], '<') !== FALSE) {
      $variables['options']['attributes']['title'] = strip_tags($variables['options']['attributes']['title']);
    }

    // Allow other modules to modify the structure of the link.
    $this->moduleHandler->alter('link', $variables);

    // Move attributes out of options. generateFromRoute(() doesn't need them.
    $attributes = new Attribute($variables['options']['attributes']);
    unset($variables['options']['attributes']);

    // The result of the url generator is a plain-text URL. Because we are using
    // it here in an HTML argument context, we need to encode it properly.
    $url = String::checkPlain($this->urlGenerator->generateFromRoute($variables['route_name'], $variables['parameters'], $variables['options']));

    // Sanitize the link text if necessary.
    $text = $variables['options']['html'] ? $variables['text'] : String::checkPlain($variables['text']);

    return '<a href="' . $url . '"' . $attributes . '>' . $text . '</a>';
  }

}
