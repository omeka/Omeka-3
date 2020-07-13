<?php
namespace Omeka\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\ResourceTemplateProperty;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;

class ResourceTemplateAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'label' => 'label',
    ];

    public function getResourceName()
    {
        return 'resource_templates';
    }

    public function getRepresentationClass()
    {
        return \Omeka\Api\Representation\ResourceTemplateRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Omeka\Entity\ResourceTemplate::class;
    }

    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (is_string($query['sort_by'])) {
            if ('resource_class_label' == $query['sort_by']) {
                $resourceClassAlias = $this->createAlias();
                $qb->leftJoin(
                    'omeka_root.resourceClass',
                    $resourceClassAlias
                )->addOrderBy("$resourceClassAlias.label", $query['sort_order']);
            } elseif ('item_count' == $query['sort_by']) {
                $this->sortByCount($qb, $query, 'resources', 'Omeka\Entity\Item');
            } else {
                parent::sortQuery($qb, $query);
            }
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['label'])) {
            $qb->andWhere($qb->expr()->eq(
                "omeka_root.label",
                $this->createNamedParameter($qb, $query['label']))
            );
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        // A resource template may not have duplicate properties with the same
        // data type or the same label.
        // Note: mysql allows to use null as part of a unique key.
        if (isset($data['o:resource_template_property'])
            && is_array($data['o:resource_template_property'])
        ) {
            $checkDataTypes = [];
            $checkLabels = [];
            foreach ($data['o:resource_template_property'] as $resTemPropData) {
                if (!isset($resTemPropData['o:property']['o:id'])) {
                    continue; // skip when no property ID
                }
                $propertyId = $resTemPropData['o:property']['o:id'];
                $dataType = isset($resTemPropData['o:data_type']) ? $resTemPropData['o:data_type'] : '';
                $check = $propertyId . '-' . $dataType;
                if (isset($checkDataTypes[$check])) {
                    $errorStore->addError('o:property', new Message(
                        'Attempting to add duplicate property %s (ID %s) with the same data type', // @translate
                        @$resTemPropData['o:original_label'], $propertyId
                    ));
                }
                $checkDataTypes[$check] = true;
                $label = isset($resTemPropData['o:alternate_label']) ? $resTemPropData['o:alternate_label'] : '';
                $check = $propertyId . '-' . $label;
                if (isset($checkLabels[$check])) {
                    $errorStore->addError('o:property', new Message(
                        'Attempting to add duplicate property %s (ID %s) with the same label', // @translate
                        @$resTemPropData['o:original_label'], $propertyId
                    ));
                }
                $checkLabels[$check] = true;
            }
        }
    }

    public function validateEntity(EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $label = $entity->getLabel();
        if (false == trim($label)) {
            $errorStore->addError('o:label', 'The label cannot be empty.'); // @translate
        }
        if (!$this->isUnique($entity, ['label' => $label])) {
            $errorStore->addError('o:label', 'The label is already taken.'); // @translate
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        /** @var \Omeka\Entity\ResourceTemplate $entity */
        $data = $request->getContent();
        $this->hydrateOwner($request, $entity);
        $this->hydrateResourceClass($request, $entity);

        if ($this->shouldHydrate($request, 'o:label')) {
            $entity->setLabel($request->getValue('o:label'));
        }

        if ($this->shouldHydrate($request, 'o:title_property')) {
            $titleProperty = $request->getValue('o:title_property');
            if (isset($titleProperty['o:id']) && is_numeric($titleProperty['o:id'])) {
                $titleProperty = $this->getAdapter('properties')->findEntity($titleProperty['o:id']);
            } else {
                $titleProperty = null;
            }
            $entity->setTitleProperty($titleProperty);
        }

        if ($this->shouldHydrate($request, 'o:description_property')) {
            $descriptionProperty = $request->getValue('o:description_property');
            if (isset($descriptionProperty['o:id']) && is_numeric($descriptionProperty['o:id'])) {
                $descriptionProperty = $this->getAdapter('properties')->findEntity($descriptionProperty['o:id']);
            } else {
                $descriptionProperty = null;
            }
            $entity->setDescriptionProperty($descriptionProperty);
        }

        if ($this->shouldHydrate($request, 'o:resource_template_property')
            && isset($data['o:resource_template_property'])
            && is_array($data['o:resource_template_property'])
        ) {
            // Prepare a one-time index of all resource template properties, to
            // be used to avoid duplicate elements (mysql is not set-theoretic).
            $resTemProps = $entity->getResourceTemplateProperties();
            $indexResTemProperties = [];
            foreach ($resTemProps as $resTemProp) {
                $index = $resTemProp->getProperty()->getId() . '-' . $resTemProp->getDataType();
                $indexResTemProperties[$index] = $resTemProp;
            }

            $propertyAdapter = $this->getAdapter('properties');
            $resTemProps = $entity->getResourceTemplateProperties();
            $resTemProps->first();
            $resTemPropsToRetain = [];
            $position = 1;
            foreach ($data['o:resource_template_property'] as $resTemPropData) {
                if (empty($resTemPropData['o:property']['o:id'])) {
                    continue; // skip when no property ID
                }

                $propertyId = (int) $resTemPropData['o:property']['o:id'];

                $altLabel = null;
                if (isset($resTemPropData['o:alternate_label'])
                    && '' !== trim($resTemPropData['o:alternate_label'])
                ) {
                    $altLabel = $resTemPropData['o:alternate_label'];
                }
                $altComment = null;
                if (isset($resTemPropData['o:alternate_comment'])
                    && '' !== trim($resTemPropData['o:alternate_comment'])
                ) {
                    $altComment = $resTemPropData['o:alternate_comment'];
                }
                $dataType = null;
                if (isset($resTemPropData['o:data_type'])
                    && '' !== trim($resTemPropData['o:data_type'])
                ) {
                    $dataType = $resTemPropData['o:data_type'];
                }
                $isRequired = false;
                if (isset($resTemPropData['o:is_required'])) {
                    $isRequired = (bool) $resTemPropData['o:is_required'];
                }
                $isPrivate = false;
                if (isset($resTemPropData['o:is_private'])) {
                    $isPrivate = (bool) $resTemPropData['o:is_private'];
                }

                // Check whether a passed property is already assigned to this
                // resource template.
                $index = $propertyId . '-' . $dataType;
                if (isset($indexResTemProperties[$index])) {
                    $resTemProp = $indexResTemProperties[$index];
                } else {
                    // It is not assigned. Add a new resource template property.
                    // No need to explicitly add it to the collection since it
                    // is added implicitly when setting the resource template.
                    $property = $propertyAdapter->findEntity($propertyId);
                    $resTemProp = new ResourceTemplateProperty;
                    $resTemProp->setResourceTemplate($entity);
                    $resTemProp->setProperty($property);
                    $resTemProps->add($resTemProp);
                }

                $resTemProp->setAlternateLabel($altLabel);
                $resTemProp->setAlternateComment($altComment);
                $resTemProp->setDataType($dataType);
                $resTemProp->setIsRequired($isRequired);
                $resTemProp->setIsPrivate($isPrivate);
                // Set the position of the property to its intrinsic order
                // within the passed array.
                $resTemProp->setPosition($position++);
                $resTemPropsToRetain[] = $resTemProp;
            }

            // Remove resource template properties that were not included in the
            // passed data.
            foreach ($resTemProps as $resTemProp) {
                if (!in_array($resTemProp, $resTemPropsToRetain)) {
                    $resTemProps->removeElement($resTemProp);
                }
            }
        }
    }
}
