<?php

namespace Drupal\jsonapi_comments\Controller;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\CommentStorageInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\Core\Http\Exception\CacheableHttpException;
use Drupal\Core\Url;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Revisions\ResourceVersionRouteEnhancer;
use Drupal\jsonapi_comments\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonapiCommentsController extends EntityResource {

  public function getComments(Request $request, ResourceType $resource_type, EntityInterface $entity) {
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $internal_field_name = $request->get(Routes::COMMENT_FIELD_NAME_KEY);
    $public_field_name = $resource_type->getPublicName($internal_field_name);
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    assert($comment_storage instanceof CommentStorageInterface);
    // @todo: add actual support for the `page` parameter.
    $pagination = $this->getPagination($request, $resource_type);
    $comments = $comment_storage->loadThread($entity, $internal_field_name, CommentManagerInterface::COMMENT_MODE_FLAT);
    $resource_objects = array_map(function (CommentInterface $comment) use ($resource_type, $entity, $public_field_name) {
      $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($comment);
      $reply_url = Url::fromRoute(
        "jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$public_field_name}.child_reply",
        ['entity' => $entity->uuid(), 'parent' => $comment->uuid()]
      );
      $cacheability = CacheableMetadata::createFromObject($entity)->addCacheableDependency($comment);
      $link_relations = ['https://jsonapi.org/profiles/drupal/hypermedia/#add'];
      $link_attributes = ['linkParams' => ['rel' => $link_relations]];
      // All of this is just copied so that the `reply` link can be added.
      return new ResourceObject(
        $resource_object,
        $resource_object->getResourceType(),
        $resource_object->getId(),
        $comment->getLoadedRevisionId(),
        $resource_object->getFields(),
        $resource_object->getLinks()->withLink('reply', new Link($cacheability, $reply_url, $link_relations, $link_attributes))
      );
    }, $comments);
    $primary_data = new ResourceObjectData($resource_objects);
    $response = $this->respondWithCollection($primary_data, $this->getIncludes($request, $primary_data), $request, $resource_type, $pagination);
    return $response;
  }

  public function reply(Request $request, ResourceType $resource_type, EntityInterface $entity, EntityInterface $parent = NULL) {
    throw new CacheableHttpException(new CacheableMetadata(), Response::HTTP_NOT_IMPLEMENTED, 'Not yet implemented');
  }

  /**
   * @return \Drupal\jsonapi\Query\OffsetPage
   */
  protected function getPagination(Request $request, ResourceType $resource_type) {
    foreach (['sort', 'filter', 'page', ResourceVersionRouteEnhancer::RESOURCE_VERSION_QUERY_PARAMETER] as $unsupported_query_param) {
      if ($request->query->has($unsupported_query_param)) {
        $cacheability = new CacheableMetadata();
        $cacheability->addCacheContexts(['url.path', "url.query_args:$unsupported_query_param"]);
        $message = "The `$unsupported_query_param` query parameter is not yet supported by the JSON:API Comments module.";
        throw new CacheableBadRequestHttpException($cacheability, $message);
      }
    }
    $params = parent::getJsonApiParams($request, $resource_type);
    return $params[OffsetPage::KEY_NAME];
  }

}