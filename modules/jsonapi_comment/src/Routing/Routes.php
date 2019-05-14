<?php

namespace Drupal\jsonapi_comment\Routing;

use Drupal\comment\CommentManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Drupal\jsonapi_comment\ParamConverter\JsonApiCommentEntityUuidConverter;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class Routes.
 *
 * @internal
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The service ID for JSON:API Comment routes.
   *
   * @var string
   */
  const CONTROLLER_SERVICE_NAME = 'jsonapi_comment.controller';

  /**
   * The route defaults key for the route's associated comment field name.
   *
   * @var string
   */
  const COMMENT_FIELD_NAME_KEY = 'comment_field_name';

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The comment manager.
   *
   * @var \Drupal\comment\CommentManagerInterface
   */
  protected $commentManager;

  /**
   * List of authentication providers.
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
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\comment\CommentManagerInterface $comment_manager
   *   The comment manager.
   * @param string[] $authentication_providers
   *   The list of available authentication providers.
   * @param string $jsonapi_base_path
   *   The JSON:API base path.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, CommentManagerInterface $comment_manager, array $authentication_providers, $jsonapi_base_path) {
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
   * Provide comment-specific JSON:API compliant route definitions.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The routes.
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
        $path = "{$host_resource_type->getPath()}/{commented_entity}/{$public_field_name}";
        $read_route = new Route($path);
        $read_route->addRequirements(['_permission' => 'access comments']);
        $read_route->setOption('parameters', [
          'commented_entity' => ['type' => JsonApiCommentEntityUuidConverter::PARAM_TYPE_NAME . ':' . $host_resource_type->getEntityTypeId()],
        ]);
        $read_route->setDefault(RouteObjectInterface::CONTROLLER_NAME, static::CONTROLLER_SERVICE_NAME . ':getComments');
        $read_route->setDefault(static::COMMENT_FIELD_NAME_KEY, $internal_field_name);
        $read_route->setMethods(['GET']);
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comment.{$public_field_name}", $read_route);
        $reply_route = new Route($path);
        $reply_route->addRequirements(['_permission' => 'post comments']);
        $reply_route->setOption('parameters', [
          'commented_entity' => ['type' => JsonApiCommentEntityUuidConverter::PARAM_TYPE_NAME . ':' . $host_resource_type->getEntityTypeId()],
        ]);
        $reply_route->setDefault(RouteObjectInterface::CONTROLLER_NAME, static::CONTROLLER_SERVICE_NAME . ':reply');
        $reply_route->setDefault(static::COMMENT_FIELD_NAME_KEY, $internal_field_name);
        $reply_route->setMethods(['POST']);
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comment.{$public_field_name}.reply", $reply_route);
        $child_reply_route = clone $reply_route;
        $child_reply_route->setPath("{$path}/{parent_comment}/replies");
        $child_reply_route->setOption('parameters', $child_reply_route->getOption('parameters') + [
          'parent_comment' => ['type' => JsonApiCommentEntityUuidConverter::PARAM_TYPE_NAME . ':' . $comment_resource_type->getEntityTypeId()],
        ]);
        $routes->add("jsonapi.{$host_resource_type->getTypeName()}.jsonapi_comment.{$public_field_name}.child_reply", $child_reply_route);
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
