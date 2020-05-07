<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\ColumnItems\CustomItem;
use Exceedone\Exment\Validator\SelectRule;
use Encore\Admin\Form\Field;
use Encore\Admin\Grid\Filter;

class Select extends CustomItem
{
    use ImportValueTrait;
    
    // public function value()
    // {
    //     return $this->getResultForSelect(false);
    // }

    public function text()
    {
        return $this->getResultForSelect(true);
    }

    protected function getResultForSelect($label)
    {
        $select_options = $this->custom_column->createSelectOptions();
        // if $value is array
        $multiple = true;
        if (!is_array($this->value()) && preg_match('/\[.+\]/i', $this->value())) {
            $this->value = json_decode($this->value());
        }
        if (!is_array($this->value())) {
            $val = [$this->value()];
            $multiple = false;
        } else {
            $val = $this->value();
        }
        // switch column_type and get return value
        $returns = $this->getReturnsValue($select_options, $val, $label);
        
        if ($multiple) {
            return $label ? implode(exmtrans('common.separate_word'), $returns) : $returns;
        } else {
            return $returns[0];
        }
    }

    protected function getReturnsValue($select_options, $val, $label)
    {
        return $val;
    }
    
    protected function getAdminFieldClass()
    {
        if (boolval(array_get($this->custom_column, 'options.multiple_enabled'))) {
            return Field\MultipleSelect::class;
        } else {
            return Field\Select::class;
        }
    }
    
    protected function getAdminFilterClass()
    {
        if (boolval($this->custom_column->getOption('multiple_enabled'))) {
            return Filter\Where::class;
        }
        return Filter\Equal::class;
    }

    protected function setAdminOptions(&$field, $form_column_options)
    {
        $field->options($this->custom_column->createSelectOptions());
    }
    
    protected function setValidates(&$validates, $form_column_options)
    {
        $select_options = $this->custom_column->createSelectOptions();
        $validates[] = new SelectRule(array_keys($select_options));
    }

    protected function setAdminFilterOptions(&$filter)
    {
        $options = $this->custom_column->createSelectOptions();
        $filter->select($options);
    }
    
    protected function getImportValueOption()
    {
        return $this->custom_column->createSelectOptions();
    }
    
    public function getAdminFilterWhereQuery($query, $input)
    {
        $index = \DB::getQueryGrammar()->wrap($this->index());
        // index is wraped
        $query->whereRaw("FIND_IN_SET(?, REPLACE(REPLACE(REPLACE(REPLACE($index, '[', ''), ' ', ''), ']', ''), '\\\"', ''))", $input);
    }
}
