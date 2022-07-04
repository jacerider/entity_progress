<?php

namespace Drupal\entity_progress;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for entity progress manager.
 *
 * @package Drupal\entity_progress
 */
interface EntityProgressManagerInterface {

  /**
   * Get progress of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check progress on. If none is provided the user's
   *   current entity will be requested.
   * @param bool $no_cache
   *   Rebuild progress if TRUE.
   *
   * @return array|null
   *   An array containing completness information.
   */
  public function getProgress(ContentEntityInterface $entity, $no_cache = FALSE);

  /**
   * Check each field for completion.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check progress on. If none is provided the user's
   *   current entity will be requested.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  public function getFieldProgress(ContentEntityInterface $entity, CacheableMetadata $cacheable_metadata = NULL);

  /**
   * Get field definition of fields set for progress checking.
   *
   * Fields that do not meet dependency requirements are not included.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check progress on. If none is provided the user's
   *   current entity will be requested.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  public function getFieldDefinitions(ContentEntityInterface $entity);

}
