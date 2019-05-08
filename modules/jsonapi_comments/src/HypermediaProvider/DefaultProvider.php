<?php

namespace Drupal\jsonapi_comments\HypermediaProvider;

use Drupal\comment\CommentFieldItemList;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_hypermedia\HypermediaProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class DefaultProvider implements HypermediaProviderInterface {

  /**
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  public function hyperlink(LinkCollection $link_collection) {
    $context = $link_collection->getContext();
    if (!$context instanceof ResourceObject) {
      return $link_collection;
    }
    // Add a `reply` link If the links is for a comment resource object.
    if ($context->getResourceType()->getEntityTypeId() === 'comment') {
      $link_collection = $this->addCommentReplyLink($context, $link_collection);
    }
    // Add a link to this module's comment collection for any comment fields.
    foreach ($context->getFields() as $field) {
      if (!$field instanceof CommentFieldItemList) {
        continue;
      }
      $link_collection = $this->addCommentCollectionLink($context, $field, $link_collection);
    }
    return $link_collection;
  }

  protected function addCommentReplyLink(ResourceObject $comment_object, LinkCollection $link_collection) {
    $host_entity = $comment_object->getField('entity_id')->entity;
    assert($host_entity instanceof FieldableEntityInterface);
    $field_name = $comment_object->getField('field_name')->value;
    if (!$host_entity->hasField($field_name) || $host_entity->{$field_name}->status != CommentItemInterface::OPEN) {
      return $link_collection;
    }
    $public_field_name = $comment_object->getResourceType()->getPublicName($field_name);
    $host_resource_type = $this->resourceTypeRepository->get($host_entity->getEntityTypeId(), $host_entity->bundle());
    $reply_route_name = "jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comments.{$public_field_name}.child_reply";
    $reply_route_parameters = [
      'entity' => $host_entity->uuid(),
      'parent' => $comment_object->getId(),
    ];
    $reply_url = Url::fromRoute($reply_route_name, $reply_route_parameters);
    $cacheability = CacheableMetadata::createFromObject($host_entity)->addCacheableDependency($comment_object);
    $link_relations = ['https://jsonapi.org/profiles/drupal/hypermedia/#add'];
    $link_attributes = ['linkParams' => ['rel' => $link_relations]];
    $link = new Link($cacheability, $reply_url, $link_relations, $link_attributes);
    return $link_collection->withLink('reply', $link);
  }

  /**
   * Adds a link for adding comments to comment-able resource objects.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $commented_resource_object
   *   The commented resource object.
   * @param \Drupal\comment\CommentFieldItemList $field
   *   The comment field.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $link_collection
   *   The existing link collection.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The modified link collection.
   */
  protected function addCommentCollectionLink(ResourceObject $commented_resource_object, CommentFieldItemList $field, LinkCollection $link_collection) {
    $resource_type = $commented_resource_object->getResourceType();
    $comment_field_name = $field->getName();
    $comment_route_name = "jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$comment_field_name}";
    try {
      $comments_url = Url::fromRoute($comment_route_name, ['entity' => $commented_resource_object->getId()]);
    }
    catch (RouteNotFoundException $e) {
      return $link_collection;
    }
    $link_relations = [];
    if ($field->status != CommentItemInterface::HIDDEN) {
      $link_relations[] = 'collection';
    }
    if ($field->status == CommentItemInterface::OPEN) {
      $link_relations[] = 'https://jsonapi.org/profiles/drupal/hypermedia/#add';
    }
    $link_attributes = [
      'linkParams' => ['rel' => $link_relations],
      'commentFieldName' => $resource_type->getPublicName($comment_field_name),
    ];
    if (!empty($link_relations)) {
      $cacheability = CacheableMetadata::createFromObject($commented_resource_object);
      $link = new Link($cacheability, $comments_url, $link_relations, $link_attributes);
      return $link_collection->withLink('comments', $link);
    }
    return $link_collection;
  }

}
