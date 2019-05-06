<?php

namespace Drupal\jsonapi_comments\Routing;

use Drupal\comment\CommentManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
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

  /**
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * @var \Drupal\comment\CommentManagerInterface
   */
  protected $commentManager;

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
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, CommentManagerInterface $comment_manager, $jsonapi_base_path) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->commentManager = $comment_manager;
    $this->jsonapiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('comment.manager'),
      $container->getParameter('jsonapi.base_path')
    );
  }

  /**
   * @return \Symfony\Component\Routing\RouteCollection
   */
  public function routes() {
    $routes = new RouteCollection();
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      if (!$resource_type->isInternal()) {
        continue;
      }
      $map = $this->commentManager->getFields($resource_type->getEntityTypeId());
      foreach ($map as $field_name => $details) {
        if (!in_array($resource_type->getBundle(), $details['bundles'])) {
          continue;
        }
        $path = "{$resource_type->getPath()}/{entity}/{$field_name}";
        $read_route = new Route($path);
        $read_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getComments']);
        $read_route->setMethods(['GET']);
        $read_route->setRequirement(CommentFieldAccess::ROUTE_REQUIREMENT_KEY, $field_name);
        $routes->add("jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$field_name}", $read_route);
        $reply_route = new Route($path);
        $reply_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':reply']);
        $reply_route->setMethods(['POST']);
        $reply_route->setRequirement(CommentFieldAccess::ROUTE_REQUIREMENT_KEY, $field_name);
        $routes->add("jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$field_name}.reply", $reply_route);
        $child_reply_route = clone $reply_route;
        $child_reply_route->setPath("{$path}/{parent}/replies");
        $routes->add("jsonapi.{$resource_type->getTypeName()}.jsonapi_comments.{$field_name}.child_reply", $reply_route);
      }
    }
    $routes->addPrefix($this->jsonapiBasePath);
    return $routes;
  }

}
