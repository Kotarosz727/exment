<?php

namespace Exceedone\Exment\Model;

use Illuminate\Support\Facades\Config;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Enums\AuthorityType;
use Exceedone\Exment\Enums\SystemTableName;
use Storage;
use DB;

class System extends ModelBase
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    
    protected $casts = ['authority' => 'json'];
    protected $primaryKey = 'system_name';

    public static function __callStatic($name, $argments)
    {
        // Get system setting value
        if (static::hasFunction($name)) {
            $setting = Define::SYSTEM_SETTING_NAME_VALUE[$name];
            return static::getset_system_value($name, $setting, $argments);
        }

        return parent::__callStatic($name, $argments);
    }

    /**
     * whether System_function keyname
     */
    public static function hasFunction($name)
    {
        return array_key_exists($name, Define::SYSTEM_SETTING_NAME_VALUE);
    }

    public static function get_system_values()
    {
        $array = [];
        foreach (Define::SYSTEM_SETTING_NAME_VALUE as $k => $v) {
            $array[$k] = static::{$k}();
        }

        // add system authority --------------------------------------------------
        // get system authority value
        $system_authority = DB::table(SystemTableName::SYSTEM_AUTHORITABLE)->where('morph_type', AuthorityType::SYSTEM)->get();
        // get Authority list for system.
        $authorities = Authority::where('authority_type', AuthorityType::SYSTEM)->get(['id', 'suuid', 'authority_name']);
        foreach ($authorities as $authority) {
            foreach ([SystemTableName::USER, SystemTableName::ORGANIZATION] as $related_type) {
                // filter related_type and authority_id
                $filter = $system_authority->filter(function ($value, $key) use ($authority, $related_type) {
                    return $value->related_type  == $related_type && $value->authority_id  == $authority->id;
                });
                if (!isset($filter)) {
                    continue;
                }

                $array[getAuthorityName($authority, $related_type)] = $filter->pluck('related_id')->toArray();
            }
        }
        return $array;
    }

    protected static function getset_system_value($name, $setting, $argments)
    {
        if (count($argments) > 0) {
            static::set_system_value($name, $setting, $argments[0]);
            return null;
        } else {
            return static::get_system_value($name, $setting);
        }
    }

    protected static function get_system_value($name, $setting)
    {
        $config_key = static::getConfigKey($name);
        ;
        if (!is_null(getRequestSession($config_key))) {
            return getRequestSession($config_key);
        }
        $system = System::find($name);
        $value = null;
        
        // if has data, return setting value or default value
        if (isset($system)) {
            $value = $system->system_value;
        }
        // if don't has data, but has default value in Define, return default value
        elseif (!is_null(array_get($setting, 'default'))) {
            $value = array_get($setting, 'default');
        }
        // if don't has data, but has config value in Define, return value from config
        elseif (!is_null(array_get($setting, 'config'))) {
            $value = Config::get(array_get($setting, 'config'));
        }

        if (array_get($setting, 'type') == 'boolean') {
            $value = boolval($value);
        } elseif (array_get($setting, 'type') == 'json') {
            $value = is_null($value) ? [] : json_decode($value);
        } elseif (array_get($setting, 'type') == 'file') {
            $value = is_null($value) ? null : Storage::disk(config('admin.upload.disk'))->url($value);
        }
        setRequestSession($config_key, $value);
        return $value;
    }

    protected static function set_system_value($name, $setting, $value)
    {
        $system = System::find($name);
        if (!isset($system)) {
            $system = new System;
            $system->system_name = $name;
        }

        // change set value by type
        if (array_get($setting, 'type') == 'json') {
            $system->system_value = is_null($value) ? null : json_encode($value);
        } elseif (array_get($setting, 'type') == 'file') {
            $old_value = $system->system_value;
            if (is_null($value)) {
                //TODO: how to check whether file is deleting by user.
                //$system->system_value = null;
            } else {
                $move = array_get($setting, 'move');
                $exmentfile = ExmentFile::storeAs($value, $move, $value->getClientOriginalName());
                $system->system_value = $exmentfile->path;
            }

            // remove old file
            if (!is_null($old_value)) {
                Storage::delete($old_value);
            }
        } else {
            $system->system_value = $value;
        }
        $system->saveOrFail();
        
        // update config
        $config_key = static::getConfigKey($name);
        setRequestSession($config_key, $system->system_value);
    }

    protected static function getConfigKey($name)
    {
        return "setting.$name";
    }
}
