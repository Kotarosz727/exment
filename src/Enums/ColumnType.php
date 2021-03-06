<?php

namespace Exceedone\Exment\Enums;

class ColumnType extends EnumBase
{
    const TEXT = 'text';
    const TEXTAREA = 'textarea';
    const EDITOR = 'editor';
    const URL = 'url';
    const EMAIL = 'email';
    const INTEGER = 'integer';
    const DECIMAL = 'decimal';
    const CURRENCY = 'currency';
    const DATE = 'date';
    const TIME = 'time';
    const DATETIME = 'datetime';
    const SELECT = 'select';
    const SELECT_VALTEXT = 'select_valtext';
    const SELECT_TABLE = 'select_table';
    const YESNO = 'yesno';
    const BOOLEAN = 'boolean';
    const AUTO_NUMBER = 'auto_number';
    const IMAGE = 'image';
    const FILE = 'file';
    const USER = 'user';
    const ORGANIZATION = 'organization';

    public static function COLUMN_TYPE_CALC()
    {
        return [
            ColumnType::INTEGER,
            ColumnType::DECIMAL,
            ColumnType::CURRENCY,
        ];
    }

    public static function COLUMN_TYPE_DATETIME()
    {
        return [
            ColumnType::DATE,
            ColumnType::TIME,
            ColumnType::DATETIME,
        ];
    }

    public static function COLUMN_TYPE_DATE()
    {
        return [
            ColumnType::DATE,
            ColumnType::DATETIME,
        ];
    }

    public static function COLUMN_TYPE_ATTACHMENT()
    {
        return [
            ColumnType::IMAGE,
            ColumnType::FILE,
        ];
    }

    public static function COLUMN_TYPE_URL()
    {
        return [
            ColumnType::SELECT_TABLE,
            ColumnType::USER,
            ColumnType::ORGANIZATION,
            ColumnType::URL,
        ];
    }

    public static function COLUMN_TYPE_USER_ORGANIZATION()
    {
        return [
            ColumnType::USER,
            ColumnType::ORGANIZATION,
        ];
    }

    public static function COLUMN_TYPE_SELECT_TABLE()
    {
        return [
            ColumnType::SELECT_TABLE,
            ColumnType::USER,
            ColumnType::ORGANIZATION,
        ];
    }

    public static function COLUMN_TYPE_SELECT_FORM()
    {
        return [
            ColumnType::SELECT,
            ColumnType::SELECT_VALTEXT,
            ColumnType::SELECT_TABLE,
            ColumnType::YESNO,
            ColumnType::BOOLEAN,
        ];
    }

    public static function COLUMN_TYPE_MULTIPLE_ENABLED()
    {
        return [
            ColumnType::SELECT,
            ColumnType::SELECT_VALTEXT,
            ColumnType::SELECT_TABLE,
            ColumnType::USER,
            ColumnType::ORGANIZATION,
        ];
    }

    public static function COLUMN_TYPE_SHOW_NOT_ESCAPE()
    {
        return [
            ColumnType::URL,
            ColumnType::TEXTAREA,
            ColumnType::SELECT_TABLE,
            ColumnType::EDITOR,
            ColumnType::USER,
            ColumnType::ORGANIZATION,
        ];
    }

    public static function COLUMN_TYPE_IMPORT_REPLACE()
    {
        return [
            ColumnType::SELECT_TABLE,
            ColumnType::USER,
            ColumnType::ORGANIZATION,
        ];
    }

    public static function COLUMN_TYPE_OPERATION_ENABLE_SYSTEM()
    {
        return [
            ColumnType::DATE,
            ColumnType::TIME,
            ColumnType::DATETIME,
            ColumnType::USER,
        ];
    }

    public static function isCalc($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_CALC());
    }

    public static function isDate($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_DATE());
    }

    public static function isDateTime($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_DATETIME());
    }
    
    public static function isUrl($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_URL());
    }
    
    public static function isAttachment($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_ATTACHMENT());
    }
    
    public static function isUserOrganization($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_USER_ORGANIZATION());
    }
    public static function isSelectTable($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_SELECT_TABLE());
    }
    public static function isMultipleEnabled($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_MULTIPLE_ENABLED());
    }
    public static function isNotEscape($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_SHOW_NOT_ESCAPE());
    }
    public static function isSelectForm($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_SELECT_FORM());
    }
    public static function isOperationEnableSystem($column_type)
    {
        return in_array($column_type, static::COLUMN_TYPE_OPERATION_ENABLE_SYSTEM());
    }

    /**
     * get text is date, or datetime
     * @return ColumnType
     */
    public static function getDateType($text)
    {
        if (is_null($text)) {
            return null;
        }
        
        if (preg_match('/\d{4}-\d{2}-\d{2}$/', $text)) {
            return static::DATE;
        } elseif (preg_match('/\d{4}-\d{2}-\d{2}\h\d{2}:\d{2}:\d{2}$/', $text)) {
            return static::DATETIME;
        }
        return null;
    }
}
