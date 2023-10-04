<?php

namespace Api\Classes;

class ApiResponse
{
    public static function Generate($code, array $data): void
    {
        print_r(
            json_encode(array(
                'code' => $code,
                'data' => $data
            ))
        );
        die();
    }
}

?>