<?php

namespace BeSimple\SoapClient\Xml\Path;

use PHPUnit\Framework\TestCase;

class RelativePathResolverTest extends TestCase
{
    /** @var RelativePathResolver */
    private $relativePathResolver;

    public function setUp(): void
    {
        $this->relativePathResolver = new RelativePathResolver();
    }

    /**
     * @param string $base
     * @param string $relative
     * @param string $assertPath
     * @dataProvider providePathInfo
     */
    public function testResolveRelativePathInUrl($base, $relative, $assertPath)
    {
        $path = $this->relativePathResolver->resolveRelativePathInUrl($base, $relative);

        self::assertEquals($assertPath, $path);
    }

    public function providePathInfo()
    {
        return [
            [
                'http://anyendpoint.tld:9999/path/to/endpoint.wsdl',
                '../Schemas/Common/SoapHeader.xsd',
                'http://anyendpoint.tld:9999/path/Schemas/Common/SoapHeader.xsd',
            ],
            [
                'http://endpoint-location.ltd/',
                'Document1.xsd',
                'http://endpoint-location.ltd/Document1.xsd',
            ],
            [
                'http://endpoint-location.ltd:8080/endpoint/',
                '../Schemas/Common/Document2.xsd',
                'http://endpoint-location.ltd:8080/Schemas/Common/Document2.xsd',
            ],
            [
                'http://endpoint-location.ltd/',
                '../Schemas/Common/Document3.xsd',
                'http://endpoint-location.ltd/Schemas/Common/Document3.xsd',
            ],
            [
                'http://endpoint-location.ltd/',
                '/Document4.xsd',
                'http://endpoint-location.ltd/Document4.xsd',
            ],
            [
                'http://endpoint-location.ltd',
                '/Document5.xsd',
                'http://endpoint-location.ltd/Document5.xsd',
            ],
            [
                'http://endpoint-location.ltd',
                'Document6.xsd',
                'http://endpoint-location.ltd/Document6.xsd',
            ]
        ];
    }
}
