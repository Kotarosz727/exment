<?php

namespace Exceedone\Exment\Model;

use Illuminate\Database\Eloquent\Builder;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Linker;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ValueType;
use Exceedone\Exment\Enums\ViewType;
use Exceedone\Exment\Enums\ConditionType;
use Exceedone\Exment\Enums\ViewColumnSort;
use Exceedone\Exment\Enums\ViewKindType;
use Exceedone\Exment\Enums\UserSetting;
use Exceedone\Exment\Enums\SummaryCondition;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\DataItems\Grid as GridItem;

class CustomView extends ModelBase implements Interfaces\TemplateImporterInterface
{
    use Traits\UseRequestSessionTrait;
    use Traits\AutoSUuidTrait;
    use Traits\DefaultFlgTrait;
    use Traits\TemplateTrait;
    use Traits\DatabaseJsonTrait;

    //protected $appends = ['view_calendar_target', 'pager_count'];
    protected $appends = ['pager_count', 'condition_join'];
    protected $guarded = ['id', 'suuid'];
    protected $casts = ['options' => 'json'];
    //protected $with = ['custom_table', 'custom_view_columns'];
    
    private $_grid_item;

    public static $templateItems = [
        'excepts' => ['custom_table', 'target_view_name', 'view_calendar_target', 'pager_count'],
        'uniqueKeys' => ['suuid'],
        'langs' => [
            'keys' => ['suuid'],
            'values' => ['view_view_name'],
        ],
        'uniqueKeyReplaces' => [
            [
                'replaceNames' => [
                    [
                        'replacingName' => 'custom_table_id',
                        'replacedName' => [
                            'table_name' => 'table_name',
                        ]
                    ]
                ],
                'uniqueKeyClassName' => CustomTable::class,
            ],
        ],
        'defaults' => [
            'view_type' => ViewType::SYSTEM,
            'view_kind_type' => ViewKindType::DEFAULT,
        ],
        'enums' => [
            'view_type' => ViewType::class,
            'view_kind_type' => ViewKindType::class,
        ],
        'children' =>[
            'custom_view_columns' => CustomViewColumn::class,
            'custom_view_filters' => CustomViewFilter::class,
            'custom_view_sorts' => CustomViewSort::class,
            'custom_view_summaries' => CustomViewSummary::class,
        ],
    ];


    //public function custom_table()
    public function getCustomTableAttribute()
    {
        return CustomTable::getEloquent($this->custom_table_id);
        //return $this->belongsTo(CustomTable::class, 'custom_table_id');
    }

    public function custom_view_columns()
    {
        return $this->hasMany(CustomViewColumn::class, 'custom_view_id');
    }

    public function custom_view_filters()
    {
        return $this->hasMany(CustomViewFilter::class, 'custom_view_id');
    }

    public function custom_view_sorts()
    {
        return $this->hasMany(CustomViewSort::class, 'custom_view_id');
    }

    public function custom_view_summaries()
    {
        return $this->hasMany(CustomViewSummary::class, 'custom_view_id');
    }

    /**
     * get Custom columns using cache
     */
    public function getCustomViewColumnsCacheAttribute()
    {
        return $this->hasManyCache(CustomViewColumn::class, 'custom_view_id');
    }

    /**
     * get Custom filters using cache
     */
    public function getCustomViewFiltersCacheAttribute()
    {
        return $this->hasManyCache(CustomViewFilter::class, 'custom_view_id');
    }

    /**
     * get Custom Sorts using cache
     */
    public function getCustomViewSortsCacheAttribute()
    {
        return $this->hasManyCache(CustomViewSort::class, 'custom_view_id');
    }

    /**
     * get Custom summaries using cache
     */
    public function getCustomViewSummariesCacheAttribute()
    {
        return $this->hasManyCache(CustomViewSummary::class, 'custom_view_id');
    }

    public function getTableNameAttribute()
    {
        return $this->custom_table->table_name;
    }

    public function getFilterIsOrAttribute()
    {
        return $this->condition_join == 'or';
    }

    public function getGridItemAttribute()
    {
        if (isset($this->_grid_item)) {
            return $this->_grid_item;
        }

        switch ($this->view_kind_type) {
            case ViewKindType::AGGREGATE:
                $this->_grid_item = GridItem\SummaryGrid::getItem($this->custom_table, $this);
                break;
            case ViewKindType::CALENDAR:
                $this->_grid_item = GridItem\CalendarGrid::getItem($this->custom_table, $this);
                break;
            default:
                $this->_grid_item = GridItem\DefaultGrid::getItem($this->custom_table, $this);
                break;
        }

        return $this->_grid_item;
    }

    public function getOption($key, $default = null)
    {
        return $this->getJson('options', $key, $default);
    }
    public function setOption($key, $val = null, $forgetIfNull = false)
    {
        return $this->setJson('options', $key, $val, $forgetIfNull);
    }

