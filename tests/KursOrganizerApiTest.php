<?php
use PHPUnit\Framework\TestCase;

final class KursOrganizerApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ko_test_options'] = array('kursorganizer_settings' => array());
        $GLOBALS['ko_test_transients'] = array();
        $GLOBALS['ko_test_remote_calls'] = 0;
    }

    public function testUrlNormalizationAndHostMatching(): void
    {
        self::assertSame(
            'https://app.example.kursorganizer.com/build/',
            KursOrganizer_API::normalize_app_url('https://app.example.kursorganizer.com')
        );
        self::assertTrue(KursOrganizer_API::is_kursorganizer_host('app.example.kursorganizer.com'));
        self::assertFalse(KursOrganizer_API::is_kursorganizer_host('evilkursorganizer.com'));
        self::assertSame(
            'url_missing_build',
            KursOrganizer_API::normalize_app_url('https://example.org/not-build')->get_error_code()
        );
    }

    public function testUrlWithoutSchemeOrBuildIsCompleted(): void
    {
        foreach (array(
            'app.example.kursorganizer.com',
            ' app.example.kursorganizer.com/build ',
            'APP.Example.kursorganizer.com/',
            'https://app.example.kursorganizer.com/build/',
        ) as $input) {
            self::assertSame(
                'https://app.example.kursorganizer.com/build/',
                KursOrganizer_API::normalize_app_url($input),
                $input
            );
        }
        self::assertSame('http://localhost:8081/build/', KursOrganizer_API::normalize_app_url('http://localhost:8081'));
        self::assertSame('http://localhost:8081/build/', KursOrganizer_API::normalize_app_url('localhost:8081'));
        self::assertSame('invalid_url', KursOrganizer_API::normalize_app_url('')->get_error_code());
        self::assertSame('invalid_url', KursOrganizer_API::normalize_app_url('ftp://app.example.kursorganizer.com')->get_error_code());
    }

    public function testLookupReturnsNameAndIdOfTheSchoolBehindTheUrl(): void
    {
        $this->setCompanyResponse('org-123');
        self::assertSame(
            array('name' => 'Schwimmschule Beispiel', 'id' => 'org-123'),
            KursOrganizer_API::lookup_company('app.example.kursorganizer.com')
        );
        self::assertSame(1, $GLOBALS['ko_test_remote_calls']);
    }

    public function testConfiguredCompanyIsCachedAndFeedsTheOrganizationId(): void
    {
        $GLOBALS['ko_test_options']['kursorganizer_settings'] = array(
            'main_app_url' => 'https://app.example.kursorganizer.com/build/',
            // Altbestand aus 1.5.x: eine gespeicherte ID wird ignoriert, es zählt die Antwort der API
            'ko_organization_id' => 'stale-id',
        );
        $this->setCompanyResponse('org-123');

        self::assertSame('Schwimmschule Beispiel', KursOrganizer_API::get_company()['name']);
        self::assertSame('org-123', KursOrganizer_API::get_organization_id());
        self::assertSame(1, $GLOBALS['ko_test_remote_calls']);

        KursOrganizer_API::clear_cache();
        KursOrganizer_API::get_company();
        self::assertSame(2, $GLOBALS['ko_test_remote_calls']);
    }

    public function testFailedLookupIsCachedBrieflyAndClearedWithTheCache(): void
    {
        $GLOBALS['ko_test_options']['kursorganizer_settings'] = array(
            'main_app_url' => 'https://app.example.kursorganizer.com/build/',
        );
        $GLOBALS['ko_test_remote_response'] = new WP_Error('timeout', 'secret transport detail');
        self::assertSame('api_unavailable', KursOrganizer_API::get_organization_id()->get_error_code());

        // Während eines Ausfalls wartet die Einstellungsseite nicht bei jedem Aufruf auf den API-Timeout
        self::assertSame('api_unavailable', KursOrganizer_API::get_company()->get_error_code());
        self::assertSame(1, $GLOBALS['ko_test_remote_calls']);

        $this->setCompanyResponse('org-123');
        KursOrganizer_API::clear_cache();
        self::assertSame('org-123', KursOrganizer_API::get_organization_id());
    }

    public function testTransportHttpJsonAndCompanyErrors(): void
    {
        $GLOBALS['ko_test_remote_response'] = new WP_Error('timeout', 'secret transport detail');
        self::assertSame('api_unavailable', $this->validate()->get_error_code());

        $GLOBALS['ko_test_remote_response'] = array('response' => array('code' => 503), 'body' => '{}');
        self::assertSame('http_error', $this->validate()->get_error_code());

        $GLOBALS['ko_test_remote_response'] = array('response' => array('code' => 200), 'body' => 'not json');
        self::assertSame('invalid_response', $this->validate()->get_error_code());

        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('companyPublic' => null))),
        );
        self::assertSame('company_not_found', $this->validate()->get_error_code());

        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array('errors' => array(array('message' => 'internal detail')))),
        );
        self::assertSame('invalid_response', $this->validate()->get_error_code());

        // So antwortet die API auf eine Adresse ohne Schwimmschule (Feld ist non-nullable)
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'errors' => array(array(
                    'message' => 'Cannot return null for non-nullable field Query.companyPublic.',
                    'path' => array('companyPublic'),
                )),
                'data' => null,
            )),
        );
        self::assertSame('company_not_found', $this->validate()->get_error_code());

        // Ein anderer Fehler am selben Pfad (Serverfehler im Resolver) ist keine fehlende Schwimmschule
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'errors' => array(array('message' => 'Bad Request', 'path' => array('companyPublic'))),
                'data' => null,
            )),
        );
        self::assertSame('invalid_response', $this->validate()->get_error_code());

        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('companyPublic' => array('koOrganization' => array())))),
        );
        self::assertSame('invalid_response', $this->validate()->get_error_code());
    }

    private function validate()
    {
        return KursOrganizer_API::lookup_company('https://app.example.kursorganizer.com/build/');
    }

    private function setCompanyResponse($organizationId): void
    {
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'data' => array(
                    'companyPublic' => array(
                        'name' => 'Schwimmschule Beispiel',
                        'koOrganization' => array('id' => $organizationId),
                    ),
                ),
            )),
        );
    }
}
