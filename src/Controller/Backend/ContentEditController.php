<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Configuration\Config;
use Bolt\ContentFromFormdata;
use Bolt\Controller\BaseController;
use Bolt\Entity\Content;
use Bolt\Repository\TaxonomyRepository;
use Bolt\TemplateChooser;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class ContentEditController.
 *
 * @Security("has_role('ROLE_ADMIN')")
 */
class ContentEditController extends BaseController
{
    public function __construct(
        TaxonomyRepository $taxonomyRepository,
        Config $config,
        CsrfTokenManagerInterface $csrfTokenManager,
        TemplateChooser $templateChooser
    ) {
        $this->taxonomyRepository = $taxonomyRepository;
        parent::__construct($config, $csrfTokenManager);
        $this->templateChooser = $templateChooser;
    }

    /**
     * @Route("/edit/{id}", name="bolt_content_edit", methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function edit(Request $request, ?Content $content = null): Response
    {
        if (! $content) {
            $content = $this->createNewContent();
        }

        $twigvars = [
            'record' => $content,
            'locales' => $content->getLocales(),
            'currentlocale' => $this->getEditLocale($request, $content),
        ];

        return $this->renderTemplate('content/edit.html.twig', $twigvars);
    }

    /**
     * @Route("/edit/{id}", name="bolt_content_edit_post", methods={"POST"})
     */
    public function editPost(Request $request, ObjectManager $manager, UrlGeneratorInterface $urlGenerator, ?Content $content = null): Response
    {
        $this->validateToken($request);

        if (! $content) {
            $content = $this->createNewContent();
        }

        $content = ContentFromFormdata::update($content, $request->request->all());

        $manager->persist($content);
        $manager->flush();

        $this->addFlash('success', 'content.updated_successfully');

        $urlParams = [
            'id' => $content->getId(),
            'locale' => $this->getEditLocale($request, $content) ?: null,
        ];
        $url = $urlGenerator->generate('bolt_content_edit', $urlParams);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/viewsaved/{id}", name="bolt_content_edit_viewsave", methods={"POST"})
     */
    public function editViewSaved(Request $request, UrlGeneratorInterface $urlGenerator, ?Content $content = null): Response
    {
        $this->validateToken($request);

        $urlParams = [
            'slugOrId' => $content->getId(),
            'contentTypeSlug' => $content->getDefinition()->get('slug'),
        ];
        $url = $urlGenerator->generate('record', $urlParams);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/preview/{id}", name="bolt_content_edit_preview", methods={"POST"})
     */
    public function editPreview(Request $request, ?Content $content = null): Response
    {
        $this->validateToken($request);

        if (! $content) {
            $content = $this->createNewContent();
        }

        $content = ContentFromFormdata::update($content, $request);

        $recordSlug = $content->getDefinition()->get('singular_slug');

        $context = [
            'record' => $content,
            $recordSlug => $content,
        ];

        $templates = $this->templateChooser->record($content);

        return $this->renderTemplate($templates, $context);
    }

    private function contentFromPost(?Content $content, Request $request): Content
    {
        $post = $request->request->all();

        $locale = $this->getPostedLocale($post);

        if (! $content) {
            $content = new Content();
            $content->setAuthor($this->getUser());
            $content->setContentType($request->attributes->get('id'));
            $content->setDefinitionFromContentTypesConfig($this->config->get('contenttypes'));
        }

        $content->setStatus(Json::findScalar($post['status']));
        $content->setPublishedAt(new Carbon($post['publishedAt']));
        $content->setDepublishedAt(new Carbon($post['depublishedAt']));

        foreach ($post['fields'] as $key => $postfield) {
            $this->updateFieldFromPost($key, $postfield, $content, $locale);
        }

        if (isset($post['taxonomy'])) {
            foreach ($post['taxonomy'] as $key => $taxonomy) {
                $this->updateTaxonomyFromPost($key, $taxonomy, $content);
            }
        }

        return $content;
    }

    private function updateFieldFromPost(string $key, $postfield, Content $content, string $locale): void
    {
        if ($content->hasLocalizedField($key, $locale)) {
            $field = $content->getLocalizedField($key, $locale);
        } else {
            $fields = collect($content->getDefinition()->get('fields'));
            $field = Field::factory($fields->get($key), $key);
            $field->setName($key);
            $content->addField($field);
        }

        // If the value is an array that contains a string of JSON, parse it
        if (is_iterable($postfield) && Json::test(current($postfield))) {
            $postfield = Json::findArray($postfield);
        }

        $field->setValue((array) $postfield);

        if ($field->getDefinition()->get('localize')) {
            $field->setLocale($locale);
        } else {
            $field->setLocale('');
        }
    }

    private function updateTaxonomyFromPost(string $key, $taxonomy, Content $content): void
    {
        $taxonomy = collect(Json::findArray($taxonomy))->filter();

        // Remove old ones
        foreach ($content->getTaxonomies($key) as $current) {
            $content->removeTaxonomy($current);
        }

        // Then (re-) add selected ones
        foreach ($taxonomy as $slug) {
            $taxonomy = $this->taxonomyRepository->findOneBy([
                'type' => $key,
                'slug' => $slug,
            ]);

            if (! $taxonomy) {
                $taxonomy = Taxonomy::factory($key, $slug);
            }

            $content->addTaxonomy($taxonomy);
        }
    }

    private function validateToken(Request $request): void
    {
        $token = new CsrfToken('editrecord', $request->request->get('_csrf_token'));

        if (! $this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
    }

    private function getEditLocale(Request $request, Content $content): string
    {
        $locale = $request->query->get('locale', '');
        $locales = $content->getLocales();

        if (! $locales->contains($locale)) {
            $locale = $locales->first();
        }

        return $locale;
    }

    private function createNewContent()
    {
        $id = $request->attributes->get('id');
        return Content::factory($id, $this->getUser(), $this->config->get('contenttypes'));
    }
}
