<?php
namespace Omeka;

use Composer\Semver\Comparator;
use Omeka\Event\Event as OmekaEvent;
use Omeka\Module\AbstractModule;
use Omeka\Session\SaveHandler\Db;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Session\Config\SessionConfig;
use Zend\Session\Container;
use Zend\Session\SessionManager;

/**
 * The Omeka module.
 */
class Module extends AbstractModule
{
    /**
     * This Omeka version.
     */
    const VERSION = '0.6.1-alpha';

    /**
     * {@inheritDoc}
     */
    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        // Set the timezone.
        $services = $this->getServiceLocator();
        if ($services->get('Omeka\Status')->isInstalled()) {
            date_default_timezone_set('UTC');
        };

        $this->bootstrapSession();
        $this->bootstrapViewHelpers();
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig()
    {
        return array_merge(
            include __DIR__ . '/config/module.config.php',
            include __DIR__ . '/config/routes.config.php',
            include __DIR__ . '/config/navigation.config.php'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Zend\View\Helper\Navigation\AbstractHelper',
            'isAllowed',
            [$this, 'navigationPageIsAllowed']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Media',
            OmekaEvent::ENTITY_REMOVE_POST,
            [$this, 'deleteMediaFiles']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Representation\MediaRepresentation',
            OmekaEvent::REP_RESOURCE_JSON,
            [$this, 'filterHtmlMediaJsonLd']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Representation\MediaRepresentation',
            OmekaEvent::REP_RESOURCE_JSON,
            [$this, 'filterYoutubeMediaJsonLd']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            [OmekaEvent::API_SEARCH_QUERY, OmekaEvent::API_FIND_QUERY],
            [$this, 'filterMedia']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\SiteAdapter',
            [OmekaEvent::API_SEARCH_QUERY, OmekaEvent::API_FIND_QUERY],
            [$this, 'filterSites']
        );

        $sharedEventManager->attach(
            'Zend\Stdlib\DispatchableInterface',
            [MvcEvent::EVENT_DISPATCH],
            [$this, 'authorizeUserAgainstController'],
            1000
        );

        $sharedEventManager->attach(
            [
                'Omeka\Controller\Admin\Item',
                'Omeka\Controller\Admin\ItemSet',
                'Omeka\Controller\Admin\Media',
                'Omeka\Controller\Site\Item',
                'Omeka\Controller\Site\Media',
            ],
            'view.show.after',
            function (OmekaEvent $event) {
                $resource = $event->getTarget()->resource;
                echo $resource->embeddedJsonLd();
            }
        );

        $sharedEventManager->attach(
            [
                'Omeka\Controller\Admin\Item',
                'Omeka\Controller\Admin\ItemSet',
                'Omeka\Controller\Admin\Media',
                'Omeka\Controller\Site\Item',
            ],
            'view.browse.after',
            function (OmekaEvent $event) {
                $resources = $event->getTarget()->resources;
                foreach ($resources as $resource) {
                    echo $resource->embeddedJsonLd();
                }
            }
        );
    }

    /**
     * Determine whether a navigation page is allowed.
     *
     * @param Event $event
     * @return bool
     */
    public function navigationPageIsAllowed(Event $event)
    {
        $accepted = true;
        $params   = $event->getParams();
        $acl      = $params['acl'];
        $page     = $params['page'];

        if (!$acl) {
            return $accepted;
        }

        $resource  = $page->getResource();
        $privilege = $page->getPrivilege();

        if ($resource || $privilege) {
            $accepted = $acl->hasResource($resource)
                && $acl->userIsAllowed($resource, $privilege);
        }

        $event->stopPropagation();
        return $accepted;
    }

    /**
     * Delete all files associated with a removed Media entity.
     *
     * @param Event $event
     */
    public function deleteMediaFiles(Event $event)
    {
        $fileManager = $this->getServiceLocator()->get('Omeka\File\Manager');
        $media = $event->getTarget();

        if ($media->hasOriginal()) {
            $fileManager->deleteOriginal($media);
        }

        if ($media->hasThumbnails()) {
            $fileManager->deleteThumbnails($media);
       }
    }

    /**
     * Filter the JSON-LD for HTML media.
     *
     * @param Event $event
     */
    public function filterHtmlMediaJsonLd(Event $event)
    {
        if ('html' !== $event->getTarget()->ingester()) {
            return;
        }
        $data = $event->getTarget()->mediaData();
        $jsonLd = $event->getParam('jsonLd');
        $jsonLd['@context']['cnt'] = 'http://www.w3.org/2011/content#';
        $jsonLd['@type'] = 'cnt:ContentAsText';
        $jsonLd['cnt:chars'] = $data['html'];
        $jsonLd['cnt:characterEncoding'] = 'UTF-8';
        $event->setParam('jsonLd', $jsonLd);
    }

