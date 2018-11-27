<?php

namespace Exceedone\Exment\Model;

use Exceedone\Exment\Auth\HasPermissions;

use Encore\Admin\Traits\AdminBuilder;
use Exceedone\Exment\Enums\SystemTableName;

class LoginUser extends ModelBase implements \Illuminate\Contracts\Auth\Authenticatable, \Illuminate\Contracts\Auth\CanResetPassword
{
    use AdminBuilder;
    use HasPermissions;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    
    //protected $guarded = ['id', 'base_user_id'];
    protected $guarded = ['id'];

    protected $hidden = ['password'];

    /**
     * taale "user"
     */
    public function base_user()
    {
        return $this->belongsTo(getModelName(SystemTableName::USER), 'base_user_id');
    }

    public function getUserNameAttribute()
    {
        return $this->base_user->value['user_name'] ?? null;
    }
    public function getUserCodeAttribute()
    {
        return $this->base_user->value['user_code'] ?? null;
    }
    public function getEmailAttribute()
    {
        return $this->base_user->value['email'] ?? null;
    }

    public function isLoginProvider(){
        return !is_nullorempty($this->login_provider);
    }
}
