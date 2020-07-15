<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\Form\Field;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Enums\UrlTagType;

class Image extends File
{
    /**
     * Set column type
     *
     * @var string
     */
    protected static $column_type = 'image';

    /**
     * get html. show link to image
     */
    public function html()
    {
        // get image url
        $url = ExmentFile::getUrl($this->fileValue());
        if (!isset($url)) {
            return $url;
        }

        return \Exment::getUrlTag($url, '<img src="'.$url.'" class="image_html" />', UrlTagType::BLANK, [], [
            'notEscape' => true,
        ]);
    }

    protected function getAdminFieldClassName()
    {
        return Field\Image::class;
    }
    
    protected function setAdminOptions(&$field, $form_column_options)
    {
        parent::setAdminOptions($field, $form_column_options);

        $field->attribute(['accept' => "image/*"]);
    }

    protected function setValidates(&$validates, $form_column_options)
    {
        $validates[] = new \Exceedone\Exment\Validator\ImageRule;
    }
}
