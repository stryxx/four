<?php

declare(strict_types=1);

namespace Bolt\DataFixtures;

use Bolt\Configuration\FileLocations;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpClient\HttpClient;

class ImageFetchFixtures extends BaseFixture implements FixtureGroupInterface
{
    /** @var Collection */
    private $urls;

    /** @var FileLocations */
    private $fileLocations;

    private const AMOUNT = 10;
    private const MAX_AMOUNT = 50;

    public function __construct(FileLocations $fileLocations)
    {
        $this->urls = new Collection([
            'https://source.unsplash.com/1280x1024/?business,workspace,interior/',
            'https://source.unsplash.com/1280x1024/?cityscape,landscape,nature/',
            'https://source.unsplash.com/1280x1024/?animal,kitten,puppy,cute/',
            'https://source.unsplash.com/1280x1024/?technology/',
        ]);

        $this->fileLocations = $fileLocations;
    }

    public static function getGroups(): array
    {
        return ['with-images'];
    }

    public function load(ObjectManager $manager): void
    {
        $path = $this->fileLocations->get('files')->getBasepath();

        // We only fetch more images, if we're currently under the MAX_AMOUNT
        if ($this->getImagesIndex($path)->count() <= self::MAX_AMOUNT) {
            $this->fetchImages();
        }
    }

    private function fetchImages(): void
    {
        $outputPath = $this->fileLocations->get('files')->getBasepath() . '/stock/';

        if (! is_dir($outputPath)) {
            mkdir($outputPath);
        }

        $output = new ConsoleOutput();
        $progressBar = new ProgressBar($output, self::AMOUNT);

        $progressBar->start();

        for ($i = 1; $i <= self::AMOUNT; $i++) {
            $url = $this->urls->random() . random_int(10000, 99999);
            $filename = 'image_' . random_int(10000, 99999) . '.jpg';

            $client = HttpClient::create();
            $resource = fopen($outputPath . $filename, 'w');
            fwrite($resource, $client->request('GET', $url)->getContent());
            fclose($resource);

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
    }
}
