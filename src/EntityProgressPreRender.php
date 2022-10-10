<?php

namespace Drupal\entity_progress;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Entity progress pre renderer.
 */
class EntityProgressPreRender implements TrustedCallbackInterface {

  /**
   * Process form.
   */
  public static function preRender($form) {
    foreach (Element::children($form) as $id) {
      $element = &$form[$id];
      if (!empty($element['#entity_progress'])) {
        static::fieldAlter($element);
      }
    }
    return $form;
  }

  /**
   * Process each field.
   */
  public static function fieldAlter(&$element) {
    if (isset($element['#type'])) {
      switch ($element['#type']) {
        case 'radio':
        case 'checkbox':
          return;
      }
    }
    $element['#required'] = TRUE;
    foreach (Element::children($element) as $id) {
      static::fieldAlter($element[$id]);
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

}
