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

        return esc_url_raw(add_query_arg($params, esc_url_raw($base_url)));
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
