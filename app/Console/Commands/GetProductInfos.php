<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OzonService;

class GetProductInfos extends Command
{
    protected $name        = 'getProductInfos';
    protected $description = 'getProductInfos worker';

    public function handle()
    {
        $ozonService = new OzonService();
        $ozonService->getProductInfos();
    }
}