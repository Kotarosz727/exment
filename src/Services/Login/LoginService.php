<?php
namespace Exceedone\Exment\Services\Login;

use Exceedone\Exment\Exceptions\SsoLoginErrorException;
use Exceedone\Exment\Providers\LoginUserProvider;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\LoginSetting;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Enums\LoginType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\SsoLoginErrorType;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Form\Widgets\ModalForm;
use Illuminate\Http\Request;

/**
 * LoginService
 */
class LoginService
{
    public static function setToken()
    {
        // get custom login user
        $custom_login_user = System::requestSession(Define::SYSTEM_KEY_SESSION_CUSTOM_LOGIN_USER);
        if (is_nullorempty($custom_login_user) || $custom_login_user != LoginType::OAUTH) {
            return;
        }

        // set session access key
        session([Define::SYSTEM_KEY_SESSION_PROVIDER_TOKEN => [
            'access_token' => $custom_login_user->token,
            'refresh_token' => $custom_login_user->refreshToken,
            'provider' => $custom_login_user->provider_name,
            'expiresIn' =>  $custom_login_user->expiresIn,
        ]]);
    }

    /**
     * Get access and refresh token
     *
     * @return array access_token, refresh_token, provider
     */
    public static function getToken()
    {
        $session = session(Define::SYSTEM_KEY_SESSION_PROVIDER_TOKEN);
        return [
            'access_token' => array_get($session, 'access_token'),
            'refresh_token' => array_get($session, 'refresh_token'),
            'provider' => array_get($session, 'provider')
        ];
    }

    /**
     * Get access token
     *
     * @return string|null
     */
    public static function getAccessToken()
    {
        return static::getToken()['access_token'];
    }
    
    /**
     * Get refresh token
     *
     * @return string|null
     */
    public static function getRefreshToken()
    {
        return static::getToken()['refresh_token'];
    }

    /**
     * Get custom login validator for synced user.
     *
     * @param array $array
     * @return \Validator
     */
    public static function validateCustomLoginSync(CustomLoginUserBase $custom_login_user)
    {
        // get target user
        $exment_user = static::getExmentUser($custom_login_user, false);
        if ($exment_user === false) {
            $exment_user = null;
        }

        $data = $custom_login_user->mapping_values;
        $rules = CustomTable::getEloquent(SystemTableName::USER)->getValidateRules($data, $exment_user);

        $rules = static::removeInitRule($custom_login_user, $exment_user, $rules);

        return \Validator::make($data, $rules);
    }

    /**
     * Remove "unique" and "init_flg" rule
     *
     * @param CustomLoginUserBase $custom_login_user
     * @param CustomValue|null $exment_user
     * @param array $rules
     * @return array updated rules
     */
    protected static function removeInitRule(CustomLoginUserBase $custom_login_user, ?CustomValue $exment_user, array $rules)
    {
        // remove unique, if not update and create. Because only use key for login
        $login_setting = $custom_login_user->login_setting;

        // If has exment user and update user info, return rules(all validate)
        if (isset($exment_user) && boolval($login_setting->getOption('update_user_info'))) {
            return $rules;
        }
        // If not has exment user and sso_jit, return rules(all validate)
        elseif (!isset($exment_user) && boolval($login_setting->getOption('sso_jit'))) {
            return $rules;
        }

        $mapping_user_column = $login_setting->getOption('mapping_user_column');
        // remove "unique" and initflg class if not key
        $rules = collect($rules)->mapWithKeys(function ($rule, $key) use ($mapping_user_column) {
            // same mapping_user_column and r, return same rules
            if ($mapping_user_column == $key) {
                return [$key => $rule];
            }

            $rule = collect($rule)->filter(function ($r) {
                if ($r instanceof \Exceedone\Exment\Validator\InitOnlyRule) {
                    return false;
                }
                
                if (!is_string($r)) {
                    return true;
                }

                if (strpos($r, 'unique') === 0) {
                    return false;
                }
                return true;
            })->toArray();

            return [$key => $rule];
        })->toArray();

        return $rules;
    }
    

    /**
     * Get login test result.
     *
     * @param [type] $result
     * @param [type] $messages
     * @param [type] $custom_login_user
     * @return array
     */
    public static function getLoginResult($result, $messages, $adminMessages = null, ?CustomLoginUserBase $custom_login_user = null)
    {
        if (is_nullorempty($adminMessages)) {
            $adminMessages = $messages;
        }

        $message = static::convertErrorMessage($result, $messages, $custom_login_user);
        $adminMessage = static::convertErrorMessage($result, $adminMessages, $custom_login_user);

        return [$result, $message, $adminMessage, $custom_login_user];
    }

