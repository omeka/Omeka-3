<?php
namespace Omeka\Mvc;

use Omeka\Service\Exception\ConfigException;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Acl\Exception\InvalidArgumentException as AclInvalidArgumentException;
use Zend\View\Model\ViewModel;

class MvcListeners extends AbstractListenerAggregate
{
    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'redirectToInstallation')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'redirectToMigration')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'redirectToLogin')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'authenticateApiKey')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'authorizeUserAgainstRoute'),
            -1000
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH,
            array($this, 'setLayoutForRoute')
        );
    }

    /**
     * Redirect all requests to install route if Omeka is not installed.
     *
     * @param MvcEvent $event
     * @return Zend\Http\PhpEnvironment\Response
     */
    public function redirectToInstallation(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        if ($serviceLocator->get('Omeka\Status')->isInstalled()) {
            // Omeka is installed
            return;
        }
        $matchedRouteName = $event->getRouteMatch()->getMatchedRouteName();
        if ('install' == $matchedRouteName) {
            // On the install route
            return;
        }
        $url = $event->getRouter()->assemble(array(), array('name' => 'install'));
        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }

    /**
     * Redirect requests if Omeka needs database migrations.
     *
     * Updates the installed version when the code version is out of sync and
     * there are no migrations to perform. When there are migrations to perform,
     * redirects to a migrate page in the admin route, and to a maintenance page
     * on all other routes.
     *
     * @param MvcEvent $event
     * @return Zend\Http\PhpEnvironment\Response
     */
    public function redirectToMigration(MvcEvent $event)
    {
        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();

        if ('install' == $matchedRouteName) {
            // No need to continue when installing the software.
            return;
        }

        $serviceLocator = $event->getApplication()->getServiceManager();
        $status = $serviceLocator->get('Omeka\Status');

        if (!$status->needsVersionUpdate()) {
            // No need to continue when the version is up to date.
            return;
        }
        if (!$status->needsMigration()) {
            // There are no migrations. Update the installed version and return.
            $serviceLocator->get('Omeka\Settings')
                ->set('version', $status->getVersion());
            return;
        }
        if ('migrate' == $matchedRouteName || 'maintenance' == $matchedRouteName) {
            // Already on the migrate or maintenance route. Do not redirect.
            return;
        }

        if ('Omeka\Controller\Admin' == $routeMatch->getParam('__NAMESPACE__')) {
            $url = $event->getRouter()->assemble(array(), array('name' => 'migrate'));
        } else {
            $url = $event->getRouter()->assemble(array(), array('name' => 'maintenance'));
        }
        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }

    /**
     * Redirect all admin requests to login route if user not logged in.
     *
     * @param MvcEvent $event
     * @return Zend\Http\PhpEnvironment\Response
     */
    public function redirectToLogin(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            // User is logged in.
            return;
        }

        $routeMatch = $event->getRouteMatch();
        if ('Omeka\Controller\Admin' == $routeMatch->getParam('__NAMESPACE__')) {
            // This is an admin request.
            $url = $event->getRouter()->assemble(array(), array('name' => 'login'));
            $response = $event->getResponse();
            $response->getHeaders()->addHeaderLine('Location', $url);
            $response->setStatusCode(302);
            $response->sendHeaders();
            return $response;
        }
    }

    /**
     * Authorize the current user against an API key.
     *
     * @param MvcEvent $event
     */
    public function authenticateApiKey(MvcEvent $event)
    {
        $status = $event->getApplication()->getServiceManager()
            ->get('Omeka\Status');

        if (!$status->isApiRequest()) {
            // This is not an API request.
            return;
        }

        $identity = $event->getRequest()->getQuery('key_identity');
        $credential = $event->getRequest()->getQuery('key_credential');

        if (is_null($identity) || is_null($credential)) {
            // No identity/credential key to authenticate against.
            return;
        }

        $auth = $event->getApplication()->getServiceManager()
            ->get('Omeka\AuthenticationService');
        $auth->getAdapter()->setIdentity($identity);
        $auth->getAdapter()->setCredential($credential);
        $auth->authenticate();
    }

    /**
     * Authorize the current user against the requested route.
     *
     * @param MvcEvent $event
     */
    public function authorizeUserAgainstRoute(MvcEvent $event)
    {
        $routeMatch = $event->getRouteMatch();
        $controller = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');
        $acl = $event->getApplication()->getServiceManager()->get('Omeka\Acl');

        try {
            if (!$acl->userIsAllowed($controller, $action)) {
                // User not allowed is 403 Forbidden.
                $response = $event->getResponse();
                $response->setStatusCode(403);

                $model = new ViewModel;
                $model->setTemplate('error/403');

                $event->setResponse($response);
                $event->getViewModel()->addChild($model);
                $event->setError(Application::ERROR_ROUTER_PERMISSION_DENIED);
            }
        } catch (AclInvalidArgumentException $e) {
            // ACL resource not found is 404 Not Found, automatically set during
            // MvcEvent::EVENT_DISPATCH_ERROR.
            $event->setParam('exception', $e);
        }
    }

    /**
     * Set the layout template according to route.
     *
     * @param MvcEvent $event
     */
    public function setLayoutForRoute(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        $config = $serviceLocator->get('Config');
        if (!isset($config['view_route_layouts'])) {
            throw new ConfigException('Missing view route layouts configuration');
        }
        $matchedRouteName = $event->getRouteMatch()->getMatchedRouteName();
        if (!array_key_exists($matchedRouteName, $config['view_route_layouts'])) {
            return;
        }
        $viewModel = $event->getViewModel();
        $viewModel->setTemplate($config['view_route_layouts'][$matchedRouteName]);
    }
}
