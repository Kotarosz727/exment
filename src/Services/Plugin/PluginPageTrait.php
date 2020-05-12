<?php

/**
 * Execute Batch
 */
namespace Exceedone\Exment\Services\Plugin;

trait PluginPageTrait
{
    use PluginTrait;

    /**
     * get load view if view exists and path
     *
     * @return void
     */
    public function _getLoadView()
    {
        $base_path = $this->plugin->getFullPath(path_join('resources', 'views'));
        if (!\File::exists($base_path)) {
            return null;
        }

        return [$base_path, 'exment_' . snake_case($this->plugin->plugin_name)];
    }
}
