<?php

namespace Moment;

use Moment\Provider\LocaleProviderInterface;

/**
 * MomentLocale
 * @package Moment
 * @author  Tino Ehrich (tino@bigpun.me)
 */
class MomentLocale
{
    const LOAD_CAREFULLY = 11;

    /**
     * @var Moment
     */
    private static $moment;

    /**
     * @var string
     */
    private static $locale = 'en_GB';

    /**
     * @var array
     */
    private static $localeContent = array();

    /**
     * @param Moment $moment
     */
    public static function setMoment(Moment $moment)
    {
        self::$moment = $moment;
    }

    /**
     * @param string|LocaleProviderInterface $locale
     *
     * @return void
     * @throws MomentException
     */
    public static function setLocale($locale)
    {
        if ($locale instanceof LocaleProviderInterface) {
            self::$locale        = $locale->getName();
            self::$localeContent = $locale->getDefinitions();

            return;
        }

        self::$locale = $locale;
        self::loadLocaleContent();
    }

    /**
     * @return void
     * @throws MomentException
     */
    public static function loadLocaleContent($loadCarefully = 0)
    {
        if(($loadCarefully === self::LOAD_CAREFULLY) && !empty(self::$localeContent)) {
            return;
        }
        $pathFile = __DIR__ . '/Locales/' . self::$locale . '.php';

        if (file_exists($pathFile) === false)
        {
            throw new MomentException('Locale does not exist: ' . $pathFile);
        }

        self::$localeContent = require $pathFile;
    }

    /**
     * @return array
     */
    public static function getLocaleContent()
    {
        return self::$localeContent;
    }

    /**
     * @param array $keys
     *
     * @return array|string|\Closure
     * @throws MomentException
     */
    public static function getLocaleString(array $keys)
    {
        $string = self::$localeContent;

        foreach ($keys as $key)
        {
            if (isset($string[$key]) === false)
            {
                throw new MomentException('Locale string does not exist for key: ' . join(' > ', $keys));
            }

            $string = $string[$key];
        }

        return $string;
    }

    /**
     * @param array $localeKeys
     * @param array $formatArgs
     *
     * @return string
     */
    public static function renderLocaleString(array $localeKeys, array $formatArgs = array())
    {
        // get locale handler
        $localeString = self::getLocaleString($localeKeys);

        // handle callback
        if ($localeString instanceof \Closure)
        {
            $localeString = call_user_func_array($localeString, $formatArgs);
        }

        return vsprintf($localeString, $formatArgs);
    }

    /**
     * @param string $format
     *
     * @return string
     */
    public static function prepareSpecialLocaleTags($format)
    {
        $placeholders = array(
            // months
            '(?<!\\\)F' => 'n__0001',
            '(?<!\\\)M' => 'n__0002',
            '(?<!\\\)f' => 'n__0005',
            // weekdays
            '(?<!\\\)l' => 'N__0003',
            '(?<!\\\)D' => 'N__0004',
        );

        foreach ($placeholders as $regexp => $tag)
        {
            $format = preg_replace('/' . $regexp . '/u', $tag, $format);
        }

        return $format;
    }

    /**
     * @param string $format
     *
     * @return string
     */
    public static function renderSpecialLocaleTags($format)
    {
        $placeholders = array(
            // months
            '\d{1,2}__0001' => 'months',
            '\d{1,2}__0002' => 'monthsShort',
            '\d{1,2}__0005' => 'monthsNominative',
            // weekdays
            '\d__0003'      => 'weekdays',
            '\d__0004'      => 'weekdaysShort',
        );

        foreach ($placeholders as $regexp => $tag)
        {
            preg_match_all('/(' . $regexp . ')/', $format, $match);

            if (isset($match[1]))
            {
                foreach ($match[1] as $date)
                {
                    list($localeIndex, $type) = explode('__', $date);
                    $localeString = self::renderLocaleString(array($tag, --$localeIndex));
                    $format = preg_replace('/' . $date . '/u', $localeString, $format);
                }
            }
        }

        return $format;
    }
}
