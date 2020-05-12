<?php
namespace Exceedone\Exment\Services\Plugin;

/**
 * Plugin (Event) base class
 */
class PluginEventBase
{
    use PluginTrait, PluginEventTrait;
    
    public $custom_table;
    public $custom_value;
    public $isCreate;

    public function __construct($plugin, $custom_table, $custom_value, $options = [])
    {
        $this->_initEvent($plugin, $custom_table, $custom_value, $options);
    }

    public function execute()
    {
    }
}
