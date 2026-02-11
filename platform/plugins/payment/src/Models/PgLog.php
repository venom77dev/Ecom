<?php
namespace Botble\Payment\Models;

use Botble\ACL\Models\User;
use Botble\Base\Facades\Html;
use Botble\Base\Models\BaseModel;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class PgLog extends BaseModel
{

    protected $table = 'tbl_pg_logs';

    protected $fillable = [
        'amount',
        'status',
        'extTransactionId',
        'qrString',
        'remark',
        'customerName',
        'customerVpa',
        'respMessage',
        'lable',
        'custRefNo',
    ];

}
