<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Controllers;

use Kennofizet\PackagesCore\Core\Model\BaseModelActions;
use Kennofizet\PackagesCore\Traits\GlobalDataTrait;
use Kennofizet\ReleaseSupport\Core\Model\BaseModelResponse;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use GlobalDataTrait, BaseModelActions;

    public function apiResponseWithContext(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(BaseModelResponse::success('Success', $data), $status);
    }
}
