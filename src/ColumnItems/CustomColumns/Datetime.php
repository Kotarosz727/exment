<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Encore\Admin\Form\Field;
use Exceedone\Exment\Enums\DatabaseDataType;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Form\Field as ExmentField;

class Datetime extends Date
{
    /**
     * Set column type
     *
     * @var string
     */
    protected static $column_type = 'datetime';

    protected $format = 'Y-m-d H:i:s';

    protected function getDisplayFormat()
    {
        if (FilterKind::useDate(array_get($this->options, 'filterKind'))) {
            return config('admin.date_format');
        } else {
            return config('admin.datetime_format');
        }
    }

    protected function getAdminFieldClass()
    {
        if ($this->displayDate()) {
            return ExmentField\Display::class;
        }
        if (FilterKind::useDate(array_get($this->options, 'filterKind'))) {
            return Field\Date::class;
        }
        return Field\Datetime::class;
    }

    /**
     * get cast name for sort
     */
    public function getCastName()
    {
        $grammar = \DB::getQueryGrammar();
        return $grammar->getCastString(DatabaseDataType::TYPE_DATETIME, true);
    }
}
