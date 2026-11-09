<?php

declare(strict_types=1);

namespace Woomoza\MobileMoney\Tests;

use PHPUnit\Framework\TestCase as Base;
use Woomoza\MobileMoney\Mpesa\Callback\CallbackHandler;

final class CallbackHandlerTest extends Base
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mpesa-cb-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/callbacks/*') ?: [] as $f) { unlink($f); }
        @rmdir($this->dir . '/callbacks');
        foreach (glob($this->dir . '/*') ?: [] as $f) { unlink($f); }
        @rmdir($this->dir);
    }

    private const BODY = '{"output_ResultCode":"0","output_ResultDesc":"Success","output_TransactionID":"T1","output_ThirdPartyReference":"TP1"}';

    public function testAcceptsValidPostAndLogsWithoutHeaders(): void
    {
        $r = (new CallbackHandler([], 65536, $this->dir))->handle('POST', '1.2.3.4', self::BODY);
        self::assertSame(200, $r->httpStatus);
        self::assertTrue($r->accepted());
        self::assertTrue($r->response->isSuccess());
        self::assertFalse($r->duplicate);
        $log = file_get_contents($this->dir . '/callback.log');
        self::assertStringContainsString('"tx":"T1"', $log);
        self::assertStringNotContainsStringIgnoringCase('cookie', $log);
    }

    public function testSameBodyTwiceIsFlaggedDuplicate(): void
    {
        $h = new CallbackHandler([], 65536, $this->dir);
        self::assertFalse($h->handle('POST', '1.1.1.1', self::BODY)->duplicate);
        $second = $h->handle('POST', '1.1.1.1', self::BODY);
        self::assertTrue($second->duplicate);
        self::assertSame(200, $second->httpStatus);
    }

    public function testRejectsNonPost(): void
    {
        self::assertSame(405, (new CallbackHandler())->handle('GET', '1.1.1.1', '')->httpStatus);
    }

    public function testIpAllowlist(): void
    {
        $h = new CallbackHandler(['9.9.9.9'], 65536, $this->dir);
        self::assertSame(403, $h->handle('POST', '1.1.1.1', self::BODY)->httpStatus);
        self::assertSame(200, $h->handle('POST', '9.9.9.9', self::BODY)->httpStatus);
    }

    public function testRejectsInvalidAndOversizedBodies(): void
    {
        $h = new CallbackHandler([], 50, $this->dir);
        self::assertSame(400, $h->handle('POST', '1.1.1.1', 'nao json')->httpStatus);
        self::assertSame(400, $h->handle('POST', '1.1.1.1', '{}')->httpStatus);
        self::assertSame(413, $h->handle('POST', '1.1.1.1', self::BODY)->httpStatus);
    }
}
