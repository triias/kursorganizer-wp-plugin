<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitizes shortcode attributes and builds the KursOrganizer iframe URL.
 */
class KursOrganizer_Shortcode_URL_Builder
{
    private const MAX_ID_LIST_ITEMS = 50;

    private const ALLOWED_DAYS = array(
        'montag' => 'Montag',
        'dienstag' => 'Dienstag',
        'mittwoch' => 'Mittwoch',
        'donnerstag' => 'Donnerstag',
        'freitag' => 'Freitag',
        'samstag' => 'Samstag',
        'sonntag' => 'Sonntag',
    );

    private const ALLOWED_LIST_TYPES = array(
        'all',
        'interest',
        'courses',
    );

    /**
     * Build a fully encoded iframe URL.
     *
     * @param string $base_url Configured KursOrganizer Web-App URL.
     * @param array  $atts Shortcode attributes.
     * @param array  $context Runtime parameters.
     * @return string
     */
    public static function build($base_url, $atts, $context)
    {
        return esc_url_raw(add_query_arg(self::params($atts, $context), esc_url_raw($base_url)));
    }

    /**
     * Query parameters of the iframe URL, also the source of the data-* attributes in embed mode.
     *
     * @param array $atts Shortcode attributes.
     * @param array $context Runtime parameters.
     * @return array<string, string|int>
     */
    public static function params($atts, $context)
    {
        $params = array();

        if (!empty($context['parent_url'])) {
            $params['parentUrl'] = esc_url_raw($context['parent_url']);
        }

        $city = isset($atts['city']) ? sanitize_text_field($atts['city']) : '';
        if ($city !== '') {
            $params['city'] = $city;
        }

        $single_ids = array(
            'instructorid' => 'instructorId',
            'coursetypeid' => 'courseTypeId',
            'locationid' => 'locationId',
            'coursecategoryid' => 'courseCategoryId',
        );

        foreach ($single_ids as $attribute => $query_key) {
            $value = self::sanitize_id(isset($atts[$attribute]) ? $atts[$attribute] : '');
            if ($value !== '') {
                $params[$query_key] = $value;
            }
        }

        $course_type_ids = self::sanitize_id_list(isset($atts['coursetypeids']) ? $atts['coursetypeids'] : '');
        if (!empty($course_type_ids)) {
            $params['courseTypeIds'] = implode(',', $course_type_ids);
        }

        $days = self::sanitize_day_filter(isset($atts['dayfilter']) ? $atts['dayfilter'] : '');
        if (!empty($days)) {
            $params['dayFilter'] = implode(',', $days);
        }

        $list_type = self::normalize_list_type(isset($atts['listtype']) ? $atts['listtype'] : '');
        if ($list_type !== '') {
            $params['listType'] = $list_type;
        }

        $params['showFilterMenu'] = self::normalize_boolean(
            isset($atts['showfiltermenu']) ? $atts['showfiltermenu'] : 'true'
        );
        $params['_v'] = isset($context['cache_version']) ? sanitize_text_field($context['cache_version']) : '';
        $params['_cb'] = isset($context['cache_buster']) ? absint($context['cache_buster']) : 1;

        if (!empty($context['custom_css_url'])) {
            $custom_css_url = esc_url_raw($context['custom_css_url']);
            if ($custom_css_url !== '') {
                $params['customCssUrl'] = $custom_css_url;
            }
        }

        $params['maxWidth'] = self::format_max_width(
            isset($context['max_width']) ? $context['max_width'] : '1200px'
        );

        return $params;
    }

    /**
     * Einbindungsart: Shortcode-Attribut `mode` schlägt die Plugin-Einstellung; alles Unbekannte ist iFrame.
     *
     * @param string|null $attribute Shortcode attribute `mode`.
     * @param string|null $setting Plugin setting `embed_mode`.
     * @return string "iframe" or "embed"
     */
    public static function resolve_mode($attribute, $setting)
    {
        $attribute = strtolower(trim((string) $attribute));
        if (in_array($attribute, array('iframe', 'embed'), true)) {
            return $attribute;
        }
        return strtolower(trim((string) $setting)) === 'embed' ? 'embed' : 'iframe';
    }

