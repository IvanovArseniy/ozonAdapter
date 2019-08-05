<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\GearmanService;

class QueueController extends BaseController
{
    public function addForRetry()
    {
        $success = GearmanService::addForRetry([]);
        return response()->json(['result' => $success]);
    }
}