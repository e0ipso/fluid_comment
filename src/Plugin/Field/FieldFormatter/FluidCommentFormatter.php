<?php

namespace Drupal\fluid_comment\Plugin\Field\FieldFormatter;

use Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\editor\Plugin\EditorManager;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a fluid comment formatter.
 *
 * @FieldFormatter(
 *   id = "fluid_comment",
 *   module = "fluid_comment",
 *   label = @Translation("Fluid comment list"),
 *   field_types = {
 *     "comment"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class FluidCommentFormatter extends CommentDefaultFormatter {

  /**
   * @var ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * @var \Drupal\editor\Plugin\EditorManager
   */
  protected $editorManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->setResourceTypeRepository($container->get('jsonapi.resource_type.repository'));
    $formatter->setEditorManager($container->get('plugin.manager.editor'));
    return $formatter;
  }

  /**
   * Sets the JSON:API resource type repository.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  protected function setResourceTypeRepository(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * @param \Drupal\editor\Plugin\EditorManager $editor_manager
   */
  protected function setEditorManager(EditorManager $editor_manager) {
    $this->editorManager = $editor_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $host_entity = $items->getEntity();
    $host_type = $this->resourceTypeRepository->get($host_entity->getEntityTypeId(), $host_entity->bundle());
    $host_type_name = $host_type->getTypeName();
    $host_id = $host_entity->uuid();
    $comment_type_name = $this->resourceTypeRepository->get('comment', $items->getFieldDefinition()->getItemDefinition()->getSetting('comment_type'))->getTypeName();
    $elements[0]['comments'] = [[
      '#theme' => 'fluid_comment_formatter',
      '#comment_target_type' => $host_type_name,
      '#comment_target_id' => $host_id,
      '#comment_type' => $comment_type_name,
      '#attached' => [
        'library' => [
          'fluid_comment/reactjs',
          'fluid_comment/fluid_comment',
        ],
      ],
    ]];
    $elements[0]['comment_form'] = [];
    // Attach Text Editor module's (this module) library.
    $elements[0]['comments'][0]['#attached']['library'][] = 'editor/drupal.editor';
    // Attach attachments for all available editors.
    $elements[0]['comments'][0]['#attached'] = BubbleableMetadata::mergeAttachments($elements[0]['comments'][0]['#attached'], $this->editorManager->getAttachments(array_keys(filter_formats())));
    return $elements;
  }

}