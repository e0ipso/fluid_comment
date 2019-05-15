<?php

namespace Drupal\jsonapi_comment\HypermediaProvider;

use Drupal\comment\CommentFieldItemList;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_hypermedia\HypermediaProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Provides comment-specific hyperlinks.
 *
 * @internal
 */
class DefaultProvider implements HypermediaProviderInterface {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * DefaultProvider constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user account.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, AccountInterface $current_user) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * Adds a reply link to a comment resource object if it can be replied to.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $comment_object
   *   The comment resource object.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $link_collection
   *   The existing link collection.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The modified link collection.
   */
  protected function addCommentReplyLink(ResourceObject $comment_object, LinkCollection $link_collection) {
    $host_entity = $comment_object->getField('entity_id')->entity;
    assert($host_entity instanceof FieldableEntityInterface);
    $field_name = $comment_object->getField('field_name')->value;
    if (!$host_entity->hasField($field_name) || $host_entity->{$field_name}->status != CommentItemInterface::OPEN) {
      return $link_collection;
    }
    $post_access = AccessResult::allowedIfHasPermission($this->currentUser, 'post comments');
    $link_collection = $this->withLinkCollectionCacheability($link_collection, $post_access);
    if (!$post_access->isAllowed()) {
      return $link_collection;
    }
    $public_field_name = $comment_object->getResourceType()->getPublicName($field_name);
    $host_resource_type = $this->resourceTypeRepository->get($host_entity->getEntityTypeId(), $host_entity->bundle());
    $reply_route_name = "jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comment.{$public_field_name}.child_reply";
    $reply_route_parameters = [
      'commented_entity' => $host_entity->uuid(),
      'parent_comment' => $comment_object->getId(),
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
    $comment_route_name = "jsonapi.{$resource_type->getTypeName()}.jsonapi_comment.{$comment_field_name}";
    try {
      $comments_url = Url::fromRoute($comment_route_name, ['commented_entity' => $commented_resource_object->getId()]);
    }
    catch (RouteNotFoundException $e) {
      return $link_collection;
    }
    $link_relations = [];
    $view_access = AccessResult::allowedIfHasPermission($this->currentUser, 'access comments');
    $link_collection = $this->withLinkCollectionCacheability($link_collection, $view_access);
    if ($field->status != CommentItemInterface::HIDDEN && $view_access->isAllowed()) {
      $link_relations[] = 'collection';
    }
    $post_access = AccessResult::allowedIfHasPermission($this->currentUser, 'post comments');
    $link_collection = $this->withLinkCollectionCacheability($link_collection, $post_access);
    if ($field->status == CommentItemInterface::OPEN && $post_access->isAllowed()) {
      $link_relations[] = 'https://jsonapi.org/profiles/drupal/hypermedia/#add';
    }
    if (!$view_access->orIf($post_access)->isAllowed()) {
      return $link_collection;
    }
    $link_attributes = [
      'linkParams' => ['rel' => $link_relations],
      'commentFieldName' => $resource_type->getPublicName($comment_field_name),
    ];
    if (!empty($link_relations)) {
      $cacheability = CacheableMetadata::createFromObject($commented_resource_object);
      $cacheability->addCacheContexts(['user.permissions']);
      $link = new Link($cacheability, $comments_url, $link_relations, $link_attributes);
      return $link_collection->withLink('comments', $link);
    }
    return $link_collection;
  }

  /**
   * Adds cacheability to a link collection without adding a new link.
   *
   * This is necessary when the *absence* of a link needs to be varied by some
   * cacheable metadata, such as when a user does not have permission to follow
   * a link.
   *
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $link_collection
   *   The link collection that needs the additional cacheability metadata. The
   *   collection must not be empty.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   The extra cacheability metadata.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The modified link collection.
   */
  protected function withLinkCollectionCacheability(LinkCollection $link_collection, CacheableDependencyInterface $cacheability) {
    foreach ($link_collection as $key => $links) {
      foreach  ($links as $link) {
        assert($link instanceof Link);
        $cacheability = CacheableMetadata::createFromObject($link)->addCacheableDependency($cacheability);
        $replace = new Link($cacheability, $link->getUri(), $link->getLinkRelationTypes(), $link->getTargetAttributes());
        break 2;
      }
    }
    assert(isset($key) && isset($replace), 'This method must be passed a non-empty link collection.');
    return $link_collection->withLink($key, $replace);
  }

}
