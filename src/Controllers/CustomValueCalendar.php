<?php

namespace Exceedone\Exment\Controllers;

use Exceedone\Exment\Form\Tools;

trait CustomValueCalendar
{
    protected function gridCalendar()
    {
        $table_name = $this->custom_table->table_name;
        $model = $this->custom_table->getValueModel()->query();
        $this->custom_view->filterModel($model);

        $tools = [];
        if ($this->custom_table->enableTableMenuButton()) {
            $tools[] = new Tools\CustomTableMenuButton('data', $this->custom_table);
        }
        if ($this->custom_table->enableViewMenuButton()) {
            $tools[] = new Tools\CustomViewMenuButton($this->custom_table, $this->custom_view);
        }

        return view('exment::widgets.calendar', [
            'view_id' => $this->custom_view->suuid,
            'data_url' => admin_url('webapi/data', [$this->custom_table->table_name, 'calendar']),
            'createUrl' => admin_url("data/$table_name/create"),
            'new' => trans('admin.new'),
            'tools' => $tools,
            'locale' => \App::getLocale(),
        ]);
    }
}
