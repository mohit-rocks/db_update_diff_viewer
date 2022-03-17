<?php

namespace Drupal\db_update_diff_viewer\Entity;

use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeListenerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Schema\DynamicallyFieldableEntityStorageSchemaInterface;
use Drupal\Core\Entity\Schema\EntityStorageSchemaInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionListenerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages entity definition updates.
 */
class EntityDefinitionUpdateManagerDecorator implements EntityDefinitionUpdateManagerInterface {
  use StringTranslationTrait;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type listener service.
   *
   * @var \Drupal\Core\Entity\EntityTypeListenerInterface
   */
  protected $entityTypeListener;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field storage definition listener service.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionListenerInterface
   */
  protected $fieldStorageDefinitionListener;

  /**
   * The last installed schema repository.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * Entity definition update manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * Constructs a new EntityDefinitionUpdateManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The last installed schema repository service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeListenerInterface $entity_type_listener
   *   The entity type listener interface.
   * @param \Drupal\Core\Field\FieldStorageDefinitionListenerInterface $field_storage_definition_listener
   *   The field storage definition listener service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository, EntityFieldManagerInterface $entity_field_manager, EntityTypeListenerInterface $entity_type_listener, FieldStorageDefinitionListenerInterface $field_storage_definition_listener, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityLastInstalledSchemaRepository = $entity_last_installed_schema_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeListener = $entity_type_listener;
    $this->fieldStorageDefinitionListener = $field_storage_definition_listener;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function needsUpdates() {
    return $this->entityDefinitionUpdateManager->needsUpdates();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangeSummary() {
    return $this->entityDefinitionUpdateManager->getChangeSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType($entity_type_id) {
    return $this->entityDefinitionUpdateManager->getEntityType($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypes() {
    return $this->entityDefinitionUpdateManager->getEntityTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function installEntityType(EntityTypeInterface $entity_type) {
    $this->entityDefinitionUpdateManager->installEntityType($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntityType(EntityTypeInterface $entity_type) {
    $this->entityDefinitionUpdateManager->updateEntityType($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function uninstallEntityType(EntityTypeInterface $entity_type) {
    $this->entityDefinitionUpdateManager->uninstallEntityType($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function installFieldableEntityType(EntityTypeInterface $entity_type, array $field_storage_definitions) {
    $this->entityDefinitionUpdateManager->installFieldableEntityType($entity_type, $field_storage_definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function updateFieldableEntityType(EntityTypeInterface $entity_type, array $field_storage_definitions, array &$sandbox = NULL) {
    $this->entityDefinitionUpdateManager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
  }

  /**
   * {@inheritdoc}
   */
  public function installFieldStorageDefinition($name, $entity_type_id, $provider, FieldStorageDefinitionInterface $storage_definition) {
    $this->entityDefinitionUpdateManager->installFieldStorageDefinition($name, $entity_type_id, $provider, $storage_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldStorageDefinition($name, $entity_type_id) {
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition($name, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function updateFieldStorageDefinition(FieldStorageDefinitionInterface $storage_definition) {
    $original = $this->getFieldStorageDefinition($storage_definition->getName(), $storage_definition->getTargetEntityTypeId());
    $this->clearCachedDefinitions();
    $this->fieldStorageDefinitionListener->onFieldStorageDefinitionUpdate($storage_definition, $original);
  }

  /**
   * {@inheritdoc}
   */
  public function uninstallFieldStorageDefinition(FieldStorageDefinitionInterface $storage_definition) {
    $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($storage_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getChangeList() {
    return $this->entityDefinitionUpdateManager->getChangeList();
  }


  protected function getFieldStorageSchemaChanges(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    $storage = $this->entityTypeManager->getStorage($storage_definition->getTargetEntityTypeId());
    if ($storage instanceof DynamicallyFieldableEntityStorageSchemaInterface) {
      return $storage->getFieldStorageSchemaChanges($storage_definition, $original);
    }
  }


  /**
   * Clears necessary caches to apply entity/field definition updates.
   */
  protected function clearCachedDefinitions() {
    $this->entityTypeManager->clearCachedDefinitions();
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

}
