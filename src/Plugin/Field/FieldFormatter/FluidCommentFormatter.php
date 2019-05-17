<?php

namespace Drupal\fluid_comment\Plugin\Field\FieldFormatter;

use Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->setResourceTypeRepository($container->get('jsonapi.resource_type.repository'));
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
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    // If $elements is empty, comments aren't intended to be shown.
    if (!empty($elements)) {
      $host_entity = $items->getEntity();
      $host_type = $this->resourceTypeRepository->get($host_entity->getEntityTypeId(), $host_entity->bundle());
      $host_type_name = $host_type->getTypeName();
      $host_id = $host_entity->uuid();
      $comment_type_name = $this->resourceTypeRepository->get('comment', $items->getFieldDefinition()->getItemDefinition()->getSetting('comment_type'))->getTypeName();
      $build['#theme'] = 'fluid_comment_formatter';
      $build['#comment_type'] = $comment_type_name;
      $build['#commented_resource_url'] = Url::fromRoute("jsonapi.$host_type_name.individual", ['entity' => $host_id])->setAbsolute()->toString(TRUE)->getGeneratedUrl();
      $build['#attached'] = [
        'library' => [
          'fluid_comment/fluid_comment',
        ],
      ];
      $elements[0]['comments'] = [$build];
      $elements[0]['comment_form'] = [];
    }
    return $elements;
  }

}
