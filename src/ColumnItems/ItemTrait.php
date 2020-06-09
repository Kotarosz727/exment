<?php

namespace Exceedone\Exment\ColumnItems;

use Encore\Admin\Form\Field;
use Encore\Admin\Grid\Filter;
use Exceedone\Exment\Form\Field as ExmentField;
use Exceedone\Exment\Grid\Filter as ExmentFilter;
use Encore\Admin\Grid\Filter\Where;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomColumnMulti;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\Traits\ColumnOptionQueryTrait;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\RelationType;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\ValueType;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Enums\FilterSearchType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\ColumnItems\CustomColumns\AutoNumber;
use Exceedone\Exment\Validator;

trait ItemTrait
{
    /**
     * this column's target custom_table
     */
    protected $value;

    protected $label;

    protected $id;

    protected $options;

    /**
     * get value
     */
    public function value()
    {
        return $this->getValue($value);
    }

    /**
     * get pure value. (In database value)
     * *Don't override this function
     */
    public function pureValue()
    {
        return $this->value;
    }

    /**
     * get text
     */
    public function text()
    {
        return arrayToString(toCollection($this->value)->map(function($value){
            return $this->getText($value);
        }));
    }
    
    /**
     * get html(for display)
     */
    public function html()
    {
        return arrayToString(toCollection($this->value)->map(function($value){
            return $this->getHtml($value);
        }));
    }
    
    /**
     * get value
     */
    public function getValue($value)
    {
        return $value;
    }

    /**
     * get or set option for convert
     */
    public function options($options = null)
    {
        if (!func_num_args()) {
            return $this->options ?? [];
        }

        $this->options = array_merge(
            $this->options ?? [],
            $options
        );

        return $this;
    }

    /**
     * get label. (user theader, form label etc...)
     */
    public function label($label = null)
    {
        if (!func_num_args()) {
            return $this->label;
        }
        if (isset($label)) {
            $this->label = $label;
        }
        return $this;
    }

    /**
     * get value's id.
     */
    public function id($id = null)
    {
        if (!func_num_args()) {
            return $this->id;
        }
        $this->id = $id;
        return $this;
    }

    public function prepare()
    {
    }
    
    /**
     * whether column is enabled index.
     *
     */
    public function indexEnabled()
    {
        return true;
    }

    /**
     * get cast name for sort
     */
    public function getCastName()
    {
        return null;
    }

    /**
     * get sort column name as SQL
     */
    public function getSortColumn()
    {
        $cast = $this->getCastName();
        $index = $this->index();
        
        if (!isset($cast)) {
            return $index;
        }

        return "CAST($index AS $cast)";
    }

    /**
     * get style string from key-values
     *
     * @param [type] $array
     * @return void
     */
    public function getStyleString($array)
    {
        $array['word-wrap'] = 'break-word';
        $array['white-space'] = 'normal';
        return implode('; ', collect($array)->map(function ($value, $key) {
            return "$key:$value";
        })->toArray());
    }

    /**
     * whether column is date
     *
     */
    public function isDate()
    {
        return false;
    }

    /**
     * whether column is Numeric
     *
     */
    public function isNumeric()
    {
        return false;
    }
    
    /**
     * Get Search queries for free text search
     *
     * @param [type] $mark
     * @param [type] $value
     * @param [type] $takeCount
     * @return void
     */
    public function getSearchQueries($mark, $value, $takeCount, $q)
    {
        list($mark, $pureValue) = $this->getQueryMarkAndValue($mark, $value, $q);

        $query = $this->custom_table->getValueModel()->query();
        
        if (is_list($pureValue)) {
            $query->whereIn($this->custom_column->getIndexColumnName(), toArray($pureValue))->select('id');
        } else {
            $query->where($this->custom_column->getIndexColumnName(), $mark, $pureValue)->select('id');
        }
        
        $query->take($takeCount);

        return [$query];
    }

    /**
     * Set Search orWhere for free text search
     *
     * @param [type] $mark
     * @param [type] $value
     * @param [type] $takeCount
     * @return void
     */
    public function setSearchOrWhere(&$query, $mark, $value, $q)
    {
        list($mark, $pureValue) = $this->getQueryMarkAndValue($mark, $value, $q);

        if (is_list($pureValue)) {
            $query->orWhereIn($this->custom_column->getIndexColumnName(), toArray($pureValue));
        } else {
            $query->orWhere($this->custom_column->getIndexColumnName(), $mark, $pureValue);
        }

        return $this;
    }

    /**
     * Get pure value. If you want to change the search value, change it with this function.
     *
     * @param [type] $value
     * @return ?string string:matched, null:not matched
     */
    public function getPureValue($label)
    {
        return null;
    }

    protected function getQueryMarkAndValue($mark, $value, $q)
    {
        if (is_nullorempty($q)) {
            return [$mark, $value];
        }

        $pureValue = $this->getPureValue($q);
        if (is_null($pureValue)) {
            return [$mark, $value];
        }

        return ['=', $pureValue];
    }

    /**
     * getTargetValue. Use "view_pivot_column" option.
     *
     * @param mixed $custom_value
     * @return mixed single or collection $custom_value
     */
    protected function getTargetValueUsePivotColumn($custom_value){
        // if options has "view_pivot_column", get select_table's custom_value first
        if (isset($custom_value) && array_key_value_exists('view_pivot_column', $this->options)) {
            $view_pivot_column = $this->options['view_pivot_column'];

            // PARENT_ID: 1:n or n:n relation
            if ($view_pivot_column == SystemColumn::PARENT_ID) {
                $relation = CustomRelation::getRelationByParentChild($this->custom_table, array_get($this->options, 'view_pivot_table'));
                
                // if n:n relation
                if(isset($relation) && $relation->relation_type == RelationType::MANY_TO_MANY){
                    // Getting n:n parent values.
                    return $custom_value->{$relation->getRelationName()};
                }

                // other
                else{
                    $custom_value = $this->custom_table->getValueModel($custom_value->parent_id);
                }
            } else {
                $pivot_custom_column = CustomColumn::getEloquent($this->options['view_pivot_column']);
                $pivot_id =  array_get($custom_value, 'value.'.$pivot_custom_column->column_name);
                $custom_value = $this->custom_table->getValueModel($pivot_id);
            }

            return $custom_value;
        }
    }
}
