<?php

namespace BeSimple\SoapClient;

use BeSimple\SoapBundle\Soap\SoapAttachment;
use BeSimple\SoapCommon\ClassMap;
use BeSimple\SoapCommon\SoapOptions\SoapOptions;
use BeSimple\SoapCommon\SoapOptionsBuilder;
use Exception;
use Fixtures\EnumValutes;
use Fixtures\GenerateTestRequest;
use Fixtures\GetUKLocationByCounty;
use SoapHeader;
use PHPUnit\Framework\TestCase;

class SoapClientTest extends TestCase
{
    const CACHE_DIR = __DIR__ . '/../../../cache';
    const FIXTURES_DIR = __DIR__ . '/../../Fixtures';
    const TEST_HTTP_URL = 'http://localhost:8000/tests';
    const TEST_ENDPOINT_UK = 'http://www.cbr.ru/DailyInfoWebServ/DailyInfo.asmx';
    const TEST_REMOTE_WSDL_UK = 'http://www.cbr.ru/DailyInfoWebServ/DailyInfo.asmx?WSDL';
    const TEST_REMOTE_ENDPOINT_NOT_WORKING = 'http://www.nosuchserverexist.tld/doesnotexist.endpoint';
    const TEST_REMOTE_WSDL_NOT_WORKING = 'http://www.nosuchserverexist.tld/doesnotexist.endpoint?wsdl';

    private $localWebServerProcess;

    public function setUp(): void
    {
        $this->localWebServerProcess = popen('php -S localhost:8000 > /dev/null 2>&1 &', 'r');
    }

    public function tearDown(): void
    {
        pclose($this->localWebServerProcess);
    }

    public function testSoapCall()
    {
        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithDefaults(),
            SoapOptionsBuilder::createWithDefaults(self::TEST_REMOTE_WSDL_UK)
        );
        $enumValutesRequest = new EnumValutes();
        $enumValutesRequest->Seld = true;
        $soapResponse = $soapClient->soapCall('EnumValutes', [$enumValutesRequest]);

