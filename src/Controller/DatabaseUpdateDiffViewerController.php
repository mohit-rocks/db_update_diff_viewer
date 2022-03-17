<?php

namespace Drupal\db_update_diff_viewer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\diff\DiffFormatter;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Schema\DynamicallyFieldableEntityStorageSchemaInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Database Update Diff Viewer routes.
 */
class DatabaseUpdateDiffViewerController extends ControllerBase {

  /**
   * Entity definition update manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager;

  /**
   * Diff formatter service.
   *
   * @var \Drupal\Core\diff\DiffFormatter
   */
  protected DiffFormatter $diffFormatter;

  /**
   * Entity last installed schema repository service.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository;

  /**
   * Entity Field Manager interface.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Construct the DatabaseUpdateDiffViewerController.
   *
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager
   *   Entity Definition Update Manager service.
   * @param \Drupal\Core\Diff\DiffFormatter $diffFormatter
   *   Diff formatter service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository
   *   Entity last installed schema repository service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager, DiffFormatter $diffFormatter, EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityDefinitionUpdateManager = $entityDefinitionUpdateManager;
    $this->diffFormatter = $diffFormatter;
    $this->entityLastInstalledSchemaRepository = $entityLastInstalledSchemaRepository;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.definition_update_manager'),
      $container->get('diff.formatter'),
      $container->get('entity.last_installed_schema.repository'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Builds the response.
   */
  public function list() {

    $changes = $this->entityDefinitionUpdateManager->getChangeList();

    $entity_field_changes = array_map(function ($entity_change) {
      return array_filter($entity_change['field_storage_definitions'], function ($change) {
        if ($change === EntityDefinitionUpdateManager::DEFINITION_UPDATED) {
          return TRUE;
        }
        return FALSE;
      });
    }, $changes);

    $header = [
      'entity' => $this->t('Title'),
      'type' => $this->t('Type'),
      'link' => $this->t('Diff Link'),
    ];

    $rows = [];
    foreach ($entity_field_changes as $entity_type_id => $fields) {
      if (!empty($fields)) {
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        foreach ($fields as $field_name => $field) {
          $link = Link::createFromRoute('Diff', 'db_update_diff_viewer.diff', [
            'entity_type_id' => $entity_type_id,
            'type' => 'field_storage_definitions',
            'field_name' => $field_name,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode([
                'width' => 700,
              ]),
            ],
          ]);
          $rows[] = [
            $entity_type->getLabel(),
            "field_storage_definition:{$field_name}",
            $link,
          ];
        }
      }
    }

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content has been found.'),
    ];

    return $build;
  }

  /**
   * Get the difference and display in modal.
   *
   * @param string $entity_type_id
   *   Entity type id for which we are checking the difference.
   * @param string $type
   *   Difference type. Typically, "field_storage_definitions".
   * @param string $field_name
   *   Field name.
   */
  public function diff(string $entity_type_id, string $type, string $field_name) {

    if ($type === 'field_storage_definitions') {
      // Detect updated field storage definitions.
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $original_storage_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
      $storage = $this->entityTypeManager->getStorage($storage_definitions[$field_name]->getTargetEntityTypeId());

      if ($storage instanceof DynamicallyFieldableEntityStorageSchemaInterface) {
        $diff = $storage->getFieldStorageSchemaChanges($storage_definitions[$field_name], $original_storage_definitions[$field_name]);
      }
    }

    $build['diff'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['diff'],
      ],
      '#header' => [
        ['data' => $this->t('Active'), 'colspan' => '2'],
        ['data' => $this->t('Staged'), 'colspan' => '2'],
      ],
      '#rows' => $this->diffFormatter->format($diff),
    ];

    return $build;
  }

}
