<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerUser extends Model
{
    protected $table = 'larasell_customer_user';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];
}
