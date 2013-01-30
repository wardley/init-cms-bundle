<?php

/**
 * This file is part of the Networking package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Networking\InitCmsBundle\Controller;

use Networking\InitCmsBundle\Entity\Page,
    Networking\InitCmsBundle\Entity\PageSnapshot,
    Networking\InitCmsBundle\Helper\PageHelper,
    Networking\InitCmsBundle\Entity\LayoutBlock,
    Networking\InitCmsBundle\Entity\ContentRoute,
    Symfony\Bundle\FrameworkBundle\Controller\Controller,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpKernel\Exception\NotFoundHttpException,
    Symfony\Component\HttpFoundation\JsonResponse,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Template,
    Symfony\Component\Security\Core\Exception\AccessDeniedException,
    Symfony\Component\HttpFoundation\RedirectResponse,
    JMS\Serializer\SerializerInterface,
    Sonata\AdminBundle\Datagrid\ProxyQueryInterface,
    Sonata\AdminBundle\Controller\CRUDController,
    Sonata\AdminBundle\Admin\Admin as SontataAdmin,
    Sonata\AdminBundle\Exception\NoValueException,
    Sonata\MediaBundle\Admin\ORM\MediaAdmin,
    Sonata\MediaBundle\Provider\MediaProviderInterface,
    Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author net working AG <info@networking.ch>
 */
class PageAdminController extends CmsCRUDController
{

    /**
     * @Template()
     */
    public function translatePageAction(Request $request, $id, $locale)
    {
        $er = $this->getDoctrine()->getRepository($this->admin->getClass());

        /** @var $page Page */
        $page = $er->find($id);

        $em = $this->get('doctrine.orm.entity_manager');

        $pageCopy = new Page();

        $pageCopy->setWorkingTitle($page->getWorkingTitle());
        $pageCopy->setMetaTitle($page->getMetaTitle());
        $pageCopy->setUrl($page->getUrl());
        $pageCopy->setMetaKeyword($page->getMetaKeyword());
        $pageCopy->setMetaDescription($page->getMetaDescription());
//        $pageCopy->setNavigationTitle($page->getNavigationTitle());
        $pageCopy->setActiveFrom($page->getActiveFrom());
//        $pageCopy->setActiveTill($page->getActiveTill());
//        $pageCopy->setShowInNavigation($page->getShowInNavigation());
        $pageCopy->setIsHome($page->getIsHome());
        $pageCopy->setLocale($locale);
        $pageCopy->setTemplate($page->getTemplate());
        $pageCopy->setOriginal($page);
        $em->persist($pageCopy);
        $em->flush();

        $this->get('session')->setFlash('sonata_flash_success', $this->translate('message.translation_saved'));

        /** @var $admin \Networking\InitCmsBundle\Admin\PageAdmin */
        $admin = $this->container->get('networking_init_cms.page.admin.page');

        return $this->redirect($admin->generateUrl('edit', array('id' => $id)));
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\Security\Core\Exception\AccessDeniedException
     *
     * @param mixed $id
     *
     * @return Response|RedirectResponse
     */
    public function deleteAction($id)
    {
        $id = $this->get('request')->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('DELETE', $object)) {
            throw new AccessDeniedException();
        }

        if ($this->getRequest()->getMethod() == 'DELETE') {
            try {
                $this->admin->delete($object);
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(
                        array(
                            'result' => 'ok',
                            'objectId' => $this->admin->getNormalizedIdentifier($object),
                            'url' => $this->admin->generateUrl('list', array('locale' => $object->getLocale()))
                        )
                    );
                } else {
                    $this->get('session')->setFlash('sonata_flash_success', 'flash_delete_success');
                }
                $this->get('session')->setFlash('sonata_flash_success', 'flash_delete_success');
            } catch (ModelManagerException $e) {
                $this->get('session')->setFlash('sonata_flash_error', 'flash_delete_error');
            }

            return new RedirectResponse($this->admin->generateUrl('list', array('locale' => $object->getLocale())));
        }

