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
        $form['#attached']['library'][] = 'entity_progress/form';
        $recursive = TRUE;
        switch ($element['#entity_progress_field_type']) {
          case 'fieldception':
            $recursive = FALSE;
            static::fieldAlterFieldception($element);
            break;
        }
        static::fieldAlter($element, $recursive);
      }
    }
    return $form;
  }

  /**
   * Process each field.
   */
  public static function fieldAlter(&$element, $recursive = TRUE) {
    if (isset($element['#type'])) {
      switch ($element['#type']) {
        case 'radio':
        case 'checkbox':
          return;
      }
    }
    $element['#required'] = TRUE;
    $element['#attributes']['class'][] = 'entity-progress-required';
    if ($recursive) {
      foreach (Element::children($element) as $id) {
        static::fieldAlter($element[$id]);
      }
    }
  }

  /**
   * Process each field.
   */
  public static function fieldAlterFieldception(&$element) {
    // ksm($element);
    $settings = $element['#entity_progress_settings'];
    if (!empty($settings['all'])) {
      foreach (Element::children($element['widget']) as $id) {
        $element['widget'][$id]['#required'] = TRUE;
      }
    }
    foreach (Element::children($element['widget']) as $id) {
      if (!empty($settings['all'])) {
        $element['widget'][$id]['#required'] = TRUE;
      }
      foreach (Element::children($element['widget'][$id]) as $subfield_id) {
        if (!empty($element['widget'][$id][$subfield_id]['#fieldception_required'])) {
          static::fieldAlter($element['widget'][$id][$subfield_id]);
        }
      }
    }
    // foreach (Element::children($element) as $id) {
    //   static::fieldAlter($element[$id]);
    // }
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

}
