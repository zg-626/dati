<?php

namespace app\api\behavior;

use think\Response;

class CORS
{

public function appInit(&$params)
{
    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: *");
    header('Access-Control-Allow-Methods: POST,GET,OPTIONS');
    
    if (request()->isOptions()) {
        exit();
    }
}

}