<?php

namespace Exceedone\Exment\Database;

trait ExtendedBuilderTrait
{
    /**
     * Execute query "where" or "whereIn". If args is array, call whereIn
     *
     * @param  string|array|\Closure  $column
     * @param  mixed   $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereOrIn($column, $operator = null, $value = null, $boolean = 'and')
    {
        // if arg is array or list, execute whereIn
        $checkArray = (func_num_args() == 3 ? $value : $operator);
        if (is_list($checkArray)) {
            if (func_num_args() == 3 && $operator == '<>') {
                return $this->whereNotIn($column, toArray($checkArray));
            }
            return $this->whereIn($column, toArray($checkArray));
        }

        return $this->where($column, $operator, $value, $boolean);
    }
}
