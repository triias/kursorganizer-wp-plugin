<?php
use PHPUnit\Framework\TestCase;

final class CustomCssTest extends TestCase
{
    public function testSanitizeRemovesStyleBreakoutAndAngleBrackets(): void
    {
        $css = "#content { color: red; }\r\n</style><script>alert(1)</script><p>a < b";
        $clean = KursOrganizer_Custom_CSS::sanitize($css);

        self::assertStringNotContainsString('</style', $clean);
        self::assertStringNotContainsString('<', $clean);
        self::assertStringContainsString('#content { color: red; }', $clean);
        self::assertStringNotContainsString("\r", $clean);
    }

    public function testSanitizeKeepsCssQuotesAndNewlinesButRejectsTooLong(): void
    {
        $css = "#content { background: url(\"a.png\"); }\n#content #footer { content: '>'; }";
        self::assertSame($css, KursOrganizer_Custom_CSS::sanitize($css));
        self::assertSame('', KursOrganizer_Custom_CSS::sanitize('   '));
        self::assertNull(KursOrganizer_Custom_CSS::sanitize(str_repeat('a', KursOrganizer_Custom_CSS::MAX_LENGTH + 1)));
    }

    public function testTranslateForEmbedRewritesPrefixAndContentId(): void
    {
        $css = "#content { background: #f3f8fc; }\n#content #footer .ant-btn-primary, .ant-card { color: red; }\n#content-extra, #contents { x: y }";
        $expected = "#ko-content { background: #f3f8fc; }\n#ko-content #footer .koe-btn-primary, .koe-card { color: red; }\n#content-extra, #contents { x: y }";

        self::assertSame($expected, KursOrganizer_Custom_CSS::translate_for_embed($css));
    }

    public function testStylesheetPrependsImportOnlyWhenOldUrlIsSet(): void
    {
        $css = '#content { color: red; }';
        self::assertSame($css, KursOrganizer_Custom_CSS::stylesheet($css, ''));
        self::assertSame(
            "@import url(\"https://wordpress.example/custom.css\");\n" . $css,
            KursOrganizer_Custom_CSS::stylesheet($css, 'https://wordpress.example/custom.css')
        );
    }

    public function testVersionChangesWithContent(): void
    {
        self::assertNotSame(
            KursOrganizer_Custom_CSS::version('a {}', ''),
            KursOrganizer_Custom_CSS::version('b {}', '')
        );
        self::assertNotSame(
            KursOrganizer_Custom_CSS::version('a {}', ''),
            KursOrganizer_Custom_CSS::version('a {}', 'https://wordpress.example/custom.css')
        );
    }

    public function testIframeParamsOmitCustomCssUrlWhenEmpty(): void
    {
        $params = KursOrganizer_Shortcode_URL_Builder::params(array(), array('custom_css_url' => ''));
        self::assertArrayNotHasKey('customCssUrl', $params);

        $params = KursOrganizer_Shortcode_URL_Builder::params(array(), array(
            'custom_css_url' => 'https://wordpress.example/wp-json/kursorganizer/v1/custom-css?v=abc',
        ));
        self::assertSame('https://wordpress.example/wp-json/kursorganizer/v1/custom-css?v=abc', $params['customCssUrl']);

        // Im Embed bleibt customCssUrl iFrame-only (nur in data-fallback-src)
        $html = KursOrganizer_Shortcode_URL_Builder::render_embed('kursorganizer-embed-1', $params, 'https://app/build/?x=1');
        self::assertStringNotContainsString('data-custom-css-url', $html);
    }
}
