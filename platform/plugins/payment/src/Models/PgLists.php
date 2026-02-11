<?php
namespace Botble\Payment\Models;

use Botble\ACL\Models\User;
use Botble\Base\Facades\Html;
use Botble\Base\Models\BaseModel;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class PgLists extends BaseModel
{

    protected $table = 'tbl_pg_list';

    protected $fillable = [
        'name',
        'slug',
        'label',
        'description',
        'pg_meta_id',
        'pg_name_id',
        'status',
    ];
}
