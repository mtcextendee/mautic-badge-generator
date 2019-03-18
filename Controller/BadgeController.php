<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticBadgeGeneratorBundle\Controller;

use Mautic\CoreBundle\Exception as MauticException;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use MauticPlugin\MauticBadgeGeneratorBundle\Entity\Badge;
use MauticPlugin\MauticBadgeGeneratorBundle\Generator\BadgeGenerator;
use MauticPlugin\MauticBadgeGeneratorBundle\Model\BadgeModel;
use MauticPlugin\MauticBadgeGeneratorBundle\Uploader\BadgeUploader;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Form\Form;

class BadgeController extends AbstractStandardFormController
{

    /**
     * {@inheritdoc}
     */
    protected function getJsLoadMethodPrefix()
    {
        return 'badge.badge';
    }

    /**
     * {@inheritdoc}
     */
    protected function getModelName()
    {
        return 'badge.badge';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRouteBase()
    {
        return 'badge_generator';
    }

    /***
     * @param null $objectId
     *
     * @return string
     */
    protected function getSessionBase($objectId = null)
    {
        return 'badgeGenerator'.(($objectId) ? '.'.$objectId : '');
    }

    /**
     * @return string
     */
    protected function getControllerBase()
    {
        return 'MauticBadgeGeneratorBundle:Badge';
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        return $this->batchDeleteStandard();
    }

    /**
     * @param $objectId
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cloneAction($objectId)
    {
        return $this->cloneStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function editAction($objectId, $ignorePost = false)
    {
       return parent::editStandard($objectId, $ignorePost);
    }

    /**
     * @param Badge $entity
     * @param Form     $form
     * @param          $action
     * @param null     $objectId
     * @param bool     $isClone
     *
     * @return bool
     */
    protected function beforeEntitySave($entity, Form $form, $action, $objectId = null, $isClone = false)
    {
        if ($isClone) {
            return true;
        }

        $model =  $this->getModel('badge.badge');
        /** @var BadgeUploader $uploader */
        $uploader = $this->get('mautic.badge.uploader');
        $uploader->uploadFiles($entity, $this->request, $form);

        return true;
    }


    /**
     * @param int $page
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction($page = 1)
    {
        return $this->indexStandard($page);
    }

    /**
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newAction()
    {
      return  $this->newStandard();
    }

    /**
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        //set the page we came from
        $page = 1;
        $returnUrl = $this->generateUrl('mautic_badge_generator_index', ['page' => $page]);

        return $this->postActionRedirect(
            [
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'MauticBadgeGeneratorBundle:Badge:index',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_badge_generator_index',
                    'mauticContent' => 'badge',
                ],
            ]
        );
    }

    /**
     * @param $args
     * @param $action
     *
     * @return mixed
     */
    protected function getViewArguments(array $args, $action)
    {
        $viewParameters = [];

        switch ($action) {
            case 'index':
            case 'edit':
             $viewParameters['uploader'] = $this->get('mautic.badge.uploader');
            break;
        }
        $args['viewParameters'] = array_merge($args['viewParameters'], $viewParameters);

        return $args;
    }

    /**
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function deleteAction($objectId)
    {
        return $this->deleteStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param null $contactId
     */
    public function generateAction($objectId, $contactId = null)
    {
        /** @var BadgeGenerator $badgeGenerator */
        $badgeGenerator = $this->get('mautic.badge.generator');
        return $badgeGenerator->generate($objectId, $contactId);
    }

}