        self::assertStringContainsString('EnumValutesResult', $soapResponse->getContent());
        self::assertStringContainsString('</EnumValutesResponse>', $soapResponse->getContent());
        self::assertEquals(self::TEST_ENDPOINT_UK, $soapResponse->getLocation());
    }

    public function testSoapCallWithCustomEndpointValid()
    {
        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithEndpointLocation(self::TEST_ENDPOINT_UK),
            SoapOptionsBuilder::createWithDefaults(self::TEST_REMOTE_WSDL_UK)
        );
        $enumValutesRequest = new EnumValutes();
        $enumValutesRequest->Seld = true;
        $soapResponse = $soapClient->soapCall('EnumValutes', [$enumValutesRequest]);

        self::assertStringContainsString('Connection: close', $soapResponse->getTracingData()->getLastRequestHeaders());
        self::assertStringContainsString('<ns1:EnumValutes><ns1:Seld>true</ns1:Seld>', $soapResponse->getTracingData()->getLastRequest());
        self::assertStringContainsString('EnumValutesResult', $soapResponse->getContent());
        self::assertStringContainsString('</EnumValutesResponse>', $soapResponse->getContent());
        self::assertEquals(self::TEST_ENDPOINT_UK, $soapResponse->getLocation());
    }

    public function testSoapCallWithKeepAliveTrue()
    {
        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithEndpointLocation(self::TEST_ENDPOINT_UK),
            SoapOptionsBuilder::createWithDefaultsKeepAlive(self::TEST_REMOTE_WSDL_UK)
        );
        $enumValutesRequest = new EnumValutes();
        $enumValutesRequest->Seld = true;
        $soapResponse = $soapClient->soapCall('EnumValutes', [$enumValutesRequest]);

        self::assertStringContainsString('Connection: Keep-Alive', $soapResponse->getTracingData()->getLastRequestHeaders());
        self::assertStringContainsString('<ns1:EnumValutes><ns1:Seld>true</ns1:Seld>', $soapResponse->getTracingData()->getLastRequest());
        self::assertStringContainsString('EnumValutesResult', $soapResponse->getContent());
        self::assertStringContainsString('</EnumValutesResponse>', $soapResponse->getContent());
        self::assertEquals(self::TEST_ENDPOINT_UK, $soapResponse->getLocation());
    }

    public function testSoapCallWithCustomEndpointInvalidShouldFail()
    {
        $this->expectExceptionMessage('Could not resolve host');

        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithEndpointLocation(self::TEST_REMOTE_ENDPOINT_NOT_WORKING),
            SoapOptionsBuilder::createWithDefaults(self::TEST_REMOTE_WSDL_UK)
        );
        $enumValutesRequest = new EnumValutes();
        $enumValutesRequest->Seld = true;
        $soapClient->soapCall('EnumValutes', [$enumValutesRequest]);
    }

    public function testSoapCallWithCacheEndpointDownShouldFail()
    {
        $this->expectExceptionMessage('Could not write WSDL cache file: Download failed with message');

        $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithDefaults(),
            SoapOptionsBuilder::createWithDefaults(
                self::TEST_REMOTE_WSDL_NOT_WORKING,
                SoapOptions::SOAP_CACHE_TYPE_DISK,
                self::CACHE_DIR
            )
        );
    }

    public function testSoapCallEndpointDownShouldFail()
    {
        $this->expectExceptionMessage('Parsing WSDL: Couldn\'t load from');

        $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithDefaults(),
            SoapOptionsBuilder::createWithDefaults(self::TEST_REMOTE_WSDL_NOT_WORKING)
        );
    }

    public function testSoapCallNoSwaWithAttachmentMustFail()
    {
        $this->setExpectedException(Exception::class, 'Non SWA SoapClient cannot handle SOAP action');

        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithDefaults(),
            SoapOptionsBuilder::createWithDefaults(self::TEST_REMOTE_WSDL_UK)
        );
        $getUKLocationByCountyRequest = new GetUKLocationByCounty();
        $getUKLocationByCountyRequest->County = 'London';

        $soapClient->soapCall(
            'GetUKLocationByCounty',
            [$getUKLocationByCountyRequest],
            [
                new SoapAttachment(
                    'first-file.txt',
                    'text/plain',
                    'unexpected file - no SWA - must fail'
                ),
            ]
        );
    }

    public function testSoapCallSwaWithTwoAttachments()
    {
        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithTracing(),
            SoapOptionsBuilder::createSwaWithClassMap(
                self::TEST_REMOTE_WSDL_UK,
                new ClassMap(),
                SoapOptions::SOAP_CACHE_TYPE_DISK,
                self::CACHE_DIR
            )
        );
        $getUKLocationByCountyRequest = new GetUKLocationByCounty();
        $getUKLocationByCountyRequest->County = 'London';

        try {
            $soapResponse = $soapClient->soapCall(
                'GetUKLocationByCounty',
                [$getUKLocationByCountyRequest],
                [
                    new SoapAttachment(
                        'first-file.txt',
                        'text/plain',
                        'hello world'
                    ),
                    new SoapAttachment(
                        'second-file.txt',
                        'text/plain',
                        'hello world'
                    )
                ]
            );
            $tracingData = $soapResponse->getTracingData();
        } catch (SoapFaultWithTracingData $e) {
            $tracingData = $e->getSoapResponseTracingData();
        }

        self::assertEquals(
            $this->getContentId($tracingData->getLastRequestHeaders()),
            $this->getContentId($tracingData->getLastRequest()),
            'Content ID must match in request XML and Content-Type: ...; start header'
        );
        self::assertEquals(
            $this->getMultiPartBoundary($tracingData->getLastRequestHeaders()),
            $this->getMultiPartBoundary($tracingData->getLastRequest()),
            'MultiPart boundary must match in request XML and Content-Type: ...; boundary header'
        );
        self::assertContains('boundary=Part_', $tracingData->getLastRequestHeaders(), 'Headers should link to boundary');
        self::assertContains('start="<part-', $tracingData->getLastRequestHeaders(), 'Headers should link to first MultiPart');
        self::assertContains('action="', $tracingData->getLastRequestHeaders(), 'Headers should contain SOAP action');
        self::assertEquals(
            $this->removeOneTimeData(
                file_get_contents(
                    self::FIXTURES_DIR.'/Message/Request/GetUKLocationByCounty.request.mimepart.message'
                )
            ),
            $this->removeOneTimeData(
                $tracingData->getLastRequest()
            ),
            'Requests must match after onetime data were removed'
        );
    }

    public function testSoapCallSwaWithNoAttachments()
    {
        $soapClient = $this->getSoapBuilder()->build(
            SoapClientOptionsBuilder::createWithTracing(),
            SoapOptionsBuilder::createSwaWithClassMap(
                self::TEST_REMOTE_WSDL_UK,
                new ClassMap(),
                SoapOptions::SOAP_CACHE_TYPE_DISK,
                self::CACHE_DIR
            )
        );
        $getUKLocationByCountyRequest = new GetUKLocationByCounty();
        $getUKLocationByCountyRequest->County = 'London';

        try {
            $soapResponse = $soapClient->soapCall(
                'GetUKLocationByCounty',
                [$getUKLocationByCountyRequest]
            );
            $tracingData = $soapResponse->getTracingData();
        } catch (SoapFaultWithTracingData $e) {
            $tracingData = $e->getSoapResponseTracingData();
        }

        self::assertNotContains('boundary=Part_', $tracingData->getLastRequestHeaders(), 'Headers should link to boundary');
        self::assertNotContains('start="<part-', $tracingData->getLastRequestHeaders(), 'Headers should link to first MultiPart');
        self::assertContains('action="', $tracingData->getLastRequestHeaders(), 'Headers should contain SOAP action');
        self::assertStringEqualsFile(
            self::FIXTURES_DIR.'/Message/Request/GetUKLocationByCounty.request.message',
            $tracingData->getLastRequest(),
            'Requests must match'
        );
    }

    /**
     * @see This test needs to start a mock server first
     */
    public function testSoapCallSwaWithAttachmentsOnResponse()
    {
        $soapClient = $this->getSoapBuilder()->buildWithSoapHeader(
            SoapClientOptionsBuilder::createWithTracing(),
            SoapOptionsBuilder::createSwaWithClassMapV11(
                self::TEST_HTTP_URL.'/SwaEndpoint.php?wsdl',
                new ClassMap([
                    'GenerateTestRequest' => GenerateTestRequest::class,
                ]),
                SoapOptions::SOAP_CACHE_TYPE_DISK,
                self::CACHE_DIR
            ),
            new SoapHeader('http://schema.testcase', 'SoapHeader', [
                'user' => 'admin',
            ])
        );
        $generateTestRequest = new GenerateTestRequest();
        $generateTestRequest->salutation = 'World';

        $soapResponse = $soapClient->soapCall('generateTest', [$generateTestRequest]);
        $attachments = $soapResponse->getAttachments();

        self::assertContains('</generateTestReturn>', $soapResponse->getResponseContent());
        self::assertTrue($soapResponse->hasAttachments());
        self::assertCount(1, $attachments);

        $firstAttachment = reset($attachments);

        self::assertEquals('text/plain', $firstAttachment->getHeader('Content-Type'));

        file_put_contents(self::CACHE_DIR . '/testSoapCallSwaWithAttachmentsOnResponse.xml', $soapResponse->getContent());
        file_put_contents(self::CACHE_DIR . '/testSoapCallSwaWithAttachmentsOnResponse.txt', $firstAttachment->getContent());
    }

    public function removeOneTimeData($string)
    {
        $contentId = $this->getContentId($string);
        $multiPartBoundary = $this->getMultiPartBoundary($string);

        return str_replace(
            $contentId,
            '{content-id-placeholder}',
            str_replace(
                $multiPartBoundary,
                '{multipart-boundary-placeholder}',
                $string
            )
        );
    }

    private function getMultiPartBoundary($string)
    {
        $realMultiParts = null;
        preg_match('/Part\_[0-9]{2}\_[a-zA-Z0-9]{13}\.[a-zA-Z0-9]{13}/', $string, $realMultiParts);
        if (count($realMultiParts) > 0) {
            return $realMultiParts[0];
        }

        throw new Exception('Could not find real MultiPart boundary');
    }

    private function getContentId($string)
    {
        $realContentIds = null;
        preg_match('/part\-[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}\@response\.info/', $string, $realContentIds);
        if (count($realContentIds) > 0) {
            return $realContentIds[0];
        }

        throw new Exception('Could not find real contentId');
    }

    private function getSoapBuilder()
    {
        return new SoapClientBuilder();
    }
}
