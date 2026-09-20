<?php

namespace App\Traits;

trait ApiResponseTrait
{
    public function successResponse($data, $message = 'Success', $status = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }
}