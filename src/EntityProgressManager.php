<?php

namespace Drupal\entity_progress;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Entity\BaseFieldOverride;

/**
 * The entity progress manager.
 *
 * @package Drupal\entity_progress
 */
class EntityProgressManager implements EntityProgressManagerInterface {
  use UseCacheBackendTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity entity being used for progress.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * An array containing the aggregated progress data keyed by entity id.
   *
   * @var array
   */
  protected $progress;

  /**
   * An array containing all field definitions enabled for progress.
   *
   * @var array
   */
  protected $definitions;

  /**
   * An array containing field completion keyed by entity id.
   *
   * @var array
   */
  protected $fieldProgress;

  /**
   * Constructs a new EntityProgressManager object.
   */
  public function __construct(RouteProviderInterface $route_provider, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->routeProvider = $route_provider;
    $this->moduleHandler = $module_handler;
    $this->cacheBackend = $cache_backend;
    $this->cacheTags = ['entity.progress'];
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Get progress of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check progress on. If none is provided the user's
   *   current entity will be requested.
   * @param bool $no_cache
   *
   *   Rebuild progress if TRUE.
   *
   * @return array|null
   *   An array containing completness information.
   */
  public function getProgress(ContentEntityInterface $entity, $no_cache = FALSE) {
    if ($entity) {
      $entity_type = $entity->getEntityTypeId();
      $keys = [
        'entity.progress',
        $entity_type,
        $entity->id(),
      ];
      $cid = implode('.', $keys);
      if ($no_cache) {
        unset($this->progress[$cid]);
        unset($this->definitions[$cid]);
        unset($this->fieldProgress[$cid]);
        $this->cacheBackend->delete($cid);
      }
      if (!isset($this->progress[$cid])) {
        if ($cache = $this->cacheGet($cid)) {
          $progress = $cache->data;
        }
        else {
          $cacheable_metadata = CacheableMetadata::createFromObject($entity);
          $completion = $this->getFieldProgress($entity, $cacheable_metadata);
          $progress = [
            'total' => count($completion),
            'complete' => count(array_filter($completion)),
            'incomplete' => count(array_diff($completion, array_filter($completion))),
            'percent' => 0,
            'entity_type' => $entity_type,
            'entity_id' => $entity->id(),
            'entity_type_label' => $entity->getEntityType()->getLabel(),
            'fields' => [],
            'extra' => [],
          ];
          $progress['percent'] = !empty($progress['total']) ? (int) (($progress['complete'] / $progress['total']) * 100 + .5) : 100;

          $definitions = $this->getFieldDefinitions($entity);
          foreach ($completion as $field_name => $is_progress) {
            if ($entity->get($field_name)->access('update')) {
              $key = $is_progress ? 'complete' : 'incomplete';
              $progress['fields'][$key][] = $definitions[$field_name];
            }
          }
          $progress['#cache'] = [
            'keys' => $keys,
          ];
          $cacheable_metadata->applyTo($progress);
          $this->cacheSet($cid, $progress, Cache::PERMANENT, $cacheable_metadata->getCacheTags());
        }
        $this->progress[$cid] = $progress;
      }
      return $this->progress[$cid];
    }
    return NULL;
  }

  /**
   * Check if field is progress.
   */
  protected function isFieldProgress($entity, $field_name, $is_zero = NULL, $require_all = FALSE, CacheableMetadata $cacheable_metadata = NULL) {
    $progress = FALSE;
    if ($entity->hasField($field_name)) {
      $field = $entity->{$field_name};
      $definition = $field->getFieldDefinition();
      if ($cacheable_metadata) {
        $cacheable_metadata->addCacheableDependency($definition);
      }
      if ($require_all) {
        // Require all items to have a value.
        $cardinality = $definition->getFieldStorageDefinition()->getCardinality();
        $count = $field->filterEmptyItems()->count();
        if ($cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && $cardinality != $count) {
          return FALSE;
        }
      }
      switch ($definition->getType()) {
        case 'boolean':
          if (!is_null($is_zero)) {
            $progress = $is_zero ? (int) $field->value === 0 : (int) $field->value === 1;
          }
          else {
            $progress = (int) $field->value === 1;
          }
          break;

        case 'field_signature':
          $progress = FALSE;
          // Signature field does not correctly check empty.
          foreach ($field->getValue() as $value) {
            if (!empty($value['value'])) {
              $progress = TRUE;
            }
          }
          break;

        case 'entity_reference':
        case 'entity_reference_revisions':
          $referenced_entities = $field->referencedEntities();
          $child_progress = empty($referenced_entities) ? FALSE : TRUE;
          foreach ($referenced_entities as $child_entity) {
            if ($cacheable_metadata) {
              $cacheable_metadata->addCacheableDependency($child_entity);
            }
            $progress = $this->getProgress($child_entity);
            if (!empty($progress['incomplete'])) {
              $child_progress = FALSE;
            }
          }
          $progress = $child_progress;
          break;

        default:
          $progress = !$field->isEmpty();
          break;
      }
    }
    return $progress;
  }

  /**
   * Check each field for completion.
   */
  public function getFieldProgress(ContentEntityInterface $entity, CacheableMetadata $cacheable_metadata = NULL) {
    $entity_type = $entity->getEntityTypeId();
    $keys = [
      'entity.progress',
      $entity_type,
      $entity->id(),
    ];
    $cid = implode('.', $keys);
    if (!isset($this->fieldProgress[$cid])) {
      $completion = [];
      $definitions = $this->getFieldDefinitions($entity);
      $this->moduleHandler->alter('entity_progress_fields', $definitions, $entity);
      foreach ($definitions as $field_name => $definition) {
        if ($entity->get($field_name)->access('update')) {
          $require_all = $this->getDefinitionSetting($definition, 'all', FALSE);
          $completion[$field_name] = $this->isFieldProgress($entity, $field_name, NULL, $require_all, $cacheable_metadata);
        }
      }
      $this->fieldProgress[$cid] = $completion;
    }
    return $this->fieldProgress[$cid];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(ContentEntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $keys = [
      'entity.progress',
      $entity_type,
      $entity->id(),
    ];
    $cid = implode('.', $keys);
    if (!isset($this->definitions[$cid])) {
      $this->definitions[$cid] = [];
      $bundle = $entity->bundle();
      $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      foreach ($definitions as $key => $definition) {
        if ($this->isDefinitionProgress($entity, $definition)) {
          $this->definitions[$cid][$key] = $definition;
        }
      }
    }
    return $this->definitions[$cid];
  }

  /**
   * Get a definition settings.
   */
  protected function getDefinitionSettings($definition) {
    $settings = [
      'enable' => FALSE,
      'dependency' => NULL,
      'optional' => FALSE,
      'zero' => FALSE,
      'negate' => FALSE,
      'all' => FALSE,
    ];
    if ($definition instanceof BaseFieldDefinition || $definition instanceof BaseFieldOverride) {
      if ($field_settings = $definition->getSetting('entity_progress')) {
        $settings = $field_settings + $settings;
      }
    }
    elseif ($definition instanceof ThirdPartySettingsInterface) {
      $field_settings = $definition->getThirdPartySettings('entity_progress');
      if (is_array($field_settings) && !empty($field_settings)) {
        $settings = $field_settings + $settings;
      }
    }
    return $settings;
  }

  /**
   * Get a definition setting.
   */
  protected function getDefinitionSetting($definition, $key, $default = NULL) {
    $settings = $this->getDefinitionSettings($definition);
    if (isset($settings[$key])) {
      return $settings[$key];
    }
    else {
      return $default;
    }
  }

  /**
   * Check if definition is a valid completion field.
   *
   * @return bool
   *   Return TRUE if progress.
   */
  protected function isDefinitionProgress($entity, $definition, $force = FALSE) {
    $cid = $entity->getEntityTypeId() . '_' . $entity->bundle() . '.' . $entity->id() . '.' . $definition->getName() . ($force ? '.force' : '');
    if (!isset($this->definition[$cid]) || $force) {
      $this->definition[$cid] = FALSE;
      $settings = $this->getDefinitionSettings($definition);
      $enabled = $settings['enable'];
      if ($force || $enabled) {
        $dependency = $settings['dependency'];
        $is_zero = $settings['zero'];
        $require_all = $settings['all'];
        $valid = TRUE;
        if ($enabled && $dependency) {
          // Incorrect configuration. Dependency cannot require itself.
          if ($dependency === $definition->getName()) {
            $valid = FALSE;
          }
          // Dependent field does not exist. Do not include in completion
          // results.
          if (!isset($entity->{$dependency})) {
            $valid = FALSE;
          }
          $dependency_definition = $entity->{$dependency}->getFieldDefinition();
          if (!$this->isDefinitionProgress($entity, $dependency_definition, TRUE)) {
            $valid = FALSE;
          }
          if ($valid) {
            $valid = $this->isFieldProgress($entity, $dependency, !empty($is_zero), $require_all);
            if (!empty($settings['negate'])) {
              $valid = empty($valid);
            }
            if (!$force && !empty($settings['optional'])) {
              $valid = FALSE;
            }
          }
        }
        $this->definition[$cid] = $valid;
      }
    }
    return $this->definition[$cid];
  }

}
