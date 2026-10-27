<?php

namespace Encore\Admin\Tests\Compatibility;

use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\MessageBag;

class NotificationTest extends TestCase
{
    public function test_notifications_render_after_json_session_round_trip(): void
    {
        $handler = new ArraySessionHandler(120);
        $session = new Store('admin-test', $handler, null, 'json');
        $session->start();
        $this->app['session']->driver()->start();
        admin_toastr('Saved', 'success', ['timeOut' => 1000]);
        admin_success('Complete', 'Record saved');
        $session->replace($this->app['session']->driver()->all());
        $session->save();
        $restored = new Store('admin-test', $handler, $session->getId(), 'json');
        $restored->start();
        $this->app['session']->driver()->replace($restored->all());

        $html = view('admin::partials.toastr')->render();
        $this->assertStringContainsString('Saved', $html);
        $this->assertStringContainsString('"timeOut":1000', $html);
        $this->assertStringContainsString('Record saved', view('admin::partials.alerts')->render());
    }

    public function test_all_alert_types_accept_json_deserialized_payloads(): void
    {
        foreach (['success', 'error', 'warning', 'info'] as $type) {
            session()->flush();
            admin_info('Title '.$type, 'Message '.$type, $type);
            $payload = json_decode(json_encode(session($type)), true);
            session()->put($type, $payload);
            $html = view('admin::partials.alerts')->render();
            $this->assertStringContainsString('Title '.$type, $html);
            $this->assertStringContainsString('Message '.$type, $html);
        }
    }

    public function test_legacy_message_bag_notifications_still_render(): void
    {
        session()->put('toastr', new MessageBag(['type' => 'success', 'message' => 'Legacy', 'options' => ['timeOut' => 800]]));
        $this->assertStringContainsString('Legacy', view('admin::partials.toastr')->render());
        session()->put('success', new MessageBag(['title' => 'Legacy alert', 'message' => 'Still works']));
        $this->assertStringContainsString('Legacy alert', view('admin::partials.alerts')->render());
    }
}
