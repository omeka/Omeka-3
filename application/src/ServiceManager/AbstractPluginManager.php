<?php
namespace Omeka\ServiceManager;

use Omeka\Event\Event;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\ServiceManager\AbstractPluginManager as ZendAbstractPluginManager;

abstract class AbstractPluginManager extends ZendAbstractPluginManager
{
    use EventManagerAwareTrait;

    /**
     * Sorted array of service names. Names specified here are sorted
     * accordingly in the getRegisteredNames output. Names not specified
     * are left in their natural order.
     *
     * @var array
     */
    protected $sortedNames = [];

    public function __construct($configOrContainerInterface = null, array $v3config = [])
    {
        parent::__construct($configOrContainerInterface, $v3config);

        if (isset($v3config['sorted_names'])) {
            $this->sortedNames = $v3config['sorted_names'];
        }
    }
    /**
     * Get registered names.
     *
     * An alternative to parent::getCanonicalNames(). Returns only the names
     * that are registered in configuration as invokable classes and factories.
     * The list many be modified during the service.registered_names event.
     *
     * @return array
     */
    public function getRegisteredNames()
    {
        $services = $this->getRegisteredServices();
        $registeredNames = array_merge($services['invokableClasses'], $services['factories']);
        $registeredNames = array_merge($this->sortedNames, array_diff($registeredNames, $this->sortedNames));
        $args = $this->getEventManager()->prepareArgs([
            'registered_names' => $registeredNames,
        ]);
        $this->getEventManager()->trigger(Event::SERVICE_REGISTERED_NAMES, $this, $args);
        return $args['registered_names'];
    }
}
