<?php

namespace DefectiveReturnStock\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Processing marker used to make the Flow action idempotent.
 *
 * @property int $orderId
 * @property string $status
 * @property string $message
 * @property int $updatedAt
 */
class ProcessedReturn extends Model
{
    protected $primaryKeyFieldName = 'orderId';
    protected $primaryKeyFieldType = 'int';
    protected $autoIncrementPrimaryKey = false;

    public $orderId = 0;
    public $status = '';
    public $message = '';
    public $updatedAt = 0;

    public function getTableName(): string
    {
        return 'DefectiveReturnStock::ProcessedReturn';
    }
}
