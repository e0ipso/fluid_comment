<?php

namespace Drupal\jsonapi_comments\Access;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Access\RelationshipFieldAccess;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class CommentFieldAccess.
 *
 * @internal
 */
class CommentFieldAccess {

  /**
   * The route requirement key for this access check.
   *
   * @var string
   */
  const ROUTE_REQUIREMENT_KEY = '_jsonapi_comments_comment_field_access';

  /**
   * @var \Drupal\jsonapi\Access\RelationshipFieldAccess
   */
  protected $inner;

  /**
   * CommentFieldAccess constructor.
   *
   * @param \Drupal\jsonapi\Access\RelationshipFieldAccess $relationship_field_access
   */
  public function __construct(RelationshipFieldAccess $relationship_field_access) {
    $this->inner = $relationship_field_access;
  }

  public function access(Request $request, Route $route, AccountInterface $account) {
    $access_result = $this->inner->access($request, $route, $account);
    $comment_field_name = $route->getRequirement(static::ROUTE_REQUIREMENT_KEY);
    if ($access_result->isAllowed()) {
      $entity = $request->get('entity');
      if ($request->isMethodCacheable()) {
        $entity_specific_access = AccessResult::allowedIf($entity->{$comment_field_name}->status != CommentItemInterface::HIDDEN);
      }
      else {
        $entity_specific_access = AccessResult::allowedIf($entity->{$comment_field_name}->status == CommentItemInterface::OPEN);
      }
      $access_result = $access_result->andIf($entity_specific_access->addCacheableDependency($entity));
    }
    return $access_result;
  }

}