        return $this->render(
            $this->admin->getTemplate('delete'),
            array(
                'object' => $object,
                'action' => 'delete'
            )
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $id
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function linkAction(Request $request, $id, $locale)
    {
        $er = $this->getDoctrine()->getRepository($this->admin->getClass());

        /** @var $page Page */
        $page = $er->find($id);

        if (!$page) {
            throw new NotFoundHttpException(sprintf('unable to find the Page with id : %s', $id));
        }

        if ($this->getRequest()->getMethod() == 'POST') {

            $linkPageId = $this->getRequest()->get('page');
            if (!$linkPageId) {
                $this->get('session')->setFlash('sonata_flash_error', 'flash_link_error');
            } else {
                /** @var $linkPage Page */
                $linkPage = $er->find($linkPageId);

                $page->addTranslation($linkPage);

                $em = $this->getDoctrine()->getManager();
                $em->persist($page);
                $em->flush();

                if ($this->isXmlHttpRequest()) {

                    $html = $this->renderView(
                        'NetworkingInitCmsBundle:PageAdmin:page_translation_settings.html.twig',
                        array('object' => $page, 'admin' => $this->admin)
                    );

                    return $this->renderJson(
                        array(
                            'result' => 'ok',
                            'html' => $html
                        )
                    );
                }


                $this->get('session')->setFlash('sonata_flash_success', 'flash_link_success');

                return new RedirectResponse($this->admin->generateUrl('edit', array('id' => $page->getId())));
            }
        }

        $pages = $er->findBy(array('locale' => $locale));

        if (count($pages)) {
            $pages = new \Doctrine\Common\Collections\ArrayCollection($pages);
            $originalLocale = $page->getLocale();
            $pages = $pages->filter(
                function (Page $linkPage) use ($originalLocale) {
                    return !in_array($originalLocale, $linkPage->getTranslatedLocales());

                }
            );
        }

        return $this->render(
            'NetworkingInitCmsBundle:PageAdmin:page_translation_link_list.html.twig',
            array(
                'page' => $page,
                'pages' => $pages,
                'locale' => $locale,
                'original_language' => \Locale::getDisplayLanguage($page->getLocale()),
                'language' => \Locale::getDisplayLanguage($locale),
                'admin' => $this->admin
            )
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $id
     * @param $translationId
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function unlinkAction(Request $request, $id, $translationId)
    {
        $er = $this->getDoctrine()->getRepository($this->admin->getClass());

        /** @var $page Page */
        $page = $er->find($id);
        $translatedPage = $er->find($translationId);

        if (!$page) {
            throw new NotFoundHttpException(sprintf('unable to find the Page with id : %s', $id));
        }

        if ($this->getRequest()->getMethod() == 'DELETE') {

            $page->removeTranslation($translatedPage);
            $translatedPage->removeTranslation($page);

            $em = $this->getDoctrine()->getManager();
            $em->persist($page);
            $em->persist($translatedPage);
            $em->flush();

            if ($this->isXmlHttpRequest()) {

                $html = $this->renderView(
                    'NetworkingInitCmsBundle:PageAdmin:page_translation_settings.html.twig',
                    array('object' => $page, 'admin' => $this->admin)
                );

                return $this->renderJson(
                    array(
                        'result' => 'ok',
                        'html' => $html
                    )
                );
            }


            $this->get('session')->setFlash('sonata_flash_success', 'flash_link_success');

            return new RedirectResponse($this->admin->generateUrl('edit', array('id' => $page->getId())));
        }

        return $this->render(
            'NetworkingInitCmsBundle:PageAdmin:page_translation_unlink.html.twig',
            array(
                'action' => 'unlink',
                'page' => $page,
                'translationId' => $translationId,
                'admin' => $this->admin,
                'translatedPage' => $translatedPage
            )
        );


    }

    /**
     *
     * @param \Networking\InitCmsBundle\Controller\Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function updateFormFieldElementAction(Request $request)
    {
        $twig = $this->get('twig');
        $helper = $this->get('sonata.admin.helper');

        $code = $request->get('code');
        $elementId = $request->get('elementId');
        $objectId = $request->get('objectId');
        $uniqid = $request->get('uniqid');

        /** @var $admin SontataAdmin */
        $admin = $this->container->get($code);
        $admin->setRequest($request);

        if ($uniqid) {
            $admin->setUniqid($uniqid);
        }

        $subject = $admin->getModelManager()->find($admin->getClass(), $objectId);
        if ($objectId && !$subject) {
            throw new NotFoundHttpException;
        }

        if (!$subject) {
            $subject = $admin->getNewInstance();
        }

        $admin->setSubject($subject);
        $formBuilder = $admin->getFormBuilder();

        $form = $formBuilder->getForm();
        $form->setData($subject);
        $form->bind($admin->getRequest());

        // create a fresh form taking into consideration deleted fields and layoutblock position
        $finalForm = $admin->getFormBuilder()->getForm();
        $finalForm->setData($subject);

        // bind the data
        $finalForm->setData($form->getData());

        $view = $helper->getChildFormView($finalForm->createView(), $elementId);

        $extension = $twig->getExtension('form');
        $extension->initRuntime($twig);
        $extension->renderer->setTheme($view, $admin->getFormTheme());

        return new Response($extension->renderer->searchAndRenderBlock($view, 'widget'));
    }

    /**
     * @param \Networking\InitCmsBundle\Controller\Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Request $request
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addLayoutBlockAction(Request $request)
    {
        $twig = $this->get('twig');
        $helper = $this->get('networking_init_cms.admin.helper');
        $code = $request->get('code');
        $elementId = $request->get('elementId');
        $objectId = $request->get('objectId');
        $uniqid = $request->get('uniqid');

        $admin = $this->container->get($code);
        $admin->setRequest($request);

        if ($uniqid) {
            $admin->setUniqid($uniqid);
        }

        $subject = $admin->getModelManager()->find($admin->getClass(), $objectId);
        if ($objectId && !$subject) {
            throw new NotFoundHttpException;
        }

        if (!$subject) {
            $subject = $admin->getNewInstance();
        }

        $admin->setSubject($subject);

        $data = array(
            'zone' => $request->get('zone'),
            'sortOrder' => $request->get('sortOrder'),
            'page' => $subject,
            'classType' => $request->get('classType')
        );


        $helper->setNewLayoutBlockParameters($data);
        list($fieldDescription, $form) = $helper->appendFormFieldElement($admin, $subject, $elementId);

        /** @var $form \Symfony\Component\Form\Form */
        $view = $helper->getChildFormView($form->createView(), $elementId);

        // render the widget
        // todo : fix this, the twig environment variable is not set inside the extension ...

        $extension = $twig->getExtension('form');
        $extension->initRuntime($twig);
        $extension->renderer->setTheme($view, $admin->getFormTheme());

        return new Response($extension->renderer->searchAndRenderBlock($view, 'widget'));
    }

    /**
     * @param ProxyQueryInterface $selectedModelQuery
     * @return RedirectResponse
     * @throws AccessDeniedException
     */
    public function batchActionPublish(ProxyQueryInterface $selectedModelQuery)
    {
        if ($this->admin->isGranted('PUBLISH') === false) {
            throw new AccessDeniedException();
        }

        $modelManager = $this->admin->getModelManager();

        $selectedModels = $selectedModelQuery->execute();


        // do the merge work here

        try {
            foreach ($selectedModels as $selectedModel) {
                $selectedModel->setStatus(Page::STATUS_PUBLISHED);
                $modelManager->update($selectedModel);
                $this->makeSnapshot($selectedModel);
            }

        } catch (\Exception $e) {
            $this->get('session')->setFlash('sonata_flash_error', 'flash_batch_publish_error');

            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }

        $this->get('session')->setFlash('sonata_flash_success', 'flash_batch_publish_success');

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateLayoutBlockSortAction(Request $request)
    {
        $layoutBlocks = $request->get('layoutBlocks');
        $zone = $request->get('zone');

        if ($layoutBlocks && is_array($layoutBlocks)) {
            foreach ($layoutBlocks as $key => $layoutBlockStr) {
                $sort = ++$key;
                $blockId = str_replace('layoutBlock_', '', $layoutBlockStr);
                $repo = $this->getDoctrine()->getRepository('NetworkingInitCmsBundle:LayoutBlock');

                try {
                    $layoutBlock = $repo->find($blockId);
                } catch (\Exception $e) {
                    $message = $e->getMessage();

                    return new JsonResponse(array('messageStatus' => 'error', 'message' => $message));
                }

                $layoutBlock->setSortOrder($sort);
                $layoutBlock->setZone($zone);

                $em = $this->getDoctrine()->getManager();
                $em->persist($layoutBlock);
                $em->flush();
            }
        }

        $data = array(
            'messageStatus' => 'success',
            'message' => $this->admin->trans('message.layout_blocks_sorted', array('zone' => $zone))
        );

        if ($layoutBlocks) {
            $pageStatus = $this->renderView(
                'NetworkingInitCmsBundle:PageAdmin:page_status_buttons.html.twig',
                array(
                    'admin' => $this->admin,
                    'object' => $layoutBlock->getPage()
                )
            );
            $data['pageStatusSettings'] = $pageStatus;
            $data['pageStatus'] = $this->admin->trans($layoutBlock->getPage()->getStatus());
        }

        return new JsonResponse($data);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deleteLayoutBlockAction(Request $request)
    {
        $layoutBlockStr = $request->get('layoutBlock');

        if ($layoutBlockStr) {
            $blockId = str_replace('layoutBlock_', '', $layoutBlockStr);
            $repo = $this->getDoctrine()->getRepository('NetworkingInitCmsBundle:LayoutBlock');

            try {
                $layoutBlock = $repo->find($blockId);
            } catch (\Exception $e) {
                $message = $e->getMessage();

                return new JsonResponse(array('messageStatus' => 'error', 'message' => $message));
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($layoutBlock);
            $em->remove($layoutBlock);
            $em->flush();
        }

        return new JsonResponse(array(
            'messageStatus' => 'success',
            'message' => $this->translate('message.layout_block_deleted')
        ));
    }

    /**
     * @param $string
     * @param array $params
     * @param null $domain
     * @return mixed
     */
    public function translate($string, $params = array(), $domain = null)
    {
        $translationDomain = $domain ? $domain : $this->admin->getTranslationDomain();

        return $this->get('translator')->trans($string, $params, $translationDomain);
    }

    /**
     * return the Response object associated to the view action
     *
     * @param null $id
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return Response
     */
    public function showAction($id = null)
    {
        $id = $this->get('request')->get($this->admin->getIdParameter());

        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('VIEW', $object)) {
            throw new AccessDeniedException();
        }

        $this->admin->setSubject($object);

        return $this->render(
            $this->admin->getTemplate('show'),
            array(
                'action' => 'show',
                'object' => $object,
                'elements' => $this->admin->getShow(),
            )
        );
    }

    /**
     * return the Response object associated to the edit action
     *
     *
     * @param mixed $id
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return Response
     */
    public function editAction($id = null)
    {
        // the key used to lookup the template
        $templateKey = 'edit';

        $id = $this->get('request')->get($this->admin->getIdParameter());

        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('EDIT', $object)) {
            throw new AccessDeniedException();
        }

        $this->admin->setSubject($object);

        /** @var $form \Symfony\Component\Form\Form */
        $form = $this->admin->getForm();
        $form->setData($object);

        if ($this->get('request')->getMethod() == 'POST') {
            $form->bind($this->get('request'));

            $isFormValid = $form->isValid();

            // persist if the form was valid and if in preview mode the preview was approved
            if ($isFormValid && (!$this->isInPreviewMode() || $this->isPreviewApproved())) {
                $this->admin->update($object);

                if ($object->getStatus() == Page::STATUS_PUBLISHED) {
                    $this->makeSnapshot($object);
                }

                if ($this->isXmlHttpRequest()) {

                    $view = $form->createView();

                    // set the theme for the current Admin Form
                    $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());


                    $pageSettingsTemplate = $this->render(
                        $this->admin->getTemplate($templateKey),
                        array(
                            'action' => 'edit',
                            'form' => $view,
                            'object' => $object,
                        )
                    );


                    return $this->renderJson(
                        array(
                            'result' => 'ok',
                            'objectId' => $this->admin->getNormalizedIdentifier($object),
                            'title' => $object->__toString(),
                            'messageStatus' => 'success',
                            'message' => $this->admin->trans('info.page_settings_updated'),
                            'pageStatus' => $this->admin->trans($object->getStatus()),
                            'pageSettings' => $pageSettingsTemplate->getContent()
                        )
                    );
                }

                $this->get('session')->setFlash('sonata_flash_success', 'flash_edit_success');

                // redirect to edit mode
                return $this->redirectTo($object);
            }

            // show an error message if the form failed validation
            if (!$isFormValid) {
                $this->get('session')->setFlash('sonata_flash_error', 'flash_edit_error');
            } elseif ($this->isPreviewRequested()) {
                // enable the preview template if the form was valid and preview was requested
                $templateKey = 'preview';
            }
        }

        $view = $form->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());

        $menuEr = $this->getDoctrine()->getRepository('NetworkingInitCmsBundle:MenuItem');

        $rootMenus = $menuEr->findBy(array('isRoot' => 1, 'locale' => $object->getLocale()));

        return $this->render(
            $this->admin->getTemplate($templateKey),
            array(
                'action' => 'edit',
                'form' => $view,
                'object' => $object,
                'rootMenus' => $rootMenus
            )
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function parentPageListAction(Request $request)
    {
        $locale = $request->get('locale');
        $pages = array();
        $er = $this->getDoctrine()->getRepository($this->admin->getClass());

        if ($result = $er->getParentPagesChoices($locale)) {
            foreach ($result as $page) {
                $pages[$page->getId()] = array($page->getAdminTitle());
            }
        }

        return $this->renderJson($pages);
    }

    /**
     * @param null $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function draftAction($id = null)
    {
        $id = $this->get('request')->get($this->admin->getIdParameter());

        return $this->changeStatus($id, Page::STATUS_DRAFT);
    }

    /**
     * @param null $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function reviewAction($id = null)
    {
        $id = $this->get('request')->get($this->admin->getIdParameter());

        return $this->changeStatus($id, Page::STATUS_REVIEW);
    }

    /**
     * @param $id
     * @param $status
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function changeStatus($id, $status)
    {

        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('EDIT', $object)) {
            throw new AccessDeniedException();
        }

        $this->admin->setSubject($object);

        $form = $this->admin->getForm();


        $object->setStatus($status);


        // persist if the form was valid and if in preview mode the preview was approved
        $this->admin->update($object);


        if ($this->isXmlHttpRequest()) {

            $view = $form->createView();

            // set the theme for the current Admin Form
            $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());

            $pageSettingsTemplate = $this->render(
                $this->admin->getTemplate('edit'),
                array(
                    'action' => 'edit',
                    'form' => $view,
                    'object' => $object,
                )
            );

            return $this->renderJson(
                array(
                    'result' => 'ok',
                    'objectId' => $this->admin->getNormalizedIdentifier($object),
                    'title' => $object->__toString(),
                    'pageStatus' => $this->admin->trans($object->getStatus()),
                    'pageSettings' => $pageSettingsTemplate
                )
            );
        }

        $this->get('session')->setFlash('sonata_flash_success', $this->admin->trans('flash_status_success'));

        return $this->redirect($this->admin->generateObjectUrl('edit', $object));
    }

    /**
     * @param null $id
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function cancelDraftAction($id = null)
    {
        $em = $this->getDoctrine()->getManager();
        $id = $this->get('request')->get($this->admin->getIdParameter());
        /** @var $draftPage Page */
        $draftPage = $this->admin->getObject($id);


        if (!$draftPage) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('EDIT', $draftPage)) {
            throw new AccessDeniedException();
        }

        if ($this->getRequest()->getMethod() == 'POST') {

            $pageSnapshot = $draftPage->getSnapshot();
            $contentRoute = $draftPage->getContentRoute();
            $em->clear();

            /** @var $serializer Serializer */
            $serializer = $this->get('serializer');

            /** @var $publishedPage Page */
            $publishedPage = $serializer->deserialize(
                $pageSnapshot->getVersionedData(),
                'Networking\InitCmsBundle\Entity\Page',
                'json'
            );

            // Save the layout blocks in a temp variable so that we can
            // assure the correct layout blocks will be saved and not
            // merged with the layout blocks from the draft page
            $tmpLayoutBlocks = $publishedPage->getLayoutBlock();


            // tell the entity manager to handle our published page
            // as if it came from the DB and not a serialized object
            $publishedPage = $em->merge($publishedPage);

            $contentRoute->setTemplate($pageSnapshot->getContentRoute()->getTemplate());
            $contentRoute->setPath($pageSnapshot->getContentRoute()->getPath());
            $em->merge($contentRoute);

            $publishedPage->setContentRoute($contentRoute);

            // Set the layout blocks of the NOW managed entity to
            // exactly that of the published version
            $publishedPage->resetLayoutBlock($tmpLayoutBlocks);

            $em->persist($publishedPage);
            $em->flush();

            if ($this->getRequest()->isXmlHttpRequest()) {
                $form = $this->admin->getForm();
                $form->setData($publishedPage);

                $pageSettingsTemplate = $this->render(
                    $this->admin->getTemplate('edit'),
                    array(
                        'action' => 'edit',
                        'form' => $form->createView(),
                        'object' => $publishedPage,
                    )
                );

                return $this->renderJson(
                    array(
                        'result' => 'ok',
                        'objectId' => $this->admin->getNormalizedIdentifier($publishedPage),
                        'title' => $publishedPage->__toString(),
                        'pageStatus' => $this->admin->trans($publishedPage->getStatus()),
                        'pageSettings' => $pageSettingsTemplate->getContent()
                    )
                );
            }


            return $this->redirect($this->admin->generateObjectUrl('edit', $publishedPage));

        }

        return $this->render(
            'NetworkingInitCmsBundle:PageAdmin:page_cancel_draft.html.twig',
            array(
                'action' => 'cancelDraft',
                'page' => $draftPage,
                'admin' => $this->admin
            )
        );
    }

