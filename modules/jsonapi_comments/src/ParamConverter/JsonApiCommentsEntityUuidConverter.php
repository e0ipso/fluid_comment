<?php

namespace Drupal\jsonapi_comments\ParamConverter;

use Drupal\jsonapi\ParamConverter\EntityUuidConverter;
use Symfony\Component\Routing\Route;

/**
 * Class JsonApiCommentsEntityUuidConverter.
 *
 * @internal
 */
class JsonApiCommentsEntityUuidConverter extends EntityUuidConverter {

  /**
   * The param conversion definition type.
   */
  const PARAM_TYPE_NAME = 'jsonapi_comments_entity_by_uuid';

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    $applies = !empty($definition['type']) && strpos($definition['type'], static::PARAM_TYPE_NAME) === 0;
    return $applies || parent::applies($definition, $name, $route);
  }

}
