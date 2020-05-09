<?php

namespace Exceedone\Exment\ConditionItems;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomViewFilter;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Model\WorkflowAuthority;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Form\Field\ChangeField;
use Exceedone\Exment\Validator\ChangeFieldRule;
use Carbon\Carbon;

abstract class ConditionItemBase
{
    protected $custom_table;
    protected $target;
    
    /**
     * Dynamic field element name
     *
     * @var string
     */
    protected $elementName;

    /**
     * Dynamic field class name
     *
     * @var string
     */
    protected $className;

    /**
     * filter kind (view, workflow, form)
     *
     * @var bool
     */
    protected $filterKind;

    /**
     * Dynamic field label
     *
     * @var string
     */
    protected $label;

    public function __construct(?CustomTable $custom_table, $target)
    {
        $this->custom_table = $custom_table;
        $this->target = $target;
    }

    public function setElement($elementName, $className, $label)
    {
        $this->elementName = $elementName;
        $this->className = $className;
        $this->label = $label;

        return $this;
    }
    
    public function filterKind($filterKind = null)
    {
        if (isset($filterKind)) {
            $this->filterKind = $filterKind;
        }

        return $this;
    }
    
    /**
     * get filter condition
     */
    public static function getItem(?CustomTable $custom_table, $target)
    {
        if (!isset($target)) {
            return null;
        }
        
        if (ConditionTypeDetail::isValidKey($target)) {
            $enum = ConditionTypeDetail::getEnum(strtolower($target));
            return $enum->getConditionItem($custom_table, $target);
        } else {
            // get column item
            $column_item = CustomViewFilter::getColumnItem($target)
                ->options([
                ]);
        
            if ($column_item instanceof \Exceedone\Exment\ColumnItems\CustomItem) {
                return new ColumnItem($custom_table, $target);
            } elseif ($column_item instanceof \Exceedone\Exment\ColumnItems\WorkflowItem) {
                return new WorkflowItem($custom_table, $target);
            } elseif ($column_item instanceof \Exceedone\Exment\ColumnItems\SystemItem) {
                return new SystemItem($custom_table, $target);
            }
        }
    }

    /**
     * get filter condition by authority
     *
     * @param CustomTable|null $custom_table
     * @param WorkflowAuthority|WorkflowValueAuthority $authority
     * @return \Exceedone\Exment\ConditionItems\ConditionItemBase
     */
    public static function getItemByAuthority(?CustomTable $custom_table, $authority)
    {
        $enum = ConditionTypeDetail::getEnum($authority->related_type);
        return $enum->getConditionItem($custom_table, null);
    }

    /**
     * get filter condition
     */
    public function getFilterCondition()
    {
        $options = $this->getFilterOption();
        
        return collect($options)->map(function ($array) {
            return ['id' => array_get($array, 'id'), 'text' => exmtrans('custom_view.filter_condition_options.'.array_get($array, 'name'))];
        });
    }
    
    /**
     * get filter value
     */
    public function getFilterValue($target_key, $target_name, $show_condition_key = true)
    {
        if (is_nullorempty($this->target) || is_nullorempty($target_key) || is_nullorempty($target_name)) {
            return [];
        }

        $field = new ChangeField($this->className, $this->label);
        $field->rules([new ChangeFieldRule($this->custom_table, $this->label, $this->target)]);
        $field->adminField(function () use ($target_key, $show_condition_key) {
            return $this->getChangeField($target_key, $show_condition_key);
        });
        $field->setElementName($this->elementName);

        $view = $field->render();
        return json_encode(['html' => $view->render(), 'script' => $field->getScript()]);
    }

    protected function getFilterOptionConditon()
    {
        return array_get($this->filterKind == FilterKind::VIEW ? FilterOption::FILTER_OPTIONS() : FilterOption::FILTER_CONDITION_OPTIONS(), FilterType::CONDITION);
    }

    /**
     * Get Condition Label
     *
     * @return void
     */
    public function getConditionLabel(Condition $condition)
    {
        $enum = ConditionTypeDetail::getEnum($condition->target_column_id);
        return $enum->transKey('condition.condition_type_options') ?: null;
    }

    /**
     * compare condition value and saved value
     *
     * @param [type] $condition
     * @param [type] $value
     * @return void
     */
    protected function compareValue($condition, $value)
    {
        if (is_nullorempty($value) || is_nullorempty($condition->condition_value)) {
            return false;
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $condition_value = $condition->condition_value;
        if (!is_array($condition_value)) {
            $condition_value = [$condition_value];
        }

        $compareOption = FilterOption::getCompareOptions($condition->condition_key);
        return collect($value)->filter(function ($v) {
            return isset($v);
        })->contains(function ($v) use ($condition_value, $compareOption) {
            return collect($condition_value)->contains(function ($condition_value) use ($v, $compareOption) {
                switch ($compareOption) {
                    case FilterOption::EQ:
                        return $v == $condition_value;
                    case FilterOption::NE:
                        return $v != $condition_value;
                    case FilterOption::NUMBER_GT:
                        return $v > $condition_value;
                    case FilterOption::NUMBER_GTE:
                        return $v >= $condition_value;
                    case FilterOption::NUMBER_LT:
                        return $v < $condition_value;
                    case FilterOption::NUMBER_LTE:
                        return $v <= $condition_value;
                    case FilterOption::DAY_ON:
                        $condition_dt = Carbon::parse($condition_value);
                        return Carbon::parse($v)->isSameDay($condition_dt);
                    case FilterOption::DAY_ON_OR_AFTER:
                        $condition_dt = Carbon::parse($condition_value);
                        return Carbon::parse($v)->gte($condition_dt);
                    case FilterOption::DAY_ON_OR_BEFORE:
                        $condition_dt = Carbon::parse($condition_value)->addDays(1);
                        return Carbon::parse($v)->lt($condition_dt);
                }
                return false;
            });
        });
    }
    
    /**
     * get condition value text.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function getConditionText(Condition $condition)
    {
        return $this->getText($condition->condition_key, $condition->condition_value);
    }
}