    protected static function convertErrorMessage($result, $messages, ?CustomLoginUserBase $custom_login_user = null)
    {
        $message = [];

        $message[] = $result === true ? exmtrans('common.message.success_execute') : exmtrans('common.message.error_execute');

        if (is_string($messages)) {
            $message = array_merge($message, (array)$messages);
        } elseif (is_array($messages)) {
            $message = array_merge($message, $messages);
        } elseif ($messages instanceof \Illuminate\Support\MessageBag) {
            $message = array_merge($message, collect($messages->messages())->map(function ($m, $key) use ($custom_login_user) {
                $inputValue = isset($custom_login_user) ? array_get($custom_login_user->mapping_values, $key) : null;
                return implode(" ", $m) . (isset($inputValue) ? " : $inputValue" : '');
            })->toArray());
        }

        if ($result === true && $custom_login_user) {
            $keys = [
                'user_code',
                'user_name',
                'email',
            ];
    
            foreach ($keys as $key) {
                $message[] = exmtrans("user.$key") . ' : ' . $custom_login_user->mapping_values[$key];
            }
        }

        return implode("\r\n", $message);
    }

    /**
     * Get test form for sso
     *
     * @param LoginSetting $login_setting
     * @return ModalForm
     */
    public static function getTestFormSso(LoginSetting $login_setting)
    {
        $form = new ModalForm();
        $form->action(route('exment.logintest_form', ['id' => $login_setting->id]));
        $form->disableReset();
        $form->disableSubmit();

        $form->description(exmtrans('login.message.login_test_description'));

        $form->url('login_test_redirect', exmtrans('login.login_test_redirect'))
        ->readonly()
        ->setElementClass(['copyScript'])
        ->default($login_setting->exment_callback_url_test)
        ->help(exmtrans('login.help.login_test_sso', ['login_type' => LoginType::getEnum($login_setting->login_type)->transKey('login.login_type_options')]));


        // get message from session
        $message = session()->pull(Define::SYSTEM_KEY_SESSION_SSO_TEST_MESSAGE);
        $form->textarea('resultarea', exmtrans('common.execute_result'))
            ->attribute(['readonly' => true])
            ->default($message)
            ->rows(4)
        ;

        $url = route('exment.logintest_sso', ['id' => $login_setting->id]);
        $form->html("<a href='{$url}' data-nopjax data-modalclose='false' class='btn btn-primary click_disabled'>" . trans('admin.login') . "</a>");

        $form->setWidth(10, 2);


        return $form;
    }
    
    public static function appendActivateSwalButtonSso($tools, LoginSetting $login_setting)
    {
        if (!$login_setting->active_flg) {
            $tools->append(new Tools\SwalInputButton([
                'url' => route('exment.login_activate', ['id' => $login_setting->id]),
                'label' => exmtrans('common.activate'),
                'icon' => 'fa-check-circle',
                'btn_class' => 'btn-success',
                'title' => exmtrans('common.activate'),
                'text' => exmtrans('login.help.activate'),
                'method' => 'post',
                'redirectUrl' => admin_urls("login_setting", $login_setting->id, "edit"),
            ]));
        } else {
            $tools->append(new Tools\SwalInputButton([
                'url' => route('exment.login_deactivate', ['id' => $login_setting->id]),
                'label' => exmtrans('common.deactivate'),
                'icon' => 'fa-check-circle',
                'btn_class' => 'btn-default',
                'title' => exmtrans('common.deactivate'),
                'text' => exmtrans('login.help.deactivate'),
                'method' => 'post',
                'redirectUrl' => admin_urls("login_setting", $login_setting->id, "edit"),
            ]));
        }
    }

    /**
     * execute login. return $login_user.
     *
     * @param Request $request
     * @param CustomLoginUserBase $custom_login_user
     * @param [type] $socialiteProvider
     * @return LoginUser|null
     */
    public static function executeLogin(Request $request, CustomLoginUserBase $custom_login_user, $socialiteProvider = null) : ?LoginUser
    {
        // if not accept domain, return error.
        if (!static::isAcceptFromDomain($custom_login_user)) {
            throw new SsoLoginErrorException(SsoLoginErrorType::NOT_ACCEPT_DOMAIN, exmtrans('login.not_accept_domain', [
                'domain' => $custom_login_user->domain(),
            ]));
        }

        // check exment user
        $exment_user = static::getExmentUser($custom_login_user);
        if ($exment_user === false) {
            // Check system setting jit
            $exment_user = static::createExmentUser($custom_login_user);
        }

        $login_user = static::getLoginUser($custom_login_user, $exment_user, $socialiteProvider);
        
        // Set custom_login_user to request session
        System::setRequestSession(Define::SYSTEM_KEY_SESSION_CUSTOM_LOGIN_USER, $custom_login_user);

        return $login_user;
    }

