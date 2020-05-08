<?php

namespace Exceedone\Exment\Form\Field;

use Encore\Admin\Form\Field  as AdminField;
use Encore\Admin\Form\EmbeddedForm;

class Embeds extends AdminField\Embeds
{
    protected $view = 'exment::form.field.embeds';

    protected $header = true;

    protected $gridFields = [];

    public function disableHeader()
    {
        $this->header = false;
    }

    /**
     * get fields in NestedEmbeddedForm
     */
    public function fields()
    {
        return $this->buildEmbeddedForm()->fields();
    }
    
    /**
     * Build a Embedded Form and fill data.
     *
     * @return EmbeddedForm
     */
    protected function buildEmbeddedForm()
    {
        $form = new EmbeddedForm($this->column);
        return $this->setFormField($form);
    }

    protected function setFormField($form)
    {
        // reset
        $this->gridFields = [];
        $form->setParent($this->form);

        // call builder.
        // if builder is array, loop setting
        if (is_array($this->builder)) {
            foreach ($this->builder as $index => $build) {
                // get fields count
                $prependFieldsCount = count($form->fields());
                call_user_func($build, $form);

                $fields = [];
                for ($i = $prependFieldsCount; $i < count($form->fields()); $i++) {
                    $fields[] = $form->fields()[$i];
                }
                $this->gridFields[$index] = $fields;
            }
        }
        // not array(default), call_user_func $this->builder
        else {
            call_user_func($this->builder, $form);
        }

        $form->fill($this->getEmbeddedData());

        return $form;
    }

    /**
     * Get form's script
     *
     * @return void
     */
    public function getScript()
    {
        return collect($this->fields())->map(function ($field) {
            /* @var Field $field  */
            return $field->getScript();
        })->filter()->values()->toArray();
    }
    /**
     * Render the form.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $form = $this->buildEmbeddedForm();
        if (count($this->gridFields) == 0) {
            return parent::render()->with(['form' => $form, 'header' => $this->header]);
        }

        return parent::render()->with([
            'gridFieldsL' => $this->gridFields[1]?? null,
            'gridFieldsR' => $this->gridFields[2]?? null,
            'gridHeaders' => $this->gridFields[8]?? null,
            'gridFooters' => $this->gridFields[9]?? null,
            'is_grid' => true,
            'header' => $this->header
        ]);
    }
}
