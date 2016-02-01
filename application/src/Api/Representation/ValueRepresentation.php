<?php
namespace Omeka\Api\Representation;

use Omeka\Entity\Value;
use Zend\ServiceManager\ServiceLocatorInterface;
use Omeka\Event\Event;

class ValueRepresentation extends AbstractRepresentation
{
    /**
     * @var Value
     */
    protected $value;

    /**
     * @var \Omeka\DataType\Manager
     */
    protected $dataType;

    /**
     * Construct the value representation object.
     *
     * @param Value $value
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(Value $value, ServiceLocatorInterface $serviceLocator)
    {
        // Set the service locator first.
        $this->setServiceLocator($serviceLocator);
        $this->value = $value;
        $this->dataType = $serviceLocator->get('Omeka\DataTypeManager')
            ->get($value->getType());
    }

    /**
     * Return this value as an unescaped string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->dataType->toString($this);
    }

    /**
     * Return this value for display on a webpage.
     *
     * @return string
     */
    public function asHtml()
    {
        $view = $this->getServiceLocator()->get('ViewManager')->getRenderer();
        $eventManager = $this->getEventManager();
        $args = $eventManager->prepareArgs([
            'html' => $this->dataType->getHtml($view, $this),
        ]);
        $eventManager->trigger(Event::REP_VALUE_HTML, $this, $args);
        return $args['html'];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $valueObject = [
            'type' => $this->type(),
            'property_id' => $this->value->getProperty()->getId(),
            'property_label' => $this->value->getProperty()->getLabel(),
        ];
        $jsonLd = $this->dataType->getJsonLd($this);
        if (!is_array($jsonLd)) {
            $jsonLd = [];
        }
        return $valueObject + $jsonLd;
    }

    /**
     * Get the resource representation.
     *
     * This is the subject of the RDF triple represented by this value.
     *
     * @return Entity\AbstractResourceEntityRepresentation
     */
    public function resource()
    {
        $resource = $this->value->getResource();
        return $this->getAdapter($resource->getResourceName())
            ->getRepresentation($resource);
    }

    /**
     * Get the property representation.
     *
     * This is the predicate of the RDF triple represented by this value.
     *
     * @return Entity\PropertyRepresentation
     */
    public function property()
    {
        return $this->getAdapter('properties')
            ->getRepresentation($this->value->getProperty());
    }

    /**
     * Get the value type.
     *
     * @return string
     */
    public function type()
    {
        return $this->value->getType();
    }

    /**
     * Get the value itself.
     *
     * This is the object of the RDF triple represented by this value.
     *
     * @return string
     */
    public function value()
    {
        return $this->value->getValue();
    }

    /**
     * Get the value language.
     *
     * @return string
     */
    public function lang()
    {
        return $this->value->getLang();
    }

    /**
     * Get the URI label.
     *
     * @return string
     */
    public function uriLabel()
    {
        return $this->value->getUriLabel();
    }

    /**
     * Get the value resource representation.
     *
     * This is the object of the RDF triple represented by this value.
     *
     * @return null|Entity\AbstractResourceEntityRepresentation
     */
    public function valueResource()
    {
        $resource = $this->value->getValueResource();
        if (!$resource) {
            return null;
        }
        $resourceAdapter = $this->getAdapter($resource->getResourceName());
        return $resourceAdapter->getRepresentation($resource);
    }
}