    /**
     * if not accept domain, return error.
     *
     * @param CustomLoginUserBase $custom_login_user
     * @return boolean
     */
    public static function isAcceptFromDomain(CustomLoginUserBase $custom_login_user)
    {
        //
        if (is_nullorempty($sso_accept_mail_domains = System::sso_accept_mail_domain())) {
            return true;
        }

        // check domain
        $email_domain = $custom_login_user->domain();
        $domain_result = false;
        foreach (explodeBreak($sso_accept_mail_domains) as $sso_accept_mail_domain) {
            if ($email_domain == $sso_accept_mail_domain) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * get exment user from users table
     *
     * @param CustomLoginUserBase $custom_login_user
     * @return CustomValue|null|false if false, not found user.
     */
    public static function getExmentUser(CustomLoginUserBase $custom_login_user, bool $isUpdate = true)
    {
        $exment_user = getModelName(SystemTableName::USER)
            ::where("value->{$custom_login_user->mapping_user_column}", $custom_login_user->login_id)
            ->first();
        if (!isset($exment_user)) {
            return false;
        }

        // update user info
        if ($isUpdate && boolval($custom_login_user->login_setting->getOption('update_user_info'))) {
            // update only init_only is false
            $update_user_columns = static::getUserColumns()->filter(function ($column) {
                return !boolval($column->getOption('init_only'));
            });

            $values = $update_user_columns->mapWithKeys(function ($column) use ($custom_login_user) {
                return [$column->column_name => array_get($custom_login_user->mapping_values, $column->column_name)];
            });

            $exment_user->setValue($values);
            $exment_user->save();
        }

        return $exment_user;
    }

    /**
     * create exment user from users table
     *
     * @param CustomLoginUserBase $custom_login_user
     * @return ?CustomValue user model
     */
    public static function createExmentUser(CustomLoginUserBase $custom_login_user) : ?CustomValue
    {
        if (!boolval($custom_login_user->login_setting->getOption('sso_jit'))) {
            throw new SsoLoginErrorException(SsoLoginErrorType::NOT_EXISTS_EXMENT_USER, exmtrans('login.noexists_user'));
        }

        $exment_user = null;
        \DB::transaction(function () use ($custom_login_user, &$exment_user) {
            $exment_user = CustomTable::getEloquent(SystemTableName::USER)->getValueModel();

            $update_user_columns = static::getUserColumns();
            $values = $update_user_columns->mapWithKeys(function ($column) use ($custom_login_user) {
                return [$column->column_name => array_get($custom_login_user->mapping_values, $column->column_name)];
            });

            $exment_user->setValue($values);
            $exment_user->save();
    
            // Set roles
            if (!is_nullorempty($jit_rolegroups = $custom_login_user->login_setting->getOption('jit_rolegroups'))) {
                $jit_rolegroups = collect($jit_rolegroups)->map(function ($sso_rolegroup) use ($exment_user) {
                    return [
                        'role_group_id' => $sso_rolegroup,
                        'role_group_user_org_type' => SystemTableName::USER,
                        'role_group_target_id' => $exment_user->id,
                    ];
                })->toArray();
                    
                \DB::table(SystemTableName::ROLE_GROUP_USER_ORGANIZATION)->insert($jit_rolegroups);
            }
        });

        return $exment_user;
    }

    
    /**
     * get login_user from login_users table
     *
     * @param CustomLoginUserBase $custom_login_user
     * @param [type] $exment_user
     * @param [type] $socialiteProvider
     * @return LoginUser
     */
    public static function getLoginUser(CustomLoginUserBase $custom_login_user, $exment_user, $socialiteProvider = null) : LoginUser
    {
        $hasLoginUser = false;
        // get login_user
        $login_user = LoginUserProvider::findByCredential(
            [
                'target_column' => $custom_login_user->mapping_user_column,
                'username' => $custom_login_user->login_id,
                'login_provider' => $custom_login_user->provider_name,
                'login_type' => $custom_login_user->login_type,
            ]
        );
        
        // if don't has, create loginuser or match email
        if (!$hasLoginUser) {
            $login_user = LoginUser::firstOrNew([
                'base_user_id' => $exment_user->getUserId(),
                'login_provider' => $custom_login_user->provider_name,
                'login_type' => $custom_login_user->login_type,
            ]);
            $login_user->base_user_id = $exment_user->getUserId();
            $login_user->login_provider = $custom_login_user->provider_name;
            $login_user->password = make_password(32);
        }

        // get avatar
        if (!$hasLoginUser || boolval($custom_login_user->login_setting->getOption('update_user_info'))) {
            $avatar  = static::getAvatar($custom_login_user, $socialiteProvider = null);
            if (isset($avatar)) {
                $login_user->avatar = $avatar;
            }
        }

        $login_user->save();
        return $login_user;
    }

    public static function getAvatar(CustomLoginUserBase $custom_login_user, $socialiteProvider = null)
    {
        try {
            // if socialiteProvider implements ProviderAvatar, call getAvatar
            if (isset($socialiteProvider) && is_subclass_of($socialiteProvider, \Exceedone\Exment\Auth\ProviderAvatar::class)) {
                $stream = $socialiteProvider->getAvatar($custom_login_user->token);
            }
            // if user obj has avatar, download avatar.
            elseif (isset($custom_login_user->avatar)) {
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', $custom_login_user->avatar, [
                    'http_errors' => false,
                ]);
                $stream = $response->getBody()->getContents();
            }
            // file upload.
            if (isset($stream) && isset($custom_login_user->id)) {
                $file = ExmentFile::put(path_join("avatar", $custom_login_user->id), $stream, true);
                return $file->path;
            }
        } finally {
        }
        return null;
    }

    protected static function getUserColumns()
    {
        return CustomTable::getEloquent(SystemTableName::USER)->custom_columns_cache;
    }
    
    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected static function guard()
    {
        return \Auth::guard('admin');
    }
}
