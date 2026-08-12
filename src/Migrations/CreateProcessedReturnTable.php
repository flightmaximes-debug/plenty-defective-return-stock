<?php

namespace DefectiveReturnStock\Migrations;

use DefectiveReturnStock\Models\ProcessedReturn;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateProcessedReturnTable
{
    public function run(Migrate $migrate): void
    {
        $migrate->createTable(ProcessedReturn::class);
    }
}
