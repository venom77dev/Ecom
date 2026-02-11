<?php

namespace App\Classes;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    private $status;
    private $message;
    private $httpCode;
    private $data;
    private $otherInfo;

    public function __construct($status, $message, $http_code, $data = null, $otherInfo = null)
    {
        $this->status = $status;
        $this->message = $message;
        $this->httpCode = $http_code;
        $this->data = $data;
        $this->otherInfo = $otherInfo;
    }

    public function get(): JsonResponse
    {
        return response()->json(
            [
                'status' => $this->status,
                'message' => $this->message,
                'data' => $this->data,
                'otherInfo' => $this->otherInfo,
                'httpCode' => $this->httpCode,
            ]
        )->setStatusCode($this->httpCode);
    }
}
