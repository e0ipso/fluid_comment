<?php

namespace Drupal\jsonapi_comment\ParamConverter;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\jsonapi\ParamConverter\EntityUuidConverter;
use Drupal\jsonapi_comment\Routing\Routes;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Route;

/**
 * Class JsonApiCommentEntityUuidConverter.
 *
 * @internal
 */
class JsonApiCommentEntityUuidConverter extends EntityUuidConverter {

  /**
   * The param conversion definition type.
   */
  const PARAM_TYPE_NAME = 'jsonapi_comment_entity_by_uuid';

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $converted = parent::convert($value, $definition, $name, $defaults);
    // Ensure that the commented entities comments are not hidden or closed.
    if ($name === 'commented_entity' && $converted) {
      $comment_field_name = $defaults[Routes::COMMENT_FIELD_NAME_KEY];
      $comment_status = (int) $converted->{$comment_field_name}->status;
      $comments_hidden = $comment_status === CommentItemInterface::HIDDEN;
      $comments_closed = $comment_status === CommentItemInterface::CLOSED;
      $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
      assert($route instanceof Route);
      if ($comments_hidden || ($comments_closed && in_array('POST', $route->getMethods()))) {
        $route_name = $defaults[RouteObjectInterface::ROUTE_NAME];
        $message = 'Comments are ' . ($comments_hidden ? 'hidden' : 'closed') . '.';
        throw new ParamNotConvertedException($message, 0, NULL, $route_name, [$name => $value]);
      }
    }
    // Ensure that the parent comment entity belongs to the commented entity.
    if ($name === 'parent_comment' && $converted) {
      $commented_entity = $defaults['commented_entity'];
      assert($commented_entity instanceof EntityInterface, 'Expect the first parameter to be converted first.');
      $type_matches = $converted->entity_type->value === $commented_entity->getEntityTypeId();
      $id_matches = (int) $converted->entity_id->target_id === (int) $commented_entity->id();
      if (!$type_matches || !$id_matches) {
        return NULL;
      }
    }
    return $converted;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && strpos($definition['type'], static::PARAM_TYPE_NAME) === 0;
  }

}
