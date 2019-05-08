<?php

namespace Drupal\jsonapi_comments\Controller;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\CommentStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Revisions\ResourceVersionRouteEnhancer;
use Drupal\jsonapi_comments\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JsonapiCommentsController extends EntityResource {

  public function getComments(Request $request, ResourceType $resource_type, FieldableEntityInterface $entity) {
    $this->blockUnsupportedQueryParameters($request);
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $internal_field_name = $request->get(Routes::COMMENT_FIELD_NAME_KEY);
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $per_page = (int) $entity->get($internal_field_name)->getFieldDefinition()->getSetting('per_page');
    assert($comment_storage instanceof CommentStorageInterface);
    $comments = $comment_storage->loadThread($entity, $internal_field_name, CommentManagerInterface::COMMENT_MODE_FLAT, $per_page, 0);
    $resource_objects = array_map(function (CommentInterface $comment) {
      return $this->entityAccessChecker->getAccessCheckedResourceObject($comment);
    }, $comments);
    $primary_data = new ResourceObjectData($resource_objects);
    return $this->buildWrappedResponse(
      $primary_data,
      $request,
      $this->getIncludes($request, $primary_data),
      Response::HTTP_OK,
      [],
      static::getPagerLinks($request, new OffsetPage(0, 0))
    );
  }

  /**
   * Copy of EntityResource::createIndividual except for one code block.
   *
   * The additional code block adds required data to a posted comment so that
   * the decoupled consumer does not need to know these Drupal implementation
   * details.
   */
  public function reply(Request $request, ResourceType $comment_resource_type, EntityInterface $entity, EntityInterface $parent = NULL) {
    $this->blockUnsupportedQueryParameters($request);
    $parsed_entity = $this->deserialize($comment_resource_type, $request, JsonApiDocumentTopLevel::class);

    if ($parsed_entity instanceof FieldableEntityInterface) {
      // Only check 'edit' permissions for fields that were actually submitted
      // by the user. Field access makes no distinction between 'create' and
      // 'update', so the 'edit' operation is used here.
      $document = Json::decode($request->getContent());
      foreach (['attributes', 'relationships'] as $data_member_name) {
        if (isset($document['data'][$data_member_name])) {
          $valid_names = array_filter(array_map(function ($public_field_name) use ($comment_resource_type) {
            return $comment_resource_type->getInternalName($public_field_name);
          }, array_keys($document['data'][$data_member_name])), function ($internal_field_name) use ($comment_resource_type) {
            return $comment_resource_type->hasField($internal_field_name);
          });
          foreach ($valid_names as $field_name) {
            $field_access = $parsed_entity->get($field_name)->access('edit', NULL, TRUE);
            if (!$field_access->isAllowed()) {
              $public_field_name = $comment_resource_type->getPublicName($field_name);
              throw new EntityAccessDeniedHttpException(NULL, $field_access, "/data/$data_member_name/$public_field_name", sprintf('The current user is not allowed to POST the selected field (%s).', $public_field_name));
            }
          }
        }
      }
    }

    // This is the only part of this method which is not an exact copy of
    // Drupal\jsonapi\Controller\EntityResource::createIndividual.
    // @todo: ensure that this can't be used to add a comment where commenting is not permitted.
    $parsed_entity->entity_type = $entity->getEntityTypeId();
    $parsed_entity->entity_id = $entity;
    $parsed_entity->field_name = $request->get(Routes::COMMENT_FIELD_NAME_KEY);
    if ($parent) {
      $parsed_entity->pid = $parent;
    }

    static::validate($parsed_entity);

    // Return a 409 Conflict response in accordance with the JSON:API spec. See
    // http://jsonapi.org/format/#crud-creating-responses-409.
    if ($this->entityExists($parsed_entity)) {
      throw new ConflictHttpException('Conflict: Entity already exists.');
    }

    $parsed_entity->save();

    // Build response object.
    $resource_object = ResourceObject::createFromEntity($comment_resource_type, $parsed_entity);
    $primary_data = new ResourceObjectData([$resource_object], 1);
    $response = $this->buildWrappedResponse($primary_data, $request, $this->getIncludes($request, $primary_data), 201);

    // According to JSON:API specification, when a new entity was created
    // we should send "Location" header to the frontend.
    if ($comment_resource_type->isLocatable()) {
      $url = $resource_object->toUrl()->setAbsolute()->toString(TRUE);
      $response->addCacheableDependency($url);
      $response->headers->set('Location', $url->getGeneratedUrl());
    }

    // Return response object with updated headers info.
    return $response;
  }

  protected function blockUnsupportedQueryParameters(Request $request) {
    foreach (['sort', 'filter', ResourceVersionRouteEnhancer::RESOURCE_VERSION_QUERY_PARAMETER] as $unsupported_query_param) {
      if ($request->query->has($unsupported_query_param)) {
        $cacheability = new CacheableMetadata();
        $cacheability->addCacheContexts(['url.path', "url.query_args:$unsupported_query_param"]);
        $message = "The `$unsupported_query_param` query parameter is not yet supported by the JSON:API Comments module.";
        throw new CacheableBadRequestHttpException($cacheability, $message);
      }
    }
  }

  protected static function getPagerLinks(Request $request, OffsetPage $page_param, array $link_context = []) {
    global $pager_total;
    // The pager element is always `0` because multiple pagers are not supported
    // via JSON:API.
    $element = 0;
    $pager_links = new LinkCollection([]);
    $current_page = pager_find_page($element);
    if ($pager_total[$element] <= 1) {
      return $pager_links;
    }
    $default_query = UrlHelper::filterQueryParameters($request->query->all(), ['page']);
    if ($current_page < $pager_total[$element]) {
      $next_url = static::getRequestLink($request, ['page' => $current_page + 1] + $default_query);
      $pager_links = $pager_links->withLink('next', new Link(new CacheableMetadata(), $next_url, ['next']));
    }
    if ($current_page > 0) {
      $first_url = static::getRequestLink($request, ['page' => 0] + $default_query);
      $pager_links = $pager_links->withLink('first', new Link(new CacheableMetadata(), $first_url, ['first']));
      $prev_url = static::getRequestLink($request, ['page' => $current_page - 1] + $default_query);
      $pager_links = $pager_links->withLink('prev', new Link(new CacheableMetadata(), $prev_url, ['prev']));
    }
    return $pager_links;
  }

}
