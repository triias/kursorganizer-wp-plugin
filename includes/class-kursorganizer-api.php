<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KursOrganizer API Helper Class
 * 
 * Handles GraphQL API requests to KursOrganizer endpoints
 */
class KursOrganizer_API
{
    /**
     * Normalize an app URL and enforce the documented /build/ path.
     *
     * @return string|WP_Error
     */
    public static function normalize_app_url($url)
    {
        $url = trim((string) $url);
        // Kunden bekommen meist nur den Host (app.ihrefirma.kursorganizer.com): Protokoll ergänzen,
        // bevor esc_url_raw ein http:// davorsetzt.
        if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            // Lokale Entwicklung läuft ohne TLS
            $url = (preg_match('#^(localhost|127\.0\.0\.1)(:|/|$)#i', $url) ? 'http://' : 'https://') . $url;
        }
        $url = esc_url_raw($url);
        $parsed = parse_url($url);

        if (
            !$parsed
            || empty($parsed['scheme'])
            || empty($parsed['host'])
            || !in_array(strtolower($parsed['scheme']), array('http', 'https'), true)
        ) {
            return new WP_Error('invalid_url', 'Die Web-App URL ist ungültig.');
        }

        if (isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment'])) {
            return new WP_Error('invalid_url', 'Die Web-App URL darf keine Zugangsdaten, Query-Parameter oder Fragmente enthalten.');
        }

        $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
        if ($path !== '' && $path !== '/build') {
            return new WP_Error('url_missing_build', 'Die URL muss auf "/build" enden.');
        }

        $normalized = strtolower($parsed['scheme']) . '://' . strtolower($parsed['host']);
        if (isset($parsed['port'])) {
            $normalized .= ':' . absint($parsed['port']);
        }