    /**
     * @param null $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @Template()
     */
    public function publishAction($id = null)
    {
        $id = $this->get('request')->get($this->admin->getIdParameter());

        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if (false === $this->admin->isGranted('PUBLISH', $object)) {
            throw new AccessDeniedException();
        }

        $this->admin->setSubject($object);

        $form = $this->admin->getForm();


        $object->setStatus(Page::STATUS_PUBLISHED);


        // persist if the form was valid and if in preview mode the preview was approved
        $this->admin->update($object);

        if ($object->getStatus() == Page::STATUS_PUBLISHED) {
            $this->makeSnapshot($object);
        }

        if ($this->isXmlHttpRequest()) {

            $view = $form->createView();

            // set the theme for the current Admin Form
            $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());

            $pageSettingsTemplate = $this->render(
                $this->admin->getTemplate('edit'),
                array(
                    'action' => 'edit',
                    'form' => $view,
                    'object' => $object,
                )
            );

            return $this->renderJson(
                array(
                    'result' => 'ok',
                    'objectId' => $this->admin->getNormalizedIdentifier($object),
                    'title' => $object->__toString(),
                    'pageStatus' => $this->admin->trans($object->getStatus()),
                    'pageSettings' => $pageSettingsTemplate
                )
            );
        }

        $this->get('session')->setFlash('sonata_flash_success', $this->admin->trans('flash_publish_success'));

