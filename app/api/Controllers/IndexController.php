<?php
namespace Api\Controllers;

class IndexController extends BaseController
{

    public function index()
    {
        if (PHP_SAPI == 'cli') {
            return;
        }
    }
}