        return $normalized . '/build/';
    }

    public static function is_kursorganizer_host($host)
    {
        $host = strtolower(trim((string) $host, '.'));
        return $host === 'kursorganizer.com'
            || (strlen($host) > strlen('.kursorganizer.com')
                && substr($host, -strlen('.kursorganizer.com')) === '.kursorganizer.com');
    }

    private static function is_local_host($host)
    {
        $host = strtolower(trim((string) $host, '.'));
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || (strlen($host) > strlen('.local') && substr($host, -strlen('.local')) === '.local');
    }

    public static function get_api_url_for_app_url($main_app_url)
    {
        $parsed = parse_url($main_app_url);
        $host = $parsed && isset($parsed['host']) ? $parsed['host'] : '';

        if (self::is_local_host($host)) {
            $port = isset($parsed['port']) ? absint($parsed['port']) : 3000;
            $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'http';
            return $scheme . '://' . $host . ':' . $port . '/graphql';
        }

        if (strpos(strtolower($host), '.stage.') !== false) {
            return 'https://api.stage.kursorganizer.com/graphql';
        }

        return 'https://api.kursorganizer.com/graphql';
    }

    public static function get_origin_for_app_url($main_app_url)
    {
        $parsed = parse_url($main_app_url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return '';
        }

        $origin = strtolower($parsed['scheme']) . '://' . strtolower($parsed['host']);
        if (isset($parsed['port'])) {
            $origin .= ':' . absint($parsed['port']);
        }
        return $origin;
    }

    /**
     * Get the API endpoint URL based on the configured Web-App URL
     * 
     * @return string API endpoint URL
     */
    public static function get_api_url()
    {
        $options = get_option('kursorganizer_settings');

        // Auto-detect from Web-App URL
        $main_app_url = isset($options['main_app_url']) ? $options['main_app_url'] : '';

        // Auto-detect if not configured
        if (empty($main_app_url)) {
            $main_app_url = self::auto_detect_app_url();
        }

        return self::get_api_url_for_app_url($main_app_url);
    }

    /**
     * Get the Origin header value from the configured Web-App URL
     * The Origin should match the Web-App URL so the API can find the correct company
     * 
     * @return string Origin URL (without path)
     */
    public static function get_origin()
    {
        $options = get_option('kursorganizer_settings');
        // Get Web-App URL (auto-detect if not set)
        $main_app_url = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';

        // Auto-detect if not configured
        if (empty($main_app_url)) {
            $main_app_url = self::auto_detect_app_url();
        }

        return self::get_origin_for_app_url($main_app_url);
    }

    /**
     * Auto-detect KursOrganizer Web-App URL from WordPress domain
     * 
     * @return string Detected Web-App URL
     */
    private static function auto_detect_app_url()
    {
        // Get current WordPress site URL
        $site_url = get_site_url();
        $parsed = parse_url($site_url);

        if (!$parsed || !isset($parsed['host'])) {
            return '';
        }

        $host = $parsed['host'];

        // Remove www. prefix if present
        $host = preg_replace('/^www\./', '', $host);

        // For local development (.local domains), use localhost:8081
        if (strpos($host, '.local') !== false || strpos($host, 'localhost') !== false) {
            return 'http://localhost:8081';
        }

        // If it's already a kursorganizer.com domain, use it as-is
        if (self::is_kursorganizer_host($host)) {
            // Check if it's already an app.* URL
            if (strpos($host, 'app.') === 0) {
                return 'https://' . $host . '/build/';
            }
            // Otherwise, assume it's a subdomain like stage.dev-schule.kursorganizer.com
            return 'https://app.' . $host . '/build/';
        }

        // Extract the main domain name (remove TLD)
        // For example: schwimmschule-xyz.de → schwimmschule-xyz
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            $domain_name = $parts[count($parts) - 2]; // Second-to-last part
            // Build KursOrganizer App URL
            return 'https://app.' . $domain_name . '.kursorganizer.com/build/';
        }

        // Fallback: use host as-is
        return 'https://app.' . $host . '.kursorganizer.com/build/';
    }

    /**
     * Execute a GraphQL query
     * 
     * @param string $query The GraphQL query string
     * @param string $operation_name The operation name
     * @param array $variables Query variables
     * @param string|null $org_id Organization ID for x-ko-organization-id header
     * @param array $additional_headers Additional headers to send
     * @return array|WP_Error Response data or error
     */
    public static function query($query, $operation_name, $variables = [], $org_id = null, $additional_headers = [])
    {
        return self::request(
            self::get_api_url(),
            self::get_origin(),
            $query,
            $operation_name,
            $variables,
            $org_id,
            $additional_headers
        );
    }

    /**
     * Look up the school behind a Web-App URL. The server resolves the tenant from the Origin,
     * so the URL alone identifies it.
     *
     * @return array{name: string, id: string}|WP_Error
     */
    public static function lookup_company($input_url)
    {
        $normalized_url = self::normalize_app_url($input_url);
        if (is_wp_error($normalized_url)) {
            return $normalized_url;
        }

        $query = 'query GetCompany {
            companyPublic {
                name
                host
                koOrganization {
                    id
                }
            }
        }';

        $result = self::request(
            self::get_api_url_for_app_url($normalized_url),
            self::get_origin_for_app_url($normalized_url),
            $query,
            'GetCompany',
            array(),
            null,
            array('x-application-type' => 'end-user-app')
        );

        if (is_wp_error($result)) {
            return $result;
        }
        if (!empty($result['errors'])) {
            // Unbekannte Adresse: companyPublic ist im Schema non-nullable, der Server meldet "keine Schule"
            // deshalb als GraphQL-Fehler mit dem Pfad companyPublic statt als data.companyPublic = null.
            foreach ((array) $result['errors'] as $error) {
                if (
                    isset($error['path'], $error['message'])
                    && $error['path'] === array('companyPublic')
                    && strpos((string) $error['message'], 'Cannot return null') === 0
                ) {
                    return new WP_Error('company_not_found', 'Für diese Web-App URL wurde keine Schwimmschule gefunden.');
                }
            }
            return new WP_Error('invalid_response', 'Die API konnte die Schwimmschule zu dieser Web-App URL nicht bestätigen.');
        }
        if (!isset($result['data']['companyPublic']) || $result['data']['companyPublic'] === null) {
            return new WP_Error('company_not_found', 'Für diese Web-App URL wurde keine Schwimmschule gefunden.');
        }
        $company = $result['data']['companyPublic'];
        if (empty($company['koOrganization']['id'])) {
            return new WP_Error('invalid_response', 'Die API-Antwort enthält keine Organisation.');
        }

        return array(
            'name' => isset($company['name']) ? trim((string) $company['name']) : '',
            'id' => (string) $company['koOrganization']['id'],
        );
    }

    private static function request($api_url, $origin, $query, $operation_name, $variables, $org_id, $additional_headers)
    {

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Add Origin header
        if (!empty($origin)) {
            $headers['Origin'] = $origin;
        }

        // Add Organization ID header if provided
        if ($org_id) {
            $headers['x-ko-organization-id'] = $org_id;
        }

        // Add any additional headers
        if (!empty($additional_headers)) {
            $headers = array_merge($headers, $additional_headers);
        }

        $body = json_encode([
            'query' => $query,
            'operationName' => $operation_name,
            'variables' => $variables
        ]);

        self::debug_log('request', array(
            'operation' => $operation_name,
            'url' => $api_url,
            'origin' => $origin,
        ));

        $response = wp_remote_post($api_url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            self::debug_log('transport_error', array('operation' => $operation_name));
            return new WP_Error('api_unavailable', 'Die KursOrganizer API ist derzeit nicht erreichbar.');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        self::debug_log('response', array(
            'operation' => $operation_name,
            'status' => $response_code,
        ));

        if ($response_code < 200 || $response_code >= 300) {
            return new WP_Error('http_error', 'Die KursOrganizer API hat einen HTTP-Fehler zurückgegeben.');
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Die KursOrganizer API hat eine ungültige Antwort zurückgegeben.');
        }

        // Don't return error here - let the calling function handle it
        // This allows us to check for null companyPublic separately
        return $data;
    }

    private static function debug_log($event, $context = array())
    {
        $options = get_option('kursorganizer_settings', array());
        $plugin_debug = !empty($options['debug_mode']);
        if (!$plugin_debug || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $allowed = array_intersect_key($context, array_flip(array('operation', 'url', 'origin', 'status')));
        error_log('KursOrganizer API ' . sanitize_key($event) . ': ' . wp_json_encode($allowed));
    }

    /**
     * School behind the configured Web-App URL, cached for one hour. A failed lookup is cached for
     * five minutes so the settings page does not wait for the API timeout on every load during an outage.
     *
     * @return array{name: string, id: string}|WP_Error
     */
    public static function get_company()
    {
        $origin = self::get_origin();
        if (empty($origin)) {
            return new WP_Error(
                'missing_origin',
                'Die KursOrganizer Web-App URL ist nicht konfiguriert oder ungültig. Bitte gehen Sie zu den Einstellungen und geben Sie eine gültige URL ein (z.B. app.ihrefirma.kursorganizer.com).'
            );
        }

        $cache_key = 'kursorganizer_company_' . md5($origin);
        $cached = get_transient($cache_key);
        if (is_array($cached) || is_wp_error($cached)) {
            return $cached;
        }

        $company = self::lookup_company($origin);
        set_transient($cache_key, $company, is_wp_error($company) ? 300 : HOUR_IN_SECONDS);
        return $company;
    }

    /**
     * Organization ID for the x-ko-organization-id header of the generator queries.
     *
     * @return string|WP_Error Organization ID or error
     */
    public static function get_organization_id()
    {
        $company = self::get_company();
        return is_wp_error($company) ? $company : $company['id'];
    }

    /**
     * Get course types from the API
     * 
     * @return array|WP_Error Array of course types or error
     */
    public static function get_course_types()
    {
        // Try cache first
        $cache_key = 'kursorganizer_course_types_' . md5(self::get_origin());
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get organization ID first
        $org_id = self::get_organization_id();
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        $query = 'query GetCourseTypesPublic {
            courseTypesPublic {
                id
                name
                description
                showInWeb
                category {
                    id
                }
            }
        }';

        $result = self::query($query, 'GetCourseTypesPublic', [], $org_id);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!isset($result['data']['courseTypesPublic'])) {
            return new WP_Error('missing_data', 'No course types data in API response');
        }

        $course_types = $result['data']['courseTypesPublic'];

        // Cache for 1 hour
        set_transient($cache_key, $course_types, HOUR_IN_SECONDS);

        return $course_types;
    }

    /**
     * Get locations from the API
     * 
     * @return array|WP_Error Array of locations or error
     */
    public static function get_locations()
    {
        // Try cache first
        $cache_key = 'kursorganizer_locations_' . md5(self::get_origin());
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get organization ID first
        $org_id = self::get_organization_id();
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        $query = 'query GetLocationsPublic {
            locationsPublic {
                id
                abbreviation
                city
                name
                generalLocationContact {
                    email
                    phoneNumber
                }
            }
        }';

        $result = self::query($query, 'GetLocationsPublic', [], $org_id);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!isset($result['data']['locationsPublic'])) {
            return new WP_Error('missing_data', 'No locations data in API response');
        }

        $locations = $result['data']['locationsPublic'];

        // Cache for 1 hour
        set_transient($cache_key, $locations, HOUR_IN_SECONDS);

        return $locations;
    }

    /**
     * Get course categories from the API
     * 
     * @return array|WP_Error Array of course categories or error
     */
    public static function get_course_categories()
    {
        // Try cache first
        $cache_key = 'kursorganizer_categories_' . md5(self::get_origin());
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get organization ID first
        $org_id = self::get_organization_id();
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        $query = 'query GetCourseCategoriesPublic {
            courseCategoriesPublic {
                id
                name
            }
        }';

        $result = self::query($query, 'GetCourseCategoriesPublic', [], $org_id);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!isset($result['data']['courseCategoriesPublic'])) {
            return new WP_Error('missing_data', 'No course categories data in API response');
        }

        $categories = $result['data']['courseCategoriesPublic'];

        // Cache for 1 hour
        set_transient($cache_key, $categories, HOUR_IN_SECONDS);

        return $categories;
    }

    /**
     * Get instructors from the API
     * 
     * @return array|WP_Error Array of instructors or error
     */
    public static function get_instructors()
    {
        // Try cache first
        $cache_key = 'kursorganizer_instructors_' . md5(self::get_origin());
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get organization ID first
        $org_id = self::get_organization_id();
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        $query = 'query CourseGetInstructorsPublic {
            instructorsPublic {
                id
                firstname
                lastname
            }
        }';

        $result = self::query($query, 'CourseGetInstructorsPublic', [], $org_id);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!isset($result['data']['instructorsPublic'])) {
            return new WP_Error('missing_data', 'No instructors data in API response');
        }

        $instructors = $result['data']['instructorsPublic'];

        // Cache for 1 hour
        set_transient($cache_key, $instructors, HOUR_IN_SECONDS);

        return $instructors;
    }

    /**
     * Clear all cached API data
     */
    public static function clear_cache()
    {
        self::clear_cache_for_origin(self::get_origin());
    }

    public static function clear_cache_for_origin($origin)
    {
        if (empty($origin)) {
            return;
        }
        $origin_hash = md5($origin);
        delete_transient('kursorganizer_company_' . $origin_hash);
        delete_transient('kursorganizer_org_id_' . $origin_hash);
        delete_transient('kursorganizer_course_types_' . $origin_hash);
        delete_transient('kursorganizer_locations_' . $origin_hash);
        delete_transient('kursorganizer_categories_' . $origin_hash);
        delete_transient('kursorganizer_instructors_' . $origin_hash);
    }
}
