<?php

namespace Drupal\jsonapi_comments\HypermediaProvider;

use Drupal\comment\CommentFieldItemList;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi_hypermedia\HypermediaProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class DefaultProvider implements HypermediaProviderInterface {

  public function hyperlink(LinkCollection $link_collection) {
    $context = $link_collection->getContext();
    if (!$context instanceof ResourceObject) {
      return $link_collection;
    }
    $resource_type = $context->getResourceType();
    foreach ($context->getFields() as $field) {
      if (!$field instanceof CommentFieldItemList) {
        continue;
      }
      $comment_field_name = $field->getName();
      $comment_route_name = "jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$comment_field_name}";
      try {
        $comments_url = Url::fromRoute($comment_route_name, ['entity' => $context->getId()]);
      }
      catch (RouteNotFoundException $e) {
        continue;
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
        $cacheability = CacheableMetadata::createFromObject($context);
        $link = new Link($cacheability, $comments_url, $link_relations, $link_attributes);
        $link_collection = $link_collection->withLink('comments', $link);
      }
    }
    return $link_collection;
  }

}
