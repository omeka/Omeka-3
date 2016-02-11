<?php
namespace Omeka\Form;

use Omeka\Form\Element\ResourceSelect;

class SettingForm extends AbstractForm
{
    public function buildForm()
    {
        $translator = $this->getTranslator();

        $this->add([
            'name' => 'administrator_email',
            'type' => 'Email',
            'options' => [
                'label' => $translator->translate('Administrator Email'),
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'installation_title',
            'type' => 'Text',
            'options' => [
                'label' => $translator->translate('Installation Title'),
            ],
            'attributes' => [
                'id' => 'installation-title',
                'required' => true,
            ],
        ]);

        $timeZones = \DateTimeZone::listIdentifiers();
        $timeZones = array_combine($timeZones, $timeZones);
        $this->add([
            'name' => 'time_zone',
            'type' => 'Select',
            'options' => [
                'label' => $translator->translate('Time Zone'),
                'value_options' => $timeZones,
            ],
            'attributes' => [
                'id' => 'time-zone',
                'required' => true,
                'value' => $this->getServiceLocator()->get('Omeka\Settings')->get('time_zone', 'UTC'),
            ],
        ]);

        $this->add([
            'name' => 'pagination_per_page',
            'type' => 'Text',
            'options' => [
                'label' => $translator->translate('Results per page'),
                'info' => $translator->translate('The maximum number of results per page on browse pages.'),
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'property_label_information',
            'type' => 'Select',
            'options' => [
                'label' => $translator->translate('Property Label Information'),
                'info' => $translator->translate('The additional information that accompanies labels on resource pages.'),
                'value_options' =>  [
                    'none' => 'None',
                    'vocab' => 'Show Vocabulary',
                    'term' => 'Show Term'
                ],
            ]
        ]);

        $siteSelect = new ResourceSelect(
            $this->getServiceLocator(), 'default_site', [
                'label' => $translator->translate('Default Site'),
                'info' => $translator->translate('Select which site should appear when users go to the front page of the installation.'),
                'empty_option' => $translator->translate('No default (Show index of sites)'),
        ]);
        $siteSelect->setResourceValueOptions(
            'sites',
            [],
            function ($site, $serviceLocator) {
                return $site->title();
            }
        );
        $this->add($siteSelect);

        $this->add([
            'name'    => 'use_htmlpurifier',
            'type'    => 'Checkbox',
            'options' => [
                'label' => $translator->translate('Use HTMLPurifier'),
                'info'  => $translator->translate('Clean up user-entered HTML.')
            ]

        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'pagination_per_page',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                ['name' => 'Digits']
            ],
        ]);
        $inputFilter->add([
            'name' => 'default_site',
            'allow_empty' => true,
        ]);
    }
}