    /**
     * Loader des Embed-Modus: liegt neben dem iFrame-Build unter /embed/ derselben Web-App.
     *
     * @param string $normalized_app_url Result of KursOrganizer_API::normalize_app_url (…/build/).
     * @param string $cache_version Cache-busting version.
     * @return string
     */
    public static function embed_loader_url($normalized_app_url, $cache_version)
    {
        $base = preg_replace('#/build/$#', '/embed/', (string) $normalized_app_url);
        return esc_url_raw(add_query_arg(array('_v' => sanitize_text_field($cache_version)), $base . 'embed.js'));
    }

    /**
     * Embed-Modus: statt des iFrames ein <div data-ko-embed …>, das der Loader (embed.js) befüllt.
     * Dieselben Parameter wie die iFrame-URL als data-Attribute (camelCase → kebab-case, der Browser
     * liefert sie als dataset.locationId usw.); parentUrl, Cache-Parameter und customCssUrl bleiben
     * iFrame-only. data-fallback-src ist die vollständige iFrame-URL für den Rückfall des Loaders.
     *
     * @param string $id Element id.
     * @param array $params Result of self::params().
     * @param string $fallback_src Full iframe URL.
     * @return string
     */
    public static function render_embed($id, $params, $fallback_src)
    {
        $iframe_only = array('parentUrl', '_v', '_cb', 'customCssUrl');
        $attributes = array('id' => $id, 'class' => 'kursorganizer-embed');
        foreach ($params as $key => $value) {
            if (in_array($key, $iframe_only, true) || $value === '' || $value === null) {
                continue;
            }
            $name = 'data-' . strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', (string) $key));
            $attributes[$name] = (string) $value;
        }
        $attributes['data-fallback-src'] = (string) $fallback_src;

        $html = '<div data-ko-embed';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }
        return $html . '></div>';
    }

    /**
     * Render the iframe with attribute-safe output.
     */
    public static function render_iframe($id, $classes, $style, $src)
    {
        return sprintf(
            '<iframe id="%1$s" class="%2$s" title="%3$s" frameborder="0" style="%4$s" src="%5$s"></iframe>',
            esc_attr($id),
            esc_attr(implode(' ', $classes)),
            esc_attr__('KursOrganizer Kursbuchung', 'kursorganizer-wp-plugin'),
            esc_attr($style),
            esc_url($src)
        );
    }

    /**
     * Accept opaque IDs used by existing integrations without accepting delimiters.
     */
    public static function sanitize_id($value)
    {
        $value = trim((string) $value);
        return preg_match('/^[A-Za-z0-9_-]{1,128}$/', $value) ? $value : '';
    }

    /**
     * Sanitize and de-duplicate a comma separated ID list.
     */
    public static function sanitize_id_list($value)
    {
        $result = array();
        foreach (explode(',', (string) $value) as $candidate) {
            $candidate = self::sanitize_id($candidate);
            if ($candidate === '' || in_array($candidate, $result, true)) {
                continue;
            }
            $result[] = $candidate;
            if (count($result) >= self::MAX_ID_LIST_ITEMS) {
                break;
            }
        }
        return $result;
    }

    /**
     * Keep only documented German weekday names.
     */
    public static function sanitize_day_filter($value)
    {
        $result = array();
        foreach (explode(',', (string) $value) as $candidate) {
            $key = strtolower(trim($candidate));
            if (!isset(self::ALLOWED_DAYS[$key])) {
                continue;
            }
            $day = self::ALLOWED_DAYS[$key];
            if (!in_array($day, $result, true)) {
                $result[] = $day;
            }
        }
        return $result;
    }

    public static function normalize_boolean($value)
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, array('true', 'false'), true) ? $value : 'true';
    }

    /**
     * Accept only the offer views supported by the end-user application.
     * An empty result deliberately omits the parameter so the tenant default applies.
     */
    public static function normalize_list_type($value)
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, self::ALLOWED_LIST_TYPES, true) ? $value : '';
    }

    /**
     * Normalize max width and reject CSS fragments.
     */
    public static function format_max_width($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return '1200px';
        }
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value)) {
            $value .= 'px';
        }
        if (!preg_match('/^[0-9]+(?:\.[0-9]+)?(?:px|%|em|rem|vh|vw)$/', $value)) {
            return '1200px';
        }
        return $value;
    }
}
