<?php

namespace Drupal\jsonapi_comments\Access;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Access\RelationshipFieldAccess;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class CommentFieldAccess.
 *
 * @internal
 */
class CommentFieldAccess implements AccessInterface {

  /**
   * The route requirement key for this access check.
   *
   * @var string
   */
  const ROUTE_REQUIREMENT_KEY = '_jsonapi_comments_comment_field_access';

  /**
   * @var \Drupal\jsonapi\Access\EntityAccessChecker
   */
  protected $entityAccessChecker;

  /**
   * CommentFieldAccess constructor.
   *
   * @param \Drupal\jsonapi\Access\EntityAccessChecker $entity_access_checker
   */
  public function __construct(EntityAccessChecker $entity_access_checker) {
    $this->entityAccessChecker = $entity_access_checker;
  }

  public function access(Request $request, Route $route, AccountInterface $account) {
    $field_operation = $request->isMethodCacheable() ? 'view' : 'edit';
    $entity_operation = $request->isMethodCacheable() ? 'view' : 'update';
    if ($resource_type = $request->get(Routes::RESOURCE_TYPE_KEY)) {
      assert($resource_type instanceof ResourceType);
      $entity = $request->get('entity');
      $comment_field_name = $route->getRequirement(static::ROUTE_REQUIREMENT_KEY);
      if ($entity instanceof FieldableEntityInterface && $entity->hasField($comment_field_name)) {
        $entity_access = $this->entityAccessChecker->checkEntityAccess($entity, $entity_operation, $account);
        $field_access = $entity->get($comment_field_name)->access($field_operation, $account, TRUE);
        // Ensure that access is respected for different entity revisions.
        $access_result = $entity_access->andIf($field_access);
        // Ensure that comment field settings are respected.
        if ($request->isMethodCacheable()) {
          $comment_specific_access = $entity->{$comment_field_name}->status != CommentItemInterface::HIDDEN
            ? AccessResult::allowed()
            : AccessResult::forbidden('These comments have been hidden by the administrator.');
        }
        else {
          $comment_specific_access = $entity->{$comment_field_name}->status == CommentItemInterface::OPEN
            ? AccessResult::allowed()
            : AccessResult::forbidden('These comments have been closed by the administrator.');
        }
        $access_result = $access_result->andIf($comment_specific_access->addCacheableDependency($entity));
        if (!$access_result->isAllowed()) {
          $reason = "The current user is not allowed to {$field_operation} this comment field.";
          $access_reason = $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL;
          $detailed_reason = empty($access_reason) ? $reason : $reason . " {$access_reason}";
          $access_result->setReason($detailed_reason);
          if ($request->isMethodCacheable()) {
            throw new CacheableAccessDeniedHttpException(CacheableMetadata::createFromObject($access_result), $detailed_reason);
          }
        }
        return $access_result;
      }
    }
    return AccessResult::neutral();
  }

}
