<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Middleware\Pjax;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Admin::guard()->setUser(new Administrator());
    }

    private function pjaxRequest(): Request
    {
        return Request::create('/admin/articles?page=2', 'GET', [], [], [], [
            'HTTP_X_PJAX' => 'true', 'HTTP_X_PJAX_CONTAINER' => '#pjax-container',
        ]);
    }

    public function test_filters_native_symfony_response_and_sets_pjax_url(): void
    {
        $response = new Response('<html><head><title>Admin</title></head><body><div id="pjax-container">&#20013;&#25991;</div><footer>hidden</footer></body></html>');
        $result = (new Pjax())->handle($this->pjaxRequest(), fn () => $response);
        $this->assertSame('<title>Admin</title>中文', $result->getContent());
        $this->assertSame('/admin/articles?page=2', $result->headers->get('X-PJAX-URL'));
    }

    public function test_preserves_error_response_without_an_attached_exception(): void
    {
        $response = new Response('Not found', 404);
        $this->assertSame($response, (new Pjax())->handle($this->pjaxRequest(), fn () => $response));
    }

    public function test_preserves_json_and_streamed_responses(): void
    {
        foreach ([new JsonResponse(['ok' => true]), new StreamedResponse(fn () => print 'csv')] as $response) {
            $this->assertSame($response, (new Pjax())->handle($this->pjaxRequest(), fn () => $response));
        }
    }
}
