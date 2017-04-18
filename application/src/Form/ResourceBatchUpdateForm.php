<?php
namespace Omeka\Form;

use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceSelect;
use Omeka\Form\Element\ResourceClassSelect;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\EventManager\Event;
use Zend\Form\Element;
use Zend\Form\Form;

class ResourceBatchUpdateForm extends Form
{
    use EventManagerAwareTrait;

    public function init()
    {
        // Visibility
        $this->add([
            'name' => 'is_public',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Set visibility',
                'value_options' => [
                    '' => 'No change',
                    '1' => 'Public',
                    '0' => 'Not public',
                ],
            ],
            'attributes' => [
                'value' => '',
            ],
        ]);

        // Resource template
        $this->add([
            'name' => 'resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Set template',
                'empty_option' => 'No change',
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
        ]);
        $this->add([
            'name' => 'resource_template_unset',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Unset template?',
            ],
        ]);

        // Resource class
        $this->add([
            'name' => 'resource_class',
            'type' => ResourceClassSelect::class,
            'options' => [
                'label' => 'Set class',
                'empty_option' => 'No change',
            ],
        ]);
        $this->add([
            'name' => 'resource_class_unset',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Unset class?',
            ],
        ]);

        // Add items to item set
        if ('item' === $this->getOption('resource_type')) {
            $this->add([
                'name' => 'add_to_item_set[]',
                'type' => ItemSetSelect::class,
                'options' => [
                    'label' => 'Add to item set',
                    'empty_option' => 'Select item set',
                ],
            ]);
            $this->add([
                'name' => 'remove_from_item_set[]',
                'type' => ItemSetSelect::class,
                'options' => [
                    'label' => 'Remove from item set',
                    'empty_option' => 'Select item set',
                ],
            ]);
        }

        // Set item set openness
        if ('item_set' === $this->getOption('resource_type')) {
            $this->add([
                'name' => 'openness',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Set openness',
                    'value_options' => [
                        '0' => 'No change',
                        'public' => 'Open',
                        'not_public' => 'Not open',
                    ],
                ],
                'attributes' => [
                    'value' => '0',
                ],
            ]);
        }

        // Clear property value
        $this->add([
            'name' => 'clear_property_values[]',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Clear property values',
                'empty_option' => 'Select property',
            ],
        ]);

        // Add property value
        $this->add([
            'name' => 'value[1][property_id]',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property',
                'empty_option' => 'Select property',
            ],
        ]);
        $this->add([
            'name' => 'value[1][@value]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Value',
            ],
        ]);
        $this->add([
            'name' => 'value[1][type]',
            'type' => Element\Hidden::class,
            'attributes' => [
                'value' => 'literal',
            ],
        ]);

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $inputFilter = $this->getInputFilter();

        $inputFilter->add([
            'name' => 'is_public',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'resource_template',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'resource_template_unset',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'resource_class',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'resource_class_unset',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'add_to_item_set[]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'remove_from_item_set[]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'clear_property_values[]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'value[1][property_id]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'value[1][@value]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'value[1][type]',
            'required' => false,
        ]);

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}