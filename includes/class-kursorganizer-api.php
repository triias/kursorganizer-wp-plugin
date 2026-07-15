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
        $url = esc_url_raw(trim((string) $url));
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
     * Validate that an organization ID belongs to a Web-App URL.
     *
     * @return true|WP_Error
     */
    public static function validate_organization_id($input_url, $input_org_id)
    {
        $normalized_url = self::normalize_app_url($input_url);
        if (is_wp_error($normalized_url)) {
            return $normalized_url;
        }

        $input_org_id = strtolower(trim((string) $input_org_id));
        if ($input_org_id === '') {
            return new WP_Error('invalid_organization_id', 'Die Organization ID fehlt.');
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
            return new WP_Error('invalid_response', 'Die API hat die Organization ID nicht bestätigt.');
        }
        if (!isset($result['data']['companyPublic']) || $result['data']['companyPublic'] === null) {
            return new WP_Error('company_not_found', 'Für diese Web-App URL wurde keine Organisation gefunden.');
        }
        if (empty($result['data']['companyPublic']['koOrganization']['id'])) {
            return new WP_Error('invalid_response', 'Die API-Antwort enthält keine Organization ID.');
        }

        $api_org_id = strtolower(trim((string) $result['data']['companyPublic']['koOrganization']['id']));
        if (!hash_equals($api_org_id, $input_org_id)) {
            return new WP_Error('organization_mismatch', 'Die Organization ID stimmt nicht mit der Web-App URL überein.');
        }

        return true;
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
     * Get organization ID from the API
     * Uses caching to avoid repeated requests
     * 
     * @return string|WP_Error Organization ID or error
     */
    public static function get_organization_id()
    {
        // Check if Origin is configured
        $origin = self::get_origin();
        if (empty($origin)) {
            return new WP_Error(
                'missing_origin',
                'Die KursOrganizer Web-App URL ist nicht konfiguriert oder ungültig. Bitte gehen Sie zu den Einstellungen und geben Sie eine gültige URL ein (z.B. https://app.ihrefirma.kursorganizer.com/build/).'
            );
        }

        // Try to get from cache first
        $cache_key = 'kursorganizer_org_id_' . md5($origin);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Query GetCompany to get organization ID
        // For public queries, we might need x-application-type header
        $query = 'query GetCompany {
            companyPublic {
                name
                host
                koOrganization {
                    id
                }
            }
        }';

        // Try with x-application-type header for public queries
        $result = self::query($query, 'GetCompany', [], null, [
            'x-application-type' => 'end-user-app'
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Check for GraphQL errors
        if (isset($result['errors'])) {
            return new WP_Error(
                'invalid_response',
                'Die API konnte die Organisation für die konfigurierte Web-App URL nicht bestätigen.'
            );
        }

        // Check if companyPublic is null (API couldn't find company for this origin)
        if (!isset($result['data']['companyPublic']) || $result['data']['companyPublic'] === null) {
            return new WP_Error(
                'company_not_found',
                sprintf(
                    'Keine Company für die Origin "%s" gefunden. Bitte prüfen Sie die KursOrganizer Web-App URL.',
                    $origin
                )
            );
        }

        if (!isset($result['data']['companyPublic']['koOrganization']['id'])) {
            return new WP_Error(
                'missing_org_id',
                'Die Organization ID konnte nicht aus der API-Antwort extrahiert werden. Bitte prüfen Sie Ihre KursOrganizer-Konfiguration.'
            );
        }

        $org_id = $result['data']['companyPublic']['koOrganization']['id'];

        // Cache for 1 hour
        set_transient($cache_key, $org_id, HOUR_IN_SECONDS);

        return $org_id;
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
        delete_transient('kursorganizer_org_id_' . $origin_hash);
        delete_transient('kursorganizer_course_types_' . $origin_hash);
        delete_transient('kursorganizer_locations_' . $origin_hash);
        delete_transient('kursorganizer_categories_' . $origin_hash);
        delete_transient('kursorganizer_instructors_' . $origin_hash);
    }
}