    /**
     * Filter the JSON-LD for YouTube media.
     *
     * @param Event $event
     */
    public function filterYoutubeMediaJsonLd(Event $event)
    {
        if ('youtube' !== $event->getTarget()->ingester()) {
            return;
        }
        $data = $event->getTarget()->mediaData();
        $jsonLd = $event->getParam('jsonLd');
        $jsonLd['@context']['time'] = 'http://www.w3.org/2006/time#';
        $jsonLd['time:hasBeginning'] = [
            '@value' => $data['start'],
            '@type' => 'time:seconds',
        ];
        $jsonLd['time:hasEnd'] = [
            '@value' => $data['end'],
            '@type' => 'time:seconds',
        ];
        $event->setParam('jsonLd', $jsonLd);
    }

    /**
     * Filter media belonging to private items.
     *
     * @param Event $event
     */
    public function filterMedia(Event $event)
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        if ($acl->userIsAllowed('Omeka\Entity\Resource', 'view-all')) {
            return;
        }

        $adapter = $event->getTarget();
        $itemAlias = $adapter->createAlias();
        $qb = $event->getParam('queryBuilder');
        $qb->innerJoin('Omeka\Entity\Media.item', $itemAlias);

        // Users can view media they do not own that belong to public items.
        $expression = $qb->expr()->eq("$itemAlias.isPublic", true);

        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        if ($identity) {
            // Users can view all media they own.
            $expression = $qb->expr()->orX(
                $expression,
                $qb->expr()->eq(
                    "$itemAlias.owner",
                    $adapter->createNamedParameter($qb, $identity)
                )
            );
        }
        $qb->andWhere($expression);
    }

    /**
     * Filter private sites.
     *
     * @param Event $event
     */
    public function filterSites(Event $event)
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        if ($acl->userIsAllowed('Omeka\Entity\Resource', 'view-all')) {
            return;
        }

        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');

        // Users can view sites they do not own that are public.
        $expression = $qb->expr()->eq("Omeka\Entity\Site.isPublic", true);

        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        if ($identity) {
            $sitePermissionAlias = $adapter->createAlias();
            $qb->leftJoin('Omeka\Entity\Site.sitePermissions', $sitePermissionAlias);

            $expression = $qb->expr()->orX(
                $expression,
                // Users can view all sites they own.
                $qb->expr()->eq(
                    "Omeka\Entity\Site.owner",
                    $adapter->createNamedParameter($qb, $identity)
                ),
                // Users can view sites where they have a role (any role).
                $qb->expr()->eq(
                    "$sitePermissionAlias.user",
                    $adapter->createNamedParameter($qb, $identity)
                )
            );
        }
        $qb->andWhere($expression);
    }

    /**
     * Authorize the current user against the dispatched controller and action.
     *
     * @param MvcEvent $event
     */
    public function authorizeUserAgainstController(MvcEvent $event)
    {
        $services = $event->getApplication()->getServiceManager();
        $t = $services->get('MvcTranslator');
        $acl = $services->get('Omeka\Acl');

        $routeMatch = $event->getRouteMatch();
        $controller = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');

        if (!$acl->userIsAllowed($controller, $action)) {
            $message = sprintf(
                $t->translate('Permission denied for the current user to access the %1$s action of the %2$s controller.'),
                $action,
                $controller
            );
            throw new \Omeka\Permissions\Exception\PermissionDeniedException($message);
        }
    }

    /**
     * Bootstrap the session manager.
     */
    private function bootstrapSession()
    {
        $serviceLocator = $this->getServiceLocator();
        $config = $serviceLocator->get('Config');

        $sessionConfig = new SessionConfig;
        $defaultOptions = [
            'name' => md5(OMEKA_PATH),
            'cookie_httponly' => true,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'gc_maxlifetime' => 1209600,
        ];
        $userOptions = isset($config['session']['config']) ? $config['session']['config'] : [];
        $sessionConfig->setOptions(array_merge($defaultOptions, $userOptions));

        $sessionSaveHandler = null;
        if (empty($config['session']['save_handler'])) {
            $currentVersion = $serviceLocator->get('Omeka\Settings')->get('version');
            if (Comparator::greaterThanOrEqualTo($currentVersion, '0.4.1-alpha')) {
                $sessionSaveHandler = new Db($serviceLocator->get('Omeka\Connection'));
            }
        } else {
            $sessionSaveHandler = $serviceLocator->get($config['session']['save_handler']);
        }

        $sessionManager = new SessionManager($sessionConfig, null, $sessionSaveHandler, []);
        Container::setDefaultManager($sessionManager);
    }

    /**
     * Bootstrap view helpers.
     */
    private function bootstrapViewHelpers()
    {
        $services = $this->getServiceLocator();
        $manager = $services->get('ViewHelperManager');

        // Set the custom form row partial.
        $manager->get('FormRow')->setPartial('common/form-row');

        // Set the ACL to the navigation helper.
        $manager->get('Navigation')->setAcl($services->get('Omeka\Acl'));
    }
}
