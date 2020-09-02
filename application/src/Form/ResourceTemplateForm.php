<?php
namespace Omeka\Form;

use Omeka\Form\Element\ResourceClassSelect;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Form;

class ResourceTemplateForm extends Form
{
    use EventManagerAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'o:label',
            'type' => 'Text',
            'options' => [
                'label' => 'Label', // @translate
            ],
            'attributes' => [
                'required' => true,
                'id' => 'o:label',
            ],
        ]);

        $this->add([
            'name' => 'o:resource_class[o:id]',
            'type' => ResourceClassSelect::class,
            'options' => [
                'label' => 'Suggested class', // @translate
                'empty_option' => '',
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a class',
                'id' => 'o:resource_class[o:id]',
            ],
        ]);

        $this->add([
            'name' => 'o:title_property[o:id]',
            'type' => 'hidden',
            'attributes' => [
                'id' => 'title-property-id',
            ],
        ]);
        $this->add([
            'name' => 'o:description_property[o:id]',
            'type' => 'hidden',
            'attributes' => [
                'id' => 'description-property-id',
            ],
        ]);

        $event = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($event);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:label',
            'required' => true,
        ]);
        $inputFilter->add([
            'name' => 'o:resource_class[o:id]',
            'allow_empty' => true,
        ]);

        // Separate events because calling $form->getInputFilters() resets
        // everything.
        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }
}
