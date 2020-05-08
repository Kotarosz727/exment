<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\ColumnItems\CustomItem;
use Exceedone\Exment\Form\Field;
use Encore\Admin\Grid\Filter;

class Boolean extends CustomItem
{
    use ImportValueTrait;

    /**
     * Set column type
     *
     * @var string
     */
    protected $column_type = 'boolean';

    /**
     * laravel-admin set required. if false, always not-set required
     */
    protected $required = false;
    
    public function text()
    {
        if (array_get($this->custom_column, 'options.true_value') == $this->value()) {
            return array_get($this->custom_column, 'options.true_label');
        } elseif (array_get($this->custom_column, 'options.false_value') == $this->value()) {
            return array_get($this->custom_column, 'options.false_label');
        }
        return null;
    }

    public function saving()
    {
        if (is_null($this->value)) {
            return array_get($this->custom_column, 'options.false_value');
        }
    }

    protected function getAdminFieldClass()
    {
        return Field\SwitchField::class;
    }
    
    protected function getAdminFilterClass()
    {
        return Filter\Equal::class;
    }

    protected function setAdminOptions(&$field, $form_column_options)
    {
        $options = $this->custom_column->options;
        
        // set options
        $states = [
            'on'  => ['value' => array_get($options, 'true_value'), 'text' => array_get($options, 'true_label')],
            'off' => ['value' => array_get($options, 'false_value'), 'text' => array_get($options, 'false_label')],
        ];
        $field->states($states);
    }
    
    protected function setAdminFilterOptions(&$filter)
    {
        $column = $this->custom_column;
        $filter->radio([
            ''   => 'All',
            array_get($column, 'options.false_value')    => array_get($column, 'options.false_label'),
            array_get($column, 'options.true_value')    => array_get($column, 'options.true_label'),
        ]);
    }
    
    protected function getImportValueOption()
    {
        $column = $this->custom_column;
        return [
            array_get($column, 'options.false_value')    => array_get($column, 'options.false_label'),
            array_get($column, 'options.true_value')    => array_get($column, 'options.true_label')
        ];
    }

    /**
     * Get pure value. If you want to change the search value, change it with this function.
     *
     * @param [type] $value
     * @return ?string string:matched, null:not matched
     */
    public function getPureValue($label)
    {
        $option = $this->getImportValueOption();

        foreach ($option as $value => $l) {
            if (strtolower($label) == strtolower($l)) {
                return $value;
            }
        }
        return null;
    }
}
