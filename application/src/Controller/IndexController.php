<?php
namespace Omeka\Controller;

use Omeka\Api\Exception as ApiException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        // Redirect to default site, if set
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $defaultSiteId = $settings->get('default_site');
        if ($defaultSiteId) {
            try {
                $defaultSiteResponse = $this->api()->read('sites', $defaultSiteId);
                $defaultSite = $defaultSiteResponse->getContent();
                return $this->redirect()->toUrl($defaultSite->siteUrl());
            } catch (ApiException\NotFoundException $e) {
                // Consume error if default site isn't found
            }
        }

        $response = $this->api()->search('sites');
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $this->layout('layout/minimal');

        $view = new ViewModel;
        $view->setVariable('sites', $response->getContent());
        return $view;
    }
}
