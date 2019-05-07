<?php

namespace Drupal\jsonapi_comments\Routing;

use Drupal\comment\CommentManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Drupal\jsonapi_comments\Access\CommentFieldAccess;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class Routes.
 */
class Routes implements ContainerInjectionInterface {

  const CONTROLLER_SERVICE_NAME = 'jsonapi_comments.controller';

  const COMMENT_FIELD_NAME_KEY = 'jsonapi_comments.comment_field_name';

  const HOST_RESOURCE_TYPE_KEY = 'comment_host_resource_type';

  const COMMENT_RESOURCE_TYPE_KEY = 'comment_resource_type';

  /**
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * @var \Drupal\comment\CommentManagerInterface
   */
  protected $commentManager;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonapiBasePath;

  /**
   * Routes constructor.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, CommentManagerInterface $comment_manager, $authentication_providers, $jsonapi_base_path) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->commentManager = $comment_manager;
    $this->providerIds = array_keys($authentication_providers);
    $this->jsonapiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('comment.manager'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('jsonapi.base_path')
    );
  }

  /**
   * @return \Symfony\Component\Routing\RouteCollection
   */
  public function routes() {
    $routes = new RouteCollection();
    foreach ($this->resourceTypeRepository->all() as $host_resource_type) {
      if ($host_resource_type->isInternal()) {
        continue;
      }
      $map = $this->commentManager->getFields($host_resource_type->getEntityTypeId());
      foreach ($map as $internal_field_name => $details) {
        if (!in_array($host_resource_type->getBundle(), $details['bundles'])) {
          continue;
        }
        $comment_field_storage_definition = FieldStorageConfig::loadByName($host_resource_type->getEntityTypeId(), $internal_field_name);
        $comment_type = $comment_field_storage_definition->getSetting('comment_type');
        $comment_resource_type = $this->resourceTypeRepository->get('comment', $comment_type);
        if ($comment_resource_type->isInternal()) {
          continue;
        }
        $public_field_name = $host_resource_type->getPublicName($internal_field_name);
        $path = "{$host_resource_type->getPath()}/{entity}/{$public_field_name}";
        $read_route = new Route($path);
        $read_route->setOption('parameters', [
          JsonapiRoutes::RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          static::HOST_RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          static::COMMENT_RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          'entity' => ['type' => 'entity:' . $host_resource_type->getEntityTypeId()],
        ]);
        $read_route->addDefaults([
          RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getComments',
          JsonapiRoutes::RESOURCE_TYPE_KEY => $host_resource_type->getTypeName(),
          static::HOST_RESOURCE_TYPE_KEY => $host_resource_type->getTypeName(),
          static::COMMENT_RESOURCE_TYPE_KEY => $comment_resource_type->getTypeName(),
          static::COMMENT_FIELD_NAME_KEY => $internal_field_name,
        ]);
        $read_route->setMethods(['GET']);
        $read_route->setRequirement(CommentFieldAccess::ROUTE_REQUIREMENT_KEY, $internal_field_name);
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comments.{$public_field_name}", $read_route);
        $reply_route = new Route($path);
        $reply_route->setOption('parameters', [
          JsonapiRoutes::RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          static::HOST_RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          static::COMMENT_RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
          'entity' => ['type' => 'entity:' . $host_resource_type->getEntityTypeId()],
        ]);
        $reply_route->addDefaults([
          RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':reply',
          JsonapiRoutes::RESOURCE_TYPE_KEY => $host_resource_type->getTypeName(),
          static::HOST_RESOURCE_TYPE_KEY => $host_resource_type->getTypeName(),
          static::COMMENT_RESOURCE_TYPE_KEY => $comment_resource_type->getTypeName(),
          static::COMMENT_FIELD_NAME_KEY => $internal_field_name,
        ]);
        $reply_route->setMethods(['POST']);
        $reply_route->setRequirement(CommentFieldAccess::ROUTE_REQUIREMENT_KEY, $internal_field_name);
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comments.{$public_field_name}.reply", $reply_route);
        $child_reply_route = clone $reply_route;
        $child_reply_route->setPath("{$path}/{parent}/replies");
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comments.{$public_field_name}.child_reply", $child_reply_route);
      }
    }
    $routes->addPrefix($this->jsonapiBasePath);

    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);
    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);
    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([JsonapiRoutes::JSON_API_ROUTE_FLAG_KEY => TRUE]);
    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

}
