<?php
/**
 * Class GetHandler
 *
 * @package IAmJulianAcosta\JsonApi\Routing
 * @author  Julian Acosta <iam@julianacosta.me>
 */


namespace IAmJulianAcosta\JsonApi\Routing;

use Illuminate\Database\Eloquent\Builder;

abstract class GetHandler extends Handler {
  /**
   * Generates a find query from model name
   *
   * @return Builder|null
   */
  protected function generateSelectQuery() {
    $modelName = $this->fullModelName;
    return forward_static_call_array([$modelName, 'generateSelectQuery']);
  }

}
