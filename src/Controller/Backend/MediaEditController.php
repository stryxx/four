<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Configuration\FileLocations;
use Bolt\Controller\CsrfTrait;
use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Media;
use Bolt\Factory\MediaFactory;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Webmozart\PathUtil\Path;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class MediaEditController extends TwigAwareController implements BackendZone
{
    use CsrfTrait;

    /** @var ObjectManager */
    private $em;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var FileLocations */
    private $fileLocations;

    /** @var MediaFactory */
    private $mediaFactory;

    public function __construct(
        ObjectManager $em,
        UrlGeneratorInterface $urlGenerator,
        FileLocations $fileLocations,
        MediaFactory $mediaFactory,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->fileLocations = $fileLocations;
        $this->mediaFactory = $mediaFactory;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * @Route("/media/edit/{id}", name="bolt_media_edit", methods={"GET"})
     */
    public function edit(?Media $media = null): Response
    {
        $context = [
            'media' => $media,
        ];

        return $this->renderTemplate('@bolt/media/edit.html.twig', $context);
    }

    /**
     * @Route("/media/edit/{id}", name="bolt_media_edit_post", methods={"POST"})
     */
    public function save(Request $request, ?Media $media = null): Response
    {
        $this->validateCsrf($request, 'media_edit');

        $post = $request->request->all();

        $media->setTitle($post['title'])
            ->setDescription($post['description'])
            ->setCopyright($post['copyright'])
            ->setOriginalFilename($post['originalFilename'])
            ->setCropX((int) $post['cropX'])
            ->setCropY((int) $post['cropY'])
            ->setCropZoom((float) $post['cropZoom']);

        $this->em->persist($media);
        $this->em->flush();

        $this->addFlash('success', 'content.updated_successfully');

        $url = $this->urlGenerator->generate('bolt_media_edit', ['id' => $media->getId()]);

        return new RedirectResponse($url);
    }

    /**
     * @Route("/media/new", name="bolt_media_new", methods={"GET"})
     */
    public function new(Request $request): RedirectResponse
    {
        $fileLocation = $request->query->get('location', 'files');
        $basepath = $this->fileLocations->get($fileLocation)->getBasepath();
        $file = '/' . $request->query->get('file');
        $filename = $basepath . $file;

        $relPath = Path::getDirectory('/' . $file);
        $relName = Path::getFilename($file);

        $file = new SplFileInfo($filename, $relPath, $relName);

        $media = $this->mediaFactory->createOrUpdateMedia($file, $fileLocation);

        $this->em->persist($media);
        $this->em->flush();

        $this->addFlash('success', 'content.created_successfully');

        $url = $this->urlGenerator->generate('bolt_media_edit', ['id' => $media->getId()]);

        return new RedirectResponse($url);
    }
}