    public function deletingChildren()
    {
        $this->custom_view_columns()->delete();
        $this->custom_view_filters()->delete();
        $this->custom_view_sorts()->delete();
        $this->custom_view_summaries()->delete();
        // delete data_share_authoritables
        DataShareAuthoritable::deleteDataAuthoritable($this);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            $model->prepareJson('options');
        });

        static::creating(function ($model) {
            $model->setDefaultFlgInTable('setDefaultFlgFilter', 'setDefaultFlgSet');
        });
        static::updating(function ($model) {
            $model->setDefaultFlgInTable('setDefaultFlgFilter', 'setDefaultFlgSet');
        });

        // delete event
        static::deleting(function ($model) {
            // Delete items
            $model->deletingChildren();
        });
        
        static::created(function ($model) {
            if ($model->view_type == ViewType::USER) {
                // save Authoritable
                DataShareAuthoritable::setDataAuthoritable($model);
            }
        });

        // add global scope
        static::addGlobalScope('showableViews', function (Builder $builder) {
            return static::showableViews($builder);
        });
    }

    protected function setDefaultFlgFilter($query)
    {
        $query->where('view_type', $this->view_type);

        if ($this->view_type == ViewType::USER) {
            $query->where('created_user_id', \Exment::user()->getUserId());
        }
    }

    protected function setDefaultFlgSet()
    {
        // set if only this flg is system
        if ($this->view_type == ViewType::SYSTEM) {
            $this->default_flg = true;
        }
    }

    // custom function --------------------------------------------------
    
    /**
     * get eloquent using request settion.
     * now only support only id.
     */
    public static function getEloquent($id, $withs = [])
    {
        if (strlen($id) == 20) {
            return static::getEloquentDefault($id, $withs, 'suuid');
        }

        return static::getEloquentDefault($id, $withs, 'id');
    }

    /**
     * set laravel-admin grid using custom_view
     */
    public function setGrid($grid)
    {
        $custom_table = $this->custom_table;
        // get view columns
        $custom_view_columns = $this->custom_view_columns_cache;
        foreach ($custom_view_columns as $custom_view_column) {
            $item = $custom_view_column->column_item;
            if (!isset($item)) {
                continue;
            }

            $item = $item->label(array_get($custom_view_column, 'view_column_name'))
                ->options([
                    'grid_column' => true,
                    'view_pivot_column' => $custom_view_column->view_pivot_column_id ?? null,
                    'view_pivot_table' => $custom_view_column->view_pivot_table_id ?? null,
                ]);
            $grid->column($item->indexEnabled() ? $item->index() : $item->name(), $item->label())
                ->sort($item->sortable())
                ->cast($item->getCastName())
                ->style($item->gridStyle())
                ->setClasses($item->indexEnabled() ? 'column-' . $item->name() : '')
                ->display(function ($v) use ($item) {
                    if (is_null($this)) {
                        return '';
                    }
                    return $item->setCustomValue($this)->html();
                });
        }

        // set parpage
        if (is_null(request()->get('per_page')) && isset($this->pager_count) && is_numeric($this->pager_count) && $this->pager_count > 0) {
            $grid->paginate(intval($this->pager_count));
        }

        // set with
        $custom_table->setQueryWith($grid->model(), $this);
    }

    /**
     * Get data paginate. default or summary
     *
     * @return void
     */
    public function getDataPaginate($options = [])
    {
        $options = array_merge([
            'paginate' => true,
            'maxCount' => System::datalist_pager_count() ?? 5,
            'target_view' => $this,
            'query' => null,
            'grid' => null,
        ], $options);

        if ($this->view_kind_type == ViewKindType::AGGREGATE) {
            $query = $options['query'] ?? $this->custom_table->getValueModel()->query();
            return $this->getValueSummary($query, $this->custom_table, $options['grid'])->paginate($options['maxCount']);
        }

        // search all data using index --------------------------------------------------
        $paginate = $this->custom_table->searchValue(null, $options);
        return $paginate;
    }
    

    /**
     * set DataTable using custom_view
     * @return array $headers : header items, $bodies : body items.
     */
    public function convertDataTable($datalist, $options = [])
    {
        $options = array_merge(
            [
                'action_callback' => null,
                'appendLink' => true,
                'valueType' => ValueType::HTML,
            ],
            $options
        );
        $custom_table = $this->custom_table;
        // get custom view columns and custom view summaries
        $view_column_items = $this->getSummaryIndexAndViewColumns();
        
        // create headers and column_styles
        $headers = [];
        $columnStyles = [];
        $columnClasses = [];
        $columnItems = [];
        
        foreach ($view_column_items as $view_column_item) {
            $item = array_get($view_column_item, 'item');
            $headers[] = $item
                ->column_item
                ->label(array_get($item, 'view_column_name'))
                ->label();

            $columnStyles[] = $item->column_item->gridStyle();
            $columnClasses[] = 'column-' . esc_html($item->column_item->name()) . ($item->column_item->indexEnabled() ? ' column-' . $item->column_item->index() : '');
            $columnItems[] = $item->column_item;
        }
        if (boolval($options['appendLink']) && $this->view_kind_type != ViewKindType::AGGREGATE) {
            $headers[] = trans('admin.action');
        }
        
        // get table bodies
        $bodies = [];
        if (isset($datalist)) {
            foreach ($datalist as $data) {
                $body_items = [];
                foreach ($view_column_items as $view_column_item) {
                    $column = array_get($view_column_item, 'item');
                    $item = $column->column_item;
                    if ($this->view_kind_type == ViewKindType::AGGREGATE) {
                        $index = array_get($view_column_item, 'index');
                        $summary_condition = array_get($column, 'view_summary_condition');
                        $item->options([
                            'summary' => true,
                            'summary_index' => $index,
                            'summary_condition' => $summary_condition,
                            'group_condition' => array_get($column, 'view_group_condition'),
                            'disable_currency_symbol' => ($summary_condition == SummaryCondition::COUNT),
                        ]);
                    }

                    $item->options([
                        'view_pivot_column' => $column->view_pivot_column_id ?? null,
                        'view_pivot_table' => $column->view_pivot_table_id ?? null,
                        'grid_column' => true,
                    ]);

                    $valueType = ValueType::getEnum($options['valueType']);
                    $body_items[] = $valueType->getCustomValue($item, $data);
                }

                $link = '';
                if (isset($options['action_callback'])) {
                    $options['action_callback']($link, $custom_table, $data);
                }

                ///// add show and edit link
                if (boolval($options['appendLink']) && $this->view_kind_type != ViewKindType::AGGREGATE) {
                    // using role
                    $link .= (new Linker)
                        ->url(admin_urls('data', array_get($custom_table, 'table_name'), array_get($data, 'id')))
                        //->linkattributes(['style' => "margin:0 3px;"])
                        ->icon('fa-eye')
                        ->tooltip(trans('admin.show'))
                        ->render();
                    if ($data->enableEdit(true) === true) {
                        $link .= (new Linker)
                            ->url(admin_urls('data', array_get($custom_table, 'table_name'), array_get($data, 'id'), 'edit'))
                            ->icon('fa-edit')
                            ->tooltip(trans('admin.edit'))
                            ->render();
                    }
                    // add hidden item about data id
                    $link .= '<input type="hidden" data-id="'.array_get($data, 'id').'" />';
                    $body_items[] = $link;
                }

                // add items to body
                $bodies[] = $body_items;
            }
        }

        //return headers, bodies
        return [$headers, $bodies, $columnStyles, $columnClasses, $columnItems];
    }

    /**
     * get alldata view using table
     *
     * @param mixed $tableObj table_name, object or id eic
     * @return CustomView
     */
    public static function getAllData($tableObj)
    {
        $tableObj = CustomTable::getEloquent($tableObj);
        
        // get all data view
        $view = $tableObj->custom_views()->where('view_kind_type', ViewKindType::ALLDATA)->first();

        // if all data view is not exists, create view
        if (!isset($view)) {
            $view = static::createDefaultView($tableObj);
        }

        // if target form doesn't have columns, add columns for has_index_columns columns.
        if (is_null($view->custom_view_columns_cache) || count($view->custom_view_columns_cache) == 0) {
            // copy default view
            $fromview = $tableObj->custom_views()
                ->where('view_kind_type', ViewKindType::DEFAULT)
                ->where('default_flg', true)
                ->first();

            // get view id for after
            if (isset($fromview)) {
                $view->copyFromDefaultViewColumns($fromview);
            }
            // not fromview, create index columns
            else {
                $view->createDefaultViewColumns(true);
            }

            // re-get view (reload view_columns)
            $view = static::find($view->id);
        }

        return $view;
    }

    /**
     * get default view using table
     *
     * @param mixed $tableObj table_name, object or id eic
     * @param boolean $getSettingValue if true, getting from UserSetting table
     * @param boolean $is_dashboard call by dashboard
     * @return CustomView
     */
    public static function getDefault($tableObj, $getSettingValue = true, $is_dashboard = false)
    {
        $user = Admin::user();
        $tableObj = CustomTable::getEloquent($tableObj);

        // get request
        $request = request();

        // get view using query
        if (!is_null($request->input('view'))) {
            $suuid = $request->input('view');
            // if query has view id, set view.
            $view = static::findBySuuid($suuid);

            // set user_setting
            if (!is_null($user) && !$is_dashboard) {
                $user->setSettingValue(implode(".", [UserSetting::VIEW, $tableObj->table_name]), $suuid);
            }
        }
        // if url doesn't contain view query, get view user setting.
        if (!isset($view) && !is_null($user) && $getSettingValue) {
            // get suuid
            $suuid = $user->getSettingValue(implode(".", [UserSetting::VIEW, $tableObj->table_name]));
            $view = CustomView::findBySuuid($suuid);
        }
        // if url doesn't contain view query, get custom view. first
        if (!isset($view)) {
            $view = static::allRecordsCache(function ($record) use ($tableObj) {
                return array_get($record, 'custom_table_id') == $tableObj->id
                    && array_get($record, 'default_flg') == true
                    && array_get($record, 'view_type') == ViewType::SYSTEM
                    && array_get($record, 'view_kind_type') != ViewKindType::FILTER;
            })->first();
        }
        
        // if default view is not setting, show all data view
        if (!isset($view)) {
            // get all data view
            $alldata = static::allRecordsCache(function ($record) use ($tableObj) {
                return array_get($record, 'custom_table_id') == $tableObj->id
                    && array_get($record, 'view_kind_type') == ViewKindType::ALLDATA;
            })->first();
            // if all data view is not exists, create view and column
            if (!isset($alldata)) {
                $alldata = static::createDefaultView($tableObj);
                $alldata->createDefaultViewColumns();
            }
            $view = $alldata;
        }

        // if target form doesn't have columns, add columns for has_index_columns columns.
        if (is_null($view->custom_view_columns_cache) || count($view->custom_view_columns_cache) == 0) {
            // get view id for after
            $view->createDefaultViewColumns();

            // re-get view (reload view_columns)
            $view = static::find($view->id);
        }

        return $view;
    }

    // user data_authoritable. it's all role data. only filter morph_type
    public function data_authoritable_users()
    {
        return $this->morphToMany(getModelName(SystemTableName::USER), 'parent', 'data_share_authoritables', 'parent_id', 'authoritable_target_id')
            ->withPivot('authoritable_target_id', 'authoritable_user_org_type', 'authoritable_type')
            ->wherePivot('authoritable_user_org_type', SystemTableName::USER)
            ;
    }

    // user data_authoritable. it's all role data. only filter morph_type
    public function data_authoritable_organizations()
    {
        return $this->morphToMany(getModelName(SystemTableName::ORGANIZATION), 'parent', 'data_share_authoritables', 'parent_id', 'authoritable_target_id')
            ->withPivot('authoritable_target_id', 'authoritable_user_org_type', 'authoritable_type')
            ->wherePivot('authoritable_user_org_type', SystemTableName::ORGANIZATION)
            ;
    }
    
    protected static function showableViews($query)
    {
        $query = $query->where(function ($qry) {
            $qry->where('view_type', ViewType::SYSTEM);
        });

        $user = \Exment::user();
        if (!isset($user)) {
            return;
        }

        if (hasTable(getDBTableName(SystemTableName::USER, false))) {
            $query->orWhere(function ($qry) use ($user) {
                $qry->where('view_type', ViewType::USER)
                    ->where('created_user_id', $user->getUserId());
            })->orWhereHas('data_authoritable_users', function ($qry) use ($user) {
                $qry->where('authoritable_target_id', $user->getUserId());
            });
        }
        if (hasTable(getDBTableName(SystemTableName::ORGANIZATION, false))) {
            $query->orWhereHas('data_authoritable_organizations', function ($qry) use ($user) {
                $enum = JoinedOrgFilterType::getEnum(System::org_joined_type_custom_value(), JoinedOrgFilterType::ONLY_JOIN);
                $qry->whereIn('authoritable_target_id', $user->getOrganizationIds($enum));
            });
        }
    }

    public static function createDefaultView($tableObj)
    {
        $tableObj = CustomTable::getEloquent($tableObj);
        
        $view = new CustomView;
        $view->custom_table_id = $tableObj->id;
        $view->view_type = ViewType::SYSTEM;
        $view->view_kind_type = ViewKindType::ALLDATA;
        $view->view_view_name = exmtrans('custom_view.alldata_view_name');
        $view->saveOrFail();
        
        return $view;
    }

    /**
     * filter target model
     */
    public function filterModel($model, $options = [])
    {
        $options = array_merge([
            'sort' => true,
            'callback' => null,
        ], $options);

        // if simple eloquent, throw
        if ($model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \Exception;
        }

        // view filter setting --------------------------------------------------
        // has $custom_view, filter
        if ($options['callback'] instanceof \Closure) {
            call_user_func($options['callback'], $model);
        } else {
            $this->setValueFilters($model);
        }

        if (boolval($options['sort'])) {
            $this->setValueSort($model);
        }

        ///// We don't need filter using role here because filter auto using global scope.

        return $model;
    }


    /**
     * Create default columns
     *
     * @param boolean $appendIndexColumn if true, append custom column has index
     * @return void
     */
    public function createDefaultViewColumns($appendIndexColumn = false)
    {
        $view_columns = [];

        // append system column function
        $systemColumnFunc = function ($isHeader, &$view_columns) {
            $filter = ['default' => true, ($isHeader ? 'header' : 'footer') => true];
            // set default view_column
            foreach (SystemColumn::getOptions($filter) as $view_column_system) {
                $view_column = new CustomViewColumn;
                $view_column->custom_view_id = $this->id;
                $view_column->view_column_target = array_get($view_column_system, 'name');
                $view_column->order = array_get($view_column_system, 'order');
                $view_columns[] = $view_column;
            }
        };

        // append system header
        $systemColumnFunc(true, $view_columns);

        // if $appendIndexColumn is true, append index column
        if ($appendIndexColumn) {
            $custom_columns = $this->custom_table->getSearchEnabledColumns();
            $order = 20;
            foreach ($custom_columns as $custom_column) {
                $view_column = new CustomViewColumn;
                $view_column->custom_view_id = $this->id;
                $view_column->view_column_type = ConditionType::COLUMN;
                $view_column->view_column_table_id = $custom_column->custom_table_id;
                $view_column->view_column_target_id = array_get($custom_column, 'id');
                $view_column->order = $order++;
                $view_columns[] = $view_column;
            }
        }

        // append system footer
        $systemColumnFunc(false, $view_columns);

        $this->custom_view_columns()->saveMany($view_columns);
        return $view_columns;
    }

    /**
     * copy from default view columns
     *
     * @param [type] $fromView copied target view
     * @return void
     */
    public function copyFromDefaultViewColumns($fromView)
    {
        $view_columns = [];

        if (!isset($fromView)) {
            return [];
        }

        // set from view column
        foreach ($fromView->custom_view_columns_cache as $from_view_column) {
            $view_column = new CustomViewColumn;
            $view_column->custom_view_id = $this->id;
            $view_column->view_column_target = array_get($from_view_column, 'view_column_target');
            $view_column->order = array_get($from_view_column, 'order');
            $view_column->options = array_get($from_view_column, 'options');
            $view_columns[] = $view_column;
        }

        $this->custom_view_columns()->saveMany($view_columns);
        return $view_columns;
    }

    /**
     * set value filters
     */
    public function setValueFilters($model, $db_table_name = null)
    {
        if (!empty($this->custom_view_filters_cache)) {
            $model->where(function ($model) use ($db_table_name) {
                foreach ($this->custom_view_filters_cache as $filter) {
                    $filter->setValueFilter($model, $db_table_name, $this->filter_is_or);
                }
            });
        }
        return $model;
    }

    /**
     * set value sort
     */
    public function setValueSort($model)
    {
        // if request has "_sort", not executing
        if (request()->has('_sort')) {
            return $model;
        }
        foreach ($this->custom_view_sorts_cache as $custom_view_sort) {
            switch ($custom_view_sort->view_column_type) {
            case ConditionType::COLUMN:
                $custom_column = $custom_view_sort->custom_column;
                if (!isset($custom_column)) {
                    break;
                }
                $column_item = $custom_column->column_item;
                if (!isset($column_item)) {
                    break;
                }
                // $view_column_target is wraped
                $view_column_target = $column_item->getSortColumn();
                $sort_order = $custom_view_sort->sort == ViewColumnSort::ASC ? 'asc' : 'desc';
                //set order
                $model->orderByRaw("$view_column_target $sort_order");
                break;
            case ConditionType::SYSTEM:
                $system_info = SystemColumn::getOption(['id' => array_get($custom_view_sort, 'view_column_target_id')]);
                $view_column_target = array_get($system_info, 'sqlname') ?? array_get($system_info, 'name');
                //set order
                $model->orderby($view_column_target, $custom_view_sort->sort == ViewColumnSort::ASC ? 'asc' : 'desc');
                break;
            case ConditionType::PARENT_ID:
                $view_column_target = 'parent_id';
                //set order
                $model->orderby($view_column_target, $custom_view_sort->sort == ViewColumnSort::ASC ? 'asc' : 'desc');
                break;
            }
        }

        return $model;
    }

    /**
     * set value summary
     */
    public function getValueSummary(&$query, $table_name, $grid = null)
    {
        // get table id
        $db_table_name = getDBTableName($table_name);

        $group_columns = [];
        $sort_columns = [];
        $custom_tables = [];
        $sub_queries = [];

        // get relation parent tables
        $parent_relations = CustomRelation::getRelationsByChild($this->custom_table);
        // get relation child tables
        $child_relations = CustomRelation::getRelationsByParent($this->custom_table);
        // join select table refered from this table.
        $select_table_columns = $this->custom_table->getSelectTables();
        // join table refer to this table as select.
        $selected_table_columns = $this->custom_table->getSelectedTables();
        
        // set grouping columns
        $view_column_items = $this->getSummaryIndexAndViewColumns();
        foreach ($view_column_items as $view_column_item) {
            $item = array_get($view_column_item, 'item');
            $index = array_get($view_column_item, 'index');
            $column_item = $item->column_item;
            // set order column
            if (!empty(array_get($item, 'sort_order'))) {
                $sort_order = array_get($item, 'sort_order');
                $sort_type = array_get($item, 'sort_type');
                $sort_columns[] = ['key' => $sort_order, 'sort_type' => $sort_type, 'column_name' => "column_$index"];
            }

            if ($item instanceof CustomViewColumn) {
                // check child item
                $is_child = $child_relations->contains(function ($value, $key) use ($item) {
                    return isset($item->custom_table) && $value->child_custom_table->id == $item->custom_table->id;
                });

                // first, set group_column. this column's name uses index.
                $column_item->options(['groupby' => true, 'group_condition' => array_get($item, 'view_group_condition'), 'summary_index' => $index, 'is_child' => $is_child]);
                $groupSqlName = $column_item->sqlname();
                $groupSqlAsName = $column_item->sqlAsName();
                $group_columns[] = $is_child ? $groupSqlAsName : $groupSqlName;
                $column_item->options(['groupby' => false, 'group_condition' => null]);

                // parent_id need parent_type
                if ($column_item instanceof \Exceedone\Exment\ColumnItems\ParentItem) {
                    $group_columns[] = $column_item->sqltypename();
                } elseif ($column_item instanceof \Exceedone\Exment\ColumnItems\WorkflowItem) {
                    \Exceedone\Exment\ColumnItems\WorkflowItem::getStatusSubquery($query, $item->custom_table);
                }

                $this->setSummaryItem($column_item, $index, $custom_tables, $grid, [
                    'column_label' => array_get($item, 'view_column_name')?? $column_item->label(),
                    'custom_view_column' => $item,
                ]);
                
                // if this is child table, set as sub group by
                if ($is_child) {
                    $custom_tables[$item->custom_table->id]['subGroupby'][] = $groupSqlAsName;
                    $custom_tables[$item->custom_table->id]['select_group'][] = $groupSqlAsName;
                }
            }
            // set summary columns
            else {
                $this->setSummaryItem($column_item, $index, $custom_tables, $grid, [
                    'column_label' => array_get($item, 'view_column_name')?? $column_item->label(),
                    'summary_condition' => $item->view_summary_condition,
                ]);
            }
        }

        // set filter columns
        foreach ($this->custom_view_filters_cache as $custom_view_filter) {
            $target_table_id = array_get($custom_view_filter, 'view_column_table_id');

            if (array_key_exists($target_table_id, $custom_tables)) {
                $custom_tables[$target_table_id]['filter'][] = $custom_view_filter;
            } else {
                $custom_tables[$target_table_id] = [
                    'table_name' => getDBTableName($target_table_id),
                    'filter' => [$custom_view_filter]
                ];
            }
        }

        $custom_table_id = $this->custom_table->id;

        foreach ($custom_tables as $table_id => $custom_table) {
            // add select column and filter
            if ($table_id == $custom_table_id) {
                $this->addQuery($query, $db_table_name, $custom_table);
                continue;
            }
            // join parent table
            if ($parent_relations->contains(function ($value, $key) use ($table_id) {
                return $value->parent_custom_table->id == $table_id;
            })) {
                $this->addQuery($query, $db_table_name, $custom_table, 'parent_id', 'id');
                continue;
            }
            // create subquery grouping child table
            if ($child_relations->contains(function ($value, $key) use ($table_id) {
                return $value->child_custom_table->id == $table_id;
            })) {
                $sub_query = $this->getSubQuery($db_table_name, 'id', 'parent_id', $custom_table);
                if (array_key_exists('select_group', $custom_table)) {
                    $query->addSelect($custom_table['select_group']);
                }
                $sub_queries[] = $sub_query;
                continue;
            }
            // join table refered from target table
            if (in_array($table_id, $select_table_columns)) {
                $column_key = array_search($table_id, $select_table_columns);
                $this->addQuery($query, $db_table_name, $custom_table, $column_key, 'id');
                continue;
            }
            // create subquery grouping table refer to target table
            if (in_array($table_id, $selected_table_columns)) {
                $column_key = array_search($table_id, $selected_table_columns);
                $sub_query = $this->getSubQuery($db_table_name, 'id', $column_key, $custom_table);
                if (array_key_exists('select_group', $custom_table)) {
                    $query->addSelect($custom_table['select_group']);
                }
                $sub_queries[] = $sub_query;
                continue;
            }
        }

        // join subquery
        foreach ($sub_queries as $table_no => $sub_query) {
            //$query->leftjoin(\DB::raw('('.$sub_query->toSql().") As table_$table_no"), $db_table_name.'.id', "table_$table_no.id");
            $alter_name = is_string($table_no)? $table_no : 'table_'.$table_no;
            $query->leftjoin(\DB::raw('('.$sub_query->toSql().") As $alter_name"), $db_table_name.'.id', "$alter_name.id");
            $query->addBinding($sub_query->getBindings(), 'join');
        }

        if (count($sort_columns) > 0) {
            $orders = collect($sort_columns)->sortBy('key')->all();
            foreach ($orders as $order) {
                $sort = ViewColumnSort::getEnum(array_get($order, 'sort_type'), ViewColumnSort::ASC)->lowerKey();
                $query->orderBy(array_get($order, 'column_name'), $sort);
            }
        }
        // set sql grouping columns
        $query->groupBy($group_columns);

        return $query;
    }
    
    /**
     * set summary item
     */
    protected function setSummaryItem($item, $index, &$custom_tables, $grid, $options = [])
    {
        extract(array_merge(
            [
                'column_label' => null,
                'summary_condition' => null,
                'custom_view_column' => null,
            ],
            $options
        ));

        $item->options([
            'summary' => true,
            'summary_condition' => $summary_condition,
            'summary_index' => $index,
            'disable_currency_symbol' => ($summary_condition == SummaryCondition::COUNT),
            'group_condition' => array_get($custom_view_column, 'view_group_condition'),
        ]);

        $table_id = $item->getCustomTable()->id;
        $db_table_name = getDBTableName($table_id);

        // set sql parts for custom table
        if (!array_key_exists($table_id, $custom_tables)) {
            $custom_tables[$table_id] = [ 'table_name' => $db_table_name ];
        }

        $custom_tables[$table_id]['select'][] = $item->sqlname();
        if ($item instanceof \Exceedone\Exment\ColumnItems\ParentItem) {
            $custom_tables[$table_id]['select'][] = $item->sqltypename();
        }

        if (isset($summary_condition)) {
            $custom_tables[$table_id]['select_group'][] = $item->getGroupName();
        }
        
        if (isset($grid)) {
            $grid->column("column_".$index, $column_label)
            ->sort($item->sortable())
            ->display(function ($id) use ($item, $index) {
                $option = SystemColumn::getOption(['name' => $item->name()]);
                if (array_get($option, 'type') == 'user') {
                    return esc_html(getUserName($id));
                } else {
                    return $item->setCustomValue($this)->html();
                }
            });
        }
    }

    /**
     * add select column and filter and join table to main query
     */
    protected function addQuery(&$query, $table_main, $custom_table, $key_main = null, $key_sub = null)
    {
        $table_name = array_get($custom_table, 'table_name');
        if ($table_name != $table_main) {
            $query->join($table_name, "$table_main.$key_main", "$table_name.$key_sub");
            $query->whereNull("$table_name.deleted_at");
        }
        if (array_key_exists('select', $custom_table)) {
            $query->addSelect($custom_table['select']);
        }
        if (array_key_exists('filter', $custom_table)) {
            foreach ($custom_table['filter'] as $filter) {
                $filter->setValueFilter($query, $table_name, $this->filter_is_or);
            }
        }
    }
    
    /**
     * add select column and filter and join table to sub query
     */
    protected function getSubQuery($table_main, $key_main, $key_sub, $custom_table)
    {
        $table_name = array_get($custom_table, 'table_name');
        // get subquery groupbys
        $groupBy = array_get($custom_table, 'subGroupby', []);
        $groupBy[] = "$table_name.$key_sub";

        $sub_query = \DB::table($table_main)
            ->select("$table_name.$key_sub as id")
            ->join($table_name, "$table_main.$key_main", "$table_name.$key_sub")
            ->whereNull("$table_name.deleted_at")
            ->groupBy($groupBy);
        if (array_key_exists('select', $custom_table)) {
            $sub_query->addSelect($custom_table['select']);
        }
        if (array_key_exists('filter', $custom_table)) {
            $custom_filter = $custom_table['filter'];
            $sub_query->where(function ($query) use ($table_name, $custom_filter) {
                foreach ($custom_filter as $filter) {
                    $filter->setValueFilter($query, $table_name, $this->filter_is_or);
                }
            });
        }
        return $sub_query;
    }

    /**
     * Get arrays about Summary Column and custom_view_columns and custom_view_summaries
     *
     * @return array
     */
    public function getSummaryIndexAndViewColumns()
    {
        $results = [];
        // set grouping columns
        foreach ($this->custom_view_columns_cache as $custom_view_column) {
            $results[] = [
                'index' => ViewKindType::DEFAULT . '_' . $custom_view_column->id,
                'item' => $custom_view_column,
            ];
        }
        // set summary columns
        foreach ($this->custom_view_summaries_cache as $custom_view_summary) {
            $results[] = [
                'index' => ViewKindType::AGGREGATE . '_' . $custom_view_summary->id,
                'item' => $custom_view_summary,
            ];
            $item = $custom_view_summary->column_item;
        }

        return $results;
    }

    /**
     * get view columns select options. It contains system column(ex. id, suuid, created_at, updated_at), and table columns.
     * @param $is_number
     */
    public function getViewColumnsSelectOptions(bool $is_y) : array
    {
        $options = [];
        
        // is summary view
        if ($this->view_kind_type == ViewKindType::AGGREGATE) {
            // if x column, set x as chart column
            if (!$is_y) {
                $options[] = ['id' => Define::CHARTITEM_LABEL, 'text' => exmtrans('chart.chartitem_label')];
            }
            // set as y
            else {
                foreach ($this->custom_view_columns_cache as $custom_view_column) {
                    $this->setViewColumnsOptions($options, ViewKindType::DEFAULT, $custom_view_column, true);
                }

                foreach ($this->custom_view_summaries_cache as $custom_view_summary) {
                    $this->setViewColumnsOptions($options, ViewKindType::AGGREGATE, $custom_view_summary, true);
                }
            }
        } else {
            // set as default view
            if (!$is_y) {
                $options[] = ['id' => Define::CHARTITEM_LABEL, 'text' => exmtrans('chart.chartitem_label')];
            }

            foreach ($this->custom_view_columns_cache as $custom_view_column) {
                $this->setViewColumnsOptions($options, ViewKindType::DEFAULT, $custom_view_column, $is_y ? true : null);
            }
        }
        
        return $options;
    }

    protected function setViewColumnsOptions(&$options, $view_kind_type, $custom_view_column, ?bool $is_number)
    {
        $option = $this->getSelectColumn($view_kind_type, $custom_view_column);
        if (is_null($is_number) || array_get($option, 'is_number') === $is_number) {
            $options[] = $option;
        }
    }

    protected function getSelectColumn($column_type, $custom_view_column)
    {
        $view_column_type = array_get($custom_view_column, 'view_column_type');
        $view_column_id = $column_type . '_' . array_get($custom_view_column, 'id');

        $custom_table_id = $this->custom_table_id;
        $column_view_name = array_get($custom_view_column, 'view_column_name');
        $is_number = false;

        switch ($view_column_type) {
            case ConditionType::COLUMN:
                $custom_column = $custom_view_column->custom_column;
                $is_number = $custom_column->isCalc();

                if (is_nullorempty($column_view_name)) {
                    $column_view_name = array_get($custom_column, 'column_view_name');
                    // if table is not equal target table, add table name to column name.
                    if ($custom_table_id != array_get($custom_column, 'custom_table_id')) {
                        $column_view_name = array_get($column->custom_table, 'table_view_name') . '::' . $column_view_name;
                    }
                }
                break;
            case ConditionType::SYSTEM:
            case ConditionType::WORKFLOW:
                $system_info = SystemColumn::getOption(['id' => array_get($custom_view_column, 'view_column_target_id')]);
                if (is_nullorempty($column_view_name)) {
                    $column_view_name = exmtrans('common.'.$system_info['name']);
                }
                break;
            case ConditionType::PARENT_ID:
                $relation = CustomRelation::with('parent_custom_table')->where('child_custom_table_id', $this->custom_table_id)->first();
                ///// if this table is child relation(1:n), add parent table
                if (isset($relation)) {
                    $column_view_name = array_get($relation, 'parent_custom_table.table_view_name');
                }
                break;
        }

        if (array_get($custom_view_column, 'view_summary_condition') == SummaryCondition::COUNT) {
            $is_number = true;
        }
        return ['id' => $view_column_id, 'text' => $column_view_name, 'is_number' => $is_number];
    }
    
    public function getViewCalendarTargetAttribute()
    {
        $custom_view_columns = $this->custom_view_columns_cache;
        if (count($custom_view_columns) > 0) {
            return $custom_view_columns[0]->view_column_target;
        }
        return null;
    }

    public function setViewCalendarTargetAttribute($view_calendar_target)
    {
        $custom_view_columns = $this->custom_view_columns_cache;
        if (count($custom_view_columns) == 0) {
            $this->custom_view_columns[] = new CustomViewColumn();
        }
        $custom_view_columns[0]->view_column_target = $view_calendar_target;
    }
    
    public function getPagerCountAttribute()
    {
        return $this->getOption('pager_count');
    }

    public function setPagerCountAttribute($val)
    {
        $this->setOption('pager_count', $val);

        return $this;
    }
    
    public function getConditionJoinAttribute()
    {
        return $this->getOption('condition_join');
    }

    public function setConditionJoinAttribute($val)
    {
        $this->setOption('condition_join', $val);

        return $this;
    }

    /**
     * Whether this model disable delete
     *
     * @return boolean
     */
    public function getDisabledDeleteAttribute()
    {
        return boolval($this->view_kind_type == ViewKindType::ALLDATA);
    }

    /**
     * Whether login user has edit permission about this view.
     */
    public function hasEditPermission()
    {
        $login_user = \Exment::user();
        if ($this->view_type == ViewType::SYSTEM) {
            return $this->custom_table->hasSystemViewPermission();
        } elseif ($this->created_user_id == $login_user->getUserId()) {
            return true;
        };

        // check if editable user exists
        $hasEdit = $this->data_authoritable_users()
            ->where('authoritable_type', 'data_share_edit')
            ->where('authoritable_target_id', $login_user->getUserId())
            ->exists();

        if (!$hasEdit && System::organization_available()) {
            $enum = JoinedOrgFilterType::getEnum(System::org_joined_type_custom_value(), JoinedOrgFilterType::ONLY_JOIN);
            // check if editable organization exists
            $hasEdit = $this->data_authoritable_organizations()
                ->where('authoritable_type', 'data_share_edit')
                ->whereIn('authoritable_target_id', $login_user->getOrganizationIds($enum))
                ->exists();
        }

        return $hasEdit;
    }
}
