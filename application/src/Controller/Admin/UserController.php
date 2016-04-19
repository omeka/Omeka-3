<?php
namespace Omeka\Controller\Admin;

use Omeka\Form\ConfirmForm;
use Omeka\Form\UserForm;
use Omeka\Form\UserKeyForm;
use Omeka\Form\UserPasswordForm;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Zend\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception;
use Zend\View\Model\ViewModel;

class UserController extends AbstractActionController
{
    public function addAction()
    {
        $serviceLocator = $this->getServiceLocator();
        $acl = $serviceLocator->get('Omeka\Acl');
        $changeRole = $acl->userIsAllowed('Omeka\Entity\User', 'change-role');
        $changeRoleAdmin = $acl->userIsAllowed('Omeka\Entity\User', 'change-role-admin');
        $activateUser = $acl->userIsAllowed('Omeka\Entity\User', 'activate-user');
        $form = new UserForm($serviceLocator, null, [
            'include_role' => $changeRole,
            'include_admin_roles' => $changeRoleAdmin,
            'include_is_active' => $activateUser,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $response = $this->api()->create('users', $formData);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $user = $response->getContent()->getEntity();
                    $serviceLocator->get('Omeka\Mailer')->sendUserActivation($user);
                    $this->messenger()->addSuccess('User created.');
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('email', 'asc');
        $response = $this->api()->search('users', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('users', $response->getContent());
        $view->setVariable('confirmForm', new ConfirmForm(
            $this->getServiceLocator(), null, [
                'button_value' => $this->translate('Confirm Delete'),
            ]
        ));
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('users', $this->params('id'));

        $view = new ViewModel;
        $view->setVariable('user', $response->getContent());
        return $view;
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('users', $this->params('id'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('resource', $response->getContent());
        return $view;
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('users', $this->params('id'));
        $user = $response->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('partialPath', 'omeka/admin/user/show-details');
        $view->setVariable('resourceLabel', 'user');
        $view->setVariable('resource', $user);
        return $view;
    }

    public function editAction()
    {
        $id = $this->params('id');

        $readResponse = $this->api()->read('users', $id);
        $user = $readResponse->getContent();
        $userEntity = $user->getEntity();

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $changeRole = $acl->userIsAllowed($userEntity, 'change-role');
        $changeRoleAdmin = $acl->userIsAllowed($userEntity, 'change-role-admin');
        $activateUser = $acl->userIsAllowed($userEntity, 'activate-user');
        $form = new UserForm($this->getServiceLocator(), null, [
            'include_role' => $changeRole,
            'include_admin_roles' => $changeRoleAdmin,
            'include_is_active' => $activateUser,
        ]);
        $data = $user->jsonSerialize();
        $form->setData($data);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $response = $this->api()->update('users', $id, $formData);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('User updated.');
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);
        return $view;
    }

    public function changePasswordAction()
    {
        $id = $this->params('id');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $readResponse = $this->api()->read('users', $id);
        $userRepresentation = $readResponse->getContent();
        $user = $userRepresentation->getEntity();
        $currentUser = $user === $this->identity();
        $form = new UserPasswordForm($this->getServiceLocator(), null, ['current_password' => $currentUser]);

        $view = new ViewModel;
        $view->setVariable('user', $userRepresentation);
        $view->setVariable('form', $form);

        if ($this->getRequest()->isPost()) {
            $acl = $this->getServiceLocator()->get('Omeka\Acl');
            if (!$acl->userIsAllowed($user, 'change-password')) {
                throw new Exception\PermissionDeniedException(
                    'User does not have permission to change the password'
                );
            }
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();
                if($currentUser && !$user->verifyPassword($values['current-password'])){
                        $this->messenger()->addError('The current password entered was invalid.');
                        return $view;
                    }
                $user->setPassword($values['password']);
                $em->flush();
                $this->messenger()->addSuccess('Password changed.');
                return $this->redirect()->toRoute(null, ['action' => 'edit'], [], true);
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        return $view;
    }

    public function editKeysAction()
    {
        $form = new UserKeyForm($this->getServiceLocator());
        $id = $this->params('id');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $readResponse = $this->api()->read('users', $id);
        $userRepresentation = $readResponse->getContent();
        $user = $userRepresentation->getEntity();
        $keys = $user->getKeys();

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        if (!$acl->userIsAllowed($user, 'edit-keys')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit API keys'
            );
        }

        if ($this->getRequest()->isPost()) {
            $postData = $this->params()->fromPost();
            $form->setData($postData);
            if ($form->isValid()) {
                $formData = $form->getData();
                $this->addKey($em, $user, $formData['new-key-label']);

                // Remove any keys marked for deletion
                if (!empty($postData['delete']) && is_array($postData['delete'])) {
                    foreach ($postData['delete'] as $deleteId) {
                        $keys->remove($deleteId);
                    }
                    $this->messenger()->addSuccess("Deleted key(s).");
                }
                $em->flush();
                return $this->redirect()->refresh();
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        // Only expose key IDs and values to the view
        $viewKeys = [];
        foreach ($keys as $id => $key) {
            $viewKeys[$id] = $key->getLabel();
        }

        $view = new ViewModel;
        $view->setVariable('user', $userRepresentation);
        $view->setVariable('keys', $viewKeys);
        $view->setVariable('form', $form);
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = new ConfirmForm($this->getServiceLocator());
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api()->delete('users', $this->params('id'));
                if ($response->isError()) {
                    $this->messenger()->addError('User could not be deleted');
                } else {
                    $this->messenger()->addSuccess('User successfully deleted');
                }
            } else {
                $this->messenger()->addError('User could not be deleted');
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    private function addKey($em, $user, $label)
    {
        if (empty($label)) {
            return;
        }

        $key = new ApiKey;
        $key->setId();
        $key->setLabel($label);
        $key->setOwner($user);
        $id = $key->getId();
        $credential = $key->setCredential();
        $em->persist($key);

        $this->messenger()->addSuccess('Key created.');
        $this->messenger()->addSuccess("ID: $id, Credential: $credential");
    }
}
