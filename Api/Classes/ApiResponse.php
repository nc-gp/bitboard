<?php

namespace Api\Classes;

class ApiResponse
{
    public static function Generate($code, array $data): void
    {
        http_response_code($code);

        echo json_encode(array(
            'code' => $code,
            'data' => $data
        ));
        die();
    }
}

?>