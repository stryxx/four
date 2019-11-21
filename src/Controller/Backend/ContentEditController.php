<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Common\Json;
use Bolt\Controller\CsrfTrait;
use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Entity\Relation;
use Bolt\Entity\Taxonomy;
use Bolt\Entity\User;
use Bolt\Enum\Statuses;
use Bolt\Event\Listener\ContentFillListener;
use Bolt\Repository\ContentRepository;
use Bolt\Repository\MediaRepository;
use Bolt\Repository\RelationRepository;
use Bolt\Repository\TaxonomyRepository;
use Bolt\TemplateChooser;
use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tightenco\Collect\Support\Collection;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class ContentEditController extends TwigAwareController implements BackendZone
{
    use CsrfTrait;

    /** @var TaxonomyRepository */
    private $taxonomyRepository;

    /** @var RelationRepository */
    private $relationRepository;

    /** @var ContentRepository */
    private $contentRepository;

    /** @var MediaRepository */
    private $mediaRepository;

    /** @var ObjectManager */
    private $em;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var TemplateChooser */
    private $templateChooser;

    /** @var ContentFillListener */
    private $contentFillListener;

    public function __construct(
        TaxonomyRepository $taxonomyRepository,
        RelationRepository $relationRepository,
        ContentRepository $contentRepository,
        MediaRepository $mediaRepository,
        ObjectManager $em,
        UrlGeneratorInterface $urlGenerator,
        ContentFillListener $contentFillListener,
        TemplateChooser $templateChooser,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->taxonomyRepository = $taxonomyRepository;
        $this->relationRepository = $relationRepository;
        $this->contentRepository = $contentRepository;
        $this->mediaRepository = $mediaRepository;
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->contentFillListener = $contentFillListener;
        $this->templateChooser = $templateChooser;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * @Route("/new/{contentType}", name="bolt_content_new", methods={"GET|POST"})
     */
    public function new(string $contentType, Request $request): Response
    {
        $content = new Content();

        /** @var User $user */
        $user = $this->getUser();

        $content->setAuthor($user);
        $content->setContentType($contentType);
        $this->contentFillListener->fillContent($content);

        if ($request->getMethod() === 'POST') {
            return $this->save($request, $content);
        }

        return $this->edit($request, $content);
    }

    /**
     * @Route("/edit/{id}", name="bolt_content_edit", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function edit(Request $request, Content $content): Response
    {
        $twigvars = [
            'record' => $content,
            'locales' => $content->getLocales(),
            'currentlocale' => $this->getEditLocale($request, $content),
        ];

        return $this->renderTemplate('@bolt/content/edit.html.twig', $twigvars);
    }

    /**
     * @Route("/edit/{id}", name="bolt_content_edit_post", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function save(Request $request, ?Content $content = null): Response
    {
        $this->validateCsrf($request, 'editrecord');

        $content = $this->contentFromPost($content, $request);

        $this->em->persist($content);
        $this->em->flush();

        $this->addFlash('success', 'content.updated_successfully');

        $urlParams = [
            'id' => $content->getId(),
            'edit_locale' => $this->getEditLocale($request, $content) ?: null,
        ];
        $url = $this->urlGenerator->generate('bolt_content_edit', $urlParams);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/viewsaved/{id}", name="bolt_content_edit_viewsave", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function viewSaved(Request $request, ?Content $content = null): RedirectResponse
    {
        $this->validateCsrf($request, 'editrecord');

        $urlParams = [
            'slugOrId' => $content->getId(),
            'contentTypeSlug' => $content->getDefinition()->get('slug'),
        ];

        $url = $this->urlGenerator->generate('record', $urlParams);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/preview/{id}", name="bolt_content_edit_preview", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function preview(Request $request, ?Content $content = null): Response
    {
        $this->validateCsrf($request, 'editrecord');

        $content = $this->contentFromPost($content, $request);
        $recordSlug = $content->getDefinition()->get('singular_slug');

        $context = [
            'record' => $content,
            $recordSlug => $content,
        ];

        $templates = $this->templateChooser->forRecord($content);

        return $this->renderTemplate($templates, $context);
    }

    /**
     * @Route("/duplicate/{id}", name="bolt_content_duplicate", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function duplicate(Request $request, Content $content): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $content->setId(null);
        $content->setCreatedAt(null);
        $content->setAuthor($user);
        $content->setModifiedAt(null);
        $content->setDepublishedAt(null);
        $content->setPublishedAt(null);

        $twigvars = [
            'record' => $content,
            'locales' => $content->getLocales(),
            'currentlocale' => $this->getEditLocale($request, $content),
        ];

        return $this->renderTemplate('@bolt/content/edit.html.twig', $twigvars);
    }

    /**
     * @Route("/duplicate/{id}", name="bolt_content_duplicate_post", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function duplicateSave(Request $request, ?Content $content = null): Response
    {
        return $this->new($content->getContentType(), $request);
    }

    /**
     * @Route("/status/{id}", name="bolt_content_status", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function status(Request $request, Content $content): Response
    {
        if (! $this->isCsrfTokenValid('status', $request->get('token'))) {
            $url = $this->urlGenerator->generate('bolt_dashboard');
            return new RedirectResponse($url);
        }

        $content->setStatus($request->get('status'));

        $this->em->persist($content);
        $this->em->flush();

        $this->addFlash('success', 'content.status_changed_successfully');

        $params = ['contentType' => $content->getContentTypeSlug()];
        $url = $this->urlGenerator->generate('bolt_content_overview', $params);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/delete/{id}", name="bolt_content_delete", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function delete(Request $request, Content $content): Response
    {
        if (! $this->isCsrfTokenValid('delete', $request->get('token'))) {
            $url = $this->urlGenerator->generate('bolt_dashboard');
            return new RedirectResponse($url);
        }

        $this->em->remove($content);
        $this->em->flush();

        $this->addFlash('success', 'content.deleted_successfully');

        $params = ['contentType' => $content->getContentTypeSlug()];
        $url = $this->urlGenerator->generate('bolt_content_overview', $params);

        return new RedirectResponse($url);
    }

    private function contentFromPost(?Content $content, Request $request): Content
    {
        $formData = $request->request->all();

        $locale = $this->getPostedLocale($formData);

        /** @var User $user */
        $user = $this->getUser();

        if ($content === null) {
            $content = new Content();
            $content->setAuthor($user);
            $content->setContentType($request->attributes->get('id'));
        }
        $this->contentFillListener->fillContent($content);

        // @todo dumb status validation, to be replaced with Symfony Form validation
        $status = Json::findScalar($formData['status']);
        if (in_array($status, Statuses::all(), true) === true) {
            $content->setStatus($status);
        }

        $content->setPublishedAt(new Carbon($formData['publishedAt']));
        $content->setDepublishedAt(new Carbon($formData['depublishedAt']));

        if (isset($formData['fields'])) {
            foreach ($formData['fields'] as $fieldName => $fieldValue) {
                $this->updateField($content, $fieldName, $fieldValue, $locale);
            }
        }

        if (isset($formData['taxonomy'])) {
            foreach ($formData['taxonomy'] as $fieldName => $taxonomy) {
                $this->updateTaxonomy($content, $fieldName, $taxonomy);
            }
        }

        if (isset($formData['relationship'])) {
            foreach ($formData['relationship'] as $relation) {
                $this->updateRelation($content, $relation);
            }
        }

        return $content;
    }

    private function updateField(Content $content, string $fieldName, $value, ?string $locale): void
    {
        /** @var Field $field */
        $field = null;

        if ($content->hasField($fieldName)) {
            $field = $content->getField($fieldName);
        }

        // If the Field exists, but it has the wrong type, we'll remove the existing one.
        if (($field !== null) && ! $content->hasField($fieldName, true)) {
            $content->removeField($field);
            $this->em->remove($field);
            $this->em->flush();
            $field = null;
        }

        // Perhaps create a new Field..
        if (! $field) {
            $fields = $content->getDefinition()->get('fields');
            $field = Field::factory($fields->get($fieldName), $fieldName);
            $field->setName($fieldName);

            $content->addField($field);
        }

        // If the Field is translatable, set the locale
        if ($field->getDefinition()->get('localize')) {
            $field->setLocale($locale);

            if ($this->em->contains($field)) {
                $this->em->refresh($field);
            }
        }

        // If the value is an array that contains a string of JSON, parse it
        if (is_iterable($value) && Json::test(current($value))) {
            $value = Json::findArray($value);
        }

        $field->setValue($value);

        // If the Field is MediaAware, link it to an existing Media Entity
        if ($field instanceof Field\MediaAware) {
            $field->setLinkedMedia($this->mediaRepository);
        }
    }

    private function updateTaxonomy(Content $content, string $key, $taxonomy): void
    {
        $taxonomy = (new Collection(Json::findArray($taxonomy)))->filter();

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

            if ($taxonomy === null) {
                $taxonomy = Taxonomy::factory($key, $slug);
            }

            $content->addTaxonomy($taxonomy);
        }
    }

    private function updateRelation(Content $content, $newRelations): void
    {
        $newRelations = (new Collection(Json::findArray($newRelations)))->filter();
        $currentRelations = $this->relationRepository->findRelations($content, null, true, null, false);

        // Remove old ones
        foreach ($currentRelations as $currentRelation) {
            $this->em->remove($currentRelation);
        }

        // Then (re-) add selected ones
        foreach ($newRelations as $id) {
            $contentTo = $this->contentRepository->findOneBy(['id' => $id]);

            if ($contentTo === null) {
                continue; // Don't add relations to things that have gone missing
            }

            $relation = new Relation($content, $contentTo);

            $this->em->persist($relation);
        }
    }

    private function getEditLocale(Request $request, Content $content): string
    {
        $locale = $request->query->get('edit_locale', '');
        $locales = $content->getLocales();

        if ($locales->contains($locale) === false) {
            $locale = $content->getDefaultLocale();
        }

        return $locale;
    }

    private function getPostedLocale(array $post): ?string
    {
        return $post['_edit_locale'] ?: null;
    }
}
