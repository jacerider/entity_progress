<?php

/**
 * @file
 * Contains entity_progress.theme.
 */

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;

/**
 * Implements hook_preprocess_entity_progress().
 */
function template_preprocess_entity_progress(&$variables) {
  $progress = $variables['progress'];

  $variables['#cache'] = $progress['#cache'];
  $variables['entity_type_label'] = $progress['entity_type_label'];
  $variables['total'] = $progress['total'];
  $variables['complete'] = $progress['complete'];
  $variables['incomplete'] = $progress['incomplete'];
  $variables['percent'] = $progress['percent'];
  $variables['incomplete_fields'] = [];
  $variables['extra'] = [];
  $variables['missing_title'] = $variables['missing_title'] ?: t('Missing Information');
  $variables['missing_subtitle'] = $variables['missing_subtitle'] ?: t('In order to complete this @entity_type the following fields need to be filled out.', [
    '@entity_type' => strtolower($variables['entity_type_label']),
  ]);
  $variables['complete_title'] = $variables['complete_title'] ?: t('Complete');
  $variables['complete_subtitle'] = $variables['complete_subtitle'] ?: t('All required information has been filled out.');

  if ($progress['incomplete']) {
    foreach ($progress['fields']['incomplete'] as $field) {
      $label = $field->getLabel();
      if ($field instanceof ThirdPartySettingsInterface && $field_label = $field->getThirdPartySetting('field_labels', 'form_label')) {
        $label = $field_label;
      }
      $variables['incomplete_fields'][$field->getName()] = $label;
    }
  }
  foreach ($progress['extra'] as $extra) {
    $label = $extra['label'];
    $var['extra'][] = $extra['label'];
  }
}

/**
 * Implements hook_preprocess_entity_progress_status().
 */
function template_preprocess_entity_progress_status(&$variables) {
  $progress = $variables['progress'];

  $variables['#cache'] = $progress['#cache'];
  $variables['total'] = $progress['total'];
  $variables['complete'] = $progress['complete'];
  $variables['incomplete'] = $progress['incomplete'];
  $variables['percent'] = $progress['percent'];
  $variables['percent_label'] = $variables['percent_label'] ?: t('Complete');
  $variables['trigger_label'] = $variables['trigger_label'] ?: t('Click for a list of missing data');
  $variables['subtitle'] = $variables['subtitle'] ?: t('@percentage Complete');

  if ($variables['show_results_link'] && !empty($variables['incomplete'])) {
    $exo_modal_generator = \Drupal::service('exo_modal.generator');
    $build = [
      '#theme' => 'entity_progress',
      '#progress' => $progress,
      '#cache' => $progress['#cache'],
      '#missing_title' => $variables['missing_title'] ?? NULL,
      '#missing_subtitle' => $variables['missing_subtitle'] ?? NULL,
      '#complete_title' => $variables['complete_title'] ?? NULL,
      '#complete_subtitle' => $variables['complete_subtitle'] ?? NULL,
    ];

    $variables['results_link'] = $exo_modal_generator
      ->generate('entity_progress_' . implode('_', [
        $progress['entity_type'],
        $progress['entity_id'],
      ]), [
        'trigger' => [
          'text' => str_replace('@percentage', $variables['percent'] . '%', $variables['trigger_label']),
          'icon' => 'regular-chart-line',
        ],
        'modal' => [
          'title' => $variables['title'],
          'subtitle' => str_replace('@percentage', $variables['percent'] . '%', $variables['subtitle']),
          'icon' => 'regular-chart-line',
          'width' => '100%',
          'top' => 0,
          'padding' => '20px',
          'transitionIn' => 'fadeInDown',
          'transitionOut' => 'fadeOutUp',
        ],
      ])
      ->setContent($build)
      ->addCacheTags($progress['#cache']['tags'])
      ->addCacheContexts($progress['#cache']['contexts'])
      ->toRenderable();
  }
}
