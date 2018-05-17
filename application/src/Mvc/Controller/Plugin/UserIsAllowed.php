<?php
namespace Omeka\Mvc\Controller\Plugin;

use Omeka\Api\ResourceInterface;
use Omeka\Permissions\Acl;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Controller plugin for authorize the current user.
 */
class UserIsAllowed extends AbstractPlugin
{
    /**
     * @var Acl
     */
    protected $acl;

    /**
     * Construct the plugin.
     *
     * @param Acl $acl
     */
    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * Authorize the current user.
     *
     * @param ResourceInterface|string $resource
     * @param string $privilege
     * @return bool
     */
    public function __invoke($resource = null, $privilege = null)
    {
        return $this->acl->userIsAllowed($resource, $privilege);
    }
}
