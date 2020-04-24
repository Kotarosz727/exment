<?php

namespace Exceedone\Exment\Auth;

use Exceedone\Exment\Enums\LoginType;
use Exceedone\Exment\Model\LoginSetting;

/**
 * Custom Login User.
 * For OAuth, Saml, Plugin login.
 * When get user info from provider, set this model.
 */
abstract class CustomLoginUserBase
{
    public $login_setting;
    public $login_id;
    public $mapping_user_column;

    public $provider_name;
    public $id;
    public $email;
    public $user_code;
    public $user_name;
    public $login_type;
    /**
     * Dummy password.
     *
     * @var string
     */
    public $dummy_password;
}
