<?php

declare(strict_types=1);

namespace Bolt\Twig;

use Bolt\Utils\LocaleHelper;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tightenco\Collect\Support\Collection;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LocaleExtension extends AbstractExtension
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var LocaleHelper */
    private $localeHelper;

    public function __construct(TranslatorInterface $translator, LocaleHelper $localeHelper)
    {
        $this->translator = $translator;
        $this->localeHelper = $localeHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];

        return [
            new TwigFilter('localedatetime', [$this, 'localedatetime'], $safe),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];
        $env = ['needs_environment' => true];

        return [
            new TwigFunction('__', [$this, 'translate'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('htmllang', [$this, 'dummy'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('locales', [$this, 'getLocales'], $env),
            new TwigFunction('locale', [$this, 'getLocale']),
            new TwigFunction('flag', [$this, 'flag'], $safe),
        ];
    }

    public function dummy($input = null)
    {
        return $input;
    }

    public function translate(string $id, array $parameters = [], $domain = null, $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @param string|Collection $localeCode
     */
    public function getLocale($localeCode): Collection
    {
        return $this->localeHelper->localeInfo($localeCode);
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     */
    public function getLocales(Environment $twig, ?Collection $localeCodes = null, bool $all = false): Collection
    {
        return $this->localeHelper->getLocales($twig, $localeCodes, $all);
    }

    /**
     * @param string|Collection $localeCode
     */
    public function flag($localeCode): string
    {
        $locale = $this->localeHelper->localeInfo($localeCode);

        return sprintf(
            '<span class="fp mr-1 %s" title="%s - %s / %s"></span>',
            $locale->get('flag'),
            $locale->get('name'),
            $locale->get('localizedname'),
            $locale->get('code')
        );
    }

    /**
     * @param string|\DateTime $dateTime
     */
    public function localedatetime($dateTime, string $format = '%B %e, %Y %H:%M', ?string $locale = '0'): string
    {
        if (! $dateTime instanceof \DateTime) {
            $dateTime = new \DateTime((string) $dateTime);
        }

        // Check for Windows to find and replace the %e modifier correctly
        // @see: http://php.net/strftime
        $os = mb_strtoupper(mb_substr(PHP_OS, 0, 3));
        $format = $os !== 'WIN' ? $format : preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);

        // According to http://php.net/manual/en/function.setlocale.php manual
        // if the second parameter is "0", the locale setting is not affected,
        // only the current setting is returned.
        $result = setlocale(LC_ALL, $locale);

        if ($result === false) {
            // This shouldn't occur, but.. Dude!
            // You ain't even got locale or English on your platform??
            // Various things we could do. We could fail miserably, but a more
            // graceful approach is to use the datetime to display a default
            // format
            // $this->systemLogger->error('No valid locale detected. Fallback on DateTime active.', ['event' => 'system']);

            return $dateTime->format('Y-m-d H:i:s');
        }
        $timestamp = $dateTime->getTimestamp();

        return strftime($format, $timestamp);
    }
}
