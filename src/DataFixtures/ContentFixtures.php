<?php

declare(strict_types=1);

namespace Bolt\DataFixtures;

use Bolt\Collection\DeepCollection;
use Bolt\Configuration\Config;
use Bolt\Configuration\FileLocations;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Enum\Statuses;
use Cocur\Slugify\Slugify;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Tightenco\Collect\Support\Collection;

class ContentFixtures extends BaseFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** @var Generator */
    private $faker;

    /** @var string */
    private $lastTitle = null;

    /** @var array */
    private $presetRecords = [];

    /** @var Collection */
    private $imagesIndex;

    /** @var Config */
    private $config;

    /** @var FileLocations */
    private $fileLocations;

    public function __construct(Config $config, FileLocations $fileLocations)
    {
        $this->faker = Factory::create();
        $this->presetRecords = $this->getPresetRecords();
        $this->config = $config;
        $this->fileLocations = $fileLocations;
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
            TaxonomyFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['with-images', 'without-images'];
    }

    public function load(ObjectManager $manager): void
    {
        $path = $this->fileLocations->get('files')->getBasepath();
        $this->imagesIndex = $this->getImagesIndex($path);

        $this->loadContent($manager);

        $manager->flush();
    }

    private function loadContent(ObjectManager $manager): void
    {
        /** @var TranslationRepository $translationRepository */
        $translationRepository = $manager->getRepository(Translation::class);

        foreach ($this->config->get('contenttypes') as $contentType) {
            // Only add Singletons on first run, not when appending
            if ($this->getOption('--append') && $contentType['singleton']) {
                continue;
            }

            $amount = $contentType['singleton'] ? 1 : (int) ($contentType['listing_records'] * 3);

            for ($i = 1; $i <= $amount; $i++) {
                if ($i === 1) {
                    $author = $this->getReference('user_admin');
                } else {
                    $author = $this->getRandomReference('user');
                }

                $content = new Content();
                $content->setContentType($contentType['slug']);
                $content->setAuthor($author);
                $content->setCreatedAt($this->faker->dateTimeBetween('-1 year'));
                $content->setModifiedAt($this->faker->dateTimeBetween('-1 year'));
                $content->setPublishedAt($this->faker->dateTimeBetween('-1 year'));
                $content->setDepublishedAt($this->faker->dateTimeBetween('-1 year'));

                $preset = $this->getPreset($contentType['slug']);

                if ($i === 1 || ! empty($preset)) {
                    $content->setStatus($preset['status'] ?? Statuses::PUBLISHED);
                } else {
                    $content->setStatus($this->getRandomStatus());
                }

                $sortorder = 1;
                foreach ($contentType['fields'] as $name => $fieldType) {
                    $field = Field::factory($fieldType, $name);

                    if (isset($preset[$name])) {
                        $field->setValue($preset[$name]);
                    } else {
                        $field->setValue($this->getValuesforFieldType($name, $fieldType));
                    }
                    $field->setSortorder($sortorder++ * 5);

                    $content->addField($field);

                    if ($fieldType['localize']) {
                        foreach ($contentType['locales'] as $locale) {
                            $translationRepository->translate($field, 'value', $locale, $field->getValue());
                        }
                    }
                }

                foreach ($contentType['taxonomy'] as $taxonomySlug) {
                    if ($taxonomySlug === 'categories') {
                        $taxonomyAmount = 2;
                    } elseif ($taxonomySlug === 'tags') {
                        $taxonomyAmount = 4;
                    } else {
                        $taxonomyAmount = 1;
                    }

                    foreach ($this->getRandomTaxonomies($taxonomySlug, $taxonomyAmount) as $taxonomy) {
                        $content->addTaxonomy($taxonomy);
                    }
                }

                $refKey = sprintf('content_%s_%s', $contentType['slug'], $content->getSlug());
                $this->addReference($refKey, $content);

                $manager->persist($content);
            }
        }
    }

    private function getRandomStatus(): string
    {
        $statuses = ['published', 'published', 'published', 'held', 'draft', 'timed'];

        return $statuses[array_rand($statuses)];
    }

    private function getValuesforFieldType(string $name, DeepCollection $field)
    {
        switch ($field['type']) {
            case 'html':
            case 'textarea':
            case 'markdown':
                $data = $this->faker->paragraphs(3, true);
                break;
            case 'image':
            case 'file':
                $randomImage = $this->imagesIndex->random();
                $data = [
                    'filename' => $randomImage->getRelativePathname(),
                    'alt' => $this->faker->sentence(4, true),
                    'title' => $this->faker->sentence(7, true),
                    'media' => '',
                ];
                break;
            case 'slug':
                $data = ( new Slugify())
                    ->slugify(
                        $this->lastTitle ?? $this->faker->sentence(3, true)
                    );
                break;
            case 'text':
                $data = $this->faker->sentence(6, true);
                break;
            case 'checkbox':
                $data = random_int(0, 1);
                break;
            case 'email':
                $data = [$this->faker->email];
                break;
            case 'templateselect':
                $data = [];
                break;
            default:
                $data = $this->faker->sentence(6, true);
        }

        if ($name === 'title' || $name === 'heading') {
            $this->lastTitle = $data;
        }

        return $data;
    }

    private function getPresetRecords(): array
    {
        $records['entries'][] = [
            'title' => 'This is a record in the "Entries" ContentType',
            'slug' => 'This is a record in the "Entries" ContentType',
        ];
        $records['blocks'][] = [
            'title' => 'About This Site',
            'slug' => 'about',
        ];
        $records['blocks'][] = [
            'title' => 'Search',
            'slug' => 'search',
        ];
        $records['blocks'][] = [
            'title' => '404 Page not found',
            'slug' => '404-not-found',
            'status' => Statuses::HELD,
        ];
        $records['blocks'][] = [
            'title' => '503 Service Unavailable (Maintenance Mode)',
            'slug' => '503-maintenance mode',
            'status' => Statuses::HELD,
        ];
        $records['tests'][] = [
            'selectfield' => 'bar',
            'multiselect' => 'Michelangelo',
            'slug' => 'title-of-the-test',
            'title' => 'Title of the test',
            'text_markup' => 'Text with <em>markup allowed</em>.',
            'text_plain' => 'Text with <strong>no</strong> markup allowed.',
            'textarea_field' => 'Textarea field with <em>simple</em> HTML in it.',
            'html_field' => 'HTML field with <em>simple</em> HTML in it.',
            'markdown_field' => 'Markdown field  with *simple* Markdown in it.',
            'text_not_sanitised' => 'Text field with <strong>markup</strong>, including <script>console.log(\'hoi\')</script>. The end.',
            'text_sanitised' => 'Text field with <strong>markup</strong>, including <script>console.log(\'hoi\')</script>. The end.',
        ];
        $records['pages'][] = [
            'heading' => 'This is a page',
            'slug' => 'this-is-a-page',
        ];

        // Only add this fixture if the file exists: It does in the "Git Clone", but not in the
        // "Composer create-project".
        $file = dirname(dirname(__DIR__)) . '/public/theme/skeleton/custom/setcontent_1.twig';
        if (file_exists($file)) {
            $records['pages'][] = [
                'heading' => 'Setcontent test page',
                'slug' => 'Setcontent test page',
                'template' => 'custom/setcontent_1.twig',
            ];
        }

        return $records;
    }

    private function getPreset(string $slug): array
    {
        if (isset($this->presetRecords[$slug]) && ! empty($this->presetRecords[$slug]) && ! $this->getOption('--append')) {
            $preset = array_shift($this->presetRecords[$slug]);
        } else {
            $preset = [];
        }

        return $preset;
    }
}
