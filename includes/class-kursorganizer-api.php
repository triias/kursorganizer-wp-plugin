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

        // Check if this is a local development environment
        if (
            strpos($main_app_url, 'localhost') !== false ||
            strpos($main_app_url, '127.0.0.1') !== false ||
            strpos($main_app_url, 'local') !== false
        ) {
            // Extract port from URL if present, otherwise default to 3000
            $parsed = parse_url($main_app_url);
            $port = isset($parsed['port']) ? $parsed['port'] : '3000';
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'http';
            $host = isset($parsed['host']) ? $parsed['host'] : 'localhost';

            return $scheme . '://' . $host . ':' . $port . '/graphql';
        }

        // Check if this is a stage environment
        if (strpos($main_app_url, '.stage.') !== false) {
            return 'https://api.stage.kursorganizer.com/graphql';
        }

        // Default to production
        return 'https://api.kursorganizer.com/graphql';
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
        $api_url = self::get_api_url();

        // Get Web-App URL (auto-detect if not set)
        $main_app_url = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';

        // Auto-detect if not configured
        if (empty($main_app_url)) {
            $main_app_url = self::auto_detect_app_url();
        }

        if (!empty($main_app_url)) {
            // Remove trailing slash if present
            $main_app_url = rtrim($main_app_url, '/');

            // Parse URL and reconstruct without path
            $parsed = parse_url($main_app_url);
            if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
                $origin = $parsed['scheme'] . '://' . $parsed['host'];

                // Include port if present
                if (isset($parsed['port'])) {
                    $origin .= ':' . $parsed['port'];
                }

                return $origin;
            }
        }

        // Fallback: If using local API, use localhost:8081 (as seen in GraphQL Playground)
        if (strpos($api_url, 'localhost') !== false || strpos($api_url, '127.0.0.1') !== false) {
            return 'http://localhost:8081';
        }

        return '';
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
        if (strpos($host, 'kursorganizer.com') !== false) {
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
        $api_url = self::get_api_url();
        $origin = self::get_origin();

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

        // Debug: Always log request details (for troubleshooting)
        error_log('=== KursOrganizer API Request ===');
        error_log('URL: ' . $api_url);
        error_log('Origin: ' . $origin);
        error_log('Headers: ' . print_r($headers, true));
        error_log('Query: ' . $operation_name);
        error_log('Body: ' . $body);

        $response = wp_remote_post($api_url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            // Enhanced error logging
            error_log('KursOrganizer API Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Debug: Always log response details (for troubleshooting)
        error_log('=== KursOrganizer API Response ===');
        error_log('Status Code: ' . $response_code);
        error_log('Full Body: ' . $body);
        $response_headers = wp_remote_retrieve_headers($response);
        error_log('Response Headers: ' . print_r($response_headers, true));

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse API response: ' . json_last_error_msg());
        }

        // Don't return error here - let the calling function handle it
        // This allows us to check for null companyPublic separately
        return $data;
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
            $error_message = $result['errors'][0]['message'] ?? 'Unknown GraphQL error';
            $full_response = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new WP_Error(
                'graphql_error',
                sprintf(
                    'API-Fehler: %s. Bitte prüfen Sie, ob die "KursOrganizer Web-App URL" korrekt ist und mit der konfigurierten Domain übereinstimmt. Aktuelle Origin: %s<br><br><strong>Vollständige API-Antwort:</strong><br><pre style="background: #f0f0f1; padding: 10px; overflow: auto; max-height: 300px;">%s</pre>',
                    $error_message,
                    $origin,
                    esc_html($full_response)
                )
            );
        }

        // Check if companyPublic is null (API couldn't find company for this origin)
        if (!isset($result['data']['companyPublic']) || $result['data']['companyPublic'] === null) {
            $full_response = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new WP_Error(
                'company_not_found',
                sprintf(
                    'Keine Company für die Origin "%s" gefunden. Bitte stellen Sie sicher, dass die "KursOrganizer Web-App URL" in den Einstellungen korrekt ist und mit Ihrer KursOrganizer-Installation übereinstimmt.<br><br><strong>Vollständige API-Antwort:</strong><br><pre style="background: #f0f0f1; padding: 10px; overflow: auto; max-height: 300px;">%s</pre>',
                    $origin,
                    esc_html($full_response)
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
        $origin_hash = md5(self::get_origin());
        delete_transient('kursorganizer_org_id_' . $origin_hash);
        delete_transient('kursorganizer_course_types_' . $origin_hash);
        delete_transient('kursorganizer_locations_' . $origin_hash);
        delete_transient('kursorganizer_categories_' . $origin_hash);
        delete_transient('kursorganizer_instructors_' . $origin_hash);
    }
}