        return $this->redirect($this->admin->generateObjectUrl('edit', $object));
    }

    /**
     * Create a snapshot of a published page
     *
     * @param \Networking\InitCmsBundle\Entity\Page $page
     */
    protected function makeSnapshot(Page $page)
    {
        if (!$this->admin->isGranted('PUBLISH', $page)) {
            return;
        }

        $pageSnapshot = new PageSnapshot($page);

        $em = $this->getDoctrine()->getManager();

        $serializer = $this->get('serializer');

        foreach ($page->getLayoutBlock() as $layoutBlock) {
            /** @var $layoutBlock \Networking\InitCmsBundle\Entity\LayoutBlock */
            $layoutBlockContent = $em->getRepository($layoutBlock->getClassType())->find($layoutBlock->getObjectId());
            $layoutBlock->takeSnapshot($serializer->serialize($layoutBlockContent, 'json'));
        }

        $pageSnapshot->setVersionedData($serializer->serialize($page, 'json'))
            ->setPage($page);

        if ($oldPageSnapshot = $page->getSnapshot()) {
            $snapshotContentRoute = $oldPageSnapshot->getContentRoute();
        } else {
            $snapshotContentRoute = new ContentRoute();
        }

        $pageSnapshot->setContentRoute($snapshotContentRoute);

        $em->persist($pageSnapshot);
        $em->flush();

        $snapshotContentRoute->setPath(PageHelper::getPageRoutePath($page->getPath()));
        $snapshotContentRoute->setObjectId($pageSnapshot->getId());

        $em->persist($snapshotContentRoute);
        $em->flush();
    }


}
