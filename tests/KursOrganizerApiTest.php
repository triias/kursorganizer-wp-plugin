<?php
use PHPUnit\Framework\TestCase;

final class KursOrganizerApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ko_test_options'] = array('kursorganizer_settings' => array());
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

    public function testValidationSuccessAndMismatch(): void
    {
        $this->setCompanyResponse('org-123');
        self::assertTrue(KursOrganizer_API::validate_organization_id('https://app.example.kursorganizer.com/build/', 'ORG-123'));
        self::assertSame(1, $GLOBALS['ko_test_remote_calls']);

        $this->setCompanyResponse('different');
        $result = KursOrganizer_API::validate_organization_id('https://app.example.kursorganizer.com/build/', 'org-123');
        self::assertSame('organization_mismatch', $result->get_error_code());
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

        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('companyPublic' => array('koOrganization' => array())))),
        );
        self::assertSame('invalid_response', $this->validate()->get_error_code());
    }

    private function validate()
    {
        return KursOrganizer_API::validate_organization_id(
            'https://app.example.kursorganizer.com/build/',
            'org-123'
        );
    }

    private function setCompanyResponse($organizationId): void
    {
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'data' => array(
                    'companyPublic' => array(
                        'koOrganization' => array('id' => $organizationId),
                    ),
                ),
            )),
        );
    }
}
