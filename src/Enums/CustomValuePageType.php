<?php

namespace Exceedone\Exment\Enums;

class CustomValuePageType extends EnumBase
{
    const CREATE = 'create';
    const EDIT = 'edit';
    const GRID = 'grid';
    const SHOW = 'show';
    const DELETE = 'delete';
 
    // For page validation ----------------------------------------------------
    const EXPORT = 'export';
    const IMPORT = 'import';
    
    const GRIDMODAL = 'gridmodal';
}
