<?php

namespace OnrampLab\Transcription\Tests\Unit\Http\Controllers;

use Illuminate\Http\Response;
use OnrampLab\Transcription\Contracts\TranscriptionManager;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Tests\TestCase;

class TranscriptionControllerTest extends TestCase
{
    private string $prefix;

    private string $type;

    private Transcript $transcript;

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $this->prefix = 'this/is/callback/prefix';

        $app['config']->set('transcription.callback.prefix', $this->prefix);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = 'fake-service';
        $this->transcript = Transcript::factory()->make(['type' => $this->type]);
    }

    /**
     * @test
     */
    public function callback_should_work(): void
    {
        $url = "/{$this->prefix}/transcription/{$this->type}/callback";
        $payload = [
            'id' => $this->transcript->external_id,
            'status' => 'completed',
            'transcript' => [
                'text' => 'Hello Word.',
            ],
        ];

        $this->mock(
            TranscriptionManager::class,
            function ($mock) use ($payload) {
                $mock
                    ->shouldReceive('callback')
                    ->once()
                    ->withArgs(function (string $type, array $requestHeader, array $requestBody) use ($payload) {
                        return $type === $this->type
                            && $requestBody == $payload;
                    })
                    ->andReturn($this->transcript);
            },
        );

        $response = $this->post($url, $payload);
        $response->assertStatus(Response::HTTP_OK);
    }
}
