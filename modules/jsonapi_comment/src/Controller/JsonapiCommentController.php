<?php

namespace Drupal\jsonapi_comment\Controller;

use Drupal\comment\CommentManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentStorageInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\Revisions\ResourceVersionRouteEnhancer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Serves specialized comment routes.
 *
 * @internal
 */
class JsonapiCommentController extends EntityResource {

  /**
   * Responds with a collection of comments.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $commented_entity
   *   The commented entity.
   * @param string $comment_field_name
   *   The comment field for which to serve comments.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   A JSON:API resource response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getComments(Request $request, FieldableEntityInterface $commented_entity, $comment_field_name) {
    $this->blockUnsupportedQueryParameters($request);
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($commented_entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $comment_field_definition = $commented_entity->get($comment_field_name)->getFieldDefinition();
    $default_mode = (int) $comment_field_definition->getSetting('default_mode');
    $per_page = (int) $comment_field_definition->getSetting('per_page');
    assert($comment_storage instanceof CommentStorageInterface);
    $comments = $comment_storage->loadThread($commented_entity, $comment_field_name, $default_mode, $per_page, 0);
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
      static::getPaginationLinks($request),
      [
        'displayOptions' => [
          'threaded' => $default_mode === CommentManagerInterface::COMMENT_MODE_THREADED,
        ],
      ]
    );
  }

  /**
   * Copy of EntityResource::createIndividual except for two code blocks.
   *
   * The additional code blocks add required data to a posted comment so that
   * the decoupled consumer does not need to know these Drupal implementation
   * details.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityInterface $commented_entity
   *   The commented entity.
   * @param string $comment_field_name
   *   The comment field for which to serve comments.
   * @param \Drupal\comment\CommentInterface $parent_comment
   *   (optional) The comment entity being replied to.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   A JSON:API resource response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function reply(Request $request, EntityInterface $commented_entity, $comment_field_name, CommentInterface $parent_comment = NULL) {
    // The following lines are needed in addition to the copied code from
    // Drupal\jsonapi\Controller\EntityResource::createIndividual. There is an
    // additional code block further below as well.
    $this->blockUnsupportedQueryParameters($request);
    $comment_field_storage_definition = FieldStorageConfig::loadByName($commented_entity->getEntityTypeId(), $comment_field_name);
    $comment_type = $comment_field_storage_definition->getSetting('comment_type');
    $comment_resource_type = $this->resourceTypeRepository->get('comment', $comment_type);

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

    // This is the other part of this method which is not an exact copy of
    // Drupal\jsonapi\Controller\EntityResource::createIndividual.
    // @todo: ensure that this can't be used to add a comment where commenting is not permitted.
    assert($parsed_entity instanceof CommentInterface);
    if (!$parsed_entity->getOwnerId()) {
      $parsed_entity->setOwnerId($this->user->id());
    }
    $parsed_entity->entity_type = $commented_entity->getEntityTypeId();
    $parsed_entity->entity_id = $commented_entity;
    $parsed_entity->field_name = $comment_field_name;
    if ($parent_comment) {
      $parsed_entity->pid = $parent_comment;
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

  /**
   * Blocks any requests using a number of unsupported query parameters.
   *
   * These comment-specific endpoints cannot support sorting and filtering
   * (because CommentStorage::loadThread cannot support them), nor does this
   * route support resource versioning.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableBadRequestHttpException
   *   Thrown when an unsupported query parameter is requested.
   */
  protected function blockUnsupportedQueryParameters(Request $request) {
    $unsupported_query_params = [
      'sort',
      'filter',
      ResourceVersionRouteEnhancer::RESOURCE_VERSION_QUERY_PARAMETER,
    ];
    foreach ($unsupported_query_params as $unsupported_query_param) {
      if ($request->query->has($unsupported_query_param)) {
        $cacheability = new CacheableMetadata();
        $cacheability->addCacheContexts(['url.path', "url.query_args:$unsupported_query_param"]);
        $message = "The `$unsupported_query_param` query parameter is not yet supported by the JSON:API Comment module.";
        throw new CacheableBadRequestHttpException($cacheability, $message);
      }
    }
  }

  /**
   * Provides pagination links compatible with CommentStorage::loadThread().
   *
   * CommentStorage::loadThread() is incompatible with JSON:API's pagination
   * query parameter syntax. This method generates the pagination links that
   * *are* compatible with it.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The pagination links.
   */
  protected static function getPaginationLinks(Request $request) {
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
    if ($current_page < $pager_total[$element] - 1) {
      $next_url = static::getRequestLink($request, ['page' => $current_page + 1] + $default_query);
      $pager_links = $pager_links->withLink('next', new Link(new CacheableMetadata(), $next_url, ['next']));
      $last_url = static::getRequestLink($request, ['page' => $pager_total[$element] - 1]);
      $pager_links = $pager_links->withLink('last', new Link(new CacheableMetadata(), $last_url, ['last']));
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